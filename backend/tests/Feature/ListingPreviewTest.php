<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Listing;
use App\Models\ModerationSanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function listing(): Listing
    {
        $seller = User::factory()->create(['status' => 'active', 'name' => 'PRIVATE-SELLER-NAME', 'email' => 'private-seller@example.com', 'phone' => '05559998877']);
        $district = District::where('province_id', 77)->firstOrFail();
        $listing = Listing::create([
            'user_id' => $seller->id, 'province_id' => 77, 'district_id' => $district->id,
            'status' => Listing::STATUS_ACTIVE, 'public_area' => 'PRIVATE-AREA-HOUSE-42',
            'approximate_latitude' => 40.1234567, 'approximate_longitude' => 29.7654321,
            'description' => 'PRIVATE-NOTE phone 05559998877 <script>alert(1)</script>',
            'published_at' => now()->subMinute(), 'expires_at' => now()->addDay(),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => 20, 'unit_price' => 0.50]);
        $listing->materials()->create(['type' => 'glass', 'quantity' => 30, 'unit_price' => 0.50]);
        $listing->privateLocation()->create(['address' => 'PRIVATE-EXACT-ADDRESS', 'latitude' => '40.0000001', 'longitude' => '29.0000001', 'delivery_notes' => 'PRIVATE-DELIVERY-NOTE']);
        $listing->photos()->create(['path' => 'PRIVATE-PHOTO.png', 'sort_order' => 0]);
        return $listing;
    }

    public function test_preview_is_public_and_contains_only_safe_summary_and_store_links(): void
    {
        config()->set('stores.app_store_available', true);
        config()->set('stores.google_play_available', true);
        $listing = $this->listing();
        $response = $this->get('/ilan/'.$listing->id)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
            ->assertSee('<title>50 Adet PET ve Cam İlanı | Döngü - Geri Dönüşüm Uygulaması</title>', false)
            ->assertSee('/images/site/social/ilan.png', false)
            ->assertSeeText('50 adet ambalaj')->assertSee('20 adet PET')->assertSee('30 adet Cam')
            ->assertSee('M9 2h6v4.5l2.1 3.1c.6.9.9 1.9.9 3V27', false)
            ->assertSee('M9 2h6v8.1c0 .9.3 1.7 1 2.3', false)
            ->assertSee('25,00 TL')->assertSee('Yalova')
            ->assertSee('dongu://home', false)
            ->assertDontSee('dongu://ilan/', false)
            ->assertSee('MOBİLDE DEVAM ET')
            ->assertSee('İlanı Döngü - Geri Dönüşüm Uygulamasında Görüntüle')
            ->assertSee('Güncel ilan durumunu incele ve güvenli iletişim için uygulamada devam et.')
            ->assertSee('Döngü - Geri Dönüşüm Uygulamasını Aç')
            ->assertSee('data-welcome-modal', false)
            ->assertSee('İlanı Uygulamada Görüntüle')
            ->assertSee('source=welcome_listing', false)
            ->assertDontSee('Yüklü değilse mağazaya yönlendirilir')
            ->assertDontSee('Döngü - Geri Dönüşüm uygulaması')
            ->assertDontSee('İlanı Mobil Uygulamada görüntüle')
            ->assertDontSee('İlanı Döngü’de görüntüle')
            ->assertDontSee('Döngü-Geri Dönüşüm uygulamasını aç')
            ->assertDontSee('Döngü-Geri Dönüşüm uygulamasını indir')
            ->assertSee('primary-action-logo', false)
            ->assertDontSee('preview-app-platforms', false)
            ->assertSee('site-header', false)->assertSee('site-footer', false)
            ->assertSee('Döngü mobil uygulaması')->assertSee('iPhone ve Android için')
            ->assertSee('manrope:400', false)
            ->assertSee('content="noindex, noarchive"', false)
            ->assertSee(config('stores.app_store_url'))
            ->assertSee(config('stores.google_play_url'))
            ->assertSee('og:title', false)->assertSee('og:description', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['PRIVATE-', 'private-seller@example.com', '05559998877', '40.1234567', '29.7654321', '40.0000001', '29.0000001', '<script>alert'] as $privateValue) {
            $response->assertDontSee($privateValue, false);
        }
    }

    public function test_listing_index_is_public_and_contains_safe_active_listing_cards(): void
    {
        $listing = $this->listing();

        $response = $this->get('/ilanlar')->assertOk()
            ->assertSee('<title>PET, Cam ve Alüminyum İlanları | Döngü - Geri Dönüşüm Uygulaması</title>', false)
            ->assertSee('/images/site/social/ilanlar.png', false)
            ->assertSee('Yakınındaki ilanları')
            ->assertSee('vision-subhero-grid', false)
            ->assertSee('vision-orbit-brandmark', false)
            ->assertSee('50 adet')
            ->assertSee('PET · Cam')
            ->assertSee('25,00 TL')
            ->assertSee(route('listing.preview', ['id' => $listing->id]), false)
            ->assertSee('content="index, follow, max-image-preview:large"', false)
            ->assertSee(route('listings.index'), false)
            ->assertSeeText('İlanlar');

        foreach (['PRIVATE-', 'private-seller@example.com', '05559998877', '40.1234567', '29.7654321'] as $privateValue) {
            $response->assertDontSee($privateValue, false);
        }
    }

    public function test_unavailable_listings_never_leak_old_content_in_body_or_metadata(): void
    {
        $listing = $this->listing();
        foreach ([['status' => 'cancelled'], ['status' => 'completed'], ['status' => 'reserved'],
            ['status' => 'active', 'expires_at' => now()->subSecond()],
            ['expires_at' => now()->addDay(), 'published_at' => null],
            ['published_at' => now()->addHour()]] as $changes) {
            $listing->update($changes);
            $this->get('/ilan/'.$listing->id)->assertNotFound()->assertSee('Bu ilan artık')
                ->assertDontSee('50 ambalaj')->assertDontSee('25,00 TL')->assertDontSee('dongu://ilan/', false)->assertDontSee('PRIVATE-', false);
        }
        $listing->delete();
        $this->get('/ilan/'.$listing->id)->assertNotFound()->assertSee('Bu ilan artık');
        $this->get('/ilan/99999999')->assertNotFound()->assertSee('Bu ilan artık');
        $this->get('/ilan')->assertNotFound(); // No public marketplace/listing directory.
    }

    public function test_inactive_or_suspended_seller_has_no_public_preview(): void
    {
        $listing = $this->listing();
        $listing->seller->update(['status' => 'closed']);
        $this->get('/ilan/'.$listing->id)->assertNotFound()->assertDontSee('50 ambalaj');
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $listing->seller->update(['status' => 'active']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        ModerationSanction::create(['user_id' => $listing->user_id, 'action' => ModerationSanction::ACCOUNT_24H,
            'reason' => 'Test', 'starts_at' => now(), 'ends_at' => now()->addDay(), 'applied_by_admin_id' => $admin->id]);
        $this->get('/ilan/'.$listing->id)->assertNotFound()->assertDontSee('50 ambalaj');
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_preview_escapes_structured_region_text(): void
    {
        $listing = $this->listing();
        District::findOrFail($listing->district_id)->update(['name' => '<script>alert(1)</script>']);
        $this->get('/ilan/'.$listing->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_preview_shows_three_safe_related_listings_without_seller_or_description(): void
    {
        $listing = $this->listing();
        $relatedSeller = User::factory()->create([
            'status' => 'active',
            'name' => 'PRIVATE-RELATED-SELLER',
            'email' => 'related-private@example.com',
            'phone' => '05551112233',
        ]);

        $related = collect(range(1, 4))->map(function (int $index) use ($listing, $relatedSeller) {
            $candidate = Listing::create([
                'user_id' => $relatedSeller->id,
                'province_id' => $listing->province_id,
                'district_id' => $listing->district_id,
                'status' => Listing::STATUS_ACTIVE,
                'public_area' => 'PRIVATE-RELATED-AREA-'.$index,
                'approximate_latitude' => 40.1234567,
                'approximate_longitude' => 29.7654321,
                'description' => 'PRIVATE-RELATED-DESCRIPTION-'.$index,
                'published_at' => now()->subMinutes($index),
                'expires_at' => now()->addDay(),
            ]);
            $candidate->materials()->create([
                'type' => 'aluminum',
                'quantity' => 10 + $index,
                'unit_price' => 1,
            ]);

            return $candidate;
        });

        $response = $this->get('/ilan/'.$listing->id)->assertOk()
            ->assertSee('Dikkatini çekebilecek diğer ilanlar')
            ->assertDontSee('PRIVATE-RELATED-', false)
            ->assertDontSee('related-private@example.com', false)
            ->assertDontSee('05551112233', false);

        $this->assertSame(3, substr_count($response->getContent(), 'class="related-listing-card"'));
        $response->assertSee('related-material-stack', false)
            ->assertSee('related-breakdown', false)
            ->assertSee('related-location', false)
            ->assertSee('Toplam satış fiyatı')
            ->assertSee('M7 5.5v21c0 1.4', false);
        foreach ($related->take(3) as $candidate) {
            $response->assertSee(route('listing.preview', ['id' => $candidate->id]), false);
        }
        $response->assertDontSee(route('listing.preview', ['id' => $related->last()->id]), false);
    }

    public function test_expired_or_future_listing_cannot_open_through_mobile_detail_api(): void
    {
        $listing = $this->listing();
        $listing->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $listing->update(['expires_at' => now()->addDay(), 'published_at' => now()->addHour()]);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }
}
