<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementCampaign;
use App\Models\AppDownloadClickDaily;
use App\Models\CycleRiskCase;
use App\Models\Listing;
use App\Models\ListingMaterial;
use App\Models\ListingReport;
use App\Models\MessageReport;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserReport;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = CarbonImmutable::now('Europe/Istanbul');
        $today = $now->toDateString();
        $sevenDaysAgo = $now->subDays(6)->toDateString();
        $thirtyDaysAgo = $now->subDays(29)->toDateString();

        $statusCounts = Listing::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $pendingModeration = MessageReport::where('status', MessageReport::PENDING)->count()
            + ListingReport::where('status', ListingReport::PENDING)->count()
            + UserReport::where('status', UserReport::PENDING)->count()
            + CycleRiskCase::where('status', CycleRiskCase::PENDING)->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::where('role', User::ROLE_USER)->count(),
                'activeListings' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'completedTransactions' => PickupRequest::where('status', PickupRequest::COMPLETED)->count(),
                'materials' => ListingMaterial::sum('quantity'),
                'pendingModeration' => $pendingModeration,
                'scheduledAnnouncements' => AnnouncementCampaign::whereIn('status', [AnnouncementCampaign::STATUS_SCHEDULED, AnnouncementCampaign::STATUS_SENDING])->count(),
            ],
            'listingStatusCounts' => [
                'active' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'reserved' => (int) ($statusCounts[Listing::STATUS_RESERVED] ?? 0),
                'completed' => (int) ($statusCounts[Listing::STATUS_COMPLETED] ?? 0),
                'cancelled' => (int) ($statusCounts[Listing::STATUS_CANCELLED] ?? 0),
            ],
            'downloadClicks' => [
                'today' => (int) AppDownloadClickDaily::whereDate('click_date', $today)->sum('clicks'),
                'last7Days' => (int) AppDownloadClickDaily::whereDate('click_date', '>=', $sevenDaysAgo)->sum('clicks'),
                'total' => (int) AppDownloadClickDaily::sum('clicks'),
                'platforms' => AppDownloadClickDaily::query()
                    ->whereDate('click_date', '>=', $thirtyDaysAgo)
                    ->selectRaw('platform, SUM(clicks) as aggregate')
                    ->groupBy('platform')->orderByDesc('aggregate')->get()
                    ->map(fn (AppDownloadClickDaily $row) => ['name' => $row->platform, 'clicks' => (int) $row->aggregate]),
                'sources' => AppDownloadClickDaily::query()
                    ->whereDate('click_date', '>=', $thirtyDaysAgo)
                    ->selectRaw('source, SUM(clicks) as aggregate')
                    ->groupBy('source')->orderByDesc('aggregate')->limit(8)->get()
                    ->map(fn (AppDownloadClickDaily $row) => ['name' => $row->source, 'clicks' => (int) $row->aggregate]),
            ],
            // Kept as an empty compatibility prop; the dashboard no longer renders the recent-listings table.
            'listings' => [],
        ]);
    }
}
