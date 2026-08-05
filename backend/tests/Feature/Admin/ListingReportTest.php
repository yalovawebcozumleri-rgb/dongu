<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use App\Services\ListingReportModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_report_and_remove_listing_reversibly(): void
    {
        [$report, $admin, $listing] = $this->report();
        $this->actingAs($admin)->get('/admin/listing-reports')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ListingReports/Index')->where('counts.pending', 1)->has('reports.data', 1)->has('enforcementActions', 3));

        $this->patch("/admin/listing-reports/{$report->id}", [
            'resolution' => 'confirmed',
            'enforcement_action' => ListingReportModerationService::REMOVE_LISTING,
            'note' => 'İlanın yanıltıcı bilgi içerdiği doğrulandığı için yayından kaldırıldı.',
        ])->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame(ListingReport::CONFIRMED, $report->status);
        $this->assertSame(ListingReportModerationService::REMOVE_LISTING, $report->enforcement_action);
        $this->assertSame($admin->id, $report->resolved_by_admin_id);
        $this->assertNotNull($report->resolved_at);
        $this->assertSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('admin_listing_actions', ['listing_id' => $listing->id, 'listing_report_id' => $report->id, 'action' => 'removed_by_report']);

        $this->patch("/admin/listing-reports/{$report->id}", [
            'resolution' => 'dismissed',
            'note' => 'Yeni bilgiler sonucunda ihlal doğrulanmadı ve kaldırma kararı geri alındı.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'deleted_at' => null]);
        $this->assertNull($report->fresh()->enforcement_action);
        $this->assertDatabaseHas('admin_listing_actions', ['listing_id' => $listing->id, 'listing_report_id' => $report->id, 'action' => 'restored_after_report_review']);
    }

    public function test_confirmed_listing_report_requires_note_and_enforcement_action(): void
    {
        [$report, $admin] = $this->report();
        $regular = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($regular)->get('/admin/listing-reports')->assertForbidden();
        $this->actingAs($admin)->patch("/admin/listing-reports/{$report->id}", ['resolution' => 'confirmed', 'note' => ''])
            ->assertSessionHasErrors(['note', 'enforcement_action']);
        $this->assertSame(ListingReport::PENDING, $report->fresh()->status);
    }

    private function report(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $seller = User::factory()->create(['status' => 'active']);
        $reporter = User::factory()->create(['status' => 'active']);
        $listing = Listing::create(['user_id' => $seller->id, 'status' => Listing::STATUS_ACTIVE, 'public_area' => 'Yalova Merkez', 'approximate_latitude' => 40.65, 'approximate_longitude' => 29.27, 'description' => 'Admin ilan bildirimi testi', 'published_at' => now(), 'expires_at' => now()->addMonth()]);
        $report = ListingReport::create(['listing_id' => $listing->id, 'reporter_id' => $reporter->id, 'reason' => 'misleading', 'details' => 'Açıklama doğru değil.']);
        return [$report, $admin, $listing];
    }
}
