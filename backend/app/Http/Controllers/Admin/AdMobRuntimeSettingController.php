<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertisementPlacementSetting;
use App\Models\AdMobRuntimeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdMobRuntimeSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'androidMode' => ['required', Rule::in(AdMobRuntimeSetting::MODES)],
            'iosMode' => ['required', Rule::in(AdMobRuntimeSetting::MODES)],
            'confirmProduction' => ['nullable', 'boolean'],
        ]);

        $current = AdMobRuntimeSetting::current();
        $requiresProductionConfirmation = ($current->android_mode !== AdMobRuntimeSetting::MODE_PRODUCTION && $validated['androidMode'] === AdMobRuntimeSetting::MODE_PRODUCTION)
            || ($current->ios_mode !== AdMobRuntimeSetting::MODE_PRODUCTION && $validated['iosMode'] === AdMobRuntimeSetting::MODE_PRODUCTION);

        if ($requiresProductionConfirmation && ! $request->boolean('confirmProduction')) {
            return back()->withErrors(['confirmation' => 'Canlı reklamları etkinleştirmek için production onayını vermelisin.']);
        }

        foreach (['android', 'ios'] as $platform) {
            if ($validated[$platform.'Mode'] === AdMobRuntimeSetting::MODE_PRODUCTION) {
                $missing = $this->missingProductionUnits($platform);
                if ($missing->isNotEmpty()) {
                    return back()->withErrors([
                        $platform.'Mode' => ucfirst($platform).' production modu açılamadı. Gerçek reklam birimi eksik alanlar: '.$missing->implode(', '),
                    ]);
                }
            }
        }

        if ($current->android_mode === $validated['androidMode'] && $current->ios_mode === $validated['iosMode']) {
            return back()->with('success', 'Reklam çalışma modunda değişiklik yok.');
        }

        DB::transaction(function () use ($request, $validated): void {
            $setting = AdMobRuntimeSetting::query()->lockForUpdate()->findOrFail(AdMobRuntimeSetting::SINGLETON_ID);
            $previousAndroidMode = $setting->android_mode;
            $previousIosMode = $setting->ios_mode;
            $nextVersion = max(1, (int) $setting->configuration_version) + 1;

            $setting->update([
                'android_mode' => $validated['androidMode'],
                'ios_mode' => $validated['iosMode'],
                'configuration_version' => $nextVersion,
                'changed_by_user_id' => $request->user()->id,
            ]);

            DB::table('admob_runtime_setting_audits')->insert([
                'admob_runtime_setting_id' => $setting->id,
                'previous_android_mode' => $previousAndroidMode,
                'new_android_mode' => $validated['androidMode'],
                'previous_ios_mode' => $previousIosMode,
                'new_ios_mode' => $validated['iosMode'],
                'configuration_version' => $nextVersion,
                'changed_by_user_id' => $request->user()->id,
                'created_at' => now(),
            ]);
        });

        AdMobRuntimeSetting::clearCache();

        return back()->with('success', 'Android ve iOS reklam çalışma modları güncellendi.');
    }

    private function missingProductionUnits(string $platform)
    {
        $column = $platform === 'ios' ? 'admob_ios_unit_id' : 'admob_android_unit_id';

        return AdvertisementPlacementSetting::query()->get()
            ->filter(fn (AdvertisementPlacementSetting $setting): bool => $setting->platformEnabled($platform)
                && in_array(AdvertisementPlacementSetting::SOURCE_ADMOB, $setting->source_order ?? [], true)
                && blank($setting->{$column}))
            ->pluck('label')
            ->values();
    }
}
