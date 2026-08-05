<?php

namespace Tests\Feature\Api;

use App\Models\ConversationUserState;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_gets_active_and_historical_requests_with_real_counts(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $pending = $this->request($buyer, $seller, PickupRequest::PENDING);
        $this->request($buyer, $seller, PickupRequest::ACCEPTED);
        $completed = $this->request($buyer, $seller, PickupRequest::COMPLETED);
        $this->request($buyer, $seller, PickupRequest::REJECTED);
        $this->request($buyer, $seller, PickupRequest::CANCELLED);
        $this->request($buyer, $seller, PickupRequest::INQUIRY);
        $this->request(User::factory()->create(), $seller, PickupRequest::PENDING);
        ConversationUserState::create(['pickup_request_id' => $completed->id, 'user_id' => $buyer->id, 'hidden_at' => now()]);
        Sanctum::actingAs($buyer, ['mobile']);

        $this->getJson('/api/v1/my/pickup-requests?scope=active&per_page=10')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.active', 2)->assertJsonPath('summary.history', 3)
            ->assertJsonFragment(['id' => $pending->id]);

        $this->getJson('/api/v1/my/pickup-requests?scope=history&per_page=10')
            ->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $completed->id, 'status' => PickupRequest::COMPLETED]);
    }

    public function test_purchase_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/my/pickup-requests')->assertUnauthorized();
    }

    private function request(User $buyer, User $seller, string $status): PickupRequest
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => $status === PickupRequest::COMPLETED ? Listing::STATUS_COMPLETED : Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.655, 'approximate_longitude' => 29.276,
            'description' => 'Alım geçmişi testi için geçerli ilan açıklaması.', 'published_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 20, 'unit_price' => .50]);

        return PickupRequest::create([
            'listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'status' => $status, 'accepted_at' => $status === PickupRequest::ACCEPTED ? now() : null,
            'completed_at' => $status === PickupRequest::COMPLETED ? now() : null,
        ]);
    }
}
