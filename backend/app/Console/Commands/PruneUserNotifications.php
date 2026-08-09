<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use Illuminate\Console\Command;

class PruneUserNotifications extends Command
{
    protected $signature = 'notifications:prune {--dry-run}';
    protected $description = '30 g?nden eski kullan?c? bildirimlerini kal?c? olarak temizler';

    public function handle(): int
    {
        $query = UserNotification::withTrashed()
            ->where('created_at', '<', now()->subDays(config('marketplace.notification_retention_days', 30)));
        $count = (clone $query)->count();

        if (! $this->option('dry-run') && $count > 0) {
            $query->forceDelete();
        }

        $this->info($this->option('dry-run')
            ? "{$count} bildirim silinecekti."
            : "{$count} bildirim kal?c? olarak silindi.");

        return self::SUCCESS;
    }
}
