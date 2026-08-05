<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'public_area', 'full_address', 'latitude',
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
}