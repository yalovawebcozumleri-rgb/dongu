<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\RewardedAdClaim;
use Illuminate\Support\Facades\DB;

class RewardedListingBoostService
{
    public function grant(RewardedAdClaim $claim, bool $verified, ?string $transactionId = null): Listing
    {
        return DB::transaction(function () use ($claim, $verified, $transactionId) {
            $lockedClaim = RewardedAdClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $listing = Listing::query()->lockForUpdate()->findOrFail($lockedClaim->listing_id);
            if (! in_array($lockedClaim->status, [RewardedAdClaim::REWARDED, RewardedAdClaim::VERIFIED], true)) {
                $base = $listing->boosted_until?->isFuture() ? $listing->boosted_until : now();
                $listing->forceFill(['boosted_until' => $base->copy()->addHours(24)])->save();
            }
            $lockedClaim->forceFill([
                'status' => $verified ? RewardedAdClaim::VERIFIED : RewardedAdClaim::REWARDED,
                'transaction_id' => $transactionId ?: $lockedClaim->transaction_id,
                'rewarded_at' => $lockedClaim->rewarded_at ?: now(),
                'verified_at' => $verified ? now() : $lockedClaim->verified_at,
            ])->save();
            return $listing->fresh(['seller', 'materials', 'photos']);
        });
    }
}
