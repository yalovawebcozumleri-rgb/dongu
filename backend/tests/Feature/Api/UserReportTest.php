<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_report_another_user_once(): void
    {
        $reported = User::factory()->create(['status' => 'active']);
        $reporter = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($reporter, ['mobile']);

        $this->postJson("/api/v1/users/{$reported->id}/report", ['reason' => 'fraud', 'details' => 'Şüpheli teslimat teklifi.'])
            ->assertCreated()->assertJsonPath('data.reported', true);
        $this->postJson("/api/v1/users/{$reported->id}/report", ['reason' => 'spam'])
            ->assertOk()->assertJsonPath('data.reported', true);
        $this->assertDatabaseCount('user_reports', 1);
    }

    public function test_guest_and_user_cannot_report_self(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->postJson("/api/v1/users/{$user->id}/report", ['reason' => 'spam'])->assertUnauthorized();
        Sanctum::actingAs($user, ['mobile']);
        $this->postJson("/api/v1/users/{$user->id}/report", ['reason' => 'spam'])->assertUnprocessable();
    }
}
