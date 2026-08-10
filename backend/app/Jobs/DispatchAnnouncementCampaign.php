<?php

namespace App\Jobs;

use App\Models\AnnouncementCampaign;
use App\Models\AnnouncementDispatch;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchAnnouncementCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public int $campaignId) {}

    public function handle(UserNotificationService $notifications): void
    {
        [$campaign, $dispatch] = DB::transaction(function () {
            $campaign = AnnouncementCampaign::query()->lockForUpdate()->find($this->campaignId);
            if (! $campaign || ! in_array($campaign->status, [AnnouncementCampaign::STATUS_SENDING, AnnouncementCampaign::STATUS_SCHEDULED], true)) return [null, null];


            $scheduledFor = $campaign->next_send_at ?? now();
            $runKey = $scheduledFor->format('YmdHis');
            $dispatch = AnnouncementDispatch::firstOrCreate(
                ['announcement_campaign_id' => $campaign->id, 'run_key' => $runKey],
                ['scheduled_for' => $scheduledFor, 'status' => 'processing']
            );
            if (! $dispatch->wasRecentlyCreated) return [null, null];
            $campaign->update(['status' => AnnouncementCampaign::STATUS_SENDING]);
            return [$campaign->fresh(), $dispatch];
        });
        if (! $campaign || ! $dispatch) return;

        $recipients = 0;
        $pushEligible = 0;
        $query = User::query()->where('status', 'active')->with('notificationPreference')->orderBy('id');
        if ($campaign->audience === 'selected') $query->whereIn('id', $campaign->target_user_ids ?? []);

        $query->chunkById(200, function ($users) use ($campaign, $dispatch, $notifications, &$recipients, &$pushEligible) {
            foreach ($users as $user) {
                $allowPush = $campaign->push_enabled && (
                    $campaign->type === AnnouncementCampaign::TYPE_SYSTEM
                    || (bool) $user->notificationPreference?->marketing_enabled
                );
                $notifications->create(
                    $user->id,
                    $campaign->type === AnnouncementCampaign::TYPE_MARKETING ? 'admin_marketing' : 'admin_system',
                    $campaign->title,
                    $campaign->body,
                    ['route' => 'notifications', 'campaignId' => $campaign->id],
                    "announcement:{$campaign->id}:{$dispatch->run_key}:{$user->id}",
                    "announcement:{$campaign->id}:{$dispatch->run_key}",
                    $allowPush,
                );
                $recipients++;
                if ($allowPush && config('services.expo.push_enabled')) $pushEligible++;
            }
        });

        DB::transaction(function () use ($campaign, $dispatch, $recipients, $pushEligible) {
            $locked = AnnouncementCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $sentAt = now();
            $next = match ($locked->recurrence) {
                'daily' => ($locked->next_send_at ?? $sentAt)->copy()->addDay(),
                'weekly' => ($locked->next_send_at ?? $sentAt)->copy()->addWeek(),
                default => null,
            };
            $continues = $next && (! $locked->ends_at || $next->lte($locked->ends_at));
            $locked->update([
                'status' => $continues ? AnnouncementCampaign::STATUS_SCHEDULED : AnnouncementCampaign::STATUS_COMPLETED,
                'last_sent_at' => $sentAt,
                'next_send_at' => $continues ? $next : null,
                'runs_count' => $locked->runs_count + 1,
                'total_in_app_deliveries' => $locked->total_in_app_deliveries + $recipients,
                'total_push_eligible' => $locked->total_push_eligible + $pushEligible,
            ]);
            $dispatch->update(['status' => 'completed', 'recipients_count' => $recipients, 'push_eligible_count' => $pushEligible, 'completed_at' => $sentAt]);
        });
    }

    public function failed(Throwable $error): void
    {
        AnnouncementCampaign::whereKey($this->campaignId)->where('status', AnnouncementCampaign::STATUS_SENDING)
            ->update(['status' => AnnouncementCampaign::STATUS_PAUSED]);
        AnnouncementDispatch::where('announcement_campaign_id', $this->campaignId)->where('status', 'processing')
            ->latest('id')->limit(1)->update(['status' => 'failed', 'error' => mb_substr($error->getMessage(), 0, 500), 'completed_at' => now()]);
    }
}
