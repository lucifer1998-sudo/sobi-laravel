<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalApplication extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_student' => 'boolean',
            'is_employed' => 'boolean',
            'has_past_employer' => 'boolean',
            'has_legal_issue' => 'boolean',
            'monthly_cost' => 'decimal:2',
            'monthly_stipend' => 'decimal:2',
            'monthly_income' => 'decimal:2',
            'past_monthly_income' => 'decimal:2',
            'move_in_date' => 'date:Y-m-d',
            'move_out_date' => 'date:Y-m-d',
            'enrollment_date' => 'date:Y-m-d',
            'graduation_date' => 'date:Y-m-d',
            'employment_start_date' => 'date:Y-m-d',
            'past_start_date' => 'date:Y-m-d',
            'past_end_date' => 'date:Y-m-d',
            'desired_move_in' => 'date:Y-m-d',
            'desired_move_out' => 'date:Y-m-d',
            'agreed_at' => 'datetime',
        ];
    }

    /**
     * The signature is a scan of the applicant's handwriting, so its path is
     * never serialised. The row id goes with it: the receipt is public to
     * anyone holding the link, and a sequential id would tell them how many
     * applications exist. Everything addresses an application by application_id.
     */
    protected $hidden = ['id', 'signature_path'];

    protected $appends = ['full_name'];

    /**
     * The listing the application was made for. Nullable, because a listing can
     * be taken down after someone applies for it.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function kids(): HasMany
    {
        return $this->hasMany(RentalApplicationKid::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(RentalApplicationPet::class);
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(RentalApplicationIncomeSource::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RentalApplicationDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * The public key, so route model binding never exposes the row id.
     */
    public function getRouteKeyName(): string
    {
        return 'application_id';
    }
}
