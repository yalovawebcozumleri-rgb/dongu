<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramAdminNotifier
{
    /**
     * @throws ConnectionException
     */
    public function newUser(User $user, string $deviceName): void
    {
        $token = trim((string) config('services.telegram_admin.bot_token'));
        $chatId = trim((string) config('services.telegram_admin.chat_id'));

        if ($token === '' || $chatId === '') {
            throw new RuntimeException('Telegram yönetici bildirimi etkin ancak bot tokenı veya chat ID eksik.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post($this->endpoint($token), [
                'chat_id' => $chatId,
                'text' => $this->message($user, $deviceName),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException('Telegram yönetici bildirimi gönderilemedi (HTTP '.$response->status().').');
        }
    }

    private function endpoint(string $token): string
    {
        return rtrim((string) config('services.telegram_admin.api_base_url'), '/')
            .'/bot'.$token.'/sendMessage';
    }

    private function message(User $user, string $deviceName): string
    {
        $registeredAt = ($user->created_at ?? now())
            ->copy()
            ->timezone('Europe/Istanbul')
            ->format('d.m.Y H:i');
        $activeUsers = User::query()
            ->where('role', User::ROLE_USER)
            ->where('status', 'active')
            ->count();

        return implode("\n", [
            '🎉 <b>Yeni Döngü kullanıcısı</b>',
            '',
            '👤 '.e($user->name),
            '📱 Platform: '.e($this->platform($deviceName)),
            '🕒 Kayıt: '.$registeredAt,
            '👥 Toplam aktif kullanıcı: '.$activeUsers,
            '🆔 Kullanıcı #'.$user->id,
        ]);
    }

    private function platform(string $deviceName): string
    {
        $normalized = mb_strtolower($deviceName);

        return match (true) {
            str_contains($normalized, 'ios') => 'iOS',
            str_contains($normalized, 'android') => 'Android',
            default => 'Bilinmiyor',
        };
    }
}
