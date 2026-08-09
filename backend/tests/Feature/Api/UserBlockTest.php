<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_is_mutual_for_visibility_and_contact_and_can_be_undone(): void
    {
        $seller = User::factory()->create(['status' => 'active', 'avatar_key' => 'avatar_06']);
        $buyer = User::factory()->create(['status' => 'active']);
        $otherSeller = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller, 'Satıcının ilanı');
        $otherListing = $this->listing($otherSeller, 'Diğer ilan');

        Sanctum::actingAs($buyer, ['mobile']);
        $requestId = $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", [
            'intent' => 'pickup',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/accept")->assertOk();
        $this->assertSame(Listing::STATUS_RESERVED, $listing->fresh()->status);

        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/users/{$seller->id}/block")
            ->assertCreated()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.avatarUrl', 'preset://avatar_06');
        $this->getJson('/api/v1/blocks')->assertOk()
            ->assertJsonPath('data.0.avatarUrl', 'preset://avatar_06');

        $pickupRequest = PickupRequest::findOrFail($requestId);
        $this->assertSame(PickupRequest::CANCELLED, $pickupRequest->status);
        $this->assertSame($buyer->id, $pickupRequest->cancelled_by_user_id);
        $this->assertSame(Listing::STATUS_ACTIVE, $listing->fresh()->status);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.isBlocked', true)
            ->assertJsonPath('data.0.blockedByMe', true)
            ->assertJsonPath('data.0.exactAddress', null);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonMissing(['sellerId' => $seller->id])
            ->assertJsonFragment(['id' => $otherListing->id]);

        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", ['message' => 'Gönderilmemeli.'])
            ->assertUnprocessable();
        $this->postJson("/api/v1/listings/{$listing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertUnprocessable();

        Sanctum::actingAs($seller, ['mobile']);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.isBlocked', true)
            ->assertJsonPath('data.0.blockedByMe', false);
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", ['message' => 'Bu da gönderilmemeli.'])
            ->assertUnprocessable();

        Sanctum::actingAs($buyer, ['mobile']);
        $this->deleteJson("/api/v1/users/{$seller->id}/block")->assertNoContent();
        // Engeli kaldırmak görünürlüğü geri getirir; engelleme sırasında iptal edilen eski görüşmeyi yeniden açmaz.
        $this->postJson("/api/v1/pickup-requests/{$requestId}/messages", ['message' => 'Eski görüşmeye yazılmamalı.'])
            ->assertUnprocessable();
        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonFragment(['sellerId' => $seller->id]);
    }

    private function listing(User $seller, string $description): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Kadıköy, İstanbul',
            'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027,
            'description' => $description,
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