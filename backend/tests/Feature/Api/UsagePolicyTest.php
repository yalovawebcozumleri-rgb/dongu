<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\MarketplaceUsageEvent;
use App\Models\MarketplaceUsagePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_current_admin_managed_limits(): void
    {
        $user = User::factory()->create(['status' => 'active', 'created_at' => now()]);
        Sanctum::actingAs($user, ['mobile']);

        MarketplaceUsagePolicy::current()->update([
            'new_account_pickup_limit' => 1,
            'new_account_contact_limit' => 3,
            'messages_per_24h' => 88,
        ]);

        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.isNewAccount', true)
            ->assertJsonPath('data.pickups.limit', 1)
            ->assertJsonPath('data.contacts.limit', 3)
            ->assertJsonPath('data.messages.limit', 88);

        MarketplaceUsagePolicy::current()->update(['new_account_pickup_limit' => 2]);

        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.pickups.limit', 2);
    }

    public function test_listing_interaction_eligibility_explains_exhausted_rights_and_retry_time(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active', 'created_at' => now()]);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_ACTIVE,
            'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.655,
            'approximate_longitude' => 29.276,
            'description' => 'Etkileşim uygunluğu testi için ilan.',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 10, 'unit_price' => 0.8]);
        MarketplaceUsagePolicy::current()->update(['new_account_contact_limit' => 1]);
        MarketplaceUsageEvent::create([
            'user_id' => $buyer->id,
            'event_type' => MarketplaceUsageEvent::CONTACT_STARTED,
            'target_user_id' => User::factory()->create()->id,
            'created_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($buyer, ['mobile']);

        $this->getJson("/api/v1/listings/{$listing->id}/interaction-eligibility")
            ->assertOk()
            ->assertJsonPath('data.message.allowed', false)
            ->assertJsonPath('data.pickup.allowed', false)
            ->assertJsonPath('data.message.reason', 'Yeni hesap dönemindeki toplam görüşme hakkın doldu.')
            ->assertJsonPath('data.account.isNewAccount', true)
            ->assertJson(fn ($json) => $json->whereType('data.message.retryAt', 'string')->etc());
    }
    public function test_admin_managed_new_account_period_is_applied_immediately(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'created_at' => now()->subHours(10),
        ]);
        Sanctum::actingAs($user, ['mobile']);

        MarketplaceUsagePolicy::current()->update([
            'new_account_hours' => 12,
            'new_account_pickup_limit' => 2,
            'pickup_24h_limit' => 9,
        ]);

        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.isNewAccount', true)
            ->assertJsonPath('data.pickups.limit', 2)
            ->assertJson(fn ($json) => $json->whereType('data.newAccountEndsAt', 'string')->etc());

        MarketplaceUsagePolicy::current()->update(['new_account_hours' => 6]);

        $this->getJson('/api/v1/usage-policy')
            ->assertOk()
            ->assertJsonPath('data.isNewAccount', false)
            ->assertJsonPath('data.newAccountEndsAt', null)
            ->assertJsonPath('data.pickups.limit', 9);
    }
    public function test_usage_policy_is_private(): void
    {
        $this->getJson('/api/v1/usage-policy')->assertUnauthorized();
    }

    public function test_admin_managed_listing_limit_is_enforced_immediately(): void
    {
        $user = User::factory()->create(['status' => 'active', 'created_at' => now()]);
        Sanctum::actingAs($user, ['mobile']);
        MarketplaceUsagePolicy::current()->update(['new_account_listing_limit' => 1]);

        $payload = [
            'materials' => [['type' => 'pet', 'quantity' => 20, 'unit_price' => 0.8]],
            'description' => 'Teslim alınmaya hazır temiz PET şişeler.',
            'packaging_condition_confirmed' => true,
            'public_area' => 'Yalova Merkez',
            'latitude' => 40.655,
            'longitude' => 29.276,
            'exact_address' => 'Rüstempaşa Mahallesi, bina 12',
        ];

        $this->postJson('/api/v1/listings', $payload)->assertCreated();
        $this->postJson('/api/v1/listings', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Yeni hesap dönemindeki ilan oluşturma hakkın doldu.');
    }
}
