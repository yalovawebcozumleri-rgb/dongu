<?php

namespace Tests\Feature\Admin;

use App\Models\CyclePointEntry;
use App\Models\CycleRiskCase;
use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\PickupRequest;
use App\Models\User;
use App\Services\CyclePointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleRiskCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_pair_is_held_for_review_and_admin_decisions_are_reversible_and_audited(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $first = $this->completedPickup($seller, $buyer, 40, now()->subHours(2));
        app(CyclePointService::class)->awardDelivery($first);
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 40]);

        $secondListing = $this->listing($seller, 30);
        $second = PickupRequest::create([
            'listing_id' => $secondListing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'status' => PickupRequest::ACCEPTED, 'delivery_code' => '2468', 'accepted_at' => now()->subMinutes(10),
        ]);
        Sanctum::actingAs($seller, ['mobile']);
        $this->postJson("/api/v1/pickup-requests/{$second->id}/complete", ['code' => '2468'])->assertOk();

        $case = CycleRiskCase::where('pickup_request_id', $second->id)->firstOrFail();
        $this->assertSame(CycleRiskCase::PENDING, $case->status);
        $this->assertGreaterThanOrEqual(30, $case->risk_score);
        $this->assertDatabaseHas('cycle_point_entries', ['pickup_request_id' => $second->id, 'status' => CyclePointEntry::PENDING_REVIEW]);
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 40, 'deliveries' => 1]);

        $regular = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($regular)->get('/admin/cycle-risk-cases')->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/cycle-risk-cases')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CycleRiskCases/Index')->where('counts.pending', 1)->has('cases.data', 1));
        $this->get("/admin/cycle-risk-cases/{$case->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CycleRiskCases/Show')->where('riskCase.id', $case->id)->has('riskCase.rules', 1)->has('riskCase.entries', 1));

        $this->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'clear', 'reason' => 'Tarafların teslimat geçmişi ve ilan miktarı tutarlı bulundu.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 70, 'deliveries' => 2]);
        $this->assertDatabaseHas('cycle_admin_audits', ['cycle_risk_case_id' => $case->id, 'admin_user_id' => $admin->id, 'action' => 'clear']);

        $this->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'revoke', 'reason' => 'Yeni kanıtlar işlemin puan amacıyla tekrarlandığını doğruladı.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 40, 'deliveries' => 1]);
        $this->assertDatabaseHas('cycle_point_entries', ['pickup_request_id' => $second->id, 'status' => CyclePointEntry::REVOKED]);

        $this->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'restore', 'reason' => 'İtiraz belgeleri gerçek teslimatı doğruladığı için puanlar iade edildi.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 70, 'deliveries' => 2]);
        $this->assertDatabaseCount('cycle_admin_audits', 3);

        $this->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'reopen', 'reason' => 'Yeni bir itiraz nedeniyle puanlar yeniden incelemeye alındı.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cycle_point_entries', ['pickup_request_id' => $second->id, 'status' => CyclePointEntry::PENDING_REVIEW]);
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 40, 'deliveries' => 1]);
        $this->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'clear', 'reason' => 'Yeniden inceleme sonucunda gerçek teslimat tekrar doğrulandı.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cycle_score_summaries', ['user_id' => $seller->id, 'period_key' => 'all', 'points' => 70, 'deliveries' => 2]);
        $this->assertDatabaseCount('cycle_admin_audits', 5);
    }

    public function test_high_value_movement_is_flagged_and_admin_reason_is_required(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $pickup = $this->completedPickup($seller, $buyer, 700, now()->subMinutes(5));
        app(CyclePointService::class)->awardDelivery($pickup);
        $case = CycleRiskCase::where('pickup_request_id', $pickup->id)->firstOrFail();

        $this->assertSame(500, $case->evidence['points']);
        $this->assertContains('maximum_points', collect($case->rules)->pluck('code')->all());
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($admin)->patch("/admin/cycle-risk-cases/{$case->id}", ['action' => 'revoke', 'reason' => 'kısa'])
            ->assertSessionHasErrors('reason');
        $this->assertSame(CycleRiskCase::PENDING, $case->fresh()->status);
        $this->assertDatabaseCount('cycle_admin_audits', 0);
    }

    private function completedPickup(User $seller, User $buyer, int $quantity, $completedAt): PickupRequest
    {
        return PickupRequest::create([
            'listing_id' => $this->listing($seller, $quantity)->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => PickupRequest::COMPLETED,
            'accepted_at' => $completedAt->copy()->subMinutes(10), 'completed_at' => $completedAt,
        ]);
    }

    private function listing(User $seller, int $quantity): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'status' => Listing::STATUS_COMPLETED,
            'public_area' => 'Kadıköy, İstanbul', 'approximate_latitude' => 40.99,
            'approximate_longitude' => 29.02, 'description' => 'Puan güvenliği test ilanı.',
            'published_at' => now()->subDay(), 'expires_at' => now()->addMonth(),
        ]);
        $listing->materials()->create(['type' => 'pet', 'quantity' => $quantity, 'unit_price' => 0.50]);
        $listing->privateLocation()->create(['latitude' => '40.9900000', 'longitude' => '29.0200000', 'address' => 'Test adresi']);
        return $listing;
    }
}
