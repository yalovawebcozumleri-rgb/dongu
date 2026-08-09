<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\PickupRequest;
use App\Services\ListingConversationClosureService;
use Illuminate\Console\Command;

class CloseExpiredListingConversations extends Command
{
    protected $signature = 'listings:close-expired-conversations';
    protected $description = 'Süresi dolan ilanların açık görüşmelerini kapatır';

    public function handle(ListingConversationClosureService $closures): int
    {
        $closed = 0;

        Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereHas('pickupRequests', fn ($query) => $query->whereIn('status', [PickupRequest::INQUIRY, PickupRequest::PENDING]))
            ->chunkById(100, function ($listings) use ($closures, &$closed): void {
                foreach ($listings as $listing) {
                    $closed += $closures->closeAndAnnounce(
                        $listing,
                        ListingConversationClosureService::LISTING_EXPIRED,
                    )->count();
                }
            });

        $this->info("{$closed} görüşme kapatıldı.");

        return self::SUCCESS;
    }
}