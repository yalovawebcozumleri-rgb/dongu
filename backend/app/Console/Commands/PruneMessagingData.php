<?php

namespace App\Console\Commands;

use App\Models\LoginCode;
use App\Models\PushToken;
use App\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMessagingData extends Command
{
    protected $signature = 'messaging:prune {--dry-run : Yalnızca silinecek kayıt sayılarını gösterir}';
    protected $description = 'Süresi dolmuş push tokenlarını ve güvenli teknik kayıtları temizler';

    public function handle(): int
    {
        $queries = [
            'İptal edilmiş push tokenı' => PushToken::query()->whereNotNull('revoked_at')->where('revoked_at', '<', now()->subDays(config('marketplace.revoked_push_token_retention_days'))),
            'Eksik eski push tokenı' => PushToken::query()->whereNull('last_used_at')->where('created_at', '<', now()->subDays(7)),
            'Uzun süredir kullanılmayan push tokenı' => PushToken::query()->whereNull('revoked_at')->where('last_used_at', '<', now()->subDays(config('marketplace.stale_push_token_days'))),
            'Eski giriş kodu' => LoginCode::query()->where('expires_at', '<', now()->subDays(config('marketplace.login_code_retention_days'))),
            'Eski parola sıfırlama kaydı' => DB::table('password_reset_tokens')->where('created_at', '<', now()->subDays(1)),
            'Eski yönetim oturumu' => DB::table('sessions')->where('last_activity', '<', now()->subDays(config('marketplace.admin_session_retention_days'))->timestamp),
            'Eski kullanıcı bildirimi' => UserNotification::query()->where('created_at', '<', now()->subDays(config('marketplace.notification_retention_days'))),
        ];

        $total = 0;
        foreach ($queries as $label => $query) {
            $count = (clone $query)->count();
            $this->line("{$label}: {$count}");
            $total += $count;
            if (! $this->option('dry-run') && $count > 0) $query->delete();
        }
        $this->info($this->option('dry-run') ? "Kuru çalıştırma tamamlandı; {$total} kayıt silinecekti." : "Temizlik tamamlandı; {$total} kayıt silindi.");
        return self::SUCCESS;
    }
}
