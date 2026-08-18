<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AdvertisementImpression;
use App\Models\AdvertisementPlacementSetting;
use App\Models\AdMobRuntimeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdvertisementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'placement' => ['required', 'string', Rule::in(Advertisement::PLACEMENTS)],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
        ]);

        $placement = $filters['placement'];
        $platform = $filters['platform'] ?? $this->platformFromRequest($request);
        $setting = AdvertisementPlacementSetting::forKey($placement);
        $platformEnabled = $setting->platformEnabled($platform);
        // Sponsorlu banner kampanyaları ayrı /sponsored-banners akışından gelir.
        // Bu uç yalnızca mevcut AdMob sözleşmesini ve yerleşim ayarlarını taşır.
        $sources = [AdvertisementPlacementSetting::SOURCE_ADMOB];
        $advertisements = collect();

        return response()->json([
            'data' => $advertisements->map(fn (Advertisement $advertisement) => [
                'id' => $advertisement->id,
                'sponsorName' => $advertisement->sponsor_name,
                'headline' => $advertisement->headline,
                'body' => $advertisement->body,
                'ctaLabel' => $advertisement->cta_label,
                'targetUrl' => $advertisement->target_url,
                'backgroundColor' => $advertisement->background_color,
                'format' => 'native',
                'imageUrl' => $advertisement->image_path ? $request->root()."/api/v1/advertisements/{$advertisement->id}/image" : null,
            ])->values(),
            'meta' => [
                'placement' => $placement,
                'enabled' => $platformEnabled,
                'sourceOrder' => $sources,
                'firstAfter' => $setting->first_after,
                'repeatEvery' => $setting->repeat_every,
                'maxPerSession' => $setting->kind === AdvertisementPlacementSetting::KIND_NATIVE
                    ? min($setting->max_per_session, $setting->nativeAdLimit())
                    : $setting->max_per_session,
                'minItems' => $setting->min_items,
                'adMobAndroidUnitId' => $setting->adMobUnitId('android', 'native'),
                'adMobIosUnitId' => $setting->adMobUnitId('ios', 'native'),
                'adMobAndroidEnvironment' => $setting->adMobMode('android'),
                'adMobIosEnvironment' => $setting->adMobMode('ios'),
                'adMobConfigurationVersion' => AdMobRuntimeSetting::configurationVersion(),
                'androidEnabled' => $setting->platformEnabled('android'),
                'iosEnabled' => $setting->platformEnabled('ios'),
            ],
        ]);
    }

    public function image(Advertisement $advertisement): BinaryFileResponse
    {
        abort_unless($advertisement->image_path, 404);
        $path = Storage::disk('public')->path($advertisement->image_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function impression(Request $request, Advertisement $advertisement): JsonResponse
    {
        return $this->record($request, $advertisement, false);
    }

    public function click(Request $request, Advertisement $advertisement): JsonResponse
    {
        return $this->record($request, $advertisement, true);
    }

    private function record(Request $request, Advertisement $advertisement, bool $click): JsonResponse
    {
        $validated = $request->validate([
            'sessionKey' => ['required', 'string', 'min:16', 'max:80'],
            'placement' => ['required', 'string', Rule::in(Advertisement::PLACEMENTS)],
            'slotIndex' => ['required', 'integer', 'min:1', 'max:1000'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
        ]);

        $setting = AdvertisementPlacementSetting::forKey($validated['placement']);
        $platform = $validated['platform'] ?? $this->platformFromRequest($request);

        abort_unless(
            $setting->platformEnabled($platform)
            && $advertisement->is_active
            && ($advertisement->starts_at === null || $advertisement->starts_at->lte(now()))
            && ($advertisement->ends_at === null || $advertisement->ends_at->gt(now()))
            && $advertisement->placements()->where('placement', $validated['placement'])->exists(),
            404
        );

        $impression = AdvertisementImpression::firstOrCreate([
            'advertisement_id' => $advertisement->id,
            'session_key' => $validated['sessionKey'],
            'placement' => $validated['placement'],
            'slot_index' => $validated['slotIndex'],
        ], ['user_id' => $request->user('sanctum')?->id, 'viewed_at' => now()]);

        if ($click && $impression->clicked_at === null) {
            $impression->update(['clicked_at' => now()]);
        }

        return response()->json(['data' => ['recorded' => true]]);
    }

    private function platformFromRequest(Request $request): ?string
    {
        $agent = strtolower($request->userAgent() ?? '');
        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios')) {
            return 'ios';
        }
        if (str_contains($agent, 'android') || str_contains($agent, 'okhttp')) {
            return 'android';
        }

        return null;
    }
}