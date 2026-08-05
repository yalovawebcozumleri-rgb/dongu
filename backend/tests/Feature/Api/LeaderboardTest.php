<?php

namespace Tests\Feature\Api;

use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_delivery_awards_only_seller_points_and_badges_once(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller, 75);
        $pickup = PickupRequest::create([
            'listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'status' => PickupRequest::ACCEPTED, 'delivery_code' => '4321', 'accepted_at' => now()->subMinutes(10),
        ]);
        Sanctum::actingAs($seller, ['mobile']);

        $this->postJson("/api/v1/pickup-requests/{$pickup->id}/complete", ['code' => '4321'])->assertOk();

        $this->assertDatabaseHas('cycle_point_entries', ['user_id' => $seller->id, 'pickup_request_id' => $pickup->id, 'role' => 'seller', 'points' => 75]);
        $this->assertDatabaseMissing('cycle_point_entries', ['user_id' => $buyer->id, 'pickup_request_id' => $pickup->id]);
        $this->assertDatabaseMissing('cycle_score_summaries', ['user_id' => $buyer->id]);
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 75, 'deliveries' => 1]);
        $this->assertDatabaseHas('user_achievements', ['user_id' => $seller->id]);
        $this->assertDatabaseCount('cycle_point_entries', 1);

        $secondBuyer = User::factory()->create(['status' => 'active']);
        $secondListing = $this->listing($seller, 30);
        $secondPickup = PickupRequest::create([
            'listing_id' => $secondListing->id, 'buyer_id' => $secondBuyer->id, 'seller_id' => $seller->id,
            'status' => PickupRequest::ACCEPTED, 'delivery_code' => '8765', 'accepted_at' => now()->subMinutes(10),
        ]);
        $this->postJson("/api/v1/pickup-requests/{$secondPickup->id}/complete", ['code' => '8765'])->assertOk();
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 105, 'deliveries' => 2]);
        $this->assertDatabaseMissing('cycle_score_summaries', ['user_id' => $secondBuyer->id]);
        $this->assertDatabaseCount('cycle_point_entries', 2);
        $this->assertDatabaseCount('user_achievements', 1);
    }

    public function test_leaderboard_returns_first_fifty_and_the_users_real_position(): void
    {
        $users = User::factory()->count(55)->create(['status' => 'active']);
        foreach ($users as $index => $user) {
            CycleScoreSummary::create(['user_id' => $user->id, 'period_key' => 'all', 'points' => 1000 - $index, 'deliveries' => 1]);
        }
        $current = $users->last();
        Sanctum::actingAs($current, ['mobile']);

        $this->getJson('/api/v1/leaderboard?period=all')->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('own.rank', 55)
            ->assertJsonPath('own.isOwn', true)
            ->assertJsonPath('meta.totalParticipants', 55);
    }

    public function test_monthly_period_and_name_privacy_are_real(): void
    {
        $private = User::factory()->create(['status' => 'active', 'avatar_path' => 'avatars/private/avatar-512.webp']);
        CycleScoreSummary::create(['user_id' => $private->id, 'period_key' => now()->format('Y-m'), 'points' => 120, 'deliveries' => 2]);
        Sanctum::actingAs($private, ['mobile']);
        $this->patchJson('/api/v1/leaderboard/privacy', ['nameVisible' => false])->assertOk()->assertJsonPath('data.nameVisible', false);
        $this->getJson('/api/v1/leaderboard?period=monthly')->assertOk()
            ->assertJsonPath('data.0.anonymous', false)
            ->assertJsonPath('data.0.avatarUrl', url('/storage/avatars/private/avatar-128.webp'));

        $viewer = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($viewer, ['mobile']);
        $this->getJson('/api/v1/leaderboard?period=monthly')->assertOk()
            ->assertJsonPath('data.0.name', 'Döngü üyesi')
            ->assertJsonPath('data.0.anonymous', true)
            ->assertJsonPath('data.0.avatarUrl', null)
            ->assertJsonPath('meta.period', 'monthly');
    }

    private function listing(User $seller, int $quantity): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => Listing::STATUS_RESERVED,
            'public_area' => 'Kadıköy, İstanbul', 'approximate_latitude' => 40.99,
            'approximate_longitude' => 29.02, 'description' => 'Döngü sıralaması için test ilanı.',
            'published_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => $quantity, 'unit_price' => 0.50]);
        $listing->privateLocation()->create(['latitude' => '40.9900000', 'longitude' => '29.0200000', 'address' => 'Test adresi']);
        return $listing;
    }
}
