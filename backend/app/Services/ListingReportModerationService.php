<?php

namespace App\Services;

use App\Models\AdminListingAction;
use App\Models\ListingReport;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingReportModerationService
{
    public const RECORD_ONLY = 'record_only';
    public const WARN_SELLER = 'warn_seller';
    public const REMOVE_LISTING = 'remove_listing';

    public const ACTIONS = [self::RECORD_ONLY, self::WARN_SELLER, self::REMOVE_LISTING];

    public function __construct(private readonly ListingConversationClosureService $closures) {}

    public function resolve(ListingReport $report, User $admin, string $resolution, ?string $action, string $note): void
    {
        $restored = false;
        $closedRequests = collect();

        DB::transaction(function () use ($report, $admin, $resolution, $action, $note, &$restored, &$closedRequests) {
            $report = ListingReport::query()->with('listing')->lockForUpdate()->findOrFail($report->id);
            $restored = $this->revertPreviousRemoval($report, $admin);

            if ($resolution === ListingReport::CONFIRMED && $action === self::REMOVE_LISTING) {
                $listing = $report->listing;
                if ($listing && ! $listing->trashed() && $listing->pickupRequests()->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->exists()) {
                    throw ValidationException::withMessages(['enforcement_action' => 'Açık alım talebi veya rezervasyonu bulunan ilan doğrudan kaldırılamaz. Önce ilgili işlemi sonuçlandır.']);
                }
                if ($listing && ! $listing->trashed()) {
                    AdminListingAction::create([
                        'listing_id' => $listing->id,
                        'listing_report_id' => $report->id,
                        'admin_id' => $admin->id,
                        'action' => 'removed_by_report',
                        'reason' => $note,
                        'snapshot' => ['status' => $listing->status, 'seller_id' => $listing->user_id, 'public_area' => $listing->public_area, 'description' => $listing->description],
                    ]);
                    $closedRequests = $this->closures->closeOpenWithinTransaction($listing, ListingConversationClosureService::LISTING_REMOVED);
                    $listing->delete();
                }
            }

            $pending = $resolution === ListingReport::PENDING;
            $report->update([
                'status' => $resolution,
                'enforcement_action' => $resolution === ListingReport::CONFIRMED ? $action : null,
                'resolution_note' => $note ?: null,
                'resolved_by_admin_id' => $pending ? null : $admin->id,
                'resolved_at' => $pending ? null : now(),
            ]);
        });

        $this->closures->announce($closedRequests, ListingConversationClosureService::LISTING_REMOVED);
        $this->notify($report->fresh()->load(['listing', 'reporter']), $resolution, $action, $note, $restored);
    }

    private function revertPreviousRemoval(ListingReport $report, User $admin): bool
    {
        if ($report->enforcement_action !== self::REMOVE_LISTING) return false;

        $listing = $report->listing;
        if (! $listing || ! $listing->trashed()) return false;

        $otherRemoval = ListingReport::query()->where('listing_id', $listing->id)->whereKeyNot($report->id)
            ->where('status', ListingReport::CONFIRMED)->where('enforcement_action', self::REMOVE_LISTING)->exists();
        if ($otherRemoval) return false;

        $listing->restore();
        AdminListingAction::create([
            'listing_id' => $listing->id,
            'listing_report_id' => $report->id,
            'admin_id' => $admin->id,
            'action' => 'restored_after_report_review',
            'reason' => 'Bildirim kararı değiştirildiği için önceki kaldırma işlemi geri alındı.',
            'snapshot' => ['status' => $listing->status, 'seller_id' => $listing->user_id, 'public_area' => $listing->public_area],
        ]);
        return true;
    }

    private function notify(ListingReport $report, string $resolution, ?string $action, string $note, bool $restored): void
    {
        $notifications = app(UserNotificationService::class);
        $confirmed = $resolution === ListingReport::CONFIRMED;
        $notifications->create($report->reporter_id, 'moderation_report_result', 'İlan bildirimin incelendi', $confirmed ? 'Bildirdiğin ilan incelendi ve gerekli işlem uygulandı.' : ($resolution === ListingReport::DISMISSED ? 'Bildirdiğin ilan incelendi; ihlal doğrulanmadı.' : 'İlan bildirimi yeniden incelemeye alındı.'), ['route' => 'notifications'], "listing-report:{$report->id}:result:{$resolution}:".now()->timestamp, null, false);

        $sellerId = $report->listing?->user_id;
        if (! $sellerId) return;
        if ($confirmed && $action === self::WARN_SELLER) {
            $notifications->create($sellerId, 'moderation_action', 'İlanın hakkında uyarı', 'İlanın platform kurallarına aykırı bulundu. Gerekçe: '.$note, ['route' => 'my-listings'], "listing-report:{$report->id}:warn:".now()->timestamp, null, false);
        } elseif ($confirmed && $action === self::REMOVE_LISTING) {
            $notifications->create($sellerId, 'moderation_action', 'İlanın yayından kaldırıldı', 'İlanın platform kurallarına aykırı bulunduğu için yayından kaldırıldı. Gerekçe: '.$note, ['route' => 'my-listings'], "listing-report:{$report->id}:remove:".now()->timestamp, null, false);
        } elseif ($restored) {
            $notifications->create($sellerId, 'moderation_action', 'İlanın yeniden yayında', 'Önceki moderasyon kararı değiştirildiği için ilan yeniden yayına alındı.', ['route' => 'my-listings'], "listing-report:{$report->id}:restore:".now()->timestamp, null, false);
        }
    }
}
