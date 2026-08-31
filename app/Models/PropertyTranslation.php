<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTranslation extends Model
{
    /**
     * The only property text the public site renders. Everything else the API
     * returns is either internal or never shown, so there is nothing to gain
     * from translating it.
     */
    public const TRANSLATABLE = ['name', 'description'];

    protected $fillable = [
        'property_id',
        'locale',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
