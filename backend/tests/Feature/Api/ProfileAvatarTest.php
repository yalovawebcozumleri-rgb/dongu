<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_select_and_replace_a_preset_avatar(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/auth/profile/avatar', ['avatar_key' => 'avatar_03'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.avatar_url', 'preset://avatar_03')
            ->assertJsonPath('data.user.avatar_thumbnail_url', 'preset://avatar_03');

        $this->assertSame('avatar_03', $user->fresh()->avatar_key);

        $this->postJson('/api/v1/auth/profile/avatar', ['avatar_key' => 'avatar_09'])
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', 'preset://avatar_09');

        $this->assertSame('avatar_09', $user->fresh()->avatar_key);
    }

    public function test_avatar_requires_authentication_and_an_allowed_preset_key(): void
    {
        $this->postJson('/api/v1/auth/profile/avatar', ['avatar_key' => 'avatar_01'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);
        $this->postJson('/api/v1/auth/profile/avatar', [])->assertUnprocessable()->assertJsonValidationErrors('avatar_key');
        $this->postJson('/api/v1/auth/profile/avatar', ['avatar_key' => 'avatar_99'])->assertUnprocessable()->assertJsonValidationErrors('avatar_key');
        $this->postJson('/api/v1/auth/profile/avatar', ['avatar' => 'photo.jpg'])->assertUnprocessable()->assertJsonValidationErrors('avatar_key');
        $this->deleteJson('/api/v1/auth/profile/avatar')->assertMethodNotAllowed();
    }
}