<?php

namespace Tests\Feature\Admin;

use App\Models\AdminListingAction;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_inspect_completed_listing_with_parties(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $seller = User::factory()->create(['name' => 'Yalova Satıcı']);
        $buyer = User::factory()->create(['name' => 'Yalova Alıcı']);
        $listing = $this->listing($seller, Listing::STATUS_COMPLETED);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 50, 'unit_price' => 0.75]);
        PickupRequest::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => PickupRequest::COMPLETED, 'completed_at' => now()]);

        $this->actingAs($admin)->get('/admin/listings?status=completed&region=Yalova&material=pet&per_page=50')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Listings/Index')
                ->where('filters.status', 'completed')
                ->where('filters.per_page', 50)
                ->has('listings.data', 1)
                ->where('listings.data.0.id', $listing->id)
                ->where('listings.data.0.seller.id', $seller->id)
                ->where('listings.data.0.buyer.id', $buyer->id));

        $this->get("/admin/listings/{$listing->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Listings/Show')
                ->where('listing.id', $listing->id)
                ->has('listing.requests', 1));
    }

    public function test_admin_removal_is_soft_deleted_and_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $listing = $this->listing(User::factory()->create(), Listing::STATUS_ACTIVE);

        $this->actingAs($admin)->delete("/admin/listings/{$listing->id}", ['reason' => 'Yanıltıcı içerik yönetici tarafından doğrulandı.'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('admin_listing_actions', ['listing_id' => $listing->id, 'admin_id' => $admin->id, 'action' => 'removed']);
        $this->assertSame('removed', AdminListingAction::firstOrFail()->action);
    }

    public function test_listing_with_open_reservation_cannot_be_removed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $seller = User::factory()->create();
        $listing = $this->listing($seller, Listing::STATUS_RESERVED);
        PickupRequest::create(['listing_id' => $listing->id, 'buyer_id' => User::factory()->create()->id, 'seller_id' => $seller->id, 'status' => PickupRequest::ACCEPTED]);

        $this->actingAs($admin)->delete("/admin/listings/{$listing->id}", ['reason' => 'Test amacıyla kaldırılmak istendi.'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'deleted_at' => null]);
    }

    private function listing(User $seller, string $status): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'status' => $status, 'public_area' => 'Yalova Merkez',
            'approximate_latitude' => 40.65, 'approximate_longitude' => 29.27,
            'description' => 'Yönetim paneli test ilanı', 'published_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
    }
}
