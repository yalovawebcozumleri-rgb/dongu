<?php

namespace Tests\Feature\Api;

use App\Models\Advertisement;
use App\Models\AdvertisementPlacementSetting;
use App\Models\AdMobRuntimeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertisementTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $placements, bool $active = true): Advertisement
    {
        $advertisement = Advertisement::create(['placement' => $placements[0], 'sponsor_name' => 'Döngü İş Ortağı', 'headline' => 'Dönüşüme destek ol', 'body' => 'Kontrollü sponsorlu içerik.', 'is_active' => $active]);
        $advertisement->placements()->createMany(array_map(fn ($placement) => ['placement' => $placement], $placements));
        return $advertisement;
    }

    public function test_admob_api_never_returns_sponsor_banners_and_keeps_placement_policy(): void
    {
        $this->campaign([Advertisement::PLACEMENT_HOME_FEED, Advertisement::PLACEMENT_LISTING_DETAIL]);

        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.sourceOrder', ['admob'])
            ->assertJsonPath('meta.firstAfter', 3)
            ->assertJsonPath('meta.repeatEvery', 8)
            ->assertJsonPath('meta.maxPerSession', 5);
    }

    public function test_native_api_applies_panel_value_with_hard_safety_cap(): void
    {
        AdvertisementPlacementSetting::forKey('home_feed')->update(['max_per_session' => 4]);
        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonPath('meta.maxPerSession', 4);

        AdvertisementPlacementSetting::forKey('home_feed')->update(['max_per_session' => 20]);
        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonPath('meta.maxPerSession', 5);

        AdvertisementPlacementSetting::forKey('listing_detail')->update(['max_per_session' => 5]);
        $this->getJson('/api/v1/advertisements?placement=listing_detail')
            ->assertOk()
            ->assertJsonPath('meta.maxPerSession', 1);
    }

    public function test_native_api_never_exposes_house_fallback_source(): void
    {
        AdvertisementPlacementSetting::forKey('home_feed')->update([
            'source_order' => ['direct', 'admob', 'house'],
        ]);

        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonPath('meta.sourceOrder', ['admob']);
    }

    public function test_test_mode_exposes_official_google_native_demo_units(): void
    {
        AdMobRuntimeSetting::current()->update(['android_mode' => 'test', 'ios_mode' => 'test']);

        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonPath('meta.adMobAndroidUnitId', 'ca-app-pub-3940256099942544/2247696110')
            ->assertJsonPath('meta.adMobIosUnitId', 'ca-app-pub-3940256099942544/3986624511')
            ->assertJsonPath('meta.adMobAndroidEnvironment', 'test')
            ->assertJsonPath('meta.adMobIosEnvironment', 'test');
    }

    public function test_production_mode_exposes_placement_native_unit(): void
    {
        AdMobRuntimeSetting::current()->update(['android_mode' => 'production']);
        AdvertisementPlacementSetting::forKey('home_feed')->update([
            'admob_android_unit_id' => 'ca-app-pub-6681150378641816/4910102351',
        ]);

        $this->getJson('/api/v1/advertisements?placement=home_feed')
            ->assertOk()
            ->assertJsonPath('meta.adMobAndroidUnitId', 'ca-app-pub-6681150378641816/4910102351');
    }

    public function test_rewarded_unit_uses_official_google_demo_unit_in_test_mode(): void
    {
        AdMobRuntimeSetting::current()->update(['android_mode' => 'test', 'ios_mode' => 'test']);
        $setting = AdvertisementPlacementSetting::forKey('home_feed');
        $setting->update([
            'admob_android_unit_id' => 'ca-app-pub-6681150378641816/1142247732',
        ]);

        $this->assertSame(
            'ca-app-pub-3940256099942544/5224354917',
            $setting->adMobUnitId('android', 'rewarded'),
        );
    }

    public function test_impression_and_click_are_unique_per_session_placement_and_slot(): void
    {
        $advertisement = $this->campaign([Advertisement::PLACEMENT_HOME_FEED, Advertisement::PLACEMENT_LISTING_DETAIL]);
        $payload = ['sessionKey' => 'mobile-session-1234567890', 'placement' => 'home_feed', 'slotIndex' => 1];
        $this->postJson("/api/v1/advertisements/{$advertisement->id}/impressions", $payload)->assertOk();
        $this->postJson("/api/v1/advertisements/{$advertisement->id}/impressions", $payload)->assertOk();
        $this->postJson("/api/v1/advertisements/{$advertisement->id}/clicks", $payload)->assertOk();
        $this->postJson("/api/v1/advertisements/{$advertisement->id}/clicks", $payload)->assertOk();
        $this->assertDatabaseCount('advertisement_impressions', 1);
        $this->assertDatabaseHas('advertisement_impressions', ['advertisement_id' => $advertisement->id, 'placement' => 'home_feed']);
        $this->assertNotNull($advertisement->impressions()->firstOrFail()->clicked_at);
    }

    public function test_event_is_rejected_for_unselected_placement(): void
    {
        $advertisement = $this->campaign([Advertisement::PLACEMENT_HOME_FEED]);
        $this->postJson("/api/v1/advertisements/{$advertisement->id}/impressions", ['sessionKey' => 'mobile-session-1234567890', 'placement' => 'profile', 'slotIndex' => 1])->assertUnprocessable();
    }
}