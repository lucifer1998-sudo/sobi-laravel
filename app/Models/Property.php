<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Property extends Model
{
    use HasFactory;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'parent_id',
        'name',
        'public_name',
        'picture_url',
        'timezone_offset',
        'listed',
        'currency',
        'summary',
        'description',
        'checkin_time',
        'checkout_time',
        'property_type',
        'room_type',
        'calendar_restricted',
        'address_number',
        'address_street',
        'address_city',
        'address_state',
        'address_postcode',
        'address_country_code',
        'address_country_name',
        'address_display',
        'latitude',
        'longitude',
        'capacity_max',
        'capacity_bedrooms',
        'capacity_beds',
        'capacity_bathrooms',
        'min_rent_age',
        'host_user_id'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'listed' => 'boolean',
            'calendar_restricted' => 'boolean',
            'latitude' => 'double',
            'longitude' => 'double',
            'checkin_time' => 'string',
            'checkout_time' => 'string',
            'capacity_max' => 'integer',
            'capacity_bedrooms' => 'integer',
            'capacity_beds' => 'integer',
            'capacity_bathrooms' => 'decimal:1',
            'timezone_offset' => 'string',
        ];
    }

    /**
     * Get the house rules for the property.
     */
    public function houseRules(): HasOne
    {
        return $this->hasOne(PropertyHouseRule::class, 'property_id');
    }

    /**
     * Get the property amenities (pivot).
     */
    public function propertyAmenities(): HasMany
    {
        return $this->hasMany(PropertyAmenity::class, 'property_id');
    }

    public function host(){
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * Get the amenities for the property.
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            Amenity::class,
            'property_amenities',
            'property_id',
            'amenity_id'
        );
    }

    /**
     * Get the images for the property.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    /**
     * Get the reviews for the property.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PropertyReview::class, 'property_id');
    }

    /**
     * Get the parent property.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'parent_id');
    }

    /**
     * Get the child properties.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Property::class, 'parent_id');
    }

    /**
     * Get the room details for the property.
     */
    public function roomDetails(): HasMany
    {
        return $this->hasMany(RoomDetail::class, 'property_id');
    }
}

