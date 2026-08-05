<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingRenewalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_listing_cannot_be_repeatedly_renewed_to_game_newest_sort(): void
    {
        $user = User::factory()->create();
        $listing = Listing::create([
            'user_id' => $user->id, 'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276, 'description' => 'Yenileme politikası için geçerli ilan.',
            'published_at' => now(), 'expires_at' => now()->addDays(20),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => .50]);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson("/api/v1/listings/{$listing->id}/renew")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'İlan yalnızca yayın süresinin son 7 gününde yenilenebilir.');
    }
}
