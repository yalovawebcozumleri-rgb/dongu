<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_listings_with_real_pagination(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach (range(1, 23) as $number) {
            $this->listing($owner, "Bölge {$number}");
        }
        $this->listing($other, 'Başkasının ilanı');
        Sanctum::actingAs($owner, ['mobile']);

        $this->getJson('/api/v1/my/listings?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonMissing(['district' => 'Başkasının ilanı']);
    }

    public function test_active_and_history_scopes_are_separated_with_real_counts(): void
    {
        $owner = User::factory()->create();
        $published = $this->listing($owner, 'Yayındaki ilan');
        $reserved = $this->listing($owner, 'Rezerve ilan');
        $reserved->update(['status' => Listing::STATUS_RESERVED]);
        $completed = $this->listing($owner, 'Tamamlanan ilan');
        $completed->update(['status' => Listing::STATUS_COMPLETED]);
        $expired = $this->listing($owner, 'Süresi dolan ilan');
        $expired->update(['expires_at' => now()->subMinute()]);
        $removed = $this->listing($owner, 'Kaldırılan ilan');
        $removed->delete();
        Sanctum::actingAs($owner, ['mobile']);

        $this->getJson('/api/v1/my/listings?scope=active&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.history', 3)
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonFragment(['id' => $reserved->id, 'ownerState' => 'reserved'])
            ->assertJsonMissing(['id' => $completed->id]);

        $this->getJson('/api/v1/my/listings?scope=history&per_page=10')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $completed->id, 'ownerState' => 'completed'])
            ->assertJsonFragment(['id' => $expired->id, 'ownerState' => 'expired'])
            ->assertJsonFragment(['id' => $removed->id, 'ownerState' => 'removed']);
    }

    public function test_listing_with_open_pickup_request_cannot_be_removed(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($seller, 'Güvenli silme testi');
        PickupRequest::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => PickupRequest::PENDING,
        ]);
        Sanctum::actingAs($seller, ['mobile']);

        $this->deleteJson("/api/v1/listings/{$listing->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Açık alım talebi veya rezervasyonu bulunan ilan kaldırılamaz.');
        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'deleted_at' => null]);
    }

    private function listing(User $seller, string $area): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => $area,
            'approximate_latitude' => 40.991,
            'approximate_longitude' => 29.027,
            'description' => 'İlanlarım ekranı için geçerli test açıklaması.',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => 0.50]);

        return $listing;
    }
}
