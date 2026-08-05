<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\LoginCode;
use App\Mail\AccountDeletionCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_account_and_personal_data_is_removed(): void
    {
        $user = User::factory()->create(['name' => 'Silinecek Kişi', 'email' => 'delete@example.com', 'phone' => '5410000000']);
        $listing = Listing::create(['user_id' => $user->id, 'status' => Listing::STATUS_ACTIVE, 'public_area' => 'Yalova', 'approximate_latitude' => 40.65, 'approximate_longitude' => 29.26, 'description' => 'Kişisel açıklama', 'published_at' => now(), 'expires_at' => now()->addMonth()]);
        $listing->privateLocation()->create(['latitude' => '40.65', 'longitude' => '29.26', 'address' => 'Gizli adres']);
        $user->addresses()->create(['label' => 'Ev', 'public_area' => 'Yalova', 'full_address' => 'Gizli adres', 'latitude' => '40.65', 'longitude' => '29.26']);
        Sanctum::actingAs($user, ['mobile']);

        $this->deleteJson('/api/v1/auth/account', ['confirmation' => 'HESABIMI SİL'])->assertOk();

        $user->refresh();
        $this->assertSame('deleted', $user->status);
        $this->assertSame('Silinen kullanıcı', $user->name);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertNull($user->phone);
        $this->assertDatabaseMissing('user_addresses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('listing_private_locations', ['listing_id' => $listing->id]);
        $this->assertDatabaseHas('account_deletion_audits', ['user_id' => $user->id, 'source' => 'mobile']);
    }

    public function test_user_can_request_and_confirm_deletion_from_public_web_page(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'web-delete@example.com']);
        $this->get('/hesap-silme')->assertOk()->assertSee('Döngü hesabını sil');
        $this->post('/hesap-silme/kod', ['email' => $user->email])->assertRedirect()->assertSessionHas('code_sent');
        Mail::assertSent(AccountDeletionCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
        LoginCode::where('email', $user->email)->where('intent', LoginCode::INTENT_DELETE)->update(['code_hash' => Hash::make('123456')]);
        $this->post('/hesap-silme', ['email' => $user->email, 'code' => '123456', 'confirmation' => '1'])->assertOk()->assertSee('Hesabın silindi');
        $this->assertSame('deleted', $user->fresh()->status);
    }
    public function test_account_deletion_requires_exact_confirmation(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['mobile']);
        $this->deleteJson('/api/v1/auth/account', ['confirmation' => 'sil'])->assertUnprocessable();
    }
}

