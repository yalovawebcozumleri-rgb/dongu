<?php

namespace Tests\Feature\Admin;

use App\Jobs\DispatchAnnouncementCampaign;
use App\Models\AnnouncementCampaign;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_panel_and_queue_immediate_announcement(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/announcements')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Announcements/Index')->has('campaigns.data')->has('audience.activeUsers'));

        $this->post('/admin/announcements', [
            'type' => 'marketing', 'title' => 'Haftanın duyurusu', 'body' => 'Yeni ilanları keşfet.',
            'audience' => 'all_active', 'targetUserIds' => [], 'pushEnabled' => true,
            'recurrence' => 'none', 'submitAction' => 'send_now',
        ])->assertSessionHasNoErrors();

        $campaign = AnnouncementCampaign::firstOrFail();
        $this->assertSame(AnnouncementCampaign::STATUS_SENDING, $campaign->status);
        Queue::assertPushed(DispatchAnnouncementCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_daily_campaign_requires_bounded_end_date_and_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = [
            'type' => 'marketing', 'title' => 'Günlük duyuru', 'body' => 'Bugünün ilanlarına göz at.',
            'audience' => 'all_active', 'targetUserIds' => [], 'pushEnabled' => true,
            'recurrence' => 'daily', 'scheduledAt' => now()->timezone('Europe/Istanbul')->addHour()->format('Y-m-d H:i:s'),
            'submitAction' => 'schedule',
        ];
        $this->actingAs($admin)->post('/admin/announcements', $payload)->assertSessionHasErrors('endsAt');
        $this->post('/admin/announcements', [...$payload, 'endsAt' => now()->addDays(40)->toDateString()])->assertSessionHasErrors('endsAt');

        $regular = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($regular)->get('/admin/announcements')->assertForbidden();
    }

    public function test_scheduled_campaign_uses_turkiye_local_time_and_end_of_day(): void
    {
        $this->travelTo(Carbon::parse('2026-08-20 09:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/announcements', [
            'type' => 'marketing',
            'title' => 'Pazartesi duyurusu',
            'body' => 'Yakınındaki ilanları keşfet.',
            'audience' => 'all_active',
            'targetUserIds' => [],
            'pushEnabled' => true,
            'recurrence' => 'weekly',
            'scheduledAt' => '2026-08-24T13:00',
            'endsAt' => '2026-09-30',
            'submitAction' => 'schedule',
        ])->assertSessionHasNoErrors();

        $campaign = AnnouncementCampaign::firstOrFail();
        $this->assertSame('2026-08-24 10:00:00', $campaign->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-24 10:00:00', $campaign->next_send_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 20:59:59', $campaign->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_admin_can_edit_an_unsent_campaign_but_not_one_that_already_ran(): void
    {
        $this->travelTo(Carbon::parse('2026-08-20 09:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $campaign = AnnouncementCampaign::create([
            'created_by_admin_id' => $admin->id,
            'type' => 'marketing',
            'title' => 'Eski başlık',
            'body' => 'Eski mesaj.',
            'audience' => 'all_active',
            'push_enabled' => true,
            'recurrence' => 'weekly',
            'status' => AnnouncementCampaign::STATUS_SCHEDULED,
            'scheduled_at' => '2026-08-24 10:00:00',
            'next_send_at' => '2026-08-24 10:00:00',
            'ends_at' => '2026-09-30 20:59:59',
        ]);
        $payload = [
            'action' => 'edit',
            'type' => 'marketing',
            'title' => 'Yeni başlık',
            'body' => 'Yeni mesaj.',
            'audience' => 'all_active',
            'targetUserIds' => [],
            'pushEnabled' => true,
            'recurrence' => 'weekly',
            'scheduledAt' => '2026-08-24T19:30',
            'endsAt' => '2026-09-30',
        ];

        $this->actingAs($admin)->patch("/admin/announcements/{$campaign->id}", $payload)->assertSessionHasNoErrors();
        $campaign->refresh();
        $this->assertSame('Yeni başlık', $campaign->title);
        $this->assertSame('2026-08-24 16:30:00', $campaign->scheduled_at->format('Y-m-d H:i:s'));

        $campaign->update(['runs_count' => 1]);
        $this->patch("/admin/announcements/{$campaign->id}", $payload)->assertStatus(422);
    }

    public function test_admin_can_soft_delete_a_campaign_but_not_while_it_is_sending(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $completed = AnnouncementCampaign::create([
            'created_by_admin_id' => $admin->id, 'type' => 'system', 'title' => 'Tamamlanan duyuru',
            'body' => 'Daha önce gönderilmiş duyuru.', 'audience' => 'all_active', 'push_enabled' => true,
            'recurrence' => 'none', 'status' => AnnouncementCampaign::STATUS_COMPLETED,
        ]);
        $sending = AnnouncementCampaign::create([
            'created_by_admin_id' => $admin->id, 'type' => 'system', 'title' => 'Gönderilen duyuru',
            'body' => 'Gönderimi devam eden duyuru.', 'audience' => 'all_active', 'push_enabled' => true,
            'recurrence' => 'none', 'status' => AnnouncementCampaign::STATUS_SENDING,
        ]);

        $this->actingAs($admin)->delete("/admin/announcements/{$completed->id}")->assertRedirect();
        $this->assertSoftDeleted('announcement_campaigns', ['id' => $completed->id]);

        $this->delete("/admin/announcements/{$sending->id}")->assertStatus(422);
        $this->assertDatabaseHas('announcement_campaigns', ['id' => $sending->id, 'deleted_at' => null]);
    }
}
