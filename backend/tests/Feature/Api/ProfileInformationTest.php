<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_real_account_information_and_updates_trimmed_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Eski İsim', 'email' => 'profil@example.com',
            'email_verified_at' => now(), 'phone' => null,
        ]);
        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.user.email', 'profil@example.com')
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.created_at', fn ($value) => is_string($value) && $value !== '');

        $this->patchJson('/api/v1/auth/profile', ['name' => '  Yeni Kullanıcı  '])
            ->assertOk()->assertJsonPath('data.user.name', 'Yeni Kullanıcı');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Yeni Kullanıcı']);
    }

    public function test_name_is_validated_after_whitespace_is_trimmed(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['mobile']);

        $this->patchJson('/api/v1/auth/profile', ['name' => '  A  '])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
