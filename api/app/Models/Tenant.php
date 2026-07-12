<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'address',
        'phone',
        'timezone',
        'hold_minutes',
        'cancellation_window_hours',
        'max_advance_days',
        'status',
        'logo_url',
        'image_url',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'hold_minutes' => 'integer',
            'cancellation_window_hours' => 'integer',
            'max_advance_days' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
