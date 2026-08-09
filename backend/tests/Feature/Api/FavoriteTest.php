<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\ListingFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_favorite_list_and_remove_an_active_listing_idempotently(): void
    {
        $seller = User::factory()->create(['completed_transactions' => 8, 'rating' => 4.75, 'rating_count' => 4]);
        $buyer = User::factory()->create();
        $listing = $this->listing($seller);
        Sanctum::actingAs($buyer, ['mobile']);

        $this->postJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.isFavorited', true);
        $this->postJson("/api/v1/listings/{$listing->id}/favorite")->assertOk();
        $this->assertDatabaseCount('listing_favorites', 1);

        $this->getJson('/api/v1/listings?latitude=40.9912&longitude=29.0275&radius=3')
            ->assertOk()
            ->assertJsonPath('data.0.isFavorited', true)
            ->assertJsonPath('data.0.sellerTransactions', 8)
            ->assertJsonPath('data.0.rating', 4.75)
            ->assertJsonPath('data.0.ratingCount', 4);

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $listing->id)
            ->assertJsonPath('meta.total', 1);


        $this->deleteJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.isFavorited', false);
        $this->deleteJson("/api/v1/listings/{$listing->id}/favorite")->assertOk();
        $this->assertDatabaseCount('listing_favorites', 0);
    }

    public function test_authenticated_feed_can_show_only_active_favorites(): void
    {
        $buyer = User::factory()->create();
        $favorite = $this->listing(User::factory()->create());
        $this->listing(User::factory()->create());
        ListingFavorite::create(['user_id' => $buyer->id, 'listing_id' => $favorite->id]);
        Sanctum::actingAs($buyer, ['mobile']);

        $this->getJson('/api/v1/listings?sort=favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $favorite->id)
            ->assertJsonPath('data.0.isFavorited', true)
            ->assertJsonPath('meta.total', 1);
    }
    public function test_guest_cannot_manage_favorites_and_user_cannot_favorite_own_or_inactive_listing(): void
    {
        $seller = User::factory()->create();
        $listing = $this->listing($seller);

        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $this->getJson('/api/v1/listings?sort=favorites')->assertUnauthorized();
        $this->postJson("/api/v1/listings/{$listing->id}/favorite")->assertUnauthorized();

        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kendi ilanını favorilerine ekleyemezsin.');

        $buyer = User::factory()->create();
        $listing->update(['status' => Listing::STATUS_COMPLETED]);
        Sanctum::actingAs($buyer, ['mobile']);
        $this->postJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bu ilan artık favorilere eklenemiyor.');
    }

    public function test_inactive_favorite_is_kept_for_history_but_not_returned_in_active_favorites(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($seller);
        ListingFavorite::create(['user_id' => $buyer->id, 'listing_id' => $listing->id]);
        $listing->update(['status' => Listing::STATUS_COMPLETED]);
        Sanctum::actingAs($buyer, ['mobile']);

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
        $this->assertDatabaseHas('listing_favorites', ['user_id' => $buyer->id, 'listing_id' => $listing->id]);
    }

    private function listing(User $seller): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Kadıköy, İstanbul',
            'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027,
            'description' => 'Favori testi için yeterince uzun açıklama.',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 25, 'unit_price' => 0.60]);

        return $listing;
    }
}
