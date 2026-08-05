<?php

namespace Tests\Feature\Admin;

use App\Models\ConversationMessage;
use App\Models\Listing;
use App\Models\MessageReport;
use App\Models\ModerationSanction;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_review_and_resolve_reported_messages(): void
    {
        [$report, $admin] = $this->reportedMessage();
        $regular = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($regular)->get('/admin/message-reports')->assertForbidden();
        $this->actingAs($admin)->get('/admin/message-reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MessageReports/Index')
                ->where('counts.pending', 1)
                ->where('adminNavigationCounts.messageReports', 1)
                ->has('reports.data', 1)
                ->where('reports.data.0.id', $report->id));

        $this->get("/admin/message-reports/{$report->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MessageReports/Show')
                ->where('report.id', $report->id)
                ->has('report.context', 3));

        $this->patch("/admin/message-reports/{$report->id}", ['resolution' => 'confirmed', 'note' => 'Hakaret içeren mesaj doğrulandı.', 'enforcement_action' => ModerationSanction::MESSAGE_24H, 'remove_message' => true])
            ->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame(MessageReport::CONFIRMED, $report->status);
        $this->assertSame($admin->id, $report->resolved_by_admin_id);
        $this->assertNotNull($report->resolved_at);
        $this->assertSame(ModerationSanction::MESSAGE_24H, $report->enforcement_action);
        $this->assertTrue($report->remove_message);
        $this->assertNotNull($report->message->fresh()->moderated_at);
        $this->assertDatabaseHas('moderation_sanctions', ['message_report_id' => $report->id, 'user_id' => $report->message->sender_id, 'action' => ModerationSanction::MESSAGE_24H, 'revoked_at' => null]);

        $this->patch("/admin/message-reports/{$report->id}", ['resolution' => 'pending'])
            ->assertSessionHasNoErrors();
        $this->assertNull($report->fresh()->resolved_by_admin_id);
        $this->assertNull($report->message->fresh()->moderated_at);
        $this->assertNotNull(ModerationSanction::where('message_report_id', $report->id)->firstOrFail()->revoked_at);
    }

    public function test_confirmed_violation_requires_an_audit_note(): void
    {
        [$report, $admin] = $this->reportedMessage();
        $this->actingAs($admin)->patch("/admin/message-reports/{$report->id}", ['resolution' => 'confirmed', 'note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertSame(MessageReport::PENDING, $report->fresh()->status);
    }

    public function test_confirmed_violation_requires_an_enforcement_action(): void
    {
        [$report, $admin] = $this->reportedMessage();
        $this->actingAs($admin)->patch("/admin/message-reports/{$report->id}", ['resolution' => 'confirmed', 'note' => 'İhlal doğrulandı.'])
            ->assertSessionHasErrors('enforcement_action');
        $this->assertSame(MessageReport::PENDING, $report->fresh()->status);
    }

    private function reportedMessage(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = Listing::create(['user_id' => $seller->id, 'status' => Listing::STATUS_ACTIVE, 'public_area' => 'Kadıköy, İstanbul', 'approximate_latitude' => 40.99, 'approximate_longitude' => 29.02, 'description' => 'Test ilanı', 'published_at' => now(), 'expires_at' => now()->addMonth()]);
        $request = PickupRequest::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => PickupRequest::INQUIRY]);
        $request->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => 'Önceki mesaj']);
        $message = $request->messages()->create(['sender_id' => $seller->id, 'type' => 'user', 'body' => 'Bildirilen uygunsuz mesaj']);
        $request->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => 'Sonraki mesaj']);
        $report = MessageReport::create(['conversation_message_id' => $message->id, 'reporter_id' => $buyer->id, 'reason' => 'harassment', 'details' => 'Hakaret içeriyor.']);
        return [$report, $admin];
    }
}
