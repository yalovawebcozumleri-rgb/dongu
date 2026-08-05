<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupporterBusiness;
use App\Models\SupporterDailyStat;
use App\Models\SupporterEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupporterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'provinceCode' => ['nullable', 'string', 'max:10'], 'province' => ['nullable', 'string', 'max:80'],
            'districtCode' => ['nullable', 'string', 'max:20'], 'district' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $provinceCode = $filters['provinceCode'] ?? null;
        $districtCode = $filters['districtCode'] ?? null;
        $province = $filters['province'] ?? null;
        $district = $filters['district'] ?? null;

        $query = SupporterBusiness::query()->currentlyActive()
            ->where(function (Builder $query) use ($provinceCode, $districtCode, $province, $district): void {
                $query->where('target_scope', 'nationwide');
                if ($provinceCode || $province) {
                    $query->orWhere(function (Builder $query) use ($provinceCode, $province): void {
                        $query->where('target_scope', 'province')->where(function (Builder $query) use ($provinceCode, $province): void {
                            if ($provinceCode) $query->where('province_code', $provinceCode);
                            if ($province) $query->orWhereRaw('LOWER(province_name) = ?', [mb_strtolower(trim($province))]);
                        });
                    });
                }
                if (($districtCode || $district) && ($provinceCode || $province)) {
                    $query->orWhere(function (Builder $query) use ($provinceCode, $districtCode, $province, $district): void {
                        $query->where('target_scope', 'district')->where(function (Builder $query) use ($districtCode, $district): void {
                            if ($districtCode) $query->where('district_code', $districtCode);
                            if ($district) $query->orWhereRaw('LOWER(district_name) = ?', [mb_strtolower(trim($district))]);
                        })->where(function (Builder $query) use ($provinceCode, $province): void {
                            if ($provinceCode) $query->where('province_code', $provinceCode);
                            if ($province) $query->orWhereRaw('LOWER(province_name) = ?', [mb_strtolower(trim($province))]);
                        });
                    });
                }
            })
            ->orderByRaw("CASE target_scope WHEN 'district' THEN 1 WHEN 'province' THEN 2 ELSE 3 END")
            ->orderByDesc('priority')->orderBy('name');

        $paginator = $query->paginate((int) ($filters['perPage'] ?? 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (SupporterBusiness $business) => $this->card($request, $business)),
            'meta' => [
                'currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
                'contactUrl' => config('supporters.contact_url', 'https://wa.me/905413342219?text=Merhaba%2C%20D%C3%B6ng%C3%BC%20reklam%20ve%20destek%C3%A7i%20se%C3%A7enekleri%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.'),
            ],
        ]);
    }

    public function show(Request $request, SupporterBusiness $supporter): JsonResponse
    {
        abort_unless($this->available($supporter), 404);
        return response()->json(['data' => [...$this->card($request, $supporter),
            'detailTitle' => $supporter->detail_title, 'detailBody' => $supporter->detail_body,
            'cta' => ['type' => $supporter->cta_type, 'label' => $supporter->cta_label, 'url' => $this->ctaUrl($supporter)],
        ]]);
    }

    public function logo(SupporterBusiness $supporter): BinaryFileResponse
    {
        abort_unless($supporter->logo_path, 404);
        $path = Storage::disk('public')->path($supporter->logo_path);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function event(Request $request, SupporterBusiness $supporter): JsonResponse
    {
        abort_unless($this->available($supporter), 404);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['impression', 'detail_view', 'cta_click'])],
            'visitorId' => ['required', 'string', 'min:16', 'max:100'],
            'eventId' => ['required', 'string', 'min:12', 'max:120'],
        ]);
        $visitorHash = hash('sha256', $validated['visitorId']);
        // İstemcinin eventId değerine güvenmek yerine sunucu zaman dilimiyle tekrarları sınırlarız.
        // Gösterim 30 dakikada, profil ve CTA olayı 5 dakikada bir kez sayılır.
        $bucketSeconds = $validated['type'] === 'impression' ? 1800 : 300;
        $timeBucket = (string) intdiv(now()->timestamp, $bucketSeconds);
        $eventKey = hash('sha256', $supporter->id.'|'.$validated['type'].'|'.$visitorHash.'|'.$timeBucket);
        $today = now()->toDateString();
        $recorded = false;

        DB::transaction(function () use ($supporter, $validated, $visitorHash, $eventKey, $today, &$recorded): void {
            $event = SupporterEvent::firstOrCreate(['event_key' => $eventKey], [
                'supporter_business_id' => $supporter->id, 'event_type' => $validated['type'],
                'visitor_hash' => $visitorHash, 'occurred_at' => now(),
            ]);
            if (! $event->wasRecentlyCreated) return;
            $recorded = true;
            $stats = SupporterDailyStat::firstOrCreate(['supporter_business_id' => $supporter->id, 'stat_date' => $today]);
            $column = ['impression' => 'impressions', 'detail_view' => 'detail_views', 'cta_click' => 'cta_clicks'][$validated['type']];
            $stats->increment($column);
            if ($validated['type'] === 'impression') {
                $inserted = DB::table('supporter_daily_visitors')->insertOrIgnore([
                    'supporter_business_id' => $supporter->id, 'visit_date' => $today,
                    'visitor_hash' => $visitorHash, 'created_at' => now(),
                ]);
                if ($inserted) $stats->increment('unique_reach');
            }
        });

        return response()->json(['data' => ['recorded' => $recorded]]);
    }

    private function card(Request $request, SupporterBusiness $business): array
    {
        return [
            'id' => $business->id, 'slug' => $business->slug, 'name' => $business->name,
            'summary' => $business->card_summary, 'initials' => mb_strtoupper(mb_substr($business->name, 0, 1)),
            'logoUrl' => $business->logo_path ? $request->root()."/api/v1/supporters/{$business->id}/logo" : null,
            'scope' => $business->target_scope,
            'areaLabel' => match ($business->target_scope) {
                'district' => trim($business->district_name.' · '.$business->province_name),
                'province' => $business->province_name.' · İl destekçisi',
                default => 'Türkiye geneli destekçi',
            },
            'detailTitle' => $business->detail_title,
            'detailBody' => $business->detail_body,
            'cta' => ['type' => $business->cta_type, 'label' => $business->cta_label, 'url' => $this->ctaUrl($business)],
        ];
    }

    private function available(SupporterBusiness $supporter): bool
    {
        return $supporter->is_active && (! $supporter->starts_at || $supporter->starts_at->lte(now())) && (! $supporter->ends_at || $supporter->ends_at->gt(now()));
    }

    private function ctaUrl(SupporterBusiness $business): string
    {
        $value = trim($business->cta_value);
        return match ($business->cta_type) {
            'phone' => 'tel:'.preg_replace('/[^0-9+]/', '', $value),
            'whatsapp' => str_starts_with($value, 'http') ? $value : $this->whatsappUrl($value),
            'instagram' => str_starts_with($value, 'http') ? $value : 'https://instagram.com/'.ltrim($value, '@'),
            default => $value,
        };
    }

    private function whatsappUrl(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = '90'.substr($digits, 1);
        elseif (strlen($digits) === 10 && str_starts_with($digits, '5')) $digits = '90'.$digits;
        return 'https://wa.me/'.$digits;
    }
}
