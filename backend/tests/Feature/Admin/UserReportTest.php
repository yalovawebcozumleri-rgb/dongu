<?php

namespace Tests\Feature\Admin;

use App\Models\ModerationSanction;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_user_report_and_apply_reversible_sanction(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $reported = User::factory()->create(['status' => 'active']);
        $reported->createToken('reported-device');
        $reporter = User::factory()->create(['status' => 'active']);
        $report = UserReport::create(['reported_user_id' => $reported->id, 'reporter_id' => $reporter->id, 'reason' => 'fraud', 'details' => 'Şüpheli davranış.']);

        $this->actingAs($admin)->get('/admin/user-reports')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UserReports/Index')->where('counts.pending', 1)->has('reports.data', 1)->has('enforcementActions', 9));

        $this->patch("/admin/user-reports/{$report->id}", [
            'resolution' => 'confirmed',
            'enforcement_action' => ModerationSanction::ACCOUNT_24H,
            'note' => 'Dolandırıcılık şüphesi hesap hareketleriyle doğrulandığı için hesap askıya alındı.',
        ])->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame(UserReport::CONFIRMED, $report->status);
        $this->assertSame(ModerationSanction::ACCOUNT_24H, $report->enforcement_action);
        $this->assertSame($admin->id, $report->resolved_by_admin_id);
        $this->assertDatabaseHas('moderation_sanctions', ['user_id' => $reported->id, 'user_report_id' => $report->id, 'action' => ModerationSanction::ACCOUNT_24H, 'revoked_at' => null]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $reported->id]);

        $this->patch("/admin/user-reports/{$report->id}", [
            'resolution' => 'dismissed',
            'note' => 'Yeni kanıtlar ihlali doğrulamadığı için yaptırım kaldırıldı.',
        ])->assertSessionHasNoErrors();

        $this->assertNull($report->fresh()->enforcement_action);
        $this->assertNotNull(ModerationSanction::where('user_report_id', $report->id)->firstOrFail()->revoked_at);
    }

    public function test_confirmation_requires_note_and_enforcement_action_and_regular_user_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $regular = User::factory()->create(['role' => User::ROLE_USER]);
        $report = UserReport::create(['reported_user_id' => User::factory()->create()->id, 'reporter_id' => $regular->id, 'reason' => 'spam']);
        $this->actingAs($regular)->get('/admin/user-reports')->assertForbidden();
        $this->actingAs($admin)->patch("/admin/user-reports/{$report->id}", ['resolution' => 'confirmed', 'note' => ''])
            ->assertSessionHasErrors(['note', 'enforcement_action']);
    }
}
