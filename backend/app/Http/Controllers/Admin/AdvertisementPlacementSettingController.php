<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertisementPlacementSetting;
use App\Services\RewardedUsageGrantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvertisementPlacementSettingController extends Controller
{
    public function update(Request $request, AdvertisementPlacementSetting $setting): RedirectResponse
    {
        abort_if($setting->locked, 422, 'Bu Döngü reklam alanı sabittir ve kapatılamaz.');
        $base = $request->validate([
            'enabled' => ['required', 'boolean'],
            'sourceOrder' => ['required', 'array', 'min:1', 'max:2'],
            'sourceOrder.*' => ['required', 'string', 'distinct', Rule::in(AdvertisementPlacementSetting::NATIVE_SOURCES)],
            'firstAfter' => ['required', 'integer', 'min:0', 'max:1000'],
            'repeatEvery' => ['required', 'integer', 'min:0', 'max:1000'],
            'maxPerSession' => ['required', 'integer', 'min:1', 'max:1000'],
            'minItems' => ['required', 'integer', 'min:0', 'max:1000'],
            'adMobAndroidUnitId' => ['nullable', 'string', 'max:80', 'regex:/^ca-app-pub-\d+\/\d+$/'],
            'adMobIosUnitId' => ['nullable', 'string', 'max:80', 'regex:/^ca-app-pub-\d+\/\d+$/'],
            'boostHours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'dailyLimit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ordinals' => ['nullable', 'array', 'max:30'],
            'ordinals.*' => ['integer', 'distinct', 'min:1', 'max:1000'],
            'usageRewards' => ['nullable', 'array'],
            'usageRewards.*.key' => ['required', 'string', 'distinct', Rule::in(array_keys(RewardedUsageGrantService::DEFINITIONS))],
            'usageRewards.*.enabled' => ['required', 'boolean'],
            'usageRewards.*.amount' => ['required', 'integer', 'min:1', 'max:100'],
            'usageRewards.*.dailyLimit' => ['required', 'integer', 'min:1', 'max:100'],
            'usageRewards.*.validHours' => ['required', 'integer', 'min:1', 'max:720'],
        ], [
            'adMobAndroidUnitId.regex' => 'Android reklam birimi kimliği ca-app-pub-…/… biçiminde olmalıdır.',
            'adMobIosUnitId.regex' => 'iOS reklam birimi kimliği ca-app-pub-…/… biçiminde olmalıdır.',
        ]);
        $sources = $base['sourceOrder'];
        if ($setting->kind === AdvertisementPlacementSetting::KIND_NATIVE) {
            abort_if(in_array('admob', $sources, true) && empty($base['adMobAndroidUnitId']) && empty($base['adMobIosUnitId']), 422, 'AdMob kaynağı açıksa en az bir reklam birimi kimliği gereklidir.');
        } else {
            $sources = ['admob'];
        }
        $extra = $setting->settings ?? [];
        if ($setting->key === 'listing_rewarded_boost') {
            $extra = ['boost_hours' => $base['boostHours'] ?? 24, 'daily_limit' => $base['dailyLimit'] ?? 3];
        }
        if ($setting->key === 'pickup_interstitial') {
            $ordinals = collect($base['ordinals'] ?? [2, 4])->map(fn ($value) => (int) $value)->sort()->values()->all();
            abort_if($ordinals === [], 422, 'En az bir alım talebi sırası seçmelisin.');
            $extra = ['ordinals' => $ordinals];
        }
        if ($setting->key === 'rewarded_extra_rights') {
            $submitted = collect($base['usageRewards'] ?? [])->keyBy('key');
            $rewardSettings = collect(RewardedUsageGrantService::DEFINITIONS)->mapWithKeys(function (array $definition, string $key) use ($submitted): array {
                $item = $submitted->get($key, []);
                return [$key => [
                    'enabled' => (bool) ($item['enabled'] ?? false),
                    'amount' => (int) ($item['amount'] ?? 1),
                    'daily_limit' => (int) ($item['dailyLimit'] ?? 1),
                    'valid_hours' => (int) ($item['validHours'] ?? 24),
                ]];
            })->all();
            $extra = ['reward_item' => 'ek_hak', 'rewards' => $rewardSettings];
        }
        $setting->update([
            'enabled' => $base['enabled'],
            'source_order' => $sources,
            'first_after' => $base['firstAfter'],
            'repeat_every' => $base['repeatEvery'],
            'max_per_session' => $base['maxPerSession'],
            'min_items' => $base['minItems'],
            'admob_android_unit_id' => $base['adMobAndroidUnitId'] ?: null,
            'admob_ios_unit_id' => $base['adMobIosUnitId'] ?: null,
            'settings' => $extra,
        ]);

        return back()->with('success', $setting->label.' reklam ayarları güncellendi.');
    }
}