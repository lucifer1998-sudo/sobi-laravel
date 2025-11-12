<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'host',
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
            'min_rent_age' => $property->min_rent_age,
            'host_user_id' => $property->host_user_id,
            'host' => $property->host
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

    /**
     * Resolve amenity identifiers from the incoming payload.
     *
     * @param array $amenities
     * @return array<int, string>
     */
    protected function resolveAmenityIds(array $amenities): array
    {
        $ids = [];

        foreach ($amenities as $amenity) {
            if (is_array($amenity)) {
                $amenityId = $amenity['id'] ?? null;
                $amenityName = $amenity['name'] ?? $amenity['title'] ?? $amenity['label'] ?? null;
                $amenityDisplayName = $amenity['display_name'] ?? null;
                $amenityIcon = $amenity['icon_url'] ?? null;
            } else {
                $amenityId = is_string($amenity) ? $amenity : null;
                $amenityName = is_string($amenity) ? $amenity : null;
                $amenityDisplayName = null;
                $amenityIcon = null;
            }

            if ($amenityId) {
                $existingAmenity = Amenity::find($amenityId);
                if ($existingAmenity) {
                    $ids[] = $existingAmenity->id;
                    continue;
                }
            }

            if (!empty($amenityName)) {
                $existingAmenity = Amenity::firstOrCreate(
                    ['name' => $amenityName],
                    [
                        'id' => (string) Str::uuid(),
                        'display_name' => $amenityDisplayName ?? $this->formatAmenityDisplayName($amenityName),
                        'icon_url' => $amenityIcon,
                    ]
                );

                $ids[] = $existingAmenity->id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Persist property images, ensuring a single primary image.
     */
    protected function storePropertyImages(Property $property, ?array $primaryImage, array $images): void
    {
        $prepared = [];

        if (!empty($primaryImage['url'])) {
            $prepared[] = [
                'url' => $primaryImage['url'],
                'caption' => $primaryImage['caption'] ?? null,
                'is_primary' => true,
            ];
        }

        foreach ($images as $image) {
            if (!is_array($image) || empty($image['url'])) {
                continue;
            }

            $prepared[] = [
                'url' => $image['url'],
                'caption' => $image['caption'] ?? null,
                'is_primary' => $this->toBoolean($image['is_primary'] ?? false),
            ];
        }

        if (empty($prepared)) {
            return;
        }

        $primaryIndex = null;
        foreach ($prepared as $index => $image) {
            if (!empty($image['is_primary'])) {
                $primaryIndex = $index;
                break;
            }
        }

        if ($primaryIndex === null) {
            $primaryIndex = 0;
            $prepared[0]['is_primary'] = true;
        }

        if ($primaryIndex !== 0) {
            $primaryImageData = $prepared[$primaryIndex];
            unset($prepared[$primaryIndex]);
            array_unshift($prepared, $primaryImageData);
        }

        $unique = [];
        $deduped = [];
        foreach ($prepared as $image) {
            $url = $image['url'];
            if (isset($unique[$url])) {
                continue;
            }
            $unique[$url] = true;
            $deduped[] = $image;
        }

        $property->images()->delete();

        foreach ($deduped as $index => $image) {
            $property->images()->create([
                'url' => $image['url'],
                'caption' => $image['caption'],
                'order' => $index,
                'is_primary' => $index === 0,
            ]);
        }

        if (empty($property->picture_url) && isset($deduped[0]['url'])) {
            $property->picture_url = $deduped[0]['url'];
            $property->save();
        }
    }

    /**
     * Normalise room detail bed payloads.
     *
     * @param array<int, mixed>|null $beds
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeRoomDetailBeds(?array $beds): array
    {
        if (empty($beds) || !is_array($beds)) {
            return [];
        }

        $normalised = [];

        foreach ($beds as $bed) {
            if (!is_array($bed)) {
                continue;
            }

            $type = $bed['type'] ?? null;
            if (empty($type)) {
                continue;
            }

            $quantity = isset($bed['quantity']) ? (int) $bed['quantity'] : 0;

            $normalised[] = [
                'type' => $type,
                'quantity' => $quantity,
            ];
        }

        return array_values($normalised);
    }

    /**
     * Derive a display name for an amenity.
     */
    protected function formatAmenityDisplayName(string $value): string
    {
        return (string) Str::of($value)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title();
    }

    /**
     * Coerce different boolean representations into a boolean value.
     */
    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getListingsTable(Request $request)
    {
        $query = Property::query();

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
     * Store a newly created property along with its related resources.
     */
    public function store(Request $request)
    {
        return response()->json($request->all());
    }

    /**
     * Update an existing property along with its related resources.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'public_name' => 'nullable|string|max:255',
            'picture_url' => 'nullable|url|max:2048',
            'timezone_offset' => 'nullable|string|max:10',
            'listed' => 'nullable|boolean',
            'currency' => 'nullable|string|max:8',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'checkin_time' => 'nullable',
            'checkout_time' => 'nullable',
            'property_type' => 'nullable|string|max:255',
            'room_type' => 'nullable|string|max:255',
            'calendar_restricted' => 'nullable|boolean',
            'address' => 'nullable|array',
            'address.number' => 'nullable|string|max:255',
            'address.street' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postcode' => 'nullable|string|max:32',
            'address.country_code' => 'nullable|string|max:2',
            'address.country' => 'nullable|string|max:2',
            'address.country_name' => 'nullable|string|max:128',
            'address.display' => 'nullable|string|max:255',
            'address.latitude' => 'nullable|numeric',
            'address.longitude' => 'nullable|numeric',
            'address.coordinates' => 'nullable|array',
            'address.coordinates.latitude' => 'nullable|numeric',
            'address.coordinates.longitude' => 'nullable|numeric',
            'capacity' => 'nullable|array',
            'capacity.max' => 'nullable|integer|min:0',
            'capacity.bedrooms' => 'nullable|integer|min:0',
            'capacity.beds' => 'nullable|integer|min:0',
            'capacity.bathrooms' => 'nullable|numeric|min:0',
            'house_rules' => 'nullable|array',
            'house_rules.events_allowed' => 'nullable|boolean',
            'house_rules.pets_allowed' => 'nullable|boolean',
            'house_rules.smoking_allowed' => 'nullable|boolean',
            'amenities' => 'nullable|array',
            'amenities.*' => 'nullable',
            'images' => 'nullable|array',
            'images.*.url' => 'required_with:images|url|max:2048',
            'images.*.caption' => 'nullable|string|max:255',
            'images.*.order' => 'nullable|integer|min:0',
            'images.*.is_primary' => 'nullable|boolean',
            'primary_image' => 'nullable|array',
            'primary_image.url' => 'nullable|url|max:2048',
            'primary_image.caption' => 'nullable|string|max:255',
            'room_details' => 'nullable|array',
            'room_details.*.type' => 'required_with:room_details|string|max:255',
            'room_details.*.beds' => 'nullable|array',
            'room_details.*.beds.*.type' => 'nullable|string|max:255',
            'room_details.*.beds.*.quantity' => 'nullable|integer|min:0',
            'min_rent_age' => 'nullable|integer|min:0',
            'host_user_id' => 'nullable|integer',
        ]);

        $property = DB::transaction(function () use ($id, $validated) {
            $property = Property::whereKey($id)->lockForUpdate()->firstOrFail();

            $attributes = [
                'name' => $validated['name'],
                'public_name' => $validated['public_name'] ?? null,
                'timezone_offset' => $validated['timezone_offset'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'description' => $validated['description'] ?? null,
                'checkin_time' => $validated['checkin_time'] ?? null,
                'checkout_time' => $validated['checkout_time'] ?? null,
                'property_type' => $validated['property_type'] ?? null,
                'room_type' => $validated['room_type'] ?? null,
                'min_rent_age' => $validated['min_rent_age'] ?? null,
                'host_user_id' => $validated['host_user_id'] ?? null,
            ];

            if (array_key_exists('listed', $validated)) {
                $attributes['listed'] = $this->toBoolean($validated['listed']);
            }

            if (array_key_exists('calendar_restricted', $validated)) {
                $attributes['calendar_restricted'] = $this->toBoolean($validated['calendar_restricted']);
            }

            $pictureUrl = $property->picture_url;
            if (array_key_exists('picture_url', $validated)) {
                $pictureUrl = $validated['picture_url'];
            } elseif (data_get($validated, 'primary_image.url')) {
                $pictureUrl = data_get($validated, 'primary_image.url');
            }
            $attributes['picture_url'] = $pictureUrl;

            $address = data_get($validated, 'address');
            if (is_array($address)) {
                $latitudeValue = data_get($address, 'coordinates.latitude', data_get($address, 'latitude'));
                $longitudeValue = data_get($address, 'coordinates.longitude', data_get($address, 'longitude'));
                $countryCode = $address['country_code'] ?? $address['country'] ?? null;

                $attributes = array_merge($attributes, [
                    'address_number' => $address['number'] ?? null,
                    'address_street' => $address['street'] ?? null,
                    'address_city' => $address['city'] ?? null,
                    'address_state' => $address['state'] ?? null,
                    'address_postcode' => $address['postcode'] ?? null,
                    'address_country_code' => $countryCode,
                    'address_country_name' => $address['country_name'] ?? null,
                    'address_display' => $address['display'] ?? null,
                    'latitude' => $latitudeValue !== null && $latitudeValue !== '' ? (float) $latitudeValue : null,
                    'longitude' => $longitudeValue !== null && $longitudeValue !== '' ? (float) $longitudeValue : null,
                    'address_display' => $address['number'] . ', ' . $address['street'] . ', ' . $address['city'] . ', ' . $address['state'] . ', ' . $address['postcode'] . ', ' . $countryCode
                ]);
            }

            $capacity = data_get($validated, 'capacity');
            if (is_array($capacity)) {
                $attributes = array_merge($attributes, [
                    'capacity_max' => isset($capacity['max']) ? (int) $capacity['max'] : null,
                    'capacity_bedrooms' => isset($capacity['bedrooms']) ? (int) $capacity['bedrooms'] : null,
                    'capacity_beds' => isset($capacity['beds']) ? (int) $capacity['beds'] : null,
                    'capacity_bathrooms' => isset($capacity['bathrooms']) ? (float) $capacity['bathrooms'] : null,
                ]);
            }

            $property->fill($attributes);
            $property->save();

            if (array_key_exists('house_rules', $validated)) {
                $houseRulesData = $validated['house_rules'] ?? [];
                $property->houseRules()->updateOrCreate(
                    ['property_id' => $property->id],
                    [
                        'pets_allowed' => $this->toBoolean($houseRulesData['pets_allowed'] ?? false),
                        'smoking_allowed' => $this->toBoolean($houseRulesData['smoking_allowed'] ?? false),
                        'events_allowed' => $this->toBoolean($houseRulesData['events_allowed'] ?? false),
                    ]
                );
            }

            if (array_key_exists('amenities', $validated)) {
                $amenityIds = $this->resolveAmenityIds($validated['amenities'] ?? []);
                $property->amenities()->sync($amenityIds);
            }

            if (array_key_exists('room_details', $validated)) {
                $property->roomDetails()->delete();

                foreach ($validated['room_details'] ?? [] as $roomDetail) {
                    if (!is_array($roomDetail)) {
                        continue;
                    }

                    $type = $roomDetail['type'] ?? null;
                    if (empty($type)) {
                        continue;
                    }

                    $beds = $this->sanitizeRoomDetailBeds($roomDetail['beds'] ?? []);

                    $property->roomDetails()->create([
                        'type' => $type,
                        'beds' => $beds,
                    ]);
                }
            }

            return $property;
        });

        $property->refresh();

        $property->load([
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
        ]);

        return response()->json($this->formatPropertyResponse($property, false));
    }

    public function updatePhotos(Request $request, $id)
    {

        PropertyImage::where('property_id', $id)->delete();

        $requestImages = $request->images;
        $proprtyImages = [];
        if (count($requestImages) > 0) {

            $images = [];
            foreach ($requestImages as $image) {
                $imgData = [];
                $imgData['property_id'] = $id;
                $imgData['url'] = str_replace(config('app.url'),'', $image['url']);
                $imgData['order'] = $image['order'];
                $imgData['is_primary'] = $image['is_primary'];
                $images[] = $imgData;
            }

            $proprtyImages = PropertyImage::insert($images);
        }

        return response()->json($proprtyImages);
    }

    public function deletePhoto(Request $request, $id)
    {
        $propertyImage = PropertyImage::where('id', $id)->first();
        $primaryImage = $propertyImage->is_primary;
        $propertyId = $propertyImage->property_id;
        $propertyImage->delete();

        if ($primaryImage) {
            $img = PropertyImage::where('property_id', $propertyId)->first();
            $img->update(['is_primary' => 1]);
        }

        return response()->json($propertyImage);
    }

    public function uploadPhotos(Request $request, $id)
    {
        try {
            $listing = Property::findOrFail($id);

            $request->validate([
                'photo' => 'required|image|mimes:jpeg,jpg,png,gif,webp',
            ]);

            $file = $request->file('photo');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file uploaded'
                ], 422);
            }
            
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('listings/' . $id, $filename, 'public');


            $url = Storage::url($path);
            $maxOrder = PropertyImage::where('property_id', $id)->max('order') ?? -1;

            $listingImage = PropertyImage::create([
                'property_id' => $id,
                'url' => $url,
                'order' => $maxOrder + 1,
                'is_primary' => false
            ]);

            $imageCount = PropertyImage::where('property_id', $id)->count();
            if ($imageCount === 1) {
                $listingImage->update(['is_primary' => true]);
            }

            return response()->json([
                'id' => $listingImage->id,
                'url' => asset($url),
                'order' => $listingImage->order,
                'is_primary' => $listingImage->is_primary,
                'caption' => $listingImage->caption,
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Listing not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Photo upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to upload photo: ' . $e->getMessage()
            ], 500);
        }
    }
}
