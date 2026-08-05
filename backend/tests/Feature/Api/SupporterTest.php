<?php

namespace Tests\Feature\Api;

use App\Models\SupporterBusiness;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupporterTest extends TestCase
{
    use RefreshDatabase;

    private function business(array $attributes = []): SupporterBusiness
    {
        return SupporterBusiness::create(array_merge([
            'name' => 'Çınarcık Esnafı', 'slug' => 'cinarcik-esnafi', 'card_summary' => 'Yerel döngüyü destekliyor.',
            'detail_title' => 'Bölgenizde yanınızdayız', 'detail_body' => 'İşletmemiz hakkında kısa bilgi.',
            'target_scope' => 'district', 'province_code' => '77', 'province_name' => 'Yalova',
            'district_code' => '77-02', 'district_name' => 'Çınarcık', 'cta_type' => 'phone',
            'cta_label' => 'Hemen ara', 'cta_value' => '+90 555 000 00 00', 'is_active' => true,
        ], $attributes));
    }

    public function test_feed_matches_district_province_and_nationwide_supporters(): void
    {
        $district = $this->business();
        $province = $this->business(['name' => 'Yalova İşletmesi', 'slug' => 'yalova-isletmesi', 'target_scope' => 'province', 'district_code' => null, 'district_name' => null, 'cta_type' => 'whatsapp', 'cta_value' => '0541 334 22 19']);
        $nationwide = $this->business(['name' => 'Türkiye İşletmesi', 'slug' => 'turkiye-isletmesi', 'target_scope' => 'nationwide', 'province_code' => null, 'province_name' => null, 'district_code' => null, 'district_name' => null]);
        $this->business(['name' => 'Bursa İşletmesi', 'slug' => 'bursa-isletmesi', 'target_scope' => 'province', 'province_code' => '16', 'province_name' => 'Bursa', 'district_code' => null, 'district_name' => null]);

        $response = $this->getJson('/api/v1/supporters?province=Yalova&district=Çınarcık');
        $response->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $district->id)
            ->assertJsonPath('data.0.detailTitle', 'Bölgenizde yanınızdayız')
            ->assertJsonPath('data.0.cta.url', 'tel:+905550000000')
            ->assertJsonFragment(['id' => $province->id])->assertJsonFragment(['url' => 'https://wa.me/905413342219'])
            ->assertJsonFragment(['id' => $nationwide->id]);
    }

    public function test_events_are_idempotent_and_unique_reach_is_daily_per_visitor(): void
    {
        $business = $this->business();
        $base = ['visitorId' => 'device-visitor-1234567890', 'type' => 'impression'];
        $this->postJson("/api/v1/supporters/{$business->id}/events", $base + ['eventId' => 'event-impression-0001'])->assertOk()->assertJsonPath('data.recorded', true);
        $this->postJson("/api/v1/supporters/{$business->id}/events", $base + ['eventId' => 'event-impression-0001'])->assertOk()->assertJsonPath('data.recorded', false);
        $this->postJson("/api/v1/supporters/{$business->id}/events", $base + ['eventId' => 'event-impression-0002'])->assertOk()->assertJsonPath('data.recorded', false);
        $this->postJson("/api/v1/supporters/{$business->id}/events", ['visitorId' => 'second-visitor-1234567890', 'type' => 'impression', 'eventId' => 'event-impression-0003'])->assertOk()->assertJsonPath('data.recorded', true);
        $this->postJson("/api/v1/supporters/{$business->id}/events", ['visitorId' => $base['visitorId'], 'type' => 'detail_view', 'eventId' => 'event-detail-view-01'])->assertOk();
        $this->postJson("/api/v1/supporters/{$business->id}/events", ['visitorId' => $base['visitorId'], 'type' => 'cta_click', 'eventId' => 'event-cta-click-0001'])->assertOk();

        $this->assertDatabaseHas('supporter_daily_stats', ['supporter_business_id' => $business->id, 'impressions' => 2, 'unique_reach' => 2, 'detail_views' => 1, 'cta_clicks' => 1]);
        $this->assertDatabaseCount('supporter_events', 4);
        $this->assertDatabaseCount('supporter_daily_visitors', 2);
    }

    public function test_inactive_supporter_is_not_public(): void
    {
        $business = $this->business(['is_active' => false]);
        $this->getJson('/api/v1/supporters?province=Yalova&district=Çınarcık')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/supporters/{$business->id}")->assertNotFound();
    }

    public function test_supporter_can_open_only_supporter_dashboard(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_SUPPORTER, 'status' => 'active']);
        $this->business(['owner_user_id' => $owner->id]);
        $this->actingAs($owner)->get('/destekci/panel')->assertOk();
        $this->actingAs($owner)->get('/admin')->assertForbidden();
    }
}
