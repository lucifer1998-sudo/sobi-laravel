<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyReview extends Model
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
        'property_id',
        'reviewer_name',
        'reviewer_avatar',
        'guest_data',
        'rating',
        'rating_platform_original',
        'comment',
        'reviewed_at',
        'responded_at',
        'can_respond',
        'platform',
        'language',
        'responses',
        'private_feedback',
        'detailed_ratings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'responded_at' => 'datetime',
            'can_respond' => 'boolean',
            'responses' => 'array',
            'detailed_ratings' => 'array',
            'guest_data' => 'array',
        ];
    }

    /**
     * Get the property that owns the review.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}

