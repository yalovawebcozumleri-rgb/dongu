<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceUsagePolicy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return collect($this->getFillablePolicyFields())->mapWithKeys(fn ($field) => [$field => 'integer'])->all();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    public function getFillablePolicyFields(): array
    {
        return [
            'new_account_hours', 'new_account_listing_limit', 'listing_24h_limit', 'active_listing_limit',
            'new_account_pickup_limit', 'pickup_24h_limit', 'active_pickup_limit', 'listing_pending_pickup_limit',
            'new_account_contact_limit', 'contact_24h_limit', 'new_account_message_conversation_limit',
            'message_conversation_24h_limit', 'same_seller_contact_24h_limit', 'contact_cooldown_seconds',
            'messages_per_minute', 'messages_per_hour', 'messages_per_24h', 'unanswered_message_limit',
        ];
    }
}
