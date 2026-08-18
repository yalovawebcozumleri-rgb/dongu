<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardedListingBoostTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_claim_a_local_reward_once_and_listing_is_boosted_for_24_hours(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($owner, now()->subDay());
        Sanctum::actingAs($owner, ['mobile']);

        $token = $this->postJson("/api/v1/listings/{$listing->id}/rewarded-boost/challenge", ['platform' => 'android'])
            ->assertOk()
            ->assertJsonPath('data.clientCompletionAllowed', true)
            ->assertJsonPath('data.testMode', true)
            ->assertJsonPath('data.adMobAndroidUnitId', 'ca-app-pub-3940256099942544/5224354917')
            ->assertJsonPath('data.adEnvironment', 'test')
            ->json('data.token');

        $this->postJson("/api/v1/listings/{$listing->id}/rewarded-boost/complete", ['token' => $token])
            ->assertOk()->assertJsonPath('data.isBoosted', true);
        $firstExpiry = $listing->fresh()->boosted_until;

        $this->postJson("/api/v1/listings/{$listing->id}/rewarded-boost/complete", ['token' => $token])
            ->assertOk();

        $this->assertTrue($listing->fresh()->boosted_until->equalTo($firstExpiry));
        $this->assertTrue($firstExpiry->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_boosted_listing_is_prioritized_and_another_user_cannot_start_reward(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $boosted = $this->listing($owner, now()->subDays(2));
        $boosted->update(['boosted_until' => now()->addHours(12)]);
        $newest = $this->listing($other, now());

        $this->getJson('/api/v1/listings?sort=newest')->assertOk()->assertJsonPath('data.0.id', $boosted->id);

        Sanctum::actingAs($other, ['mobile']);
        $this->postJson("/api/v1/listings/{$boosted->id}/rewarded-boost/challenge", ['platform' => 'android'])->assertForbidden();
        $this->assertNotSame($boosted->id, $newest->id);
    }

    private function listing(User $owner, $publishedAt): Listing
    {
        $listing = Listing::create([
            'user_id' => $owner->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276,
            'description' => 'Ödüllü reklam testi için geçerli ilan.',
            'published_at' => $publishedAt,
            'expires_at' => now()->addDays(20),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => .50]);
        return $listing;
    }
}
