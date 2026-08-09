<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_listing_expires_and_owner_can_renew_it(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $listing = $this->listing($user, now()->subDay());

        $response = $this->postJson('/api/v1/listings/'.$listing->id.'/renew');

        $response->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.expiresInDays', config('marketplace.listing_lifetime_days'));

        $this->assertTrue(
            $listing->fresh()->expires_at->isSameDay(now()->addDays(config('marketplace.listing_lifetime_days')))
        );
    }

    public function test_prune_command_removes_only_records_past_retention_and_their_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $old = $this->listing(
            $user,
            now()->subDays(config('marketplace.expired_listing_retention_days') + 1),
        );
        $recent = $this->listing($user, now()->subDay());

        Storage::disk('public')->put('listings/old.jpg', 'photo');
        $old->photos()->create(['path' => 'listings/old.jpg', 'sort_order' => 0]);

        $this->artisan('listings:prune')->assertSuccessful();

        $this->assertDatabaseMissing('listings', ['id' => $old->id]);
        $this->assertDatabaseHas('listings', ['id' => $recent->id]);
        Storage::disk('public')->assertMissing('listings/old.jpg');
    }

    public function test_pruning_listing_preserves_transaction_and_message_history(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing(
            $seller,
            now()->subDays(config('marketplace.expired_listing_retention_days') + 1),
        );
        $pickupRequest = PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::COMPLETED,
            'listing_snapshot' => [
                'id' => $listing->id,
                'sellerId' => $seller->id,
                'seller' => $seller->name,
                'district' => $listing->public_area,
                'items' => [['material' => 'PET', 'type' => 'pet', 'count' => 10, 'unitPrice' => .50]],
            ],
            'completed_at' => now()->subMonth(),
        ]);
        $message = $pickupRequest->messages()->create([
            'sender_id' => $buyer->id,
            'type' => 'user',
            'body' => 'Kalıcı işlem kaydı',
        ]);

        $this->artisan('listings:prune')->assertSuccessful();

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('pickup_requests', ['id' => $pickupRequest->id, 'listing_id' => null]);
        $this->assertDatabaseHas('conversation_messages', ['id' => $message->id]);
    }

    private function listing(User $user, $expiresAt): Listing
    {
        $listing = Listing::create([
            'user_id' => $user->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276,
            'description' => 'Saklama süresi testi için yeterli ilan açıklaması.',
            'published_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => .50]);

        return $listing;
    }
}