<?php

namespace Tests\Feature\Api;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_emailed_one_time_code(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/code/request', [
            'intent' => 'register',
            'name' => 'Ramazan Test',
            'email' => 'ramazan@example.com',
            'terms_accepted' => true,
        ])->assertAccepted()->assertJsonPath('data.expires_in', 600);

        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->code;
            return $mail->hasTo('ramazan@example.com');
        });

        $this->postJson('/api/v1/auth/code/verify', [
            'email' => 'ramazan@example.com',
            'code' => $code,
            'device_name' => 'Test Telefonu',
        ])->assertCreated()
            ->assertJsonPath('data.user.email', 'ramazan@example.com')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'ramazan@example.com',
            'password' => null,
            'terms_version' => '2026-08-05.1',
            'privacy_notice_version' => '2026-08-05.1',
        ]);
        $this->assertDatabaseMissing('login_codes', ['email' => 'ramazan@example.com', 'consumed_at' => null]);
    }

    public function test_existing_user_can_login_read_update_and_logout(): void
    {
        Mail::fake();
        $user = User::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/auth/code/request', [
            'intent' => 'login', 'email' => $user->email,
        ])->assertAccepted();

        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $token = $this->postJson('/api/v1/auth/code/verify', [
            'email' => $user->email, 'code' => $code, 'device_name' => 'Test Telefonu',
        ])->assertOk()->json('data.token');

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->withHeaders($headers)->patchJson('/api/v1/auth/profile', ['name' => 'Yeni İsim'])->assertOk()->assertJsonPath('data.user.profile_complete', true);
        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_code_is_single_use_and_limited_to_five_wrong_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/code/request', ['intent' => 'login', 'email' => $user->email])->assertAccepted();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/code/verify', [
                'email' => $user->email, 'code' => '000000', 'device_name' => 'Test Telefonu',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/code/verify', [
            'email' => $user->email, 'code' => '000000', 'device_name' => 'Test Telefonu',
        ])->assertTooManyRequests();
    }

    public function test_code_request_rate_limit_is_turkish_and_returns_wait_time(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/code/request', [
                'intent' => 'login',
                'email' => $user->email,
            ])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/code/request', [
            'intent' => 'login',
            'email' => $user->email,
        ])->assertTooManyRequests()
            ->assertJsonPath('retry_after', fn ($seconds) => is_int($seconds) && $seconds >= 1 && $seconds <= 600)
            ->assertJsonFragment(['message' => 'Kısa sürede çok fazla kod istedin. Güvenliğin ve e-posta kutunun korunması için 10 dakika sonra tekrar deneyebilirsin.']);
    }
    public function test_registration_requires_name_and_terms(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/code/request', [
            'intent' => 'register', 'email' => 'new@example.com', 'terms_accepted' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'terms_accepted']);

        Mail::assertNothingSent();
    }

    public function test_suspended_user_cannot_request_login_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/code/request', [
            'intent' => 'login', 'email' => $user->email,
        ])->assertForbidden();

        Mail::assertNothingSent();
    }
}
