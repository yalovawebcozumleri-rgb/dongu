<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceUsageEvent extends Model
{
    public const UPDATED_AT = null;
    public const LISTING_CREATED = 'listing_created';
    public const CONTACT_STARTED = 'contact_started';
    public const MESSAGE_CONVERSATION_STARTED = 'message_conversation_started';
    public const PICKUP_REQUESTED = 'pickup_requested';

    protected $fillable = ['user_id', 'event_type', 'target_user_id', 'listing_id', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
