<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramAdminNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAdminNewUserTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $userId,
        public string $deviceName,
    ) {}

    public function handle(TelegramAdminNotifier $notifier): void
    {
        $user = User::find($this->userId);

        if (! $user || ! config('services.telegram_admin.enabled')) {
            return;
        }

        $notifier->newUser($user, $this->deviceName);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Yeni kullanıcı Telegram bildirimi tüm denemelerden sonra başarısız oldu.', [
            'user_id' => $this->userId,
            'exception' => $exception::class,
        ]);
    }
}
