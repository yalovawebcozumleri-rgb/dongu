<?php

namespace App\Http\Resources;

use App\Models\ListingMaterial;
use App\Models\PickupRequest;
use App\Services\ProfileAvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $labels = [
            ListingMaterial::PET => 'PET',
            ListingMaterial::GLASS => 'Cam',
            ListingMaterial::ALUMINUM => 'Alüminyum',
        ];

        $pickupStatus = $this->relationLoaded('pickupRequests')
            ? $this->pickupRequests->first()?->status
            : null;
        $requestStatus = match ($pickupStatus) {
            PickupRequest::PENDING => 'pending',
            PickupRequest::ACCEPTED => 'reserved',
            PickupRequest::REJECTED => 'rejected',
            PickupRequest::CANCELLED => 'cancelled',
            default => 'none',
        };

        return [
            'id' => $this->id,
            'items' => $this->materials->map(fn ($material) => [
                'material' => $labels[$material->type],
                'type' => $material->type,
                'count' => $material->quantity,
                'unitPrice' => (float) $material->unit_price,
            ])->values(),
            'latitude' => (float) $this->approximate_latitude,
            'longitude' => (float) $this->approximate_longitude,
            'district' => $this->public_area,
            'seller' => $this->seller->name,
            'sellerId' => $this->seller->id,
            'sellerAvatarUrl' => $this->seller->avatar_path ? app(ProfileAvatarService::class)->url($this->seller->avatar_path, true).'?v='.($this->seller->updated_at?->timestamp ?? 0) : null,
            'sellerTransactions' => $this->seller->completed_transactions,
            'rating' => $this->seller->rating !== null ? (float) $this->seller->rating : null,
            'ratingCount' => (int) $this->seller->rating_count,
            'isFavorited' => (bool) ($this->is_favorited ?? false),
            'time' => ($this->published_at ?? $this->created_at)?->locale('tr')->diffForHumans(),
            'note' => $this->description,
            'status' => $this->status,
            'requestStatus' => $requestStatus,
            'distanceKm' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'photos' => $this->photos->map(fn ($photo) => asset('storage/'.$photo->path))->values(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'boostedUntil' => $this->boosted_until?->toIso8601String(),
            'isBoosted' => $this->boosted_until?->isFuture() ?? false,
            'expiresInDays' => $this->expires_at
                ? max(0, (int) ceil(now()->diffInDays($this->expires_at, false)))
                : null,
        ];
    }
}
