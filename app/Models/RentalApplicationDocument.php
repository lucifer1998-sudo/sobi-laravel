<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalApplicationDocument extends Model
{
    protected $guarded = ['id'];

    /**
     * The path is on a private disk and is only ever reachable through the
     * gated download route, so it is not serialised.
     */
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }
}
