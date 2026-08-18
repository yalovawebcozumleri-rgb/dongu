<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AdvertisementImpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SponsoredBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'placement' => ['required', 'string', Rule::in(Advertisement::PLACEMENTS)],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'sessionKey' => ['required', 'string', 'min:16', 'max:80'],
        ]);

        $platformColumn = $validated['platform'] === 'ios' ? 'ios_enabled' : 'android_enabled';
        $campaigns = Advertisement::query()
            ->currentlyActive()
            ->where('format', Advertisement::FORMAT_BANNER)
            ->whereNotNull('image_path')
            ->where($platformColumn, true)
            ->whereHas('placements', fn ($query) => $query->where('placement', $validated['placement']))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($campaigns->isEmpty()) {
            return response()->json(['data' => null]);
        }

        $campaigns = $campaigns->values();
        $fingerprint = sha1($campaigns->pluck('id')->implode(','));
        $sessionCacheKey = "sponsored-banner:session:{$validated['platform']}:{$validated['placement']}:{$validated['sessionKey']}:{$fingerprint}";
        $selectedId = Cache::remember($sessionCacheKey, now()->addHours(12), function () use ($campaigns, $validated, $fingerprint): int {
            $rotationKey = "sponsored-banner:rotation:{$validated['platform']}:{$validated['placement']}:{$fingerprint}";
            Cache::add($rotationKey, -1, now()->addDays(30));
            $position = (int) Cache::increment($rotationKey);

            return (int) $campaigns->get($position % $campaigns->count())->id;
        });
        $advertisement = $campaigns->firstWhere('id', $selectedId) ?? $campaigns->first();
        return response()->json(['data' => $this->serialize($request, $advertisement)]);
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
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);
        $platformEnabled = $validated['platform'] === 'ios'
            ? $advertisement->ios_enabled
            : $advertisement->android_enabled;

        abort_unless(
            $advertisement->format === Advertisement::FORMAT_BANNER
            && $advertisement->image_path
            && $platformEnabled
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
            'slot_index' => 1,
        ], [
            'user_id' => $request->user('sanctum')?->id,
            'viewed_at' => now(),
        ]);

        if ($click && $impression->clicked_at === null) {
            $impression->update(['clicked_at' => now()]);
        }

        return response()->json(['data' => ['recorded' => true]]);
    }

    private function serialize(Request $request, Advertisement $advertisement): array
    {
        return [
            'id' => $advertisement->id,
            'sponsorName' => $advertisement->sponsor_name,
            'headline' => $advertisement->headline,
            'body' => $advertisement->body,
            'ctaLabel' => $advertisement->cta_label,
            'targetUrl' => $advertisement->target_url,
            'imageUrl' => $request->root()."/api/v1/advertisements/{$advertisement->id}/image",
        ];
    }
}