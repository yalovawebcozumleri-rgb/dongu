<?php

namespace Tests\Feature\Api;

use App\Models\Achievement;
use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_returns_real_public_data_without_private_contact_information(): void
    {
        $seller = User::factory()->create([
            'name' => 'Doğa Dostu', 'email' => 'private@example.com', 'phone' => '05551112233',
            'status' => 'active', 'rating' => 4.5, 'rating_count' => 1, 'completed_transactions' => 3,
        ]);
        $reviewer = User::factory()->create(['status' => 'active', 'avatar_key' => 'avatar_04']);
        CycleScoreSummary::create(['user_id' => $seller->id, 'period_key' => 'all', 'points' => 240, 'deliveries' => 3]);
        $achievement = Achievement::query()->first();
        DB::table('user_achievements')->insert(['user_id' => $seller->id, 'achievement_id' => $achievement->id, 'awarded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $active = $this->listing($seller, Listing::STATUS_ACTIVE);
        $this->listing($seller, Listing::STATUS_CANCELLED);
        $pickup = PickupRequest::create(['listing_id' => $active->id, 'buyer_id' => $reviewer->id, 'seller_id' => $seller->id, 'status' => PickupRequest::COMPLETED, 'completed_at' => now()]);
        Review::create(['pickup_request_id' => $pickup->id, 'reviewer_id' => $reviewer->id, 'reviewee_id' => $seller->id, 'rating' => 5, 'comment' => 'Teslimat sorunsuzdu.']);

        $response = $this->getJson("/api/v1/users/{$seller->id}/public-profile")
            ->assertOk()->assertJsonPath('data.name', 'Doğa Dostu')->assertJsonPath('data.rating.average', 4.5)
            ->assertJsonPath('data.completedDeliveries', 3)->assertJsonPath('data.cycle.points', 240)
            ->assertJsonCount(1, 'data.badges')->assertJsonCount(1, 'data.activeListings');
        $this->assertArrayNotHasKey('email', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
        $this->assertArrayNotHasKey('address', $response->json('data'));

        $this->getJson("/api/v1/users/{$seller->id}/reviews")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Teslimat sorunsuzdu.')
            ->assertJsonMissing(['email' => $reviewer->email])
            ->assertJsonPath('data.0.reviewer.avatarUrl', 'preset://avatar_04');
    }

    public function test_profile_listings_include_the_viewers_pickup_request_status(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $viewer = User::factory()->create(['status' => 'active']);
        $otherBuyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller, Listing::STATUS_ACTIVE);
        PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $otherBuyer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::REJECTED,
        ]);
        PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $viewer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::PENDING,
        ]);
        Sanctum::actingAs($viewer, ['mobile']);

        $this->getJson("/api/v1/users/{$seller->id}/public-profile")
            ->assertOk()
            ->assertJsonPath('data.activeListings.0.id', $listing->id)
            ->assertJsonPath('data.activeListings.0.requestStatus', 'pending');
    }
    public function test_blocked_or_inactive_profiles_are_not_visible(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $seller = User::factory()->create(['status' => 'active']);
        UserBlock::create(['blocker_id' => $seller->id, 'blocked_id' => $viewer->id]);
        Sanctum::actingAs($viewer, ['mobile']);
        $this->getJson("/api/v1/users/{$seller->id}/public-profile")->assertNotFound();
        $this->getJson("/api/v1/users/{$seller->id}/reviews")->assertNotFound();

        $inactive = User::factory()->create(['status' => 'suspended']);
        $this->getJson("/api/v1/users/{$inactive->id}/public-profile")->assertNotFound();
    }

    private function listing(User $seller, string $status): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => $status, 'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.65, 'approximate_longitude' => 29.27,
            'description' => 'Kullanıcı profili için test ilanı.', 'published_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 20, 'unit_price' => .50]);
        return $listing;
    }
}
