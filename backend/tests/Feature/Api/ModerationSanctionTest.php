<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\MessageReport;
use App\Models\ModerationSanction;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationSanctionTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_restriction_blocks_messages_until_expiry_and_removed_message_is_sanitized(): void
    {
        [$report, $admin, $offender, $reporter, $conversation] = $this->reportedConversation();
        $this->actingAs($admin)->patch("/admin/message-reports/{$report->id}", [
            'resolution' => 'confirmed', 'note' => 'Mesajlaşma ihlali doğrulandı.',
            'enforcement_action' => ModerationSanction::MESSAGE_24H, 'remove_message' => true,
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($reporter, ['mobile']);
        $this->getJson("/api/v1/pickup-requests/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.sender', 'system')
            ->assertJsonPath('data.0.text', 'Bu mesaj topluluk kurallarını ihlal ettiği için kaldırıldı.');

        Sanctum::actingAs($offender, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$conversation->id}/messages", ['message' => 'Yeni mesaj'])
            ->assertForbidden()
            ->assertJsonPath('moderation.action', ModerationSanction::MESSAGE_24H);

        ModerationSanction::where('message_report_id', $report->id)->update(['ends_at' => now()->subMinute()]);
        $this->postJson("/api/v1/pickup-requests/{$conversation->id}/messages", ['message' => 'Süre dolduktan sonra mesaj'])
            ->assertCreated();
    }

    public function test_account_suspension_revokes_tokens_and_blocks_login_until_expiry(): void
    {
        [$report, $admin, $offender] = $this->reportedConversation();
        $offender->createToken('test-phone', ['mobile']);

        $this->actingAs($admin)->patch("/admin/message-reports/{$report->id}", [
            'resolution' => 'confirmed', 'note' => 'Hesap askısı gerektiren ihlal.',
            'enforcement_action' => ModerationSanction::ACCOUNT_7D,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $offender->id]);
        $this->postJson('/api/v1/auth/code/request', ['intent' => 'login', 'email' => $offender->email])
            ->assertForbidden()
            ->assertJsonPath('moderation.action', ModerationSanction::ACCOUNT_7D);

        ModerationSanction::where('message_report_id', $report->id)->update(['ends_at' => now()->subMinute()]);
        $this->assertNull(app(\App\Services\ModerationSanctionService::class)->activeFor($offender, 'account_suspension_'));
    }

    private function reportedConversation(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = Listing::create(['user_id' => $seller->id, 'status' => Listing::STATUS_ACTIVE, 'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.65, 'approximate_longitude' => 29.27, 'description' => 'Moderasyon test ilanı', 'published_at' => now(), 'expires_at' => now()->addMonth()]);
        $conversation = PickupRequest::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => PickupRequest::INQUIRY]);
        $message = $conversation->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => 'Kuralları ihlal eden mesaj']);
        $conversation->messages()->create(['sender_id' => $seller->id, 'type' => 'user', 'body' => 'Yanıt mesajı']);
        $report = MessageReport::create(['conversation_message_id' => $message->id, 'reporter_id' => $seller->id, 'reason' => 'harassment']);
        return [$report, $admin, $buyer, $seller, $conversation];
    }
}
