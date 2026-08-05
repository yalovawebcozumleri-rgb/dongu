<?php

namespace App\Http\Resources;

use App\Models\PickupRequest;
use App\Models\UserBlock;
use App\Services\ProfileAvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickupRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isBuyer = $user && $this->buyer_id === $user->id;
        $counterpart = $isBuyer ? $this->seller : $this->buyer;
        $reviewed = $user
            ? $this->reviews->contains('reviewer_id', $user->id)
            : false;
        $blockedByMe = $user ? (bool) ($this->blocked_by_me
            ?? UserBlock::query()->where('blocker_id', $user->id)->where('blocked_id', $counterpart->id)->exists()) : false;
        $isBlocked = $user ? (bool) ($this->is_blocked
            ?? ($blockedByMe || UserBlock::query()->where('blocker_id', $counterpart->id)->where('blocked_id', $user->id)->exists())) : false;
        $addressVisible = ! $isBlocked && in_array($this->status, [PickupRequest::ACCEPTED, PickupRequest::COMPLETED], true);
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
                'avatarUrl' => $counterpart->avatar_path ? app(ProfileAvatarService::class)->url($counterpart->avatar_path, true).'?v='.($counterpart->updated_at?->timestamp ?? 0) : null,
                'rating' => $counterpart->rating !== null ? (float) $counterpart->rating : null,
                'ratingCount' => (int) $counterpart->rating_count,
            ],
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'lastMessage' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => $this->latestMessage->moderated_at ? 'Bu mesaj topluluk kurallarını ihlal ettiği için kaldırıldı.' : $this->latestMessage->body,
                'time' => $this->latestMessage->created_at?->format('H:i'),
            ] : null),
            'unreadCount' => (int) ($this->unread_count ?? 0),
            'isBlocked' => $isBlocked,
            'blockedByMe' => $blockedByMe,
            'deliveryCode' => $isBuyer && $this->status === PickupRequest::ACCEPTED ? $this->delivery_code : null,
            'exactAddress' => $addressVisible ? $this->listing->privateLocation?->address : null,
            'exactLatitude' => $addressVisible ? (float) $this->listing->privateLocation?->latitude : null,
            'exactLongitude' => $addressVisible ? (float) $this->listing->privateLocation?->longitude : null,
            'deliveryNotes' => $addressVisible ? $this->listing->privateLocation?->delivery_notes : null,
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
}
