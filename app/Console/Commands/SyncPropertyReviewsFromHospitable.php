<?php

namespace App\Console\Commands;

use App\Http\Traits\HospitableTrait;
use App\Models\Property;
use App\Models\PropertyReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPropertyReviewsFromHospitable extends Command
{
    use HospitableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hospitable:sync-reviews 
                            {--property= : Sync reviews for a specific property ID}
                            {--all : Sync reviews for all properties}
                            {--per-page=100 : Number of reviews to fetch per page}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync property reviews from Hospitable API to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting property reviews synchronization from Hospitable API...');

        $propertyId = $this->option('property');
        $syncAll = $this->option('all');
        $perPage = (int) $this->option('per-page');

        $totalSynced = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        if ($propertyId) {
            // Sync reviews for a specific property
            $this->info("Syncing reviews for property: {$propertyId}");
            $result = $this->syncPropertyReviews($propertyId, $perPage);
            $totalSynced += $result['synced'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $totalErrors += $result['errors'];
        } elseif ($syncAll) {
            // Sync reviews for all properties
            $this->info('Fetching all properties from database...');
            $properties = Property::all();
            $totalProperties = $properties->count();

            $this->info("Found {$totalProperties} properties to sync reviews for.");

            $bar = $this->output->createProgressBar($totalProperties);
            $bar->start();

            foreach ($properties as $property) {
                $result = $this->syncPropertyReviews($property->id, $perPage);
                $totalSynced += $result['synced'];
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $totalErrors += $result['errors'];
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        } else {
            $this->error('Please specify either --property=UUID or --all option.');
            return Command::FAILURE;
        }

        // Summary
        $this->newLine();
        $this->info('=== Synchronization Complete ===');
        $this->info("Total reviews synced: {$totalSynced}");
        $this->info("Created: {$totalCreated}");
        $this->info("Updated: {$totalUpdated}");
        if ($totalErrors > 0) {
            $this->warn("Errors: {$totalErrors}");
        }

        return Command::SUCCESS;
    }

    /**
     * Sync reviews for a single property.
     *
     * @param string $propertyId
     * @param int $perPage
     * @return array
     */
    protected function syncPropertyReviews(string $propertyId, int $perPage): array
    {
        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        try {
            // Fetch all reviews with pagination
            $allReviews = [];
            $page = 1;
            $hasMorePages = true;

            // Build a map of included guests if present (accumulates across pages)
            $guestsMap = [];
            
            while ($hasMorePages) {
                $response = $this->getPropertyReviews($propertyId, [
                    'page' => $page,
                    'per_page' => $perPage,
                    'include' => 'guest',
                ]);

                if (!$response) {
                    $this->warn("Failed to fetch reviews for property {$propertyId} on page {$page}");
                    break;
                }

                // Extract reviews from response (handle different response structures)
                $reviews = $response['data'] ?? $response['reviews'] ?? [];

                // If response is directly an array, use it
                if (is_array($response) && !isset($response['data']) && !isset($response['reviews']) && !isset($response['included'])) {
                    $reviews = $response;
                }

                // Handle JSON API format (with attributes wrapper)
                if (isset($reviews[0]) && isset($reviews[0]['attributes'])) {
                    $reviews = array_map(function ($review) {
                        return array_merge($review, $review['attributes'] ?? []);
                    }, $reviews);
                }

                // Handle included section for guest data (if using JSON API format)
                if (isset($response['included']) && is_array($response['included'])) {
                    foreach ($response['included'] as $included) {
                        if (isset($included['type']) && $included['type'] === 'guest' && isset($included['id'])) {
                            $guestsMap[$included['id']] = $included['attributes'] ?? $included;
                        }
                    }
                    
                    // Enrich reviews with guest data from included section if needed
                    foreach ($reviews as &$review) {
                        if (isset($review['relationships']['guest']['data']['id'])) {
                            $guestId = $review['relationships']['guest']['data']['id'];
                            if (isset($guestsMap[$guestId])) {
                                $review['guest'] = $guestsMap[$guestId];
                            }
                        }
                    }
                    unset($review); // Break reference
                }

                if (!is_array($reviews)) {
                    $this->warn("Invalid reviews format for property {$propertyId} on page {$page}");
                    break;
                }

                // If we got reviews, add them
                if (!empty($reviews)) {
                    $allReviews = array_merge($allReviews, $reviews);
                    $this->line("  Fetched " . count($reviews) . " reviews from page {$page} (Total so far: " . count($allReviews) . ")");
                }

                // Determine if there are more pages
                $hasMorePages = false;

                // Check pagination metadata
                $meta = $response['meta'] ?? [];
                $links = $response['links'] ?? [];

                if (!empty($meta)) {
                    // Using meta object (Laravel-style pagination)
                    $currentPage = $meta['current_page'] ?? $page;
                    $lastPage = $meta['last_page'] ?? null;
                    $total = $meta['total'] ?? null;
                    $perPageCount = $meta['per_page'] ?? $perPage;

                    if ($lastPage !== null) {
                        // We have explicit last_page
                        $hasMorePages = $currentPage < $lastPage;
                    } elseif ($total !== null) {
                        // Calculate last page from total
                        $calculatedLastPage = (int) ceil($total / $perPageCount);
                        $hasMorePages = $currentPage < $calculatedLastPage;
                    } elseif (count($reviews) >= $perPageCount) {
                        // If we got a full page, there might be more
                        $hasMorePages = true;
                    }
                } elseif (!empty($links)) {
                    // Using links object (REST-style pagination)
                    $nextLink = $links['next'] ?? null;
                    $hasMorePages = $nextLink !== null;
                } else {
                    // Fallback: if we got fewer results than per_page, we're done
                    // Otherwise, assume there might be more
                    $hasMorePages = count($reviews) >= $perPage;
                }

                // Safety check: don't loop forever
                if ($hasMorePages) {
                    $page++;
                    if ($page > 10000) { // Safety limit
                        $this->error("Pagination limit reached for property {$propertyId}. Stopping to prevent infinite loop.");
                        break;
                    }
                }
            }

            // Log summary of fetched reviews
            $totalFetched = count($allReviews);
            if ($totalFetched > 0) {
                $this->info("  Total reviews fetched for property {$propertyId}: {$totalFetched}");
            } else {
                $this->line("  No reviews found for property {$propertyId}");
                return [
                    'synced' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'errors' => 0,
                ];
            }

            // Sync each review
            foreach ($allReviews as $index => $reviewData) {
                try {
                    $result = $this->syncReview($propertyId, $reviewData);
                    if ($result['created']) {
                        $created++;
                    } else {
                        $updated++;
                    }
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    $reviewId = $reviewData['id'] ?? 'unknown';
                    $errorMsg = "Error syncing review {$reviewId} (index {$index}) for property {$propertyId}: " . $e->getMessage();
                    Log::warning($errorMsg);
                    $this->warn("  " . $errorMsg);
                }
            }
        } catch (\Exception $e) {
            $errors++;
            Log::error("Failed to sync reviews for property {$propertyId}: " . $e->getMessage());
        }

        return [
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync a single review to the database.
     *
     * @param string $propertyId
     * @param array $reviewData
     * @return array
     */
    protected function syncReview(string $propertyId, array $reviewData): array
    {
        $reviewId = $reviewData['id'] ?? null;

        if (!$reviewId) {
            throw new \Exception('Review ID is missing');
        }

        // Map API data to database attributes
        $reviewAttributes = $this->mapReviewData($propertyId, $reviewData);

        $review = PropertyReview::updateOrCreate(
            ['id' => $reviewId],
            $reviewAttributes
        );

        return [
            'created' => $review->wasRecentlyCreated,
            'review' => $review,
        ];
    }

    /**
     * Map API review data to database attributes.
     *
     * @param string $propertyId
     * @param array $data
     * @return array
     */
    protected function mapReviewData(string $propertyId, array $data): array
    {
        // Extract public and private review data
        $public = $data['public'] ?? [];
        $private = $data['private'] ?? [];

        // Get rating from public section
        $rating = $public['rating'] ?? $data['rating'] ?? null;
        
        // Get rating_platform_original from public section (e.g., "5.00")
        $ratingPlatformOriginal = $public['rating_platform_original'] ?? null;
        
        // Get comment/review text from public section
        $comment = $public['review'] ?? $public['comment'] ?? $data['review'] ?? $data['comment'] ?? null;
        
        // Handle host response (stored as string, not array in API)
        $hostResponse = $public['response'] ?? null;
        $responses = null;
        if ($hostResponse) {
            $responses = [
                [
                    'text' => $hostResponse,
                    'responded_at' => $data['responded_at'] ?? null,
                ]
            ];
        }

        // Parse reviewed_at date
        $reviewedAt = null;
        if (isset($data['reviewed_at'])) {
            try {
                $reviewedAt = \Carbon\Carbon::parse($data['reviewed_at']);
            } catch (\Exception $e) {
                // Keep as null if parsing fails
            }
        }

        // Parse responded_at date
        $respondedAt = null;
        if (isset($data['responded_at']) && $data['responded_at'] !== null) {
            try {
                $respondedAt = \Carbon\Carbon::parse($data['responded_at']);
            } catch (\Exception $e) {
                // Keep as null if parsing fails
            }
        }

        // Get can_respond boolean
        $canRespond = $data['can_respond'] ?? false;

        // Get private feedback
        $privateFeedback = $private['feedback'] ?? null;

        // Get detailed_ratings array
        $detailedRatings = $private['detailed_ratings'] ?? null;

        // Extract guest information (when include=guest is used)
        $guest = $data['guest'] ?? null;
        $guestData = null;
        $reviewerName = null;
        $reviewerAvatar = null;
        $guestLanguage = null;
        
        if ($guest && is_array($guest)) {
            // Store complete guest data as JSON
            $guestData = $guest;
            
            // Extract name - combine first_name and last_name (as per API structure)
            $firstName = $guest['first_name'] ?? '';
            $lastName = $guest['last_name'] ?? '';
            if ($firstName || $lastName) {
                $reviewerName = trim($firstName . ' ' . $lastName);
            } else {
                // Fallback to other possible name fields
                $reviewerName = $guest['name'] 
                    ?? $guest['display_name'] 
                    ?? $guest['full_name']
                    ?? null;
            }
            
            // Extract avatar - try multiple possible fields
            $reviewerAvatar = $guest['avatar'] 
                ?? $guest['avatar_url'] 
                ?? $guest['picture'] 
                ?? $guest['picture_url']
                ?? $guest['photo']
                ?? $guest['photo_url']
                ?? null;
            
            // Get language from guest
            $guestLanguage = $guest['language'] ?? null;
        } elseif ($guest && is_string($guest)) {
            // If guest is just an ID reference, store it
            $guestData = ['id' => $guest];
        }

        return [
            'property_id' => $propertyId,
            'reviewer_name' => $reviewerName,
            'reviewer_avatar' => $reviewerAvatar,
            'guest_data' => $guestData,
            'rating' => $rating ? (int) $rating : null,
            'rating_platform_original' => $ratingPlatformOriginal,
            'comment' => $comment,
            'reviewed_at' => $reviewedAt,
            'responded_at' => $respondedAt,
            'can_respond' => $canRespond,
            'platform' => $data['platform'] ?? null,
            'language' => $guestLanguage ?? $data['language'] ?? $data['lang'] ?? null,
            'responses' => $responses,
            'private_feedback' => $privateFeedback,
            'detailed_ratings' => $detailedRatings,
        ];
    }
}
