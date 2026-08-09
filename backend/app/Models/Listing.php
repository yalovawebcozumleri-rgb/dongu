<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const PACKAGING_CONDITION_VERSION = 'doa-2026-08-v1';

    protected $fillable = [
        'user_id', 'source_address_id', 'province_id', 'district_id', 'status', 'public_area', 'approximate_latitude',
        'approximate_longitude', 'description',
        'packaging_condition_confirmed_at', 'packaging_condition_version',
        'published_at', 'expires_at', 'boosted_until',
    ];

    protected function casts(): array
    {
        return [
            'approximate_latitude' => 'float',
            'approximate_longitude' => 'float',
            'packaging_condition_confirmed_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'boosted_until' => 'datetime',
        ];
    }

    public function sourceAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'source_address_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ListingMaterial::class)->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ListingFavorite::class);
    }

    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class);
    }
    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }
    public function privateLocation(): HasOne
    {
        return $this->hasOne(ListingPrivateLocation::class);
    }
}
