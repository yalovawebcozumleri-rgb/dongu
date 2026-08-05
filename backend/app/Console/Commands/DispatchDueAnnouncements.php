<?php

namespace App\Console\Commands;

use App\Jobs\DispatchAnnouncementCampaign;
use App\Models\AnnouncementCampaign;
use Illuminate\Console\Command;

class DispatchDueAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch-due';
    protected $description = 'Planlanan duyuru kampanyalarını queue sistemine aktarır';

    public function handle(): int
    {
        $count = 0;
        AnnouncementCampaign::query()->where('status', AnnouncementCampaign::STATUS_SCHEDULED)
            ->whereNotNull('next_send_at')->where('next_send_at', '<=', now())->orderBy('id')->chunkById(100, function ($campaigns) use (&$count) {
                foreach ($campaigns as $campaign) {
                    if (AnnouncementCampaign::whereKey($campaign->id)->where('status', AnnouncementCampaign::STATUS_SCHEDULED)->update(['status' => AnnouncementCampaign::STATUS_SENDING])) {
                        DispatchAnnouncementCampaign::dispatch($campaign->id);
                        $count++;
                    }
                }
            });
        $this->info("{$count} duyuru gönderim kuyruğuna alındı.");
        return self::SUCCESS;
    }
}
