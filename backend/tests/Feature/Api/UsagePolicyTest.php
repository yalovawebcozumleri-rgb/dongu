<?php

namespace Tests\Feature\Api;

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
            'materials' => [['type' => 'pet', 'quantity' => 10, 'unit_price' => 0.8]],
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
            ->assertJsonPath('message', 'Son 24 saatteki ilan oluşturma hakkın doldu.');
    }
}
