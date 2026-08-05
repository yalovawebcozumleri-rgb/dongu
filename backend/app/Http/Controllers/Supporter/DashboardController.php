<?php

namespace App\Http\Controllers\Supporter;

use App\Http\Controllers\Controller;
use App\Models\SupporterBusiness;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $business = SupporterBusiness::query()->where('owner_user_id', $request->user()->id)->firstOrFail();
        $daily = $business->dailyStats()->where('stat_date', '>=', now()->subDays(29)->toDateString())->orderBy('stat_date')->get();
        $today = $daily->firstWhere('stat_date', now()->startOfDay()) ?? $business->dailyStats()->whereDate('stat_date', today())->first();
        $totals = $business->dailyStats()->selectRaw('SUM(impressions) impressions, SUM(unique_reach) unique_reach, SUM(detail_views) detail_views, SUM(cta_clicks) cta_clicks')->first();
        $impressions = (int) ($totals->impressions ?? 0); $clicks = (int) ($totals->cta_clicks ?? 0);

        return Inertia::render('Supporter/Dashboard', [
            'business' => ['name' => $business->name, 'isActive' => $business->is_active, 'startsAt' => $business->starts_at?->format('d.m.Y H:i'), 'endsAt' => $business->ends_at?->format('d.m.Y H:i'), 'area' => $this->area($business)],
            'today' => ['impressions' => (int) ($today?->impressions ?? 0), 'uniqueReach' => (int) ($today?->unique_reach ?? 0)],
            'totals' => ['impressions' => $impressions, 'uniqueReach' => (int) ($totals->unique_reach ?? 0), 'detailViews' => (int) ($totals->detail_views ?? 0), 'ctaClicks' => $clicks, 'ctr' => $impressions ? round($clicks / $impressions * 100, 2) : 0],
            'daily' => $daily->map(fn ($row) => ['date' => $row->stat_date->format('d.m'), 'impressions' => $row->impressions, 'uniqueReach' => $row->unique_reach, 'detailViews' => $row->detail_views, 'ctaClicks' => $row->cta_clicks])->values(),
        ]);
    }

    private function area(SupporterBusiness $business): string
    {
        return match ($business->target_scope) { 'district' => $business->district_name.', '.$business->province_name, 'province' => $business->province_name, default => 'Türkiye geneli' };
    }
}
