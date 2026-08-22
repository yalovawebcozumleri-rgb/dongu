<?php

namespace Tests\Feature;

use App\Models\AppDownloadClickDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDownloadClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('stores.google_play_available', true);
        config()->set('stores.google_play_url', 'https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu');
    }

    public function test_clicks_are_aggregated_by_platform_and_source(): void
    {
        $headers = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 16) Mobile Safari/537.36'];
        $this->withHeaders($headers)->get('/indir?source=facebook-group');
        $this->withHeaders($headers)->get('/indir?source=facebook-group');

        $this->assertDatabaseCount('app_download_click_daily', 1);
        $this->assertDatabaseHas('app_download_click_daily', [
            'platform' => 'android', 'destination' => 'google_play', 'source' => 'facebook_group', 'clicks' => 2,
        ]);
    }

    public function test_link_preview_bots_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/indir');
        $this->assertSame(0, AppDownloadClickDaily::count());
    }
}
