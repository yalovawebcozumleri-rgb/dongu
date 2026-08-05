<?php

namespace Tests\Feature\Api;

use App\Models\ListingPrivateLocation;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_private_saved_addresses(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $response = $this->postJson('/api/v1/addresses', $this->addressPayload());

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Ev')
            ->assertJsonPath('data.isDefault', true)
            ->assertJsonPath('data.fullAddress', 'Rüstempaşa Mahallesi, Örnek Sokak No: 12');

        $address = UserAddress::firstOrFail();
        $this->assertNotSame($address->full_address, $address->getRawOriginal('full_address'));

        $this->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $otherUser = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($otherUser, ['mobile']);

        $this->patchJson('/api/v1/addresses/'.$address->id, $this->addressPayload())
            ->assertForbidden();
    }

    public function test_listing_uses_an_address_snapshot(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $address = $user->addresses()->create([
            'label' => 'Depo',
            'public_area' => 'Yalova Merkez',
            'full_address' => 'Rüstempaşa Mahallesi, Depo Sokak No: 5',
            'latitude' => '40.6551234',
            'longitude' => '29.2764567',
            'delivery_notes' => 'Arka kapıdan teslim alın.',
            'is_default' => true,
        ]);
        Sanctum::actingAs($user, ['mobile']);

        $response = $this->postJson('/api/v1/listings', [
            'materials' => [
                ['type' => 'pet', 'quantity' => 1, 'unit_price' => 0.75],
            ],
            'description' => 'Ambalaj temiz ve teslimata hazır durumda.',
            'packaging_condition_confirmed' => true,
            'address_id' => $address->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.district', 'Yalova Merkez')
            ->assertJsonMissingPath('data.exact_address');

        $privateLocation = ListingPrivateLocation::firstOrFail();
        $this->assertSame('Rüstempaşa Mahallesi, Depo Sokak No: 5', $privateLocation->address);
        $this->assertSame('Arka kapıdan teslim alın.', $privateLocation->delivery_notes);

        $address->update(['full_address' => 'Daha sonra değiştirilen adres']);

        $this->assertSame(
            'Rüstempaşa Mahallesi, Depo Sokak No: 5',
            $privateLocation->fresh()->address,
        );
    }

    private function addressPayload(): array
    {
        return [
            'label' => 'Ev',
            'public_area' => 'Yalova Merkez',
            'full_address' => 'Rüstempaşa Mahallesi, Örnek Sokak No: 12',
            'latitude' => 40.6551234,
            'longitude' => 29.2764567,
            'delivery_notes' => 'Giriş kapısından teslim alın.',
            'is_default' => true,
        ];
    }
}