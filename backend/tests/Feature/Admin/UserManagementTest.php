<?php

namespace Tests\Feature\Admin;

use App\Models\ModerationSanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_filter_and_view_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['name' => 'Aranan Kullanıcı', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/users?search=Aranan&status=active&per_page=50')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('filters.per_page', 50)
                ->has('users.data', 1)
                ->where('users.data.0.id', $user->id)
                ->where('users.data.0.account_state', 'active'));

        $this->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Show')
                ->where('profile.id', $user->id)
                ->has('actions', 10));
    }

    public function test_admin_can_suspend_user_and_existing_api_session_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['status' => 'active']);
        $user->createToken('test-device');

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/account", [
            'action' => ModerationSanction::ACCOUNT_24H,
            'reason' => 'Olağan dışı ve tekrarlanan spam hareketleri tespit edildi.',
        ])->assertRedirect();

        $this->assertDatabaseHas('moderation_sanctions', [
            'user_id' => $user->id,
            'message_report_id' => null,
            'action' => ModerationSanction::ACCOUNT_24H,
            'applied_by_admin_id' => $admin->id,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Sanctum::actingAs($user, ['mobile']);
        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('moderation.action', ModerationSanction::ACCOUNT_24H);
    }

    public function test_admin_can_restore_suspended_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['status' => 'active']);
        $sanction = ModerationSanction::create([
            'user_id' => $user->id,
            'action' => ModerationSanction::ACCOUNT_INDEFINITE,
            'reason' => 'İnceleme tamamlanana kadar erişim durduruldu.',
            'starts_at' => now(),
            'applied_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/account", [
            'action' => 'restore',
            'reason' => 'İnceleme tamamlandı ve hesap yeniden açılabilir.',
        ])->assertRedirect();

        $this->assertNotNull($sanction->fresh()->revoked_at);
        $this->assertSame($admin->id, $sanction->fresh()->revoked_by_admin_id);
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admin_can_close_account_without_deleting_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/account", [
            'action' => ModerationSanction::ACCOUNT_CLOSED,
            'reason' => 'Hesabın kalıcı olarak kullanıma kapatılması onaylandı.',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'closed']);
        $this->assertDatabaseHas('moderation_sanctions', ['user_id' => $user->id, 'action' => ModerationSanction::ACCOUNT_CLOSED]);
    }

    public function test_action_requires_auditable_reason(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/account", [
            'action' => ModerationSanction::ACCOUNT_7D,
            'reason' => 'kısa',
        ])->assertSessionHasErrors('reason');
    }

    public function test_regular_user_cannot_open_management_pages(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $target = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->patch("/admin/users/{$target->id}/account", ['action' => 'restore', 'reason' => 'Bu işlem için yetkisi bulunmuyor.'])->assertForbidden();
        $this->get('/admin/listings')->assertForbidden();
    }
}
