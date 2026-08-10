<?php

namespace Tests\Feature\Api;

use App\Models\ConversationUserState;
use App\Models\Listing;
use App\Models\MarketplaceUsagePolicy;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickupRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_pickup_chat_delivery_and_review_flow(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $stranger = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
            'message' => 'Bugün teslim alabilirim.',
        ])->assertCreated()
            ->assertJsonPath('data.status', PickupRequest::PENDING)
            ->assertJsonPath('data.role', 'buyer')
            ->json('data.id');

        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Saat konusunda konuşabiliriz.',
        ])->assertCreated()->assertJsonPath('data.sender', 'me')
            ->assertJsonPath('data.createdAt', fn ($value) => is_string($value) && str_contains($value, 'T'));

        Sanctum::actingAs($stranger, ['mobile']);
        $this->getJson("/api/v1/pickup-requests/{$requestId}/messages")->assertForbidden();

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'seller')
            ->assertJsonPath('data.0.counterpart.id', $buyer->id)
            ->assertJsonPath('data.0.lastMessage.createdAt', fn ($value) => is_string($value) && str_contains($value, 'T'));

        $this->postJson("/api/v1/pickup-requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::ACCEPTED)
            ->assertJsonPath('data.deliveryCode', null)
            ->assertJsonPath('data.exactAddress', 'Caferağa Mahallesi, bina 12')
            ->assertJsonPath('data.exactLatitude', 40.9912345)
            ->assertJsonPath('data.exactLongitude', 29.0274567);

        $listing->refresh();
        $this->assertSame(Listing::STATUS_RESERVED, $listing->status);

        Sanctum::actingAs($buyer, ['mobile']);
        $code = $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.status', PickupRequest::ACCEPTED)
            ->json('data.0.deliveryCode');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/complete", ['code' => '99999'])
            ->assertUnprocessable();
        $this->postJson("/api/v1/pickup-requests/{$requestId}/complete", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::COMPLETED)
            ->assertJsonPath('data.canReview', true);

        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Teslimattan sonra gönderilmemeli.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Teslimat tamamlandığı için bu görüşme yeni mesajlara kapatıldı.');

        $this->assertSame(Listing::STATUS_COMPLETED, $listing->fresh()->status);
        $this->assertSame(1, $seller->fresh()->completed_transactions);
        $this->assertSame(1, $buyer->fresh()->completed_transactions);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/review", [
            'rating' => 4,
            'comment' => 'Teslimat sorunsuzdu.',
        ])->assertOk()->assertJsonPath('data.reviewed', true);
        $seller->refresh();
        $this->assertSame('4.00', $seller->rating);
        $this->assertSame(1, $seller->rating_count);

        $this->postJson("/api/v1/pickup-requests/{$requestId}/review", ['rating' => 5])
            ->assertUnprocessable();
    }

    public function test_message_only_conversation_can_be_promoted_to_pickup_request(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
        ])->assertCreated()->assertJsonPath('data.status', PickupRequest::INQUIRY)->json('data.id');

        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertOk()->assertJsonPath('data.id', $requestId)->assertJsonPath('data.status', PickupRequest::PENDING);
    }



    public function test_empty_message_conversation_can_be_hidden_by_its_owner(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
        ])->assertCreated()->assertJsonPath('data.lastMessage', null)->json('data.id');

        $this->deleteJson("/api/v1/pickup-requests/{$requestId}/conversation")
            ->assertOk()
            ->assertJsonPath('data.hidden', true);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
    }
    public function test_normal_conversation_with_messages_can_be_hidden_and_new_message_restores_only_recipient(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
            'message' => 'Merhaba, ilan hâlâ güncel mi?',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/pickup-requests/{$requestId}/conversation")->assertOk();
        ConversationUserState::updateOrCreate([
            'pickup_request_id' => $requestId,
            'user_id' => $seller->id,
        ], ['hidden_at' => now()]);

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Evet, ilan güncel.',
        ])->assertCreated();

        $this->assertDatabaseHas('conversation_user_states', [
            'pickup_request_id' => $requestId,
            'user_id' => $buyer->id,
            'hidden_at' => null,
        ]);
        $this->assertNotNull(ConversationUserState::query()
            ->where('pickup_request_id', $requestId)
            ->where('user_id', $seller->id)
            ->value('hidden_at'));
    }

    public function test_reopening_hidden_inquiry_from_listing_only_restores_initiating_user(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
        ])->assertCreated()->json('data.id');

        foreach ([$buyer->id, $seller->id] as $userId) {
            ConversationUserState::updateOrCreate([
                'pickup_request_id' => $requestId,
                'user_id' => $userId,
            ], ['hidden_at' => now()]);
        }

        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
        ])->assertOk()->assertJsonPath('data.id', $requestId);

        $this->assertDatabaseHas('conversation_user_states', [
            'pickup_request_id' => $requestId,
            'user_id' => $buyer->id,
            'hidden_at' => null,
        ]);
        $this->assertNotNull(ConversationUserState::query()
            ->where('pickup_request_id', $requestId)
            ->where('user_id', $seller->id)
            ->value('hidden_at'));
    }
    public function test_existing_pending_request_is_opened_without_downgrading_its_status(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $stranger = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->assertJsonPath('data.status', PickupRequest::PENDING)->json('data.id');

        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
        ])->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.status', PickupRequest::PENDING);

        $this->getJson("/api/v1/pickup-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.status', PickupRequest::PENDING);

        Sanctum::actingAs($stranger, ['mobile']);
        $this->getJson("/api/v1/pickup-requests/{$requestId}")->assertForbidden();
    }
    public function test_rejected_request_is_read_only_and_cannot_be_sent_again(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::REJECTED);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Tekrar yazılmamalı.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Satıcı talebi reddettiği için bu görüşme yeni mesajlara kapatıldı.');

        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertUnprocessable();
        $this->getJson("/api/v1/listings/{$listing->id}/interaction-eligibility")
            ->assertOk()
            ->assertJsonPath('data.message.allowed', false)
            ->assertJsonPath('data.pickup.allowed', false);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.0.requestStatus', 'rejected');
    }
    public function test_withdrawing_a_pickup_request_closes_old_chat_and_allows_a_new_request(): void
    {
        MarketplaceUsagePolicy::current()->update(['same_seller_contact_24h_limit' => 10]);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/pickup-requests/{$requestId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::CANCELLED)
            ->assertJsonPath('data.listing.requestStatus', 'none');

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.0.requestStatus', 'cancelled');

        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Talebi geri çektim ama bir şey danışmak istiyorum.',
        ])->assertUnprocessable();

        $this->travel(31)->seconds();
        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.status', PickupRequest::PENDING);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.0.requestStatus', 'pending');
    }

    public function test_withdrawing_an_accepted_request_reactivates_the_listing(): void
    {
        MarketplaceUsagePolicy::current()->update(['same_seller_contact_24h_limit' => 10]);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::ACCEPTED);
        $this->assertSame(Listing::STATUS_RESERVED, $listing->fresh()->status);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::CANCELLED)
            ->assertJsonPath('data.deliveryCode', null)
            ->assertJsonPath('data.exactAddress', null)
            ->assertJsonPath('data.exactLatitude', null)
            ->assertJsonPath('data.exactLongitude', null)
            ->assertJsonPath('data.cancelledByRole', 'buyer');

        $this->assertSame(Listing::STATUS_ACTIVE, $listing->fresh()->status);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Başka bir gün için konuşabiliriz.',
        ])->assertUnprocessable();
        $this->travel(31)->seconds();
        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
            'message' => 'Başka bir gün için konuşabiliriz.',
        ])->assertOk()->assertJsonPath('data.status', PickupRequest::INQUIRY);
    }

    public function test_seller_can_cancel_an_accepted_reservation(): void
    {
        MarketplaceUsagePolicy::current()->update(['same_seller_contact_24h_limit' => 10]);
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/accept")->assertOk();
        $response = $this->postJson("/api/v1/pickup-requests/{$requestId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::CANCELLED)
            ->assertJsonPath('data.cancelledByRole', 'seller')
            ->assertJsonPath('data.deliveryCode', null);

        $this->assertNotNull($response->json('data.cancelledAt'));
        $this->assertSame($seller->id, PickupRequest::findOrFail($requestId)->cancelled_by_user_id);
        $this->assertSame(Listing::STATUS_ACTIVE, $listing->fresh()->status);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", [
            'message' => 'Başka bir teslim zamanı konuşabiliriz.',
        ])->assertUnprocessable();
        $this->travel(31)->seconds();
        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
            'message' => 'Başka bir teslim zamanı konuşabiliriz.',
        ])->assertOk()->assertJsonPath('data.status', PickupRequest::INQUIRY);
    }

    public function test_accepting_one_buyer_closes_other_conversations_neutrally(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $winner = User::factory()->create(['status' => 'active']);
        $otherBuyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($winner, ['mobile']);
        $winnerRequestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($otherBuyer, ['mobile']);
        $otherRequestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$winnerRequestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', PickupRequest::ACCEPTED);

        $otherRequest = PickupRequest::findOrFail($otherRequestId);
        $this->assertSame(PickupRequest::CLOSED, $otherRequest->status);
        $this->assertSame('listing_unavailable', $otherRequest->closed_reason);
        $this->assertNotNull($otherRequest->closed_at);
        $this->assertDatabaseHas('conversation_messages', [
            'pickup_request_id' => $otherRequestId,
            'type' => 'system',
            'body' => 'İlan artık alım taleplerine kapalı. Bu ilan için görüşme sona erdi.',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $otherBuyer->id,
            'type' => 'listing_unavailable',
            'title' => 'İlan durumu güncellendi',
        ]);

        Sanctum::actingAs($otherBuyer, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.status', PickupRequest::CLOSED)
            ->assertJsonPath('data.0.listingAvailable', false)
            ->assertJsonPath('data.0.listingSummary.id', $listing->id)
            ->assertJsonPath('data.0.listingSummary.items.0.material', 'PET');
        $this->postJson("/api/v1/pickup-requests/{$otherRequestId}/messages", ['message' => 'Hâlâ alabilir miyim?'])
            ->assertUnprocessable();
        $this->deleteJson("/api/v1/pickup-requests/{$otherRequestId}/conversation")
            ->assertOk()
            ->assertJsonPath('data.hidden', true);

        $notificationCount = UserNotification::where('user_id', $otherBuyer->id)
            ->where('type', 'listing_unavailable')
            ->count();
        Sanctum::actingAs($seller, ['mobile']);
        $code = PickupRequest::findOrFail($winnerRequestId)->delivery_code;
        $this->postJson("/api/v1/pickup-requests/{$winnerRequestId}/complete", ['code' => $code])->assertOk();
        $this->assertSame($notificationCount, UserNotification::where('user_id', $otherBuyer->id)->where('type', 'listing_unavailable')->count());
    }

    public function test_deleting_listing_keeps_conversation_summary_and_makes_it_deletable(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'message',
            'message' => 'Ürünler hâlâ mevcut mu?',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();

        Sanctum::actingAs($buyer, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.status', PickupRequest::CLOSED)
            ->assertJsonPath('data.0.listingAvailable', false)
            ->assertJsonPath('data.0.listingSummary.district', 'Kadıköy, İstanbul');
        $this->deleteJson("/api/v1/pickup-requests/{$requestId}/conversation")->assertOk();
    }
    public function test_expired_listing_command_closes_open_conversations(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);
        $pickupRequest = PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::INQUIRY,
            'listing_snapshot' => [
                'id' => $listing->id,
                'sellerId' => $seller->id,
                'seller' => $seller->name,
                'district' => $listing->public_area,
                'items' => [['material' => 'PET', 'type' => 'pet', 'count' => 20, 'unitPrice' => .75]],
            ],
        ]);
        $pickupRequest->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => 'Ürünler mevcut mu?']);
        $listing->update(['expires_at' => now()->subMinute()]);

        Artisan::call('listings:close-expired-conversations');

        $this->assertSame(PickupRequest::CLOSED, $pickupRequest->fresh()->status);
        $this->assertSame('listing_expired', $pickupRequest->fresh()->closed_reason);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $buyer->id,
            'type' => 'listing_unavailable',
        ]);
    }
    public function test_review_window_closes_after_twenty_four_hours(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);
        $listing->update(['status' => Listing::STATUS_COMPLETED]);
        $pickupRequest = PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::COMPLETED,
            'completed_at' => now()->subHours(25),
        ]);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.canReview', false);

        $this->postJson("/api/v1/pickup-requests/{$pickupRequest->id}/review", [
            'rating' => 5,
        ])->assertUnprocessable()
            ->assertJsonPath('message', '24 saatlik değerlendirme süresi sona erdi.');
    }
    private function listing(User $seller): Listing


    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Kadıköy, İstanbul',
            'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027,
            'description' => 'Temiz ve poşetlenmiş ambalajlar.',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 20, 'unit_price' => .75]);
        $listing->privateLocation()->create([
            'latitude' => '40.9912345',
            'longitude' => '29.0274567',
            'address' => 'Caferağa Mahallesi, bina 12',
        ]);

        return $listing;
    }
}