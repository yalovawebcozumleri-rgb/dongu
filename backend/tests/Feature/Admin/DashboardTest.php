<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_open_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))
            ->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_professional_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('health')
                ->has('users')
                ->has('listingMetrics')
                ->has('transactions')
                ->has('announcements.days', 3)
                ->has('downloadClicks')
                ->has('moderation', 4)
                ->has('listings')
            );
    }

    public function test_dashboard_total_includes_deleted_accounts_but_growth_and_marketplace_data_remain_clean(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $seller = User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'deleted']);

        $completed = $this->listing($seller, Listing::STATUS_COMPLETED);
        $completed->materials()->create(['type' => 'pet', 'quantity' => 100, 'unit_price' => 0.50]);
        $active = $this->listing($seller, Listing::STATUS_ACTIVE);
        $active->materials()->create(['type' => 'glass', 'quantity' => 50, 'unit_price' => 0.75]);
        $removed = $this->listing($seller, Listing::STATUS_COMPLETED);
        $removed->materials()->create(['type' => 'aluminum', 'quantity' => 999999, 'unit_price' => 1.00]);
        $removed->delete();

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 2)
                ->where('users.active', 1)
                ->where('users.deleted', 1)
                ->where('users.today', 1)
                ->where('listingMetrics.total', 2)
                ->where('listingMetrics.completed', 1)
                ->where('listingMetrics.materials', 150)
            );
    }

    private function listing(User $seller, string $status): Listing
    {
        return Listing::create([
            'user_id' => $seller->id,
            'status' => $status,
            'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.65,
            'approximate_longitude' => 29.27,
            'description' => 'Yönetim paneli özet testi için ilan.',
            'published_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }
}
