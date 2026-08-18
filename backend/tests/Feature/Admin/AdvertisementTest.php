<?php

namespace Tests\Feature\Admin;

use App\Models\Advertisement;
use App\Models\AdvertisementPlacementSetting;
use App\Models\User;
use App\Services\RewardedUsageGrantService;
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
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post('/admin/advertisements', [
            'sponsorName' => 'Yerel sponsor', 'headline' => 'Dönüşüme destek ol', 'body' => 'Kontrollü kampanya metni.',
            'ctaLabel' => 'İncele', 'targetUrl' => 'https://example.com/kampanya',
            'startsAt' => now()->format('Y-m-d H:i:s'), 'endsAt' => now()->addDay()->format('Y-m-d H:i:s'),
            'priority' => 20, 'isActive' => true, 'format' => 'banner', 'image' => UploadedFile::fake()->image('sponsor.png', 1200, 600),
            'androidEnabled' => true, 'iosEnabled' => true, 'placements' => ['home_feed', 'leaderboard', 'listing_detail'],
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
            'format' => 'banner', 'image' => UploadedFile::fake()->image('sponsor.jpg', 1200, 600),
            'priority' => 10, 'isActive' => true, 'androidEnabled' => true, 'iosEnabled' => true, 'placements' => ['listing_detail'],
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
        $advertisement = Advertisement::create(['placement' => 'home_feed', 'format' => 'banner', 'sponsor_name' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama', 'image_path' => 'advertisements/banner.png', 'is_active' => true]);
        $advertisement->placements()->create(['placement' => 'home_feed']);
        $advertisement->impressions()->create(['placement' => 'home_feed', 'session_key' => 'mobile-session-1234567890', 'slot_index' => 1, 'viewed_at' => now(), 'clicked_at' => now()]);

        $this->actingAs($admin)->get('/admin/advertisements/sponsors')->assertOk();
    }

    public function test_admin_advertising_pages_are_separated_and_old_url_redirects_to_admob(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/advertisements')
            ->assertRedirect('/admin/advertisements/admob');

        $this->actingAs($admin)->get('/admin/advertisements/sponsors?per_page=25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Advertisements/Index')
                ->has('placementOptions', 13)
                ->where('placementOptions.0.hint', 'Konum kartından sonra, ilan listesinden önce.')
                ->where('placementOptions.2.hint', 'Ambalaj ve fiyat bölümünden sonra, teslimat bilgilerinden önce.')
                ->where('placementOptions.3.hint', 'Sayfa özetinden sonra, favori listesinden önce.')
                ->where('campaigns.per_page', 25));

        $this->actingAs($admin)->get('/admin/advertisements/admob')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Advertisements/AdMob')
                ->has('placementSettings', 16)
                ->where('adMob.runtime.androidMode', 'test')
                ->where('adMob.runtime.iosMode', 'test')
                ->where('adMob.runtime.configurationVersion', 1));
    }

    public function test_admin_index_tracks_android_and_ios_runtime_changes_independently(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Reklam Yöneticisi']);

        $this->actingAs($admin)->patch('/admin/advertising-runtime', [
            'androidMode' => 'production',
            'iosMode' => 'test',
            'confirmProduction' => true,
        ])->assertRedirect();

        $this->actingAs($admin)->get('/admin/advertisements/admob')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Advertisements/AdMob')
                ->where('adMob.runtime.platforms.android.mode', 'production')
                ->where('adMob.runtime.platforms.android.updatedBy', 'Reklam Yöneticisi')
                ->where('adMob.runtime.platforms.android.configurationVersion', 2)
                ->where('adMob.runtime.platforms.android.updatedAt', fn (string $value) => str_ends_with($value, '+00:00'))
                ->where('adMob.runtime.platforms.ios.mode', 'test')
                ->where('adMob.runtime.platforms.ios.updatedBy', 'Sistem')
                ->where('adMob.runtime.platforms.ios.configurationVersion', 1));
    }
    public function test_native_placement_is_always_saved_as_admob_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $setting = AdvertisementPlacementSetting::forKey('home_feed');

        $this->actingAs($admin)->from('/admin/advertisements')->patch("/admin/advertisement-placements/{$setting->id}", [
            'enabled' => true,
            'androidEnabled' => true,
            'iosEnabled' => true,
            'sourceOrder' => ['direct', 'admob', 'house'],
            'firstAfter' => 3,
            'repeatEvery' => 8,
            'maxPerSession' => 5,
            'minItems' => 3,
            'adMobAndroidUnitId' => 'ca-app-pub-6681150378641816/4910102351',
            'adMobIosUnitId' => null,
            'boostHours' => 24,
            'dailyLimit' => 3,
            'ordinals' => [2, 4],
        ])->assertRedirect('/admin/advertisements')->assertSessionDoesntHaveErrors();

        $this->assertSame(['admob'], $setting->fresh()->source_order);
    }

    public function test_admin_can_disable_native_placement_and_both_platforms(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $setting = AdvertisementPlacementSetting::forKey('home_feed');

        $this->actingAs($admin)->patch("/admin/advertisement-placements/{$setting->id}", [
            'enabled' => false,
            'androidEnabled' => false,
            'iosEnabled' => false,
            'firstAfter' => 3,
            'repeatEvery' => 8,
            'maxPerSession' => 5,
            'minItems' => 3,
            'adMobAndroidUnitId' => 'ca-app-pub-6681150378641816/4910102351',
            'adMobIosUnitId' => 'ca-app-pub-6681150378641816/7166691451',
            'boostHours' => 24,
            'dailyLimit' => 3,
            'ordinals' => [2, 4],
        ])->assertRedirect();

        $fresh = $setting->fresh();
        $this->assertFalse($fresh->enabled);
        $this->assertFalse($fresh->android_enabled);
        $this->assertFalse($fresh->ios_enabled);
    }
    public function test_native_placement_rejects_more_than_five_ads(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $setting = AdvertisementPlacementSetting::forKey('home_feed');

        $this->actingAs($admin)->from('/admin/advertisements')->patch("/admin/advertisement-placements/{$setting->id}", [
            'enabled' => true,
            'androidEnabled' => true,
            'iosEnabled' => true,
            'sourceOrder' => ['direct', 'admob'],
            'firstAfter' => 3,
            'repeatEvery' => 5,
            'maxPerSession' => 6,
            'minItems' => 3,
            'adMobAndroidUnitId' => 'ca-app-pub-6681150378641816/4910102351',
            'adMobIosUnitId' => 'ca-app-pub-6681150378641816/7166691451',
            'boostHours' => 24,
            'dailyLimit' => 3,
            'ordinals' => [2, 4],
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('maxPerSession');
    }


    public function test_unknown_mobile_placement_cannot_be_added_to_a_campaign(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->from('/admin/advertisements')->post('/admin/advertisements', [
            'sponsorName' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama',
            'priority' => 0, 'isActive' => false, 'format' => 'banner',
            'image' => UploadedFile::fake()->image('sponsor.png', 1200, 600), 'androidEnabled' => true, 'iosEnabled' => true, 'placements' => ['chat_screen'],
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('placements.0');
    }

    public function test_admin_must_select_at_least_one_valid_placement(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->from('/admin/advertisements')->post('/admin/advertisements', [
            'sponsorName' => 'Sponsor', 'headline' => 'Başlık', 'body' => 'Açıklama',
            'priority' => 0, 'isActive' => false, 'format' => 'banner', 'image' => UploadedFile::fake()->image('sponsor.png', 1200, 600), 'androidEnabled' => true, 'iosEnabled' => true, 'placements' => [],
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('placements');
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $this->get('/admin/advertisements')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/advertisements')->assertForbidden();
    }
    public function test_admin_can_manage_every_rewarded_usage_right_from_one_setting(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $setting = AdvertisementPlacementSetting::forKey('rewarded_extra_rights');
        $usageRewards = collect(RewardedUsageGrantService::DEFINITIONS)->map(function (array $definition, string $key): array {
            return [
                'key' => $key,
                'enabled' => $key !== 'contact_cooldown',
                'amount' => $key === 'message_daily' ? 12 : 1,
                'dailyLimit' => 3,
                'validHours' => 24,
            ];
        })->values()->all();

        $this->actingAs($admin)->patch("/admin/advertisement-placements/{$setting->id}", [
            'enabled' => true,
            'androidEnabled' => true,
            'iosEnabled' => true,
            'sourceOrder' => ['admob'],
            'firstAfter' => 0,
            'repeatEvery' => 0,
            'maxPerSession' => 20,
            'minItems' => 0,
            'adMobAndroidUnitId' => 'ca-app-pub-6681150378641816/6596149732',
            'adMobIosUnitId' => null,
            'boostHours' => 24,
            'dailyLimit' => 3,
            'ordinals' => [2, 4],
            'usageRewards' => $usageRewards,
        ])->assertRedirect();

        $fresh = $setting->fresh();
        $this->assertCount(count(RewardedUsageGrantService::DEFINITIONS), $fresh->settings['rewards']);
        $this->assertSame(12, $fresh->settings['rewards']['message_daily']['amount']);
        $this->assertFalse($fresh->settings['rewards']['contact_cooldown']['enabled']);
        $this->assertSame('ek_hak', $fresh->settings['reward_item']);
    }
}
