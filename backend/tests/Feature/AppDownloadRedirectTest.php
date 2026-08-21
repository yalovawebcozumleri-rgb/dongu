<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppDownloadRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stores.app_store_available', true);
        config()->set('stores.app_store_url', 'https://apps.apple.com/tr/app/id6800822946');
        config()->set('stores.google_play_available', true);
        config()->set('stores.google_play_url', 'https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu');
    }

    public function test_android_devices_are_sent_directly_to_google_play(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Linux; Android 16; Pixel 9) AppleWebKit/537.36 Mobile Safari/537.36'
        )->get('/indir');

        $response
            ->assertRedirect(config('stores.google_play_url'))
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Vary', 'User-Agent, Sec-CH-UA-Platform');
    }

    public function test_iphones_are_sent_directly_to_the_app_store(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'
        )->get('/indir');

        $response->assertRedirect(config('stores.app_store_url'));
    }

    public function test_ipads_using_a_desktop_style_user_agent_are_sent_to_the_app_store(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'
        )->get('/indir');

        $response->assertRedirect(config('stores.app_store_url'));
    }

    public function test_client_platform_header_can_identify_android_in_an_in_app_browser(): void
    {
        $response = $this
            ->withHeader('User-Agent', 'FacebookExternalHit/1.1')
            ->withHeader('Sec-CH-UA-Platform', '"Android"')
            ->get('/indir');

        $response->assertRedirect(config('stores.google_play_url'));
    }

    public function test_desktop_and_unknown_devices_see_the_existing_store_choice_page(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0 Safari/537.36'
        )->get('/indir');

        $response->assertRedirect(route('marketing.mobile-app'));
    }

    public function test_missing_store_url_falls_back_to_the_existing_store_choice_page(): void
    {
        config()->set('stores.app_store_url', '');

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; Mobile)')->get('/indir');

        $response->assertRedirect(route('marketing.mobile-app', ['platform' => 'ios']));
    }

    public function test_iphone_visitors_see_the_mobile_page_while_the_app_store_release_is_unavailable(): void
    {
        config()->set('stores.app_store_available', false);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; Mobile)')->get('/indir');

        $response->assertRedirect(route('marketing.mobile-app', ['platform' => 'ios']));
    }

    public function test_mobile_page_keeps_google_play_live_and_marks_the_app_store_as_coming_soon(): void
    {
        config()->set('stores.app_store_available', false);

        $response = $this->get(route('marketing.mobile-app', ['platform' => 'ios']));

        $response
            ->assertOk()
            ->assertSee('Döngü iOS sürümü çok yakında.')
            ->assertSee(config('stores.google_play_url'))
            ->assertSee('<b class="vision-orbit-chip chip-two vision-orbit-chip-unavailable">App Store</b>', false)
            ->assertDontSee('href="'.config('stores.app_store_url').'"', false);
    }
}
