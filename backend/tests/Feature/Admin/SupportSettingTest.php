<?php

namespace Tests\Feature\Admin;

use App\Models\SupportSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_change_is_used_by_public_api_and_email_redirect_without_cached_phone(): void
    {
        $this->getJson('/api/v1/auth/support')->assertOk()->assertJsonPath('data.phone', '905413342219');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/admin/support-settings')->assertOk();
        $this->actingAs($admin)->patch('/admin/support-settings', ['phone' => '+90 (555) 111 22 33'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_settings', ['id' => 1, 'whatsapp_phone' => '905551112233', 'changed_by_user_id' => $admin->id]);
        $this->getJson('/api/v1/auth/support')->assertJsonPath('data.phone', '905551112233');
        $this->get('/destek/whatsapp')->assertRedirect(SupportSetting::whatsappUrl());
        $this->get('/iletisim')
            ->assertOk()
            ->assertSee(SupportSetting::whatsappUrl('Merhaba, Döngü hakkında bilgi almak istiyorum.'), false)
            ->assertSee('+90 555 111 22 33');
        $this->get('/reklam-ve-isbirligi')
            ->assertOk()
            ->assertSee(SupportSetting::whatsappUrl('Merhaba, Döngü reklam ve kurumsal iş birliği seçenekleri hakkında bilgi almak istiyorum.'), false)
            ->assertSee('tel:+905413342219', false)
            ->assertSee('+90 541 334 22 19');
        $this->patch('/admin/support-settings', ['phone' => '05413342219'])->assertSessionHasNoErrors();
        $this->getJson('/api/v1/auth/support')->assertJsonPath('data.phone', '905413342219');
        $this->assertStringStartsWith('https://wa.me/905413342219?', $this->get('/destek/whatsapp')->headers->get('Location'));
    }

    public function test_non_admin_cannot_change_phone(): void
    {
        $this->patch('/admin/support-settings', ['phone' => '905551112233'])->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))
            ->patch('/admin/support-settings', ['phone' => '905551112233'])->assertForbidden();
        $this->assertDatabaseCount('support_settings', 0);
    }

    public function test_invalid_number_or_external_url_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        foreach (['https://example.com', '555', '', 'abc905551112233', ['number' => '905551112233']] as $phone) {
            $this->patch('/admin/support-settings', ['phone' => $phone])->assertSessionHasErrors('phone');
        }
        $this->assertDatabaseCount('support_settings', 0);
    }
}
