<?php

namespace Tests\Feature\Admin;

use App\Models\MarketplaceUsagePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketplaceUsagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_usage_policy(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $policy = MarketplaceUsagePolicy::current();
        $payload = $policy->only($policy->getFillablePolicyFields());
        $payload['pickup_24h_limit'] = 4;
        $payload['contact_24h_limit'] = 7;

        $this->actingAs($admin)->get('/admin/usage-policies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/UsagePolicies/Edit')
                ->where('policy.pickup_24h_limit', 5));

        $this->actingAs($admin)->patch('/admin/usage-policies', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('marketplace_usage_policies', [
            'id' => 1,
            'pickup_24h_limit' => 4,
            'contact_24h_limit' => 7,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_policy_rejects_inconsistent_limits(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $policy = MarketplaceUsagePolicy::current();
        $payload = $policy->only($policy->getFillablePolicyFields());
        $payload['messages_per_minute'] = 50;
        $payload['messages_per_hour'] = 10;
        $payload['new_account_pickup_limit'] = 5;
        $payload['new_account_contact_limit'] = 2;

        $this->actingAs($admin)->from('/admin/usage-policies')->patch('/admin/usage-policies', $payload)
            ->assertRedirect('/admin/usage-policies')
            ->assertSessionHasErrors(['messages_per_minute', 'new_account_pickup_limit']);
    }

    public function test_regular_user_cannot_manage_usage_policy(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))
            ->get('/admin/usage-policies')
            ->assertForbidden();
    }
}
