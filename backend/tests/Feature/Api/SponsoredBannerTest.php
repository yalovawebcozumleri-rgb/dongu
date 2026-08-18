<?php

namespace Tests\Feature\Api;

use App\Models\Advertisement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SponsoredBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }
    private function banner(string $name, array $placements = ['home_feed'], bool $android = true, bool $ios = true, int $priority = 0): Advertisement
    {
        $advertisement = Advertisement::create([
            'placement' => $placements[0],
            'format' => Advertisement::FORMAT_BANNER,
            'sponsor_name' => $name,
            'headline' => "{$name} kampanyası",
            'body' => 'Sponsorlu banner açıklaması.',
            'target_url' => 'https://example.com',
            'image_path' => 'advertisements/banner.png',
            'android_enabled' => $android,
            'ios_enabled' => $ios,
            'is_active' => true,
            'priority' => $priority,
        ]);
        $advertisement->placements()->createMany(array_map(fn (string $placement) => ['placement' => $placement], $placements));

        return $advertisement;
    }

    public function test_empty_or_ineligible_campaign_returns_null_without_placeholder_contract(): void
    {
        $this->banner('Yalnız Android', ['home_feed'], android: true, ios: false);

        $this->getJson('/api/v1/sponsored-banners?placement=home_feed&platform=ios&sessionKey=sponsor-session-1234567890')
            ->assertOk()
            ->assertExactJson(['data' => null]);
        $this->getJson('/api/v1/sponsored-banners?placement=favorites&platform=android&sessionKey=sponsor-session-1234567890')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_banner_is_filtered_by_placement_and_platform(): void
    {
        $banner = $this->banner('Doğru Sponsor', ['home_feed', 'listing_detail'], android: true, ios: false);

        $this->getJson('/api/v1/sponsored-banners?placement=home_feed&platform=android&sessionKey=sponsor-session-1234567890')
            ->assertOk()
            ->assertJsonPath('data.id', $banner->id)
            ->assertJsonPath('data.imageUrl', fn (string $value) => str_contains($value, "/api/v1/advertisements/{$banner->id}/image"));
    }

    public function test_same_session_is_stable_and_new_sessions_rotate_equal_priority_campaigns(): void
    {
        $first = $this->banner('A');
        $second = $this->banner('B');
        $path = '/api/v1/sponsored-banners?placement=home_feed&platform=android&sessionKey=';
        $stableKey = 'sponsor-stable-session-123456';
        $stableId = $this->getJson($path.$stableKey)->assertOk()->json('data.id');
        $this->assertSame($stableId, $this->getJson($path.$stableKey)->assertOk()->json('data.id'));

        $seen = [];
        foreach (range(1, 40) as $index) {
            $seen[] = $this->getJson($path.sprintf('sponsor-rotation-session-%04d', $index))->assertOk()->json('data.id');
        }

        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_values(array_unique($seen)));
    }

    public function test_impression_and_click_are_deduplicated_per_banner_session_and_placement(): void
    {
        $banner = $this->banner('Ölçülen Sponsor');
        $payload = ['sessionKey' => 'sponsor-session-1234567890', 'placement' => 'home_feed', 'platform' => 'android'];

        $this->postJson("/api/v1/sponsored-banners/{$banner->id}/impressions", $payload)->assertOk();
        $this->postJson("/api/v1/sponsored-banners/{$banner->id}/impressions", $payload)->assertOk();
        $this->postJson("/api/v1/sponsored-banners/{$banner->id}/clicks", $payload)->assertOk();
        $this->postJson("/api/v1/sponsored-banners/{$banner->id}/clicks", $payload)->assertOk();

        $this->assertDatabaseCount('advertisement_impressions', 1);
        $this->assertNotNull($banner->impressions()->firstOrFail()->clicked_at);
    }
}