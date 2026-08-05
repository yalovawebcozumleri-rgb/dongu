<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\MarketplaceUsagePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickupInterstitialPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_second_new_pickup_in_rolling_day_opens_interstitial_opportunity(): void
    {
        MarketplaceUsagePolicy::current()->update(['contact_cooldown_seconds' => 0]);
        $buyer = User::factory()->create(['status' => 'active', 'created_at' => now()->subDays(2)]);
        $firstListing = $this->listing(User::factory()->create(['status' => 'active']));
        $secondListing = $this->listing(User::factory()->create(['status' => 'active']));
        Sanctum::actingAs($buyer, ['mobile']);

        $this->postJson("/api/v1/listings/{$firstListing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertCreated()
            ->assertJsonPath('monetization.showInterstitial', false)
            ->assertJsonPath('monetization.dailyPickupOrdinal', 1);

        $this->postJson("/api/v1/listings/{$secondListing->id}/pickup-requests", ['intent' => 'pickup'])
            ->assertCreated()
            ->assertJsonPath('monetization.showInterstitial', true)
            ->assertJsonPath('monetization.dailyPickupOrdinal', 2);
    }

    private function listing(User $seller): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276,
            'description' => 'Talep reklam sıklığı testi için ilan.',
            'published_at' => now(),
            'expires_at' => now()->addDays(20),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => .50]);
        return $listing;
    }
}
