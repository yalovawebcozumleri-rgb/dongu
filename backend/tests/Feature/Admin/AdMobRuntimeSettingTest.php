<?php

namespace Tests\Feature\Admin;

use App\Models\AdMobRuntimeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdMobRuntimeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_android_production_with_confirmation_and_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->patch('/admin/advertising-runtime', [
            'androidMode' => 'production',
            'iosMode' => 'test',
            'confirmProduction' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = AdMobRuntimeSetting::current();
        $this->assertSame('production', $setting->android_mode);
        $this->assertSame('test', $setting->ios_mode);
        $this->assertSame(2, $setting->configuration_version);
        $this->assertSame($admin->id, $setting->changed_by_user_id);
        $this->assertDatabaseHas('admob_runtime_setting_audits', [
            'admob_runtime_setting_id' => $setting->id,
            'previous_android_mode' => 'test',
            'new_android_mode' => 'production',
            'previous_ios_mode' => 'test',
            'new_ios_mode' => 'test',
            'configuration_version' => 2,
            'changed_by_user_id' => $admin->id,
        ]);
    }

    public function test_production_transition_requires_explicit_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->from('/admin/advertisements')->patch('/admin/advertising-runtime', [
            'androidMode' => 'production',
            'iosMode' => 'test',
            'confirmProduction' => false,
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('confirmation');

        $this->assertSame('test', AdMobRuntimeSetting::current()->android_mode);
        $this->assertDatabaseCount('admob_runtime_setting_audits', 0);
    }

    public function test_production_transition_is_rejected_when_an_enabled_platform_unit_is_missing(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->from('/admin/advertisements')->patch('/admin/advertising-runtime', [
            'androidMode' => 'test',
            'iosMode' => 'production',
            'confirmProduction' => true,
        ])->assertRedirect('/admin/advertisements')->assertSessionHasErrors('iosMode');

        $this->assertSame('test', AdMobRuntimeSetting::current()->ios_mode);
    }

    public function test_non_admin_cannot_change_admob_runtime_mode(): void
    {
        $this->actingAs(User::factory()->create())->patch('/admin/advertising-runtime', [
            'androidMode' => 'test',
            'iosMode' => 'test',
        ])->assertForbidden();
    }
}
