<?php

namespace Tests\Feature\Api;

use App\Jobs\SendAdminNewUserTelegram;
use App\Models\User;
use App\Services\TelegramAdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminNewUserTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_job_sends_private_telegram_notification(): void
    {
        config()->set('services.telegram_admin', [
            'enabled' => true,
            'bot_token' => 'test-token',
            'chat_id' => '123456',
            'api_base_url' => 'https://api.telegram.test',
        ]);
        Http::fake([
            'https://api.telegram.test/bottest-token/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Yeni Kullanıcı',
            'status' => 'active',
        ]);

        (new SendAdminNewUserTelegram($user->id, 'ios telefon'))
            ->handle(app(TelegramAdminNotifier::class));

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://api.telegram.test/bottest-token/sendMessage'
                && $request['chat_id'] === '123456'
                && str_contains($request['text'], 'Yeni Döngü kullanıcısı')
                && str_contains($request['text'], 'Yeni Kullanıcı')
                && str_contains($request['text'], 'Platform: iOS')
                && str_contains($request['text'], 'Kullanıcı #'.$user->id)
                && ! str_contains($request['text'], $user->email);
        });
    }

    public function test_disabled_notification_job_does_not_call_telegram(): void
    {
        config()->set('services.telegram_admin.enabled', false);
        Http::fake();
        $user = User::factory()->create();

        (new SendAdminNewUserTelegram($user->id, 'android telefon'))
            ->handle(app(TelegramAdminNotifier::class));

        Http::assertNothingSent();
    }
}
