<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\ListingPrivateLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_multi_material_listing(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $response = $this->postJson('/api/v1/listings', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.items.0.type', 'pet')
            ->assertJsonPath('data.items.1.type', 'glass')
            ->assertJsonMissingPath('data.exact_address');

        $this->assertDatabaseHas('listings', [
            'user_id' => $user->id,
            'public_area' => 'Kadıköy, İstanbul',
            'approximate_latitude' => 40.9910000,
            'approximate_longitude' => 29.0270000,
        ]);
        $this->assertDatabaseCount('listing_materials', 2);

        $privateLocation = ListingPrivateLocation::firstOrFail();
        $this->assertSame('40.9912345', $privateLocation->latitude);
        $this->assertSame('Caferağa Mahallesi, bina 12', $privateLocation->address);
        $this->assertNotSame('40.9912345', $privateLocation->getRawOriginal('latitude'));
    }

    public function test_material_quantity_must_be_at_least_one(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);
        $payload = $this->payload();
        $payload['materials'][0]['quantity'] = 0;

        $this->postJson('/api/v1/listings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('materials.0.quantity');
    }

    public function test_unit_price_cannot_exceed_one_lira(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);
        $payload = $this->payload();
        $payload['materials'][0]['unit_price'] = 1.01;

        $this->postJson('/api/v1/listings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('materials.0.unit_price');
    }

    public function test_packaging_condition_confirmation_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);
        $payload = $this->payload();
        unset($payload['packaging_condition_confirmed']);

        $this->postJson('/api/v1/listings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('packaging_condition_confirmed');
    }

    public function test_public_feed_uses_approximate_location_and_radius(): void
    {
        $seller = User::factory()->create();
        $near = $this->listing($seller, 40.991, 29.027);
        $this->listing($seller, 41.250, 29.400);

        $this->getJson('/api/v1/listings?latitude=40.9912&longitude=29.0275&radius=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id)
            ->assertJsonMissingPath('data.0.exact_address');
    }

    public function test_public_feed_filters_material_and_returns_real_pagination_totals(): void
    {
        $seller = User::factory()->create();
        $this->listing($seller, 40.991, 29.027);
        $this->listing($seller, 40.992, 29.028);
        $this->listing($seller, 40.993, 29.029);
        $glass = $this->listing($seller, 40.994, 29.030);
        $glass->materials()->update(['type' => 'glass']);

        $this->getJson('/api/v1/listings?latitude=40.9912&longitude=29.0275&radius=3&material=pet&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_public_feed_supports_quantity_and_price_sorting(): void
    {
        $seller = User::factory()->create();
        $small = $this->listing($seller, 40.991, 29.027);
        $small->materials()->update(['quantity' => 5, 'unit_price' => 0.20]);
        $large = $this->listing($seller, 40.992, 29.028);
        $large->materials()->update(['quantity' => 80, 'unit_price' => 0.90]);

        $baseQuery = '/api/v1/listings?latitude=40.9912&longitude=29.0275&radius=3&per_page=20';

        $this->getJson($baseQuery.'&sort=quantity_desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $large->id);

        $this->getJson($baseQuery.'&sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $small->id);
    }

    public function test_authenticated_feed_excludes_the_users_own_listings(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->listing($owner, 40.991, 29.027);
        $visible = $this->listing($other, 40.992, 29.028);
        Sanctum::actingAs($owner, ['mobile']);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_location_feed_never_crosses_the_selected_province_boundary(): void
    {
        $seller = User::factory()->create();
        $yalovaId = Province::query()->where('name', 'Yalova')->value('id');
        $istanbulId = Province::query()->where('name', 'İstanbul')->value('id');

        $yalova = $this->listing($seller, 40.655, 29.276);
        $yalova->update(['province_id' => $yalovaId]);
        $istanbul = $this->listing($seller, 40.656, 29.277);
        $istanbul->update(['province_id' => $istanbulId]);

        $this->getJson('/api/v1/listings?province=Yalova')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $yalova->id)
            ->assertJsonMissing(['id' => $istanbul->id]);
    }

    private function payload(): array
    {
        return [
            'materials' => [
                ['type' => 'pet', 'quantity' => 50, 'unit_price' => 0.70],
                ['type' => 'glass', 'quantity' => 20, 'unit_price' => 0.55],
            ],
            'description' => 'Ambalajlar temiz ve poşetlenmiş durumda.',
            'packaging_condition_confirmed' => true,
            'public_area' => 'Kadıköy, İstanbul',
            'latitude' => 40.9912345,
            'longitude' => 29.0274567,
            'exact_address' => 'Caferağa Mahallesi, bina 12',
        ];
    }

    private function listing(User $seller, float $latitude, float $longitude): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Test bölgesi',
            'approximate_latitude' => $latitude,
            'approximate_longitude' => $longitude,
            'description' => 'Test ilan açıklaması yeterince uzun.',
            'published_at' => now(),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => 0.50]);
        return $listing;
    }
}
