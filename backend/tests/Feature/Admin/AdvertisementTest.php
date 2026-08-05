<?php

namespace Tests\Feature\Admin;

use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_multi_placement_campaign_pause_and_delete_it(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post('/admin/advertisements', [
            'sponsorName' => 'Yerel sponsor', 'headline' => 'Dönüşüme destek ol', 'body' => 'Kontrollü kampanya metni.',
            'ctaLabel' => 'İncele', 'targetUrl' => 'https://example.com/kampanya', 'backgroundColor' => '#E8F4E9',
            'startsAt' => now()->format('Y-m-d H:i:s'), 'endsAt' => now()->addDay()->format('Y-m-d H:i:s'),
            'priority' => 20, 'isActive' => true, 'format' => 'native', 'placements' => ['home_feed', 'leaderboard', 'listing_detail'],
        ])->assertRedirect();

        $advertisement = Advertisement::firstOrFail();
        $this->assertEqualsCanonicalizing(['home_feed', 'leaderboard', 'listing_detail'], $advertisement->placements()->pluck('placement')->all());
        $this->actingAs($admin)->patch("/admin/advertisements/{$advertisement->id}", ['isActive' => false])->assertRedirect();
        $this->assertFalse($advertisement->fresh()->is_active);
        $this->actingAs($admin)->delete("/admin/advertisements/{$advertisement->id}")->assertRedirect();
        $this->assertDatabaseCount('advertisements', 0); $this->assertDatabaseCount('advertisement_placements', 0);
    }

    public function test_admin_can_upload_serve_and_delete_image_advertisement(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post('/admin/advertisements', [
            'sponsorName' => 'Görselli sponsor', 'headline' => 'Görselli kampanya', 'body' => 'Görsel reklam açıklaması.',
            'format' => 'image', 'image' => UploadedFile::fake()->image('sponsor.jpg', 1200, 630),
            'backgroundColor' => '#E8F4E9', 'priority' => 10, 'isActive' => true, 'placements' => ['listing_detail'],
        ])->assertRedirect();

        $advertisement = Advertisement::firstOrFail();
        Storage::disk('public')->assertExists($advertisement->image_path);
        $this->get("/api/v1/advertisements/{$advertisement->id}/image")->assertOk();
        $this->actingAs($admin)->delete("/admin/advertisements/{$advertisement->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($advertisement->image_path);
    }

    public function test_admin_can_view_aggregated_campaign_statistics(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $advertisement = Advertisement::create(['placement' => 'home_feed', 'sponsor_name' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama', 'is_active' => true]);
        $advertisement->placements()->create(['placement' => 'home_feed']);
        $advertisement->impressions()->create(['placement' => 'home_feed', 'session_key' => 'mobile-session-1234567890', 'slot_index' => 1, 'viewed_at' => now(), 'clicked_at' => now()]);

        $this->actingAs($admin)->get('/admin/advertisements')->assertOk();
    }

    public function test_admin_index_separates_admob_and_exposes_only_current_mobile_placements(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/advertisements?per_page=25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Advertisements/Index')
                ->has('placementOptions', 3)
                ->where('placementOptions.0.value', 'home_feed')
                ->where('placementOptions.1.value', 'leaderboard')
                ->where('placementOptions.2.value', 'listing_detail')
                ->where('adMob.mode', 'test')
                ->where('adMob.earnsRevenue', false)
                ->where('adMob.coveredPlacements', [])
                ->where('campaigns.per_page', 25));
    }

    public function test_removed_mobile_placement_cannot_be_added_to_a_campaign(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->from('/admin/advertisements')->post('/admin/advertisements', [
            'sponsorName' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama',
            'backgroundColor' => '#E8F4E9', 'priority' => 0, 'isActive' => false,
            'format' => 'native', 'placements' => ['profile'],
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('placements.0');
    }

    public function test_admin_must_select_at_least_one_valid_placement(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->from('/admin/advertisements')->post('/admin/advertisements', [
            'sponsorName' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama', 'backgroundColor' => '#E8F4E9',
            'priority' => 0, 'isActive' => false, 'format' => 'native', 'placements' => [],
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('placements');
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $this->get('/admin/advertisements')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/advertisements')->assertForbidden();
    }
}