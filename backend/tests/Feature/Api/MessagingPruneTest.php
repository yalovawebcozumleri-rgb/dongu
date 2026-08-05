<?php

namespace Tests\Feature\Api;

use App\Models\LoginCode;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessagingPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_removes_only_expired_technical_records_and_supports_dry_run(): void
    {
        $user = User::factory()->create();
        $oldRevoked = PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[old-revoked]', 'platform' => 'android', 'last_used_at' => now()->subDays(40), 'revoked_at' => now()->subDays(31)]);
        $recentRevoked = PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[recent-revoked]', 'platform' => 'ios', 'last_used_at' => now()->subDays(5), 'revoked_at' => now()->subDays(5)]);
        $staleActive = PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[active]', 'platform' => 'android', 'last_used_at' => now()->subDays(500)]);
        $incomplete = PushToken::create(['user_id' => $user->id, 'token' => 'ExpoPushToken[incomplete]', 'platform' => 'android', 'last_used_at' => null]);
        $incomplete->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->saveQuietly();
        $oldCode = LoginCode::create(['email' => 'old@example.com', 'intent' => LoginCode::INTENT_LOGIN, 'code_hash' => 'x', 'expires_at' => now()->subDays(8)]);
        $recentCode = LoginCode::create(['email' => 'new@example.com', 'intent' => LoginCode::INTENT_LOGIN, 'code_hash' => 'x', 'expires_at' => now()->subHours(2)]);
        DB::table('password_reset_tokens')->insert([['email' => 'old@example.com', 'token' => 'x', 'created_at' => now()->subDays(2)], ['email' => 'new@example.com', 'token' => 'y', 'created_at' => now()->subHours(2)]]);
        DB::table('sessions')->insert([['id' => 'old-session', 'payload' => 'x', 'last_activity' => now()->subDays(31)->timestamp], ['id' => 'new-session', 'payload' => 'x', 'last_activity' => now()->subHours(2)->timestamp]]);

        $this->artisan('messaging:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseHas('push_tokens', ['id' => $oldRevoked->id]);

        $this->artisan('messaging:prune')->assertSuccessful();
        $this->assertDatabaseMissing('push_tokens', ['id' => $oldRevoked->id]);
        $this->assertDatabaseMissing('push_tokens', ['id' => $incomplete->id]);
        $this->assertDatabaseHas('push_tokens', ['id' => $recentRevoked->id]);
        $this->assertDatabaseMissing('push_tokens', ['id' => $staleActive->id]);
        $this->assertDatabaseMissing('login_codes', ['id' => $oldCode->id]);
        $this->assertDatabaseHas('login_codes', ['id' => $recentCode->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'new@example.com']);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'new-session']);
    }
}
