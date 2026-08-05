<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ListingFavorite;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();

        $listings = Listing::query()
            ->join('listing_favorites', 'listing_favorites.listing_id', '=', 'listings.id')
            ->select('listings.*')
            ->selectRaw('1 AS is_favorited')
            ->with(['seller', 'materials', 'photos'])
            ->where('listing_favorites.user_id', $user->id)
            ->where('listings.status', Listing::STATUS_ACTIVE)
            ->whereNotNull('listings.published_at')
            ->where(fn ($query) => $query->whereNull('listings.expires_at')->orWhere('listings.expires_at', '>', now()))
            ->whereNotIn('listings.user_id', UserBlock::relatedUserIds($user->id))
            ->orderByDesc('listing_favorites.created_at')
            ->paginate($filters['per_page'] ?? 20);

        return ListingResource::collection($listings);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_if($listing->user_id === $user->id, 422, 'Kendi ilanını favorilerine ekleyemezsin.');
        abort_if(UserBlock::existsBetween($user->id, $listing->user_id), 404);
        abort_unless(
            $listing->status === Listing::STATUS_ACTIVE
                && $listing->published_at
                && (! $listing->expires_at || $listing->expires_at->isFuture()),
            422,
            'Bu ilan artık favorilere eklenemiyor.'
        );

        ListingFavorite::firstOrCreate([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);

        return response()->json(['data' => ['listingId' => $listing->id, 'isFavorited' => true]]);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        ListingFavorite::query()
            ->where('user_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->delete();

        return response()->json(['data' => ['listingId' => $listing->id, 'isFavorited' => false]]);
    }
}
