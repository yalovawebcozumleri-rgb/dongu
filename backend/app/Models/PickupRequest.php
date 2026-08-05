<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupRequest extends Model
{
    public const INQUIRY = 'inquiry';
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';

    protected $fillable = [
        'listing_id', 'buyer_id', 'seller_id', 'status', 'delivery_code',
        'accepted_at', 'completed_at', 'cancelled_by_user_id', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_code' => 'encrypted',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
    public function messages(): HasMany { return $this->hasMany(ConversationMessage::class); }
    public function latestMessage() { return $this->hasOne(ConversationMessage::class)->latestOfMany(); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function userStates(): HasMany { return $this->hasMany(ConversationUserState::class); }

    public function involves(User $user): bool
    {
        return $this->buyer_id === $user->id || $this->seller_id === $user->id;
    }
}