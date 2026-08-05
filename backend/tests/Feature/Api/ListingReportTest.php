<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_listing_once_with_a_real_reason(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $reporter = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);
        Sanctum::actingAs($reporter, ['mobile']);

        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'misleading', 'details' => 'Adet bilgisi gerçeği yansıtmıyor.'])
            ->assertCreated()->assertJsonPath('data.reported', true);
        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'spam'])
            ->assertOk()->assertJsonPath('data.reported', true);

        $this->assertDatabaseCount('listing_reports', 1);
        $this->assertDatabaseHas('listing_reports', ['listing_id' => $listing->id, 'reporter_id' => $reporter->id, 'reason' => 'misleading']);
    }

    public function test_guest_and_owner_cannot_report_listing(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $listing = $this->listing($seller);
        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'spam'])->assertUnauthorized();
        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'spam'])->assertUnprocessable();
    }

    private function listing(User $seller): Listing
    {
        return Listing::create(['user_id' => $seller->id, 'status' => Listing::STATUS_ACTIVE, 'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.65, 'approximate_longitude' => 29.27, 'description' => 'İlan bildirimi test ilanı', 'published_at' => now(), 'expires_at' => now()->addMonth()]);
    }
}
