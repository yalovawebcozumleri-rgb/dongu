<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ])->assertCreated()->assertJsonPath('data.sender', 'me');

        Sanctum::actingAs($stranger, ['mobile']);
        $this->getJson("/api/v1/pickup-requests/{$requestId}/messages")->assertForbidden();

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'seller')
            ->assertJsonPath('data.0.counterpart.id', $buyer->id);

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

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.0.requestStatus', 'rejected');
    }
    public function test_withdrawing_a_pickup_request_keeps_chat_open_and_allows_a_new_request(): void

    {
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
        ])->assertCreated()->assertJsonPath('data.sender', 'me');

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
        ])->assertCreated();
    }

    public function test_seller_can_cancel_an_accepted_reservation(): void
    {
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
        ])->assertCreated();
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