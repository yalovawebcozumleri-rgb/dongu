<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'messages_enabled', 'pickup_requests_enabled', 'delivery_enabled',
        'reviews_enabled', 'listing_updates_enabled', 'marketing_enabled',
    ];

    protected function casts(): array
    {
        return [
            'messages_enabled' => 'boolean',
            'pickup_requests_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'reviews_enabled' => 'boolean',
            'listing_updates_enabled' => 'boolean',
            'marketing_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
