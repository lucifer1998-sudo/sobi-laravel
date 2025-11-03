<?php

namespace App\Console\Commands;

use App\Http\Traits\HospitableTrait;
use App\Models\Property;
use App\Models\PropertyHouseRule;
use App\Models\Amenity;
use App\Models\PropertyAmenity;
use App\Models\PropertyImage;
use App\Models\RoomDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class SyncPropertiesFromHospitable extends Command
{
    use HospitableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hospitable:sync-properties 
                            {--page=1 : Page number to start from}
                            {--per-page=100 : Number of properties to fetch per page}
                            {--all : Fetch all properties with pagination}
                            {--include= : Related resources to include (comma-separated)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync properties from Hospitable API to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting property synchronization from Hospitable API...');

        $page = (int) $this->option('page');
        $perPage = (int) $this->option('per-page');
        $fetchAll = $this->option('all');
        $include = $this->option('include');

        $totalSynced = 0;
        $totalUpdated = 0;
        $totalCreated = 0;
        $totalErrors = 0;

        do {
            $this->info("Fetching properties (page: {$page}, per_page: {$perPage})...");

            $queryParams = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            // Add include parameter if provided
            if ($include) {
                $queryParams['include'] = $include;
            }

            $response = $this->getProperties($queryParams);

            if (!$response) {
                $this->error('Failed to fetch properties from Hospitable API.');
                return Command::FAILURE;
            }

            // Handle different response structures
            $properties = $response['data'] ?? $response['properties'] ?? $response;

            if (empty($properties) || !is_array($properties)) {
                $this->warn('No properties found in API response.');
                break;
            }

            $this->info('Found ' . count($properties) . ' properties to process.');

            // Process each property
            foreach ($properties as $propertyData) {
                try {
                    $result = $this->syncProperty($propertyData);
                    if ($result['created']) {
                        $totalCreated++;
                    } else {
                        $totalUpdated++;
                    }
                    $totalSynced++;
                } catch (\Exception $e) {
                    $totalErrors++;
                    $propertyId = $propertyData['id'] ?? 'unknown';
                    $this->error("Error syncing property {$propertyId}: " . $e->getMessage());
                    continue;
                }
            }

            // Check if there are more pages
            $hasMore = false;
            if ($fetchAll) {
                // Check pagination metadata from meta object
                $meta = $response['meta'] ?? [];
                $currentPage = $meta['current_page'] ?? $page;
                $lastPage = $meta['last_page'] ?? null;
                $totalCount = $meta['total'] ?? null;
                $perPageCount = $meta['per_page'] ?? $perPage;
                
                // If we have pagination metadata, use it
                if ($lastPage && $currentPage < $lastPage) {
                    $hasMore = true;
                    $page++;
                } elseif ($totalCount) {
                    // Calculate if there are more pages based on total count
                    $totalPages = (int) ceil($totalCount / $perPageCount);
                    if ($currentPage < $totalPages) {
                        $hasMore = true;
                        $page++;
                    }
                } elseif (count($properties) >= $perPage) {
                    // If we got a full page, there might be more
                    $hasMore = true;
                    $page++;
                }
            }

            $this->info("Processed page. Total synced so far: {$totalSynced}");

        } while ($fetchAll && $hasMore);

        // Summary
        $this->newLine();
        $this->info('=== Synchronization Complete ===');
        $this->info("Total synced: {$totalSynced}");
        $this->info("Created: {$totalCreated}");
        $this->info("Updated: {$totalUpdated}");
        if ($totalErrors > 0) {
            $this->warn("Errors: {$totalErrors}");
        }

        return Command::SUCCESS;
    }

    /**
     * Sync a single property from API data to database.
     *
     * @param array $propertyData
     * @return array
     */
    protected function syncProperty(array $propertyData): array
    {
        $propertyId = $propertyData['id'] ?? null;
        
        if (!$propertyId) {
            throw new \Exception('Property ID is missing');
        }

        // Map API data to database schema
        $propertyAttributes = $this->mapPropertyData($propertyData);
        
        // Use database transaction for consistency
        return DB::transaction(function () use ($propertyId, $propertyAttributes, $propertyData) {
            // Update or create property
            $property = Property::updateOrCreate(
                ['id' => $propertyId],
                $propertyAttributes
            );

            $wasRecentlyCreated = $property->wasRecentlyCreated;

            // Sync house rules (always present in API response)
            if (isset($propertyData['house_rules'])) {
                $this->syncHouseRules($propertyId, $propertyData);
            }

            // Sync amenities if present
            if (isset($propertyData['amenities']) && is_array($propertyData['amenities'])) {
                $this->syncAmenities($propertyId, $propertyData['amenities']);
            }

            // Sync images from API
            $this->syncImages($propertyId);

            // Sync room details if present
            if (isset($propertyData['room_details']) && is_array($propertyData['room_details'])) {
                $this->syncRoomDetails($propertyId, $propertyData['room_details']);
            }

            return [
                'created' => $wasRecentlyCreated,
                'property' => $property,
            ];
        });
    }

    /**
     * Map API property data to database attributes.
     *
     * @param array $data
     * @return array
     */
    protected function mapPropertyData(array $data): array
    {
        $address = $data['address'] ?? [];
        $coordinates = $address['coordinates'] ?? [];
        $capacity = $data['capacity'] ?? [];
        $parentChild = $data['parent_child'] ?? null;
        
        // Extract parent_id from parent_child relationship
        $parentId = null;
        if ($parentChild && isset($parentChild['parent']) && $parentChild['parent'] !== null) {
            $parentId = $parentChild['parent'];
        }

        return [
            'parent_id' => $parentId,
            'name' => $data['name'] ?? '',
            'public_name' => $data['public_name'] ?? null,
            'picture_url' => $data['picture'] ?? null,
            'timezone_offset' => $data['timezone'] ?? null,
            'listed' => $data['listed'] ?? false,
            'currency' => $data['currency'] ?? null,
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'checkin_time' => $this->parseTime($data['checkin'] ?? null),
            'checkout_time' => $this->parseTime($data['checkout'] ?? null),
            'property_type' => $data['property_type'] ?? null,
            'room_type' => $data['room_type'] ?? null,
            'calendar_restricted' => $data['calendar_restricted'] ?? false,
            
            // Address fields
            'address_number' => $address['number'] ?? null,
            'address_street' => $address['street'] ?? null,
            'address_city' => $address['city'] ?? null,
            'address_state' => $address['state'] ?? null,
            'address_postcode' => $address['postcode'] ?? null,
            'address_country_code' => $address['country'] ?? null,
            'address_country_name' => $address['country_name'] ?? null,
            'address_display' => $address['display'] ?? null,
            'latitude' => isset($coordinates['latitude']) ? (float) $coordinates['latitude'] : null,
            'longitude' => isset($coordinates['longitude']) ? (float) $coordinates['longitude'] : null,
            
            // Capacity fields
            'capacity_max' => $capacity['max'] ?? null,
            'capacity_bedrooms' => $capacity['bedrooms'] ?? null,
            'capacity_beds' => $capacity['beds'] ?? null,
            'capacity_bathrooms' => $capacity['bathrooms'] ?? null,
        ];
    }

    /**
     * Sync house rules for a property.
     *
     * @param string $propertyId
     * @param array $propertyData
     * @return void
     */
    protected function syncHouseRules(string $propertyId, array $propertyData): void
    {
        $houseRules = $propertyData['house_rules'] ?? [];
        
        // Extract house rules (values can be boolean or null)
        $petsAllowed = $houseRules['pets_allowed'] ?? false;
        $smokingAllowed = $houseRules['smoking_allowed'] ?? false;
        $eventsAllowed = $houseRules['events_allowed'] ?? false;
        
        // Convert null to false (database expects boolean)
        $petsAllowed = $petsAllowed === null ? false : (bool) $petsAllowed;
        $smokingAllowed = $smokingAllowed === null ? false : (bool) $smokingAllowed;
        $eventsAllowed = $eventsAllowed === null ? false : (bool) $eventsAllowed;

        PropertyHouseRule::updateOrCreate(
            ['property_id' => $propertyId],
            [
                'pets_allowed' => $petsAllowed,
                'smoking_allowed' => $smokingAllowed,
                'events_allowed' => $eventsAllowed,
            ]
        );
    }

    /**
     * Sync amenities for a property.
     *
     * @param string $propertyId
     * @param array $amenities
     * @return void
     */
    protected function syncAmenities(string $propertyId, array $amenities): void
    {
        // Delete existing amenities
        PropertyAmenity::where('property_id', $propertyId)->delete();

        // Insert new amenities
        foreach ($amenities as $amenity) {
            $amenityName = is_array($amenity) 
                ? ($amenity['name'] ?? $amenity['title'] ?? $amenity['label'] ?? '')
                : (string) $amenity;

            if (!empty($amenityName)) {
                // Find or create the amenity
                $amenity = Amenity::firstOrCreate(
                    ['name' => $amenityName],
                    [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'display_name' => ucfirst(str_replace('_', ' ', $amenityName)),
                        'icon_url' => null,
                    ]
                );

                // Link the amenity to the property
                PropertyAmenity::create([
                    'property_id' => $propertyId,
                    'amenity_id' => $amenity->id,
                ]);
            }
        }
    }

    /**
     * Sync images for a property from the Hospitable API.
     *
     * @param string $propertyId
     * @return void
     */
    protected function syncImages(string $propertyId): void
    {
        try {
            // Fetch images from API with pagination support
            $allImages = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = $this->getPropertyImages($propertyId, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                if (!$response) {
                    break;
                }

                // Extract images from response (handle different response structures)
                $images = $response['data'] ?? $response['images'] ?? $response;
                
                if (!is_array($images) || empty($images)) {
                    break;
                }

                $allImages = array_merge($allImages, $images);

                // Check if there are more pages
                $meta = $response['meta'] ?? [];
                $currentPage = $meta['current_page'] ?? $page;
                $lastPage = $meta['last_page'] ?? null;

                if ($lastPage && $currentPage < $lastPage) {
                    $page++;
                } else {
                    break;
                }
            } while (true);

            // Delete existing images
            PropertyImage::where('property_id', $propertyId)->delete();

            // Insert new images
            foreach ($allImages as $index => $imageData) {
                $imageUrl = null;
                $caption = null;

                // Handle different response structures
                if (is_string($imageData)) {
                    $imageUrl = $imageData;
                } elseif (is_array($imageData)) {
                    $imageUrl = $imageData['url'] ?? $imageData['picture'] ?? $imageData['image_url'] ?? $imageData['src'] ?? null;
                    $caption = $imageData['caption'] ?? $imageData['description'] ?? $imageData['title'] ?? null;
                }

                if (!empty($imageUrl)) {
                    PropertyImage::create([
                        'property_id' => $propertyId,
                        'url' => $imageUrl,
                        'caption' => $caption,
                        'order' => $index,
                        'is_primary' => $index === 0, // First image is primary
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the entire sync
            Log::warning("Failed to sync images for property {$propertyId}: " . $e->getMessage());
        }
    }

    /**
     * Sync room details for a property.
     *
     * @param string $propertyId
     * @param array $roomDetails
     * @return void
     */
    protected function syncRoomDetails(string $propertyId, array $roomDetails): void
    {
        // Delete existing room details
        RoomDetail::where('property_id', $propertyId)->delete();

        // Insert new room details
        foreach ($roomDetails as $roomDetail) {
            $type = $roomDetail['type'] ?? null;
            $beds = $roomDetail['beds'] ?? [];

            if (!empty($type)) {
                RoomDetail::create([
                    'property_id' => $propertyId,
                    'type' => $type,
                    'beds' => !empty($beds) ? $beds : null,
                ]);
            }
        }
    }

    /**
     * Parse time string to time format or return null.
     *
     * @param mixed $time
     * @return string|null
     */
    protected function parseTime($time): ?string
    {
        if (empty($time)) {
            return null;
        }

        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        try {
            // Try to parse as time (HH:MM or HH:MM:SS)
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)) {
                return sprintf('%02d:%02d:%02d', $matches[1], $matches[2], $matches[3] ?? 0);
            }

            // Try to parse as datetime
            $carbon = Carbon::parse($time);
            return $carbon->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
