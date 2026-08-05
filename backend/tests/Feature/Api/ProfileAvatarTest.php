<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_replace_and_remove_public_avatar_variants(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $first = $this->post('/api/v1/auth/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('profil.jpg', 900, 700)->size(700),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.avatar_url', fn ($value) => is_string($value) && str_contains($value, '-512.webp'))
            ->assertJsonPath('data.user.avatar_thumbnail_url', fn ($value) => is_string($value) && str_contains($value, '-128.webp'));

        $firstMain = $user->fresh()->avatar_path;
        $firstThumb = str_replace('-512.webp', '-128.webp', $firstMain);
        Storage::disk('public')->assertExists([$firstMain, $firstThumb]);

        $this->post('/api/v1/auth/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('yeni.png', 600, 800)->size(600),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing([$firstMain, $firstThumb]);

        $current = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists([$current, str_replace('-512.webp', '-128.webp', $current)]);
        $this->deleteJson('/api/v1/auth/profile/avatar')->assertOk()->assertJsonPath('data.user.avatar_url', null);
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($current);
    }

    public function test_avatar_requires_authentication_image_and_five_megabyte_limit(): void
    {
        Storage::fake('public');
        $this->post('/api/v1/auth/profile/avatar', [], ['Accept' => 'application/json'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);
        $this->post('/api/v1/auth/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('dosya.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->post('/api/v1/auth/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('buyuk.jpg')->size(5200),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }
}
