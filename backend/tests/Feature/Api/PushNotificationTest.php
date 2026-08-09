<?php

namespace Tests\Feature\Api;

use App\Jobs\SendConversationMessagePush;
use App\Jobs\SendUserNotificationPush;
use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ExpoPushService;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_queues_push_only_once(): void
    {
        Queue::fake();
        config(['services.expo.push_enabled' => true]);
        $user = User::factory()->create();
        $service = app(UserNotificationService::class);

        $first = $service->create($user->id, 'pickup_request', 'Yeni talep', 'Talep geldi.', [], 'pickup:1');
        $second = $service->create($user->id, 'pickup_request', 'Yeni talep', 'Talep geldi.', [], 'pickup:1');

        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(SendUserNotificationPush::class, 1);
    }

    public function test_each_message_queues_push_while_notification_center_keeps_one_conversation_row(): void
    {
        Queue::fake();
        config(['services.expo.push_enabled' => true]);
        $user = User::factory()->create();
        $service = app(UserNotificationService::class);

        $first = $service->create($user->id, 'new_message', 'Yeni mesaj', 'Birinci mesaj', ['route' => 'chat', 'conversationId' => 41], 'message:1', 'conversation:41');
        $second = $service->create($user->id, 'new_message', 'Yeni mesaj', 'İkinci mesaj', ['route' => 'chat', 'conversationId' => 41], 'message:2', 'conversation:41');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('İkinci mesaj', $second->fresh()->body);
        $this->assertDatabaseCount('user_notifications', 1);
        Queue::assertPushed(SendConversationMessagePush::class, 2);
        Queue::assertNotPushed(SendUserNotificationPush::class);
    }
    public function test_message_pushes_are_grouped_and_sent_through_expo(): void
    {
        $user = User::factory()->create();
        PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[group-test]', 'platform' => 'android', 'last_used_at' => now()]);
        $first = $this->notification($user, 'İlk mesaj');
        $second = $this->notification($user, 'İkinci mesaj');
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]], 200)]);

        (new SendUserNotificationPush($first->id))->handle(app(ExpoPushService::class));

        $this->assertNotNull($first->fresh()->push_sent_at);
        $this->assertNotNull($second->fresh()->push_sent_at);
        Http::assertSent(function ($request) {
            $messages = $request->data();
            return $messages[0]['title'] === '2 yeni mesajın var'
                && $messages[0]['channelId'] === 'messages'
                && $messages[0]['data']['conversationId'] === 41;
        });
    }

    public function test_preferences_skip_push_and_invalid_token_is_revoked(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'messages_enabled' => false]);
        $skipped = $this->notification($user, 'Sessiz mesaj', 'chat:disabled');
        PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[disabled]', 'platform' => 'ios', 'last_used_at' => now()]);
        Http::fakeSequence()->push(['data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]], 200);
        (new SendUserNotificationPush($skipped->id))->handle(app(ExpoPushService::class));
        $this->assertSame('preference_disabled', $skipped->fresh()->push_error);
        Http::assertNothingSent();

        NotificationPreference::where('user_id', $user->id)->update(['messages_enabled' => true]);
        $invalid = $this->notification($user, 'Geçersiz cihaz', 'chat:invalid');
        (new SendUserNotificationPush($invalid->id))->handle(app(ExpoPushService::class));

        $this->assertNotNull(PushToken::where('token', 'ExpoPushToken[disabled]')->first()->revoked_at);
        $this->assertSame('all_tokens_failed', $invalid->fresh()->push_error);
    }

    private function notification(User $user, string $body, string $group = 'chat:41'): UserNotification
    {
        return UserNotification::create([
            'user_id' => $user->id,
            'type' => 'new_message',
            'title' => 'Yeni mesaj',
            'body' => $body,
            'group_key' => $group,
            'data' => ['route' => 'chat', 'conversationId' => 41],
        ]);
    }
}
