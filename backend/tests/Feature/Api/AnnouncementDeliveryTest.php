<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchAnnouncementCampaign;
use App\Jobs\SendUserNotificationPush;
use App\Models\AnnouncementCampaign;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnnouncementDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_announcement_is_in_app_for_all_but_push_only_for_opted_in_users(): void
    {
        config(['services.expo.push_enabled' => true]);
        Queue::fake([SendUserNotificationPush::class]);
        $optedIn = User::factory()->create(['status' => 'active']);
        $optedOut = User::factory()->create(['status' => 'active']);
        NotificationPreference::create(['user_id' => $optedIn->id, 'marketing_enabled' => true]);
        NotificationPreference::create(['user_id' => $optedOut->id, 'marketing_enabled' => false]);
        $campaign = AnnouncementCampaign::create([
            'type' => 'marketing', 'title' => 'Döngü duyurusu', 'body' => 'Yeni ilanlara göz at.',
            'audience' => 'all_active', 'push_enabled' => true, 'recurrence' => 'none',
            'status' => AnnouncementCampaign::STATUS_SENDING, 'scheduled_at' => now(), 'next_send_at' => now(),
        ]);

        (new DispatchAnnouncementCampaign($campaign->id))->handle(app(UserNotificationService::class));

        $this->assertDatabaseHas('user_notifications', ['user_id' => $optedIn->id, 'type' => 'admin_marketing']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $optedOut->id, 'type' => 'admin_marketing']);
        $this->assertSame(2, $campaign->fresh()->total_in_app_deliveries);
        $this->assertSame(1, $campaign->fresh()->total_push_eligible);
        $this->assertSame(AnnouncementCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
        Queue::assertPushed(SendUserNotificationPush::class, 1);
    }

    public function test_second_marketing_campaign_inside_twenty_four_hours_is_sent_without_admin_cooldown(): void
    {
        User::factory()->create(['status' => 'active']);
        AnnouncementCampaign::create([
            'type' => 'marketing', 'title' => 'Önceki', 'body' => 'Önceki kampanya', 'audience' => 'all_active',
            'push_enabled' => false, 'recurrence' => 'none', 'status' => AnnouncementCampaign::STATUS_COMPLETED,
            'last_sent_at' => now()->subHour(), 'runs_count' => 1,
        ]);
        $campaign = AnnouncementCampaign::create([
            'type' => 'marketing', 'title' => 'Yeni', 'body' => 'Yeni kampanya', 'audience' => 'all_active',
            'push_enabled' => false, 'recurrence' => 'none', 'status' => AnnouncementCampaign::STATUS_SENDING,
            'scheduled_at' => now(), 'next_send_at' => now(),
        ]);

        (new DispatchAnnouncementCampaign($campaign->id))->handle(app(UserNotificationService::class));

        $campaign->refresh();
        $this->assertSame(AnnouncementCampaign::STATUS_COMPLETED, $campaign->status);
        $this->assertNull($campaign->next_send_at);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_due_command_queues_campaign_only_once(): void
    {
        Queue::fake([DispatchAnnouncementCampaign::class]);
        $campaign = AnnouncementCampaign::create([
            'type' => 'system', 'title' => 'Bakım', 'body' => 'Planlı bakım bildirimi.', 'audience' => 'all_active',
            'push_enabled' => true, 'recurrence' => 'none', 'status' => AnnouncementCampaign::STATUS_SCHEDULED,
            'scheduled_at' => now()->subMinute(), 'next_send_at' => now()->subMinute(),
        ]);
        $this->artisan('announcements:dispatch-due')->assertSuccessful();
        $this->artisan('announcements:dispatch-due')->assertSuccessful();
        Queue::assertPushed(DispatchAnnouncementCampaign::class, 1);
        $this->assertSame(AnnouncementCampaign::STATUS_SENDING, $campaign->fresh()->status);
    }
}
