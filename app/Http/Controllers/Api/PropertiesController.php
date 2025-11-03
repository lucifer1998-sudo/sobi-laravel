<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PropertiesController extends Controller
{
    /**
     * Display a listing of properties (paginated).
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 15, max: 100)
     * - search: Search term for name, city, etc.
     * - sort_by: Field to sort by (name, city, created_at, etc.)
     * - sort_order: asc or desc (default: desc)
     * - listed: Filter by listed status (true/false)
     * - city: Filter by city
     * - include: Comma-separated list of relationships to include (default: all)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Property::query();

        // Eager load only necessary relationships for list view
        $query->with([
            'images' => function ($query) {
                $query->where('is_primary', true)->orderBy('order');
            },
            'reviews' => function ($query) {
                // Only need ratings for average calculation
                $query->select('id', 'property_id', 'rating');
            },
            'amenities', // Load amenities for list view if needed later
        ]);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('public_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('address_city', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('address_state', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('summary', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by listed status
        if ($request->has('listed')) {
            $listed = filter_var($request->listed, FILTER_VALIDATE_BOOLEAN);
            $query->where('listed', $listed);
        }

        // Filter by city
        if ($request->has('city') && !empty($request->city)) {
            $query->where('address_city', 'LIKE', "%{$request->city}%");
        }

        // Filter by property type
        if ($request->has('property_type') && !empty($request->property_type)) {
            $query->where('property_type', $request->property_type);
        }

        // Sorting
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $sortBy = $request->sort_by;
            $sortOrder = in_array(strtolower($request->sort_order), ['asc', 'desc']) 
                ? strtolower($request->sort_order) 
                : 'desc';
            
            $allowedSortFields = [
                'name',
                'public_name',
                'address_city',
                'address_state',
                'created_at',
                'updated_at',
                'capacity_max',
            ];
            
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $properties = $query->paginate($perPage);

        // Transform the response to minimal format for list view
        $properties->getCollection()->transform(function ($property) {
            return $this->formatPropertyMinimalResponse($property);
        });

        return response()->json($properties);
    }

    /**
     * Display the specified property by ID.
     */
    public function show(string $id): JsonResponse
    {
        $property = Property::with([
            'houseRules',
            'amenities',
            'images' => function ($query) {
                $query->orderBy('order')->orderBy('is_primary', 'desc');
            },
            'reviews' => function ($query) {
                $query->orderBy('reviewed_at', 'desc');
            },
            'parent',
            'children',
            'roomDetails',
        ])->findOrFail($id);

        // For detail view, include all reviews (no limit)
        return response()->json($this->formatPropertyResponse($property, false));
    }

    /**
     * Format minimal property response for list view.
     * 
     * @param Property $property The property instance
     * @return array
     */
    protected function formatPropertyMinimalResponse(Property $property): array
    {
        // Calculate average rating from reviews
        $averageRating = $property->reviews->avg('rating');
        
        // Get primary image
        $primaryImage = $property->images->firstWhere('is_primary');

        return [
            'id' => $property->id,
            'name' => $property->name,
            'primary_image' => $primaryImage ? [
                'url' => $primaryImage->url,
            ] : null,
            'reviews_summary' => [
                'average_rating' => $averageRating ? round($averageRating, 2) : null,
            ],
        ];
    }

    /**
     * Format property response with all relationships.
     * 
     * @param Property $property The property instance
     * @param bool $limitReviews Whether to limit reviews to 10 (for list view)
     * @return array
     */
    protected function formatPropertyResponse(Property $property, bool $limitReviews = true): array
    {
        // Calculate average rating from reviews
        $reviews = $property->reviews;
        $averageRating = $reviews->avg('rating');
        $totalReviews = $reviews->count();
        
        // Calculate percentage of reviews by rating
        $avg5 = $totalReviews > 0 ? round(($reviews->where('rating', 5)->count() / $totalReviews) * 100, 2) : 0;
        $avg4 = $totalReviews > 0 ? round(($reviews->where('rating', 4)->count() / $totalReviews) * 100, 2) : 0;
        $avg3 = $totalReviews > 0 ? round(($reviews->where('rating', 3)->count() / $totalReviews) * 100, 2) : 0;
        $avg2 = $totalReviews > 0 ? round(($reviews->where('rating', 2)->count() / $totalReviews) * 100, 2) : 0;
        $avg1 = $totalReviews > 0 ? round(($reviews->where('rating', 1)->count() / $totalReviews) * 100, 2) : 0;
        
        // Calculate average ratings for detailed categories
        $categoryRatings = [
            'cleanliness' => [],
            'accuracy' => [],
            'checkin' => [],
            'communication' => [],
            'location' => [],
        ];
        
        // Collect ratings from detailed_ratings array
        foreach ($reviews as $review) {
            $detailedRatings = $review->detailed_ratings ?? [];
            if (is_array($detailedRatings)) {
                foreach ($detailedRatings as $detailedRating) {
                    $type = $detailedRating['type'] ?? null;
                    $rating = $detailedRating['rating'] ?? null;
                    
                    if ($rating !== null && $rating > 0 && in_array($type, array_keys($categoryRatings))) {
                        $categoryRatings[$type][] = $rating;
                    }
                }
            }
        }
        
        // Calculate averages for each category
        $avgCleanliness = !empty($categoryRatings['cleanliness']) 
            ? round(array_sum($categoryRatings['cleanliness']) / count($categoryRatings['cleanliness']), 2) 
            : null;
        $avgAccuracy = !empty($categoryRatings['accuracy']) 
            ? round(array_sum($categoryRatings['accuracy']) / count($categoryRatings['accuracy']), 2) 
            : null;
        $avgCheckin = !empty($categoryRatings['checkin']) 
            ? round(array_sum($categoryRatings['checkin']) / count($categoryRatings['checkin']), 2) 
            : null;
        $avgCommunication = !empty($categoryRatings['communication']) 
            ? round(array_sum($categoryRatings['communication']) / count($categoryRatings['communication']), 2) 
            : null;
        $avgLocation = !empty($categoryRatings['location']) 
            ? round(array_sum($categoryRatings['location']) / count($categoryRatings['location']), 2) 
            : null;

        // Get primary image
        $primaryImage = $property->images->firstWhere('is_primary');
        
        // Format amenities with full details including icons
        $amenities = $property->amenities->map(function ($amenity) {
            return [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'display_name' => $amenity->display_name,
                'icon_url' => $amenity->icon_url,
            ];
        })->toArray();

        // Format house rules
        $houseRules = $property->houseRules ? [
            'pets_allowed' => $property->houseRules->pets_allowed ?? false,
            'smoking_allowed' => $property->houseRules->smoking_allowed ?? false,
            'events_allowed' => $property->houseRules->events_allowed ?? false,
        ] : null;

        // Format address
        $address = [
            'number' => $property->address_number,
            'street' => $property->address_street,
            'city' => $property->address_city,
            'state' => $property->address_state,
            'postcode' => $property->address_postcode,
            'country_code' => $property->address_country_code,
            'country_name' => $property->address_country_name,
            'display' => $property->address_display,
            'coordinates' => [
                'latitude' => $property->latitude,
                'longitude' => $property->longitude,
            ],
        ];

        // Format capacity
        $capacity = [
            'max' => $property->capacity_max,
            'bedrooms' => $property->capacity_bedrooms,
            'beds' => $property->capacity_beds,
            'bathrooms' => $property->capacity_bathrooms,
        ];

        // Format images
        $images = $property->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'caption' => $image->caption,
                'order' => $image->order,
                'is_primary' => $image->is_primary,
            ];
        })->toArray();

        // Format reviews (limit to 10 most recent for list view, or all for detail)
        $reviewsCollection = $limitReviews ? $property->reviews->take(10) : $property->reviews;
        $reviews = $reviewsCollection->map(function ($review) {
            return [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name,
                'reviewer_avatar' => $review->reviewer_avatar,
                'rating' => $review->rating,
                'rating_platform_original' => $review->rating_platform_original,
                'comment' => $review->comment,
                'reviewed_at' => $review->reviewed_at?->toISOString(),
                'responded_at' => $review->responded_at?->toISOString(),
                'can_respond' => $review->can_respond,
                'platform' => $review->platform,
                'language' => $review->language,
                'guest_data' => $review->guest_data,
                'responses' => $review->responses,
                'private_feedback' => $review->private_feedback,
                'detailed_ratings' => $review->detailed_ratings,
            ];
        })->toArray();

        // Format parent property (if exists)
        $parent = $property->parent ? [
            'id' => $property->parent->id,
            'name' => $property->parent->name,
            'public_name' => $property->parent->public_name,
        ] : null;

        // Format child properties
        $children = $property->children->map(function ($child) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'public_name' => $child->public_name,
            ];
        })->toArray();

        // Format room details
        $roomDetails = $property->roomDetails->map(function ($roomDetail) {
            $beds = [];
            if (!empty($roomDetail->beds) && is_array($roomDetail->beds)) {
                foreach ($roomDetail->beds as $bed) {
                    $beds[] = [
                        'type' => $bed['type'] ?? null,
                        'type_display' => $this->formatBedType($bed['type'] ?? null),
                        'quantity' => $bed['quantity'] ?? 0,
                    ];
                }
            }
            
            return [
                'type' => $roomDetail->type,
                'type_display' => $this->formatRoomType($roomDetail->type),
                'beds' => $beds,
            ];
        })->toArray();

        return [
            'id' => $property->id,
            'name' => $property->name,
            'public_name' => $property->public_name,
            'picture_url' => $property->picture_url,
            'primary_image' => $primaryImage ? [
                'url' => $primaryImage->url,
                'caption' => $primaryImage->caption,
            ] : null,
            'timezone' => $property->timezone_offset,
            'listed' => $property->listed,
            'currency' => $property->currency,
            'summary' => $property->summary,
            'description' => $property->description,
            'checkin_time' => $property->checkin_time,
            'checkout_time' => $property->checkout_time,
            'property_type' => $property->property_type,
            'room_type' => $property->room_type,
            'calendar_restricted' => $property->calendar_restricted,
            'address' => $address,
            'capacity' => $capacity,
            'house_rules' => $houseRules,
            'amenities' => $amenities,
            'images' => $images,
            'room_details' => $roomDetails,
            'reviews' => $reviews,
            'reviews_summary' => [
                'average_rating' => $averageRating ? round($averageRating, 2) : null,
                'total_reviews' => $totalReviews,
                'cleanliness' => $avgCleanliness,
                'accuracy' => $avgAccuracy,
                'checkin' => $avgCheckin,
                'communication' => $avgCommunication,
                'location' => $avgLocation,
                'avg' => [
                    '5' => $avg5,
                    '4' => $avg4,
                    '3' => $avg3,
                    '2' => $avg2,
                    '1' => $avg1,
                ],
            ],
            'parent' => $parent,
            'children' => $children,
            'created_at' => $property->created_at?->toISOString(),
            'updated_at' => $property->updated_at?->toISOString(),
        ];
    }

    /**
     * Format room type from snake_case to readable format.
     *
     * @param string|null $type
     * @return string|null
     */
    protected function formatRoomType(?string $type): ?string
    {
        if (empty($type)) {
            return null;
        }

        // Convert snake_case to title case
        return str_replace('_', ' ', ucwords($type, '_'));
    }

    /**
     * Format bed type from snake_case to readable format.
     *
     * @param string|null $type
     * @return string|null
     */
    protected function formatBedType(?string $type): ?string
    {
        if (empty($type)) {
            return null;
        }

        // Convert snake_case to title case
        return str_replace('_', ' ', ucwords($type, '_'));
    }
}

