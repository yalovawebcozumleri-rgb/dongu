<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\AdvertisementPlacementSetting;
use App\Models\Listing;
use App\Models\RewardedAdClaim;
use App\Services\RewardedListingBoostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RewardedListingBoostController extends Controller
{
    public function challenge(Request $request, Listing $listing): JsonResponse
    {
        $validated = $request->validate(['platform' => ['required', Rule::in(['android', 'ios'])]]);
        abort_unless($listing->user_id === $request->user()->id, 403, 'Yalnızca kendi ilanını öne çıkarabilirsin.');
        abort_unless($listing->status === Listing::STATUS_ACTIVE && (! $listing->expires_at || $listing->expires_at->isFuture()), 422, 'Yalnızca yayındaki ilanlar öne çıkarılabilir.');
        abort_if($listing->boosted_until?->isFuture(), 422, 'İlanın zaten öne çıkarılmış durumda.');
        $setting = AdvertisementPlacementSetting::forKey('listing_rewarded_boost');
        abort_unless($setting->enabled, 422, 'İlan öne çıkarma şu anda kullanıma kapalı.');
        $dailyLimit = max(1, (int) data_get($setting->settings, 'daily_limit', 3));
        $boostHours = max(1, (int) data_get($setting->settings, 'boost_hours', 24));
        abort_if(
            RewardedAdClaim::query()->where('user_id', $request->user()->id)->where('reward_type', 'listing_boost')->where('created_at', '>=', now()->subDay())->count() >= $dailyLimit,
            429,
            "24 saat içinde en fazla {$dailyLimit} öne çıkarma reklamı başlatabilirsin.",
        );
        $unitId = $validated['platform'] === 'ios' ? $setting->admob_ios_unit_id : $setting->admob_android_unit_id;
        abort_unless($unitId, 422, "Bu platform i\u{00E7}in \u{00F6}d\u{00FC}ll\u{00FC} reklam birimi hen\u{00FC}z tan\u{0131}mlanmad\u{0131}.");
        $token = Str::random(64);
        RewardedAdClaim::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'token_hash' => hash('sha256', $token),
            'reward_type' => 'listing_boost',
            'reward_amount' => 1,
            'expected_ad_unit_id' => $unitId,
            'expected_reward_item' => 'ilan_one_cikarma',
            'status' => RewardedAdClaim::PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json(['data' => [
            'token' => $token,
            'expiresAt' => now()->addMinutes(30)->toIso8601String(),
            'clientCompletionAllowed' => app()->environment(['local', 'testing']),
            'testMode' => config('advertising.admob.mode') === 'test',
            'boostHours' => $boostHours,
            'dailyLimit' => $dailyLimit,
            'adMobAndroidUnitId' => $setting->admob_android_unit_id,
            'adMobIosUnitId' => $setting->admob_ios_unit_id,
        ]]);
    }

    public function complete(Request $request, Listing $listing, RewardedListingBoostService $boosts): ListingResource
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Canlı ortamda reklam ödülü Google tarafından doğrulanır.');
        abort_unless($listing->user_id === $request->user()->id, 403);
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $claim = RewardedAdClaim::query()->where('token_hash', hash('sha256', $validated['token']))
            ->where('user_id', $request->user()->id)->where('listing_id', $listing->id)->firstOrFail();
        abort_if($claim->expires_at->isPast(), 422, 'Reklam ödülü süresi doldu. Lütfen yeniden dene.');

        return new ListingResource($boosts->grant($claim, false));
    }

    public function status(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->user_id === $request->user()->id, 403);
        $setting = AdvertisementPlacementSetting::forKey('listing_rewarded_boost');

        return response()->json(['data' => [
            'isBoosted' => $listing->boosted_until?->isFuture() ?? false,
            'boostedUntil' => $listing->boosted_until?->toIso8601String(),
            'enabled' => $setting->enabled,
            'boostHours' => max(1, (int) data_get($setting->settings, 'boost_hours', 24)),
            'dailyLimit' => max(1, (int) data_get($setting->settings, 'daily_limit', 3)),
        ]]);
    }
}
