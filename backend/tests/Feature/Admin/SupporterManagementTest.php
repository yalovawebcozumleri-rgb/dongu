<?php

namespace Tests\Feature\Admin;

use App\Models\SupporterBusiness;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupporterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_supporter_management_and_create_business_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/supporters')->assertOk();

        $this->actingAs($admin)->post('/admin/supporters', [
            'name' => 'Yalova Döngü Market', 'cardSummary' => 'Yerel ekonomiyi destekliyor.',
            'detailTitle' => 'Yalova için birlikte', 'detailBody' => 'İşletme tanıtım metni.',
            'targetScope' => 'province', 'provinceCode' => '77', 'provinceName' => 'Yalova',
            'districtCode' => null, 'districtName' => null, 'ctaType' => 'website',
            'ctaLabel' => 'Web sitesini aç', 'ctaValue' => 'https://example.com', 'priority' => 10,
            'startsAt' => null, 'endsAt' => null, 'isActive' => true,
            'accountName' => 'İşletme Yetkilisi', 'accountEmail' => 'isletme@example.com',
            'accountPassword' => 'Guvenli123',
        ])->assertRedirect();

        $owner = User::where('email', 'isletme@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_SUPPORTER, $owner->role);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertDatabaseHas('supporter_businesses', ['name' => 'Yalova Döngü Market', 'owner_user_id' => $owner->id, 'is_active' => true]);
    }

    public function test_normal_user_cannot_open_supporter_management(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user)->get('/admin/supporters')->assertForbidden();
    }
}
