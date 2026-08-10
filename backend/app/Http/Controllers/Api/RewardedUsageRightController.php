<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RewardedAdClaim;
use App\Services\RewardedUsageGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RewardedUsageRightController extends Controller
{
    public function challenge(Request $request, string $rewardKey, RewardedUsageGrantService $rewards): JsonResponse
    {
        abort_unless(isset(RewardedUsageGrantService::DEFINITIONS[$rewardKey]), 404);
        $validated = $request->validate(['platform' => ['required', Rule::in(['android', 'ios'])]]);
        $offer = $rewards->offer($request->user(), $rewardKey);
        abort_unless($offer, 404, 'Bu ek hak kapalı.');
        abort_unless($offer['available'], 429, 'Bu hak için son 24 saatte izleyebileceğin reklam sınırına ulaştın.');
        $setting = $rewards->setting();
        $unitId = $validated['platform'] === 'ios' ? $setting->admob_ios_unit_id : $setting->admob_android_unit_id;
        abort_unless($unitId, 422, 'Bu platform için ödüllü reklam birimi henüz tanımlanmadı.');
        $token = Str::random(64);
        RewardedAdClaim::create([
            'user_id' => $request->user()->id,
            'listing_id' => null,
            'token_hash' => hash('sha256', $token),
            'reward_type' => 'usage_bonus',
            'reward_key' => $rewardKey,
            'reward_amount' => $offer['amount'],
            'expected_ad_unit_id' => $unitId,
            'expected_reward_item' => (string) data_get($setting->settings, 'reward_item', 'ek_hak'),
            'status' => RewardedAdClaim::PENDING,
            'expires_at' => now()->addMinutes(20),
        ]);

        return response()->json(['data' => [
            'token' => $token,
            'offer' => $offer,
            'testMode' => config('advertising.admob.mode') === 'test',
            'clientCompletionAllowed' => app()->environment(['local', 'testing']),
            'adMobAndroidUnitId' => $setting->admob_android_unit_id,
            'adMobIosUnitId' => $setting->admob_ios_unit_id,
        ]]);
    }

    public function complete(Request $request, string $rewardKey, RewardedUsageGrantService $rewards): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Canlı ortamda reklam ödülü Google tarafından doğrulanır.');
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $claim = RewardedAdClaim::query()->where('token_hash', hash('sha256', $validated['token']))
            ->where('user_id', $request->user()->id)->where('reward_key', $rewardKey)->firstOrFail();
        abort_if($claim->expires_at->isPast(), 422, 'Reklam ödülü süresi doldu.');
        $rewards->grant($claim, false);

        return response()->json(['data' => $rewards->offer($request->user(), $rewardKey)]);
    }

    public function status(Request $request, string $rewardKey, RewardedUsageGrantService $rewards): JsonResponse
    {
        abort_unless(isset(RewardedUsageGrantService::DEFINITIONS[$rewardKey]), 404);
        return response()->json(['data' => $rewards->offer($request->user(), $rewardKey)]);
    }
}