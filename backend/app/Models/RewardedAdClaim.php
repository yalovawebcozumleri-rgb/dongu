<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardedAdClaim extends Model
{
    public const PENDING = 'pending';
    public const REWARDED = 'rewarded';
    public const VERIFIED = 'verified';

    protected $fillable = ['user_id', 'listing_id', 'token_hash', 'reward_type', 'status', 'transaction_id', 'expires_at', 'rewarded_at', 'verified_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'rewarded_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
