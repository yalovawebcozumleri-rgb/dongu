<?php

namespace App\Services;

use App\Events\ConversationChanged;
use App\Models\ConversationUserState;
use App\Models\Listing;
use App\Models\PickupRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListingConversationClosureService
{
    public const LISTING_UNAVAILABLE = 'listing_unavailable';
    public const LISTING_REMOVED = 'listing_removed';
    public const LISTING_EXPIRED = 'listing_expired';

    public function __construct(private readonly UserNotificationService $notifications)
    {
    }

    public function closeOpenWithinTransaction(Listing $listing, string $reason, ?int $exceptRequestId = null): Collection
    {
        $requests = PickupRequest::query()
            ->where('listing_id', $listing->id)
            ->when($exceptRequestId, fn ($query) => $query->whereKeyNot($exceptRequestId))
            ->whereIn('status', [PickupRequest::INQUIRY, PickupRequest::PENDING])
            ->withCount(['messages as user_messages_count' => fn ($query) => $query->where('type', 'user')])
            ->lockForUpdate()
            ->get();

        foreach ($requests as $pickupRequest) {
            $hasInteraction = $pickupRequest->status === PickupRequest::PENDING
                || (int) $pickupRequest->user_messages_count > 0;

            $pickupRequest->update([
                'status' => PickupRequest::CLOSED,
                'closed_reason' => $reason,
                'closed_at' => now(),
                'delivery_code' => null,
            ]);

            if ($hasInteraction) {
                $pickupRequest->messages()->create([
                    'sender_id' => null,
                    'type' => 'system',
                    'body' => $reason === self::LISTING_UNAVAILABLE
                        ? 'İlan artık alım taleplerine kapalı. Bu ilan için görüşme sona erdi.'
                        : 'İlan artık mevcut değil. Bu ilan için görüşme sona erdi.',
                ]);
            } else {
                foreach ([$pickupRequest->buyer_id, $pickupRequest->seller_id] as $participantId) {
                    ConversationUserState::updateOrCreate([
                        'pickup_request_id' => $pickupRequest->id,
                        'user_id' => $participantId,
                    ], ['hidden_at' => now()]);
                }
            }

            $pickupRequest->setAttribute('should_announce_closure', $hasInteraction);
        }

        return $requests;
    }

    public function closeAndAnnounce(Listing $listing, string $reason, ?int $exceptRequestId = null): Collection
    {
        $requests = DB::transaction(
            fn () => $this->closeOpenWithinTransaction($listing, $reason, $exceptRequestId)
        );
        $this->announce($requests, $reason);

        return $requests;
    }

    public function announce(Collection $requests, string $reason): void
    {
        foreach ($requests as $pickupRequest) {
            ConversationChanged::dispatch($pickupRequest->buyer_id, $pickupRequest->id, 'status');
            ConversationChanged::dispatch($pickupRequest->seller_id, $pickupRequest->id, 'status');

            if (! $pickupRequest->getAttribute('should_announce_closure')) continue;

            $this->notifications->create(
                $pickupRequest->buyer_id,
                'listing_unavailable',
                'İlan durumu güncellendi',
                $reason === self::LISTING_UNAVAILABLE
                    ? 'İlgilendiğin ilan artık yeni talep kabul etmiyor.'
                    : 'İlgilendiğin ilan artık mevcut değil.',
                [
                    'route' => 'chat',
                    'conversationId' => $pickupRequest->id,
                    'listingId' => $pickupRequest->listing_id,
                ],
                'pickup:'.$pickupRequest->id.':closed:'.$reason,
                'conversation:'.$pickupRequest->id,
            );
        }
    }
}
