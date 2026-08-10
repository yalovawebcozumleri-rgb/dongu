<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardedUsageGrant extends Model
{
    protected $fillable = ['user_id', 'rewarded_ad_claim_id', 'reward_key', 'amount', 'remaining_amount', 'expires_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'remaining_amount' => 'integer', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function claim(): BelongsTo { return $this->belongsTo(RewardedAdClaim::class, 'rewarded_ad_claim_id'); }
}