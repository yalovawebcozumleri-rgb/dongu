<?php

namespace Tests\Feature\Api;

use App\Models\Achievement;
use App\Models\CyclePointEntry;
use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileImpactTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_impact_uses_real_verified_points_ratings_deliveries_and_badges(): void
    {
        $user = User::factory()->create([
            'rating' => 4.50, 'rating_count' => 3, 'completed_transactions' => 4,
        ]);
        CycleScoreSummary::create(['user_id' => $user->id, 'period_key' => 'all', 'points' => 1500, 'deliveries' => 2]);
        CycleScoreSummary::create(['user_id' => $user->id, 'period_key' => now()->format('Y-m'), 'points' => 50, 'deliveries' => 1]);
        $earned = Achievement::whereIn('code', ['first_cycle', 'cycle_friend'])->orderBy('sort_order')->get();
        foreach ($earned as $achievement) {
            DB::table('user_achievements')->insert([
                'user_id' => $user->id, 'achievement_id' => $achievement->id,
                'awarded_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $pickup = $this->pickup($user);
        CyclePointEntry::create([
            'user_id' => $user->id, 'pickup_request_id' => $pickup->id, 'role' => 'buyer',
            'reason' => 'delivery_completed', 'points' => 30,
            'status' => CyclePointEntry::PENDING_REVIEW, 'earned_at' => now(),
        ]);
        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/v1/profile/impact')->assertOk()
            ->assertJsonPath('data.rating.average', 4.5)
            ->assertJsonPath('data.rating.count', 3)
            ->assertJsonPath('data.completedDeliveries', 4)
            ->assertJsonPath('data.cycle.points', 1500)
            ->assertJsonPath('data.cycle.monthlyPoints', 50)
            ->assertJsonPath('data.cycle.pendingReviewPoints', 30)
            ->assertJsonCount(2, 'data.badges')
            ->assertJsonPath('data.nextBadge.code', 'nature_ambassador')
            ->assertJsonPath('data.nextBadge.current', 1500)
            ->assertJsonPath('data.nextBadge.target', 2500)
            ->assertJsonPath('data.nextBadge.progress', 60);
    }

    public function test_new_user_has_truthful_empty_impact_state_and_data_is_private(): void
    {
        $user = User::factory()->create(['rating' => null, 'rating_count' => 0, 'completed_transactions' => 0]);
        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/v1/profile/impact')->assertOk()
            ->assertJsonPath('data.rating.average', null)
            ->assertJsonPath('data.rating.count', 0)
            ->assertJsonPath('data.cycle.points', 0)
            ->assertJsonCount(0, 'data.badges')
            ->assertJsonPath('data.nextBadge.code', 'first_cycle');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/profile/impact')->assertUnauthorized();
    }

    private function pickup(User $user): PickupRequest
    {
        $seller = User::factory()->create();
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => Listing::STATUS_COMPLETED,
            'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276, 'description' => 'Profil katkı testi için geçerli ilan.',
            'published_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 30, 'unit_price' => .50]);

        return PickupRequest::create([
            'listing_id' => $listing->id, 'buyer_id' => $user->id, 'seller_id' => $seller->id,
            'status' => PickupRequest::COMPLETED, 'completed_at' => now(),
        ]);
    }
}
