<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_region_endpoints_return_provinces_and_filtered_districts(): void
    {
        $this->getJson('/api/v1/regions/provinces')
            ->assertOk()
            ->assertJsonCount(81, 'data')
            ->assertJsonFragment(['id' => 77, 'name' => 'Yalova']);

        $this->getJson('/api/v1/regions/provinces/77/districts')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonFragment(['id' => 2021, 'name' => 'Çınarcık']);
    }

    public function test_address_rejects_a_district_that_does_not_belong_to_selected_province(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $this->postJson('/api/v1/addresses', [
            'label' => 'Ev',
            'province_id' => 77,
            'district_id' => 1103,
            'neighborhood' => 'Karpuzdere',
            'full_address' => 'Örnek Mahallesi Test Sokak No: 10',
            'latitude' => 40.617,
            'longitude' => 29.111,
            'is_default' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('district_id');
    }
}
