<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'province_id', 'district_id', 'neighborhood',
        'public_area', 'full_address', 'latitude',
        'longitude', 'delivery_notes', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'full_address' => 'encrypted',
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'delivery_notes' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceListings(): HasMany
    {
        return $this->hasMany(Listing::class, 'source_address_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
