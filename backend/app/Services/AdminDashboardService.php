<?php

namespace App\Services;

use App\Models\AnnouncementCampaign;
use App\Models\AnnouncementDispatch;
use App\Models\AppDownloadClickDaily;
use App\Models\CycleRiskCase;
use App\Models\Listing;
use App\Models\ListingMaterial;
use App\Models\ListingReport;
use App\Models\MessageReport;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    private const TIMEZONE = 'Europe/Istanbul';

    public function data(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $today = $now->startOfDay();
        $tomorrow = $today->addDay();
        $last7 = $today->subDays(6);
        $previous7 = $today->subDays(13);
        $last30 = $today->subDays(29);
        $userQuery = fn (): Builder => User::query()->where('role', User::ROLE_USER)->where('status', '!=', 'deleted');
        $listingQuery = fn (): Builder => Listing::query();
        $statusCounts = $listingQuery()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $moderation = $this->moderation();
        $announcementTimeline = $this->announcementTimeline($today);
        $announcementProblems = collect($announcementTimeline['days'])->flatMap(fn (array $day) => $day['items'])->whereIn('state', ['failed', 'delayed'])->count();

        return [
            'generatedAt' => $now->toIso8601String(),
            'health' => ['issueCount' => array_sum(array_column($moderation, 'count')) + $announcementProblems, 'announcementProblems' => $announcementProblems],
            'users' => [
                'total' => $userQuery()->count(),
                'active' => $userQuery()->where('status', 'active')->count(),
                'today' => $this->createdBetween($userQuery(), $today, $tomorrow),
                'last7Days' => $this->createdBetween($userQuery(), $last7, $tomorrow),
                'previous7Days' => $this->createdBetween($userQuery(), $previous7, $last7),
                'last30Days' => $this->createdBetween($userQuery(), $last30, $tomorrow),
            ],
            'listingMetrics' => [
                'total' => $listingQuery()->count(),
                'active' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'reserved' => (int) ($statusCounts[Listing::STATUS_RESERVED] ?? 0),
                'completed' => (int) ($statusCounts[Listing::STATUS_COMPLETED] ?? 0),
                'cancelled' => (int) ($statusCounts[Listing::STATUS_CANCELLED] ?? 0),
                'materials' => (int) ListingMaterial::whereHas('listing')->sum('quantity'),
                'today' => $this->createdBetween($listingQuery(), $today, $tomorrow),
                'last7Days' => $this->createdBetween($listingQuery(), $last7, $tomorrow),
                'previous7Days' => $this->createdBetween($listingQuery(), $previous7, $last7),
            ],
            'transactions' => [
                'pending' => PickupRequest::where('status', PickupRequest::PENDING)->count(),
                'reserved' => PickupRequest::where('status', PickupRequest::ACCEPTED)->count(),
                'inquiries' => PickupRequest::where('status', PickupRequest::INQUIRY)->count(),
                'completedToday' => $this->timestampBetween(PickupRequest::query(), 'completed_at', $today, $tomorrow),
                'completedLast7Days' => $this->timestampBetween(PickupRequest::query(), 'completed_at', $last7, $tomorrow),
                'completedPrevious7Days' => $this->timestampBetween(PickupRequest::query(), 'completed_at', $previous7, $last7),
                'cancelledLast7Days' => $this->timestampBetween(PickupRequest::query(), 'cancelled_at', $last7, $tomorrow),
            ],
            'announcements' => $announcementTimeline,
            'downloadClicks' => $this->downloads($today, $last7, $last30),
            'moderation' => $moderation,
            'listings' => [],
        ];
    }

    private function createdBetween(Builder $query, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $this->timestampBetween($query, 'created_at', $start, $end);
    }

    private function timestampBetween(Builder $query, string $column, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $query->where($column, '>=', $start->utc())->where($column, '<', $end->utc())->count();
    }

    private function moderation(): array
    {
        return [
            ['key' => 'messages', 'label' => 'Mesaj bildirimleri', 'count' => MessageReport::where('status', MessageReport::PENDING)->count(), 'href' => '/admin/message-reports'],
            ['key' => 'listings', 'label' => 'İlan bildirimleri', 'count' => ListingReport::where('status', ListingReport::PENDING)->count(), 'href' => '/admin/listing-reports'],
            ['key' => 'users', 'label' => 'Kullanıcı bildirimleri', 'count' => UserReport::where('status', UserReport::PENDING)->count(), 'href' => '/admin/user-reports'],
            ['key' => 'risk', 'label' => 'Puan denetimi', 'count' => CycleRiskCase::where('status', CycleRiskCase::PENDING)->count(), 'href' => '/admin/cycle-risk-cases'],
        ];
    }

    private function downloads(CarbonImmutable $today, CarbonImmutable $last7, CarbonImmutable $last30): array
    {
        $sources = $this->groupDownloadRows('source', $last30, true)
            ->groupBy(fn (array $row) => $this->normalizeSource($row['name']))
            ->map(fn (Collection $rows, string $name) => ['name' => $name, 'clicks' => $rows->sum('clicks')])
            ->sortByDesc('clicks')->values();

        return [
            'today' => (int) AppDownloadClickDaily::whereDate('click_date', $today->toDateString())->sum('clicks'),
            'last7Days' => (int) AppDownloadClickDaily::whereDate('click_date', '>=', $last7->toDateString())->sum('clicks'),
            'total' => (int) AppDownloadClickDaily::sum('clicks'),
            'platforms' => $this->groupDownloadRows('platform', $last30),
            'sources' => $sources,
        ];
    }

    private function groupDownloadRows(string $column, CarbonImmutable $start, bool $unlimited = false): Collection
    {
        $query = AppDownloadClickDaily::query()->whereDate('click_date', '>=', $start->toDateString())
            ->selectRaw("{$column}, SUM(clicks) as aggregate")->groupBy($column)->orderByDesc('aggregate');
        if (! $unlimited) {
            $query->limit(8);
        }

        return $query->get()->map(fn (AppDownloadClickDaily $row) => ['name' => (string) $row->{$column}, 'clicks' => (int) $row->aggregate]);
    }

    private function normalizeSource(string $source): string
    {
        return match (strtolower(trim($source))) {
            '', 'direct' => 'direct', 'ig', 'instagram' => 'instagram', 'fb', 'facebook' => 'facebook',
            'yt', 'youtube' => 'youtube', 'wa', 'whatsapp' => 'whatsapp', default => strtolower(trim($source)),
        };
    }

    private function announcementTimeline(CarbonImmutable $today): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $rangeStart = $today->subDay();
        $rangeEnd = $today->addDays(2);
        $dispatches = AnnouncementDispatch::query()->with('campaign')
            ->where('scheduled_for', '>=', $rangeStart->utc())->where('scheduled_for', '<', $rangeEnd->utc())
            ->orderBy('scheduled_for')->get();
        $groupKeys = $dispatches->map(fn (AnnouncementDispatch $dispatch) => "announcement:{$dispatch->announcement_campaign_id}:{$dispatch->run_key}");
        $pushStats = UserNotification::query()->whereIn('group_key', $groupKeys)
            ->selectRaw('group_key, SUM(CASE WHEN push_sent_at IS NOT NULL THEN 1 ELSE 0 END) as accepted, SUM(CASE WHEN push_error IS NOT NULL THEN 1 ELSE 0 END) as failed')
            ->groupBy('group_key')->get()->keyBy('group_key');

        $events = $dispatches->map(function (AnnouncementDispatch $dispatch) use ($pushStats, $now): array {
            $scheduled = CarbonImmutable::instance($dispatch->scheduled_for)->setTimezone(self::TIMEZONE);
            $push = $pushStats->get("announcement:{$dispatch->announcement_campaign_id}:{$dispatch->run_key}");
            $state = match (true) {
                $dispatch->status === 'failed' => 'failed', $dispatch->status === 'completed' => 'completed',
                $scheduled->addMinutes(10)->lt($now) => 'delayed', default => 'processing',
            };

            return [
                'key' => "dispatch-{$dispatch->id}", 'title' => $dispatch->campaign?->title ?? 'Silinmiş duyuru',
                'scheduledFor' => $scheduled->toIso8601String(), 'state' => $state,
                'recipients' => (int) $dispatch->recipients_count, 'pushAccepted' => (int) ($push?->accepted ?? 0),
                'pushFailed' => (int) ($push?->failed ?? 0), 'error' => $dispatch->error,
            ];
        });
        $dispatchedIds = $dispatches->pluck('announcement_campaign_id')->unique();
        $planned = AnnouncementCampaign::query()->whereNotNull('next_send_at')
            ->whereIn('status', [AnnouncementCampaign::STATUS_SCHEDULED, AnnouncementCampaign::STATUS_SENDING])
            ->where('next_send_at', '>=', $rangeStart->utc())->where('next_send_at', '<', $rangeEnd->utc())
            ->whereNotIn('id', $dispatchedIds)->get()->map(function (AnnouncementCampaign $campaign) use ($now): array {
                $scheduled = CarbonImmutable::instance($campaign->next_send_at)->setTimezone(self::TIMEZONE);

                return [
                    'key' => "planned-{$campaign->id}", 'title' => $campaign->title, 'scheduledFor' => $scheduled->toIso8601String(),
                    'state' => $scheduled->addMinutes(10)->lt($now) ? 'delayed' : 'scheduled',
                    'recipients' => 0, 'pushAccepted' => 0, 'pushFailed' => 0, 'error' => null,
                ];
            });
        $events = $events->concat($planned)->sortBy('scheduledFor')->values();
        $days = collect([
            ['key' => 'yesterday', 'label' => 'Dün', 'date' => $today->subDay()],
            ['key' => 'today', 'label' => 'Bugün', 'date' => $today],
            ['key' => 'tomorrow', 'label' => 'Yarın', 'date' => $today->addDay()],
        ])->map(function (array $day) use ($events): array {
            $date = $day['date']->toDateString();

            return ['key' => $day['key'], 'label' => $day['label'], 'date' => $date, 'items' => $events->filter(fn (array $event) => str_starts_with($event['scheduledFor'], $date))->values()->all()];
        })->all();

        return ['days' => $days, 'activeScheduled' => AnnouncementCampaign::whereIn('status', [AnnouncementCampaign::STATUS_SCHEDULED, AnnouncementCampaign::STATUS_SENDING])->count()];
    }
}
