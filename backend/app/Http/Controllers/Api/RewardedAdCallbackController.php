<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RewardedAdClaim;
use App\Services\AdMobSsvVerifier;
use App\Services\RewardedListingBoostService;
use App\Services\RewardedUsageGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RewardedAdCallbackController extends Controller
{
    public function __invoke(Request $request, AdMobSsvVerifier $verifier, RewardedListingBoostService $boosts, RewardedUsageGrantService $usageRewards): JsonResponse
    {
        abort_unless($verifier->verify($request), 403, 'Geçersiz reklam doğrulama imzası.');
        $token = (string) $request->query('custom_data');
        $claim = RewardedAdClaim::query()->where('token_hash', hash('sha256', $token))->first();
        if (! $claim || $claim->expires_at->isPast()) return response()->json(['data' => ['verified' => true, 'rewardGranted' => false]]);
        $callbackUnit = (string) $request->query('ad_unit');
        $expectedUnit = (string) $claim->expected_ad_unit_id;
        abort_if($expectedUnit !== '' && ! in_array($callbackUnit, [$expectedUnit, Str::afterLast($expectedUnit, '/')], true), 403, 'Beklenmeyen reklam birimi.');
        $expectedItem = (string) $claim->expected_reward_item;
        abort_if($expectedItem !== '' && (string) $request->query('reward_item') !== $expectedItem, 403, 'Beklenmeyen reklam ödülü.');
        $transactionId = (string) $request->query('transaction_id');
        if ($transactionId !== '' && RewardedAdClaim::query()->where('transaction_id', $transactionId)->whereKeyNot($claim->id)->exists()) {
            return response()->json(['data' => ['verified' => true, 'rewardGranted' => false]]);
        }
        if ($claim->reward_type === 'usage_bonus') {
            $grant = $usageRewards->grant($claim, true, $transactionId ?: null);
            return response()->json(['data' => ['verified' => true, 'rewardGranted' => true, 'rewardKey' => $grant->reward_key]]);
        }
        $listing = $boosts->grant($claim, true, $transactionId ?: null);

        return response()->json(['data' => ['verified' => true, 'rewardGranted' => true, 'listingId' => $listing->id]]);
    }
}