<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Models\ListingMaterial;
use App\Models\PickupRequest;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickupRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isBuyer = $user && $this->buyer_id === $user->id;
        $counterpart = $isBuyer ? $this->seller : $this->buyer;
        $listing = $this->relationLoaded('listing') ? $this->listing : null;
        $listingAvailable = $listing
            && ! $listing->trashed()
            && $listing->status === Listing::STATUS_ACTIVE
            && (! $listing->expires_at || $listing->expires_at->isFuture());
        $listingSummary = $this->listing_snapshot ?: $this->snapshotFromListing($listing);
        $reviewed = $user ? $this->reviews->contains('reviewer_id', $user->id) : false;
        $blockedByMe = $user ? (bool) ($this->blocked_by_me
            ?? UserBlock::query()->where('blocker_id', $user->id)->where('blocked_id', $counterpart->id)->exists()) : false;
        $isBlocked = $user ? (bool) ($this->is_blocked
            ?? ($blockedByMe || UserBlock::query()->where('blocker_id', $counterpart->id)->where('blocked_id', $user->id)->exists())) : false;
        $conversationHidden = $this->relationLoaded('userStates')
            && (bool) $this->userStates->first()?->hidden_at;
        $hasMessages = (int) ($this->user_messages_count ?? 0) > 0;
        $canSendMessage = ! $isBlocked && in_array($this->status, [
            PickupRequest::INQUIRY,
            PickupRequest::PENDING,
            PickupRequest::ACCEPTED,
        ], true);
        $addressVisible = $listing && ! $isBlocked && $this->status === PickupRequest::ACCEPTED;
        $reviewExpiresAt = $this->status === PickupRequest::COMPLETED
            ? $this->completed_at?->copy()->addHours(config('marketplace.review_window_hours'))
            : null;
        $reviewWindowOpen = $reviewExpiresAt && now()->lessThanOrEqualTo($reviewExpiresAt);
        $cancelledByRole = $this->cancelled_by_user_id === $this->buyer_id
            ? 'buyer'
            : ($this->cancelled_by_user_id === $this->seller_id ? 'seller' : null);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'role' => $isBuyer ? 'buyer' : 'seller',
            'counterpart' => [
                'id' => $counterpart->id,
                'name' => $counterpart->name,
                'avatarUrl' => $counterpart->avatarReference(),
                'rating' => $counterpart->rating !== null ? (float) $counterpart->rating : null,
                'ratingCount' => (int) $counterpart->rating_count,
            ],
            'listing' => $listing ? (new ListingResource($listing))->resolve($request) : null,
            'listingSummary' => $listingSummary,
            'listingAvailable' => (bool) $listingAvailable,
            'closureReason' => $this->closed_reason,
            'closedAt' => $this->closed_at?->toIso8601String(),
            'lastMessage' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => $this->latestMessage->moderated_at ? 'Bu mesaj topluluk kurallarını ihlal ettiği için kaldırıldı.' : $this->latestMessage->body,
                'time' => $this->latestMessage->created_at?->format('H:i'),
                'createdAt' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null),
            'unreadCount' => (int) ($this->unread_count ?? 0),
            'conversationHidden' => $conversationHidden,
            'hasMessages' => $hasMessages,
            'canOpenConversation' => ! $conversationHidden && $hasMessages,
            'canSendMessage' => $canSendMessage,
            'isBlocked' => $isBlocked,
            'blockedByMe' => $blockedByMe,
            'deliveryCode' => $isBuyer && $this->status === PickupRequest::ACCEPTED ? $this->delivery_code : null,
            'exactAddress' => $addressVisible ? $listing?->privateLocation?->address : null,
            'exactLatitude' => $addressVisible ? (float) $listing?->privateLocation?->latitude : null,
            'exactLongitude' => $addressVisible ? (float) $listing?->privateLocation?->longitude : null,
            'deliveryNotes' => $addressVisible ? $listing?->privateLocation?->delivery_notes : null,
            'canReview' => $this->status === PickupRequest::COMPLETED && $reviewWindowOpen && ! $reviewed,
            'reviewed' => $reviewed,
            'reviewExpiresAt' => $reviewExpiresAt?->toIso8601String(),
            'cancelledByRole' => $cancelledByRole,
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function snapshotFromListing(?Listing $listing): ?array
    {
        if (! $listing) return null;

        $labels = [
            ListingMaterial::PET => 'PET',
            ListingMaterial::GLASS => 'Cam',
            ListingMaterial::ALUMINUM => 'Alüminyum',
        ];

        return [
            'id' => $listing->id,
            'sellerId' => $listing->user_id,
            'seller' => $listing->seller->name,
            'district' => $listing->public_area,
            'items' => $listing->materials->map(fn ($material) => [
                'material' => $labels[$material->type] ?? $material->type,
                'type' => $material->type,
                'count' => (int) $material->quantity,
                'unitPrice' => (float) $material->unit_price,
            ])->values()->all(),
        ];
    }
}