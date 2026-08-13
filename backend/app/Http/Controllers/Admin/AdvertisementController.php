<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AdvertisementPlacementSetting;
use App\Services\RewardedUsageGrantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdvertisementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'scheduled', 'paused', 'ended'])],
            'placement' => ['nullable', Rule::in(Advertisement::PLACEMENTS)],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? '';
        $placement = $filters['placement'] ?? '';
        $perPage = (int) ($filters['per_page'] ?? 50);

        $query = Advertisement::query()
            ->with('placements')
            ->withCount(['impressions', 'impressions as clicks_count' => fn ($query) => $query->whereNotNull('clicked_at')])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('sponsor_name', 'like', "%{$search}%")
                ->orWhere('headline', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->when($placement !== '', fn (Builder $query) => $query->whereHas('placements', fn ($query) => $query->where('placement', $placement)))
            ->when($status === 'active', fn (Builder $query) => $query->currentlyActive())
            ->when($status === 'scheduled', fn (Builder $query) => $query->where('is_active', true)->where('starts_at', '>', now()))
            ->when($status === 'paused', fn (Builder $query) => $query->where('is_active', false))
            ->when($status === 'ended', fn (Builder $query) => $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<=', now()))
            ->orderByDesc('is_active')->orderByDesc('priority')->latest('id');

        $campaigns = $query->paginate($perPage)->withQueryString();
        $campaignIds = $campaigns->getCollection()->pluck('id');
        $statisticsByCampaign = DB::table('advertisement_impressions')
            ->whereIn('advertisement_id', $campaignIds)
            ->select('advertisement_id', 'placement')
            ->selectRaw('COUNT(*) as impressions')
            ->selectRaw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks')
            ->groupBy('advertisement_id', 'placement')
            ->get()->groupBy('advertisement_id');

        $campaigns->through(function (Advertisement $advertisement) use ($statisticsByCampaign): array {
            $campaignStatistics = $statisticsByCampaign->get($advertisement->id, collect())->keyBy('placement');
            $statistics = collect(Advertisement::PLACEMENTS)->mapWithKeys(function (string $placement) use ($campaignStatistics): array {
                $row = $campaignStatistics->get($placement);
                return [$placement => ['impressions' => (int) ($row->impressions ?? 0), 'clicks' => (int) ($row->clicks ?? 0)]];
            });
            $status = ! $advertisement->is_active
                ? 'paused'
                : ($advertisement->starts_at?->isFuture()
                    ? 'scheduled'
                    : ($advertisement->ends_at?->isPast() ? 'ended' : 'active'));

            return [
                'id' => $advertisement->id,
                'sponsorName' => $advertisement->sponsor_name,
                'headline' => $advertisement->headline,
                'body' => $advertisement->body,
                'ctaLabel' => $advertisement->cta_label,
                'targetUrl' => $advertisement->target_url,
                'backgroundColor' => $advertisement->background_color,
                'format' => $advertisement->format,
                'imageUrl' => $advertisement->image_path ? url("/api/v1/advertisements/{$advertisement->id}/image") : null,
                'isActive' => $advertisement->is_active,
                'status' => $status,
                'startsAt' => $advertisement->starts_at?->toIso8601String(),
                'endsAt' => $advertisement->ends_at?->toIso8601String(),
                'createdAt' => $advertisement->created_at?->toIso8601String(),
                'priority' => $advertisement->priority,
                'placements' => $advertisement->placements->pluck('placement')->values(),
                'statistics' => $statistics,
                'impressions' => (int) $advertisement->impressions_count,
                'clicks' => (int) $advertisement->clicks_count,
            ];
        });

        $allCampaigns = Advertisement::query();
        $totalImpressions = (int) DB::table('advertisement_impressions')->count();
        $totalClicks = (int) DB::table('advertisement_impressions')->whereNotNull('clicked_at')->count();
        $coveredPlacements = DB::table('advertisement_placements')
            ->join('advertisements', 'advertisements.id', '=', 'advertisement_placements.advertisement_id')
            ->where('advertisements.is_active', true)
            ->where(fn ($query) => $query->whereNull('advertisements.starts_at')->orWhere('advertisements.starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('advertisements.ends_at')->orWhere('advertisements.ends_at', '>', now()))
            ->whereIn('advertisement_placements.placement', Advertisement::PLACEMENTS)
            ->distinct()
            ->pluck('advertisement_placements.placement');
        $placementSettings = AdvertisementPlacementSetting::query()->orderBy('id')->get();

        return Inertia::render('Admin/Advertisements/Index', [
            'campaigns' => $campaigns,
            'filters' => ['search' => $search, 'status' => $status, 'placement' => $placement, 'per_page' => $perPage],
            'counts' => [
                'all' => (clone $allCampaigns)->count(),
                'active' => (clone $allCampaigns)->currentlyActive()->count(),
                'scheduled' => (clone $allCampaigns)->where('is_active', true)->where('starts_at', '>', now())->count(),
                'paused' => (clone $allCampaigns)->where('is_active', false)->count(),
                'ended' => (clone $allCampaigns)->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<=', now())->count(),
                'impressions' => $totalImpressions,
                'clicks' => $totalClicks,
                'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 1) : 0,
            ],
            'pageSizes' => [25, 50, 100],
            'placementSettings' => $placementSettings->map(fn (AdvertisementPlacementSetting $setting) => [
                'id' => $setting->id,
                'key' => $setting->key,
                'label' => $setting->label,
                'kind' => $setting->kind,
                'locationLabel' => $setting->location_label,
                'enabled' => $setting->enabled,
                'androidEnabled' => $setting->android_enabled,
                'iosEnabled' => $setting->ios_enabled,
                'locked' => $setting->locked,
                'sourceOrder' => $setting->source_order ?? [],
                'firstAfter' => $setting->first_after,
                'repeatEvery' => $setting->repeat_every,
                'maxPerSession' => $setting->max_per_session,
                'minItems' => $setting->min_items,
                'adMobAndroidUnitId' => $setting->admob_android_unit_id,
                'adMobIosUnitId' => $setting->admob_ios_unit_id,
                'boostHours' => (int) data_get($setting->settings, 'boost_hours', 24),
                'dailyLimit' => (int) data_get($setting->settings, 'daily_limit', 3),
                'ordinals' => data_get($setting->settings, 'ordinals', []),
                'usageRewards' => collect(RewardedUsageGrantService::DEFINITIONS)->map(function (array $definition, string $key) use ($setting): array {
                    $config = data_get($setting->settings, "rewards.{$key}", []);
                    return [
                        'key' => $key,
                        'label' => $definition['label'],
                        'unit' => $definition['unit'],
                        'enabled' => (bool) ($config['enabled'] ?? false),
                        'amount' => (int) ($config['amount'] ?? 1),
                        'dailyLimit' => (int) ($config['daily_limit'] ?? 1),
                        'validHours' => (int) ($config['valid_hours'] ?? 24),
                    ];
                })->values(),
            ])->values(),
            'placementOptions' => collect(Advertisement::PLACEMENT_LABELS)
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                    'hint' => $this->placementHint($placementSettings->firstWhere('key', $value)),
                    'policy' => config("advertising.placements.{$value}"),
                ])->values(),
            'adMob' => [
                'mode' => config('advertising.admob.mode'),
                'modeLabel' => config('advertising.admob.mode') === 'production' ? 'Canlı reklamlar' : 'Google test reklamları',
                'earnsRevenue' => config('advertising.admob.mode') === 'production',
                'coveredPlacements' => $coveredPlacements->values(),
                'coveredPlacementLabels' => $coveredPlacements
                    ->map(fn (string $placement) => Advertisement::PLACEMENT_LABELS[$placement])
                    ->values(),
            ],
        ]);
    }

    private function placementHint(?AdvertisementPlacementSetting $setting): string
    {
        if (! $setting) {
            return '';
        }

        if ($setting->repeat_every > 0) {
            return "{$setting->first_after}. içerikten sonra; devamında her {$setting->repeat_every} içerikte bir reklam yuvası.";
        }

        if ($setting->first_after > 0) {
            return "{$setting->first_after}. içerikten sonra tek reklam yuvası.";
        }

        return rtrim((string) $setting->location_label, ". \t\n\r\0\x0B").'.';
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sponsorName' => ['required', 'string', 'max:100'],
            'headline' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:240'],
            'format' => ['required', Rule::in(['native'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'ctaLabel' => ['nullable', 'string', 'max:40', 'required_with:targetUrl'],
            'targetUrl' => ['nullable', 'url:http,https', 'max:500'],
            'backgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => ['string', 'distinct', Rule::in(Advertisement::PLACEMENTS)],
        ], [
            'required' => ':attribute alanı zorunludur.',
            'placements.min' => 'En az bir yayın alanı seçmelisin.',
            'placements.*.in' => 'Seçilen yayın alanı artık desteklenmiyor.',
            'endsAt.after' => 'Bitiş zamanı başlangıç zamanından sonra olmalıdır.',
            'targetUrl.url' => 'Yönlendirme bağlantısı http:// veya https:// ile başlayan geçerli bir adres olmalıdır.',
            'image.max' => 'Reklam görseli en fazla 4 MB olabilir.',
        ], [
            'sponsorName' => 'Sponsor adı',
            'headline' => 'Başlık',
            'body' => 'Açıklama',
            'format' => 'Reklam biçimi',
            'image' => 'Reklam görseli',
            'backgroundColor' => 'Arka plan rengi',
            'priority' => 'Öncelik',
            'isActive' => 'Kampanya durumu',
            'placements' => 'Yayın alanları',
            'endsAt' => 'Bitiş zamanı',
            'targetUrl' => 'Yönlendirme bağlantısı',
        ]);

        $imagePath = $request->file('image')?->store('advertisements', 'public');
        try {
            DB::transaction(function () use ($validated, $imagePath): void {
                $advertisement = Advertisement::create([
                    'placement' => $validated['placements'][0],
                    'format' => $validated['format'],
                    'sponsor_name' => trim($validated['sponsorName']),
                    'headline' => trim($validated['headline']),
                    'body' => trim($validated['body']),
                    'cta_label' => isset($validated['ctaLabel']) ? trim($validated['ctaLabel']) : null,
                    'target_url' => $validated['targetUrl'] ?? null,
                    'background_color' => strtoupper($validated['backgroundColor']),
                    'image_path' => $imagePath,
                    'starts_at' => $validated['startsAt'] ?? null,
                    'ends_at' => $validated['endsAt'] ?? null,
                    'priority' => $validated['priority'],
                    'is_active' => $validated['isActive'],
                ]);
                $advertisement->placements()->createMany(collect($validated['placements'])->map(fn (string $placement) => ['placement' => $placement])->all());
            });
        } catch (Throwable $exception) {
            if ($imagePath) Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        return back()->with('success', 'Doğrudan reklam kampanyası oluşturuldu.');
    }

    public function update(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $validated = $request->validate(['isActive' => ['required', 'boolean']]);
        $advertisement->update(['is_active' => $validated['isActive']]);
        return back()->with('success', $advertisement->is_active ? 'Kampanya etkinleştirildi.' : 'Kampanya durduruldu.');
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        $imagePath = $advertisement->image_path;
        $advertisement->delete();
        if ($imagePath) Storage::disk('public')->delete($imagePath);
        return back()->with('success', 'Kampanya, görseli ve ölçüm kayıtları silindi.');
    }
}