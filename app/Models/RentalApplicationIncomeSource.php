<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalApplicationIncomeSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['monthly_amount' => 'decimal:2'];
    }

    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }
}
