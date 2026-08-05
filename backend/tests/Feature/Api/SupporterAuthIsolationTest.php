<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupporterAuthIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporter_web_account_cannot_request_mobile_login_code(): void
    {
        Mail::fake();
        $supporter = User::factory()->create(['role' => User::ROLE_SUPPORTER, 'status' => 'active']);
        $this->postJson('/api/v1/auth/code/request', ['intent' => 'login', 'email' => $supporter->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }
}
