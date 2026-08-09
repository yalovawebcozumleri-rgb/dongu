<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Achievement;
use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicUserProfileController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($user->status === 'active', 404);
        $viewer = $request->user('sanctum');
        abort_if($viewer && UserBlock::existsBetween($viewer->id, $user->id), 404);
        $summary = CycleScoreSummary::where('user_id', $user->id)->where('period_key', 'all')->first();
        $badges = DB::table('user_achievements')
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $user->id)->orderBy('achievements.sort_order')
            ->get(['achievements.code', 'achievements.name', 'achievements.description', 'achievements.icon', 'user_achievements.awarded_at']);
        $earnedCodes = $badges->pluck('code');
        $nextAchievement = Achievement::query()
            ->when($earnedCodes->isNotEmpty(), fn ($query) => $query->whereNotIn('code', $earnedCodes))
            ->orderBy('sort_order')
            ->first();
        $listingRelations = ['seller:id,name,rating,rating_count,completed_transactions,avatar_path,avatar_key,updated_at', 'materials', 'photos'];
        if ($viewer) {
            $listingRelations['pickupRequests'] = fn ($query) => $query
                ->where('buyer_id', $viewer->id)
                ->latest('updated_at');
        }

        $listings = Listing::query()->where('user_id', $user->id)->where('status', Listing::STATUS_ACTIVE)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with($listingRelations)
            ->when($viewer, fn ($query) => $query->withExists([
                'favorites as is_favorited' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', $viewer->id),
            ]))
            ->latest('published_at')->limit(20)->get();

        return response()->json(['data' => [
            'id' => $user->id, 'name' => $user->name,
            'avatarUrl' => $user->avatarReference(),
            'memberSince' => $user->created_at?->toDateString(),
            'isNewUser' => $user->created_at?->greaterThanOrEqualTo(now()->subDays(30)) ?? true,
            'isOwnProfile' => $viewer?->id === $user->id,
            'blockedByMe' => $viewer ? UserBlock::where('blocker_id', $viewer->id)->where('blocked_id', $user->id)->exists() : false,
            'rating' => ['average' => $user->rating !== null ? (float) $user->rating : null, 'count' => (int) $user->rating_count],
            'completedDeliveries' => (int) $user->completed_transactions,
            'cycle' => ['points' => (int) ($summary?->points ?? 0), 'verifiedDeliveries' => (int) ($summary?->deliveries ?? 0)],
            'badges' => $badges->map(fn ($badge) => [
                'code' => $badge->code, 'name' => $badge->name, 'description' => $badge->description,
                'icon' => $badge->icon, 'awardedAt' => $badge->awarded_at,
            ])->values(),
            'nextBadge' => $nextAchievement ? $this->nextBadge(
                $nextAchievement,
                (int) ($summary?->points ?? 0),
                (int) ($summary?->deliveries ?? 0)
            ) : null,
            'activeListings' => ListingResource::collection($listings)->resolve($request),
        ]]);
    }

    private function nextBadge(Achievement $achievement, int $points, int $deliveries): array
    {
        $usesDeliveries = $achievement->deliveries_threshold > 0;
        $target = $usesDeliveries ? $achievement->deliveries_threshold : $achievement->points_threshold;
        $current = $usesDeliveries ? $deliveries : $points;

        return [
            'code' => $achievement->code,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'current' => min($current, $target),
            'target' => $target,
            'unit' => $usesDeliveries ? 'teslimat' : 'puan',
            'progress' => $target > 0 ? min(100, (int) floor(($current / $target) * 100)) : 100,
        ];
    }

    public function reviews(Request $request, User $user): JsonResponse
    {
        abort_unless($user->status === 'active', 404);
        $viewer = $request->user('sanctum');
        abort_if($viewer && UserBlock::existsBetween($viewer->id, $user->id), 404);
        $validated = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:20']]);
        $reviews = Review::where('reviewee_id', $user->id)->with('reviewer:id,name,avatar_path,avatar_key')->latest()->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $review) => [
                'id' => $review->id, 'rating' => $review->rating, 'comment' => $review->comment,
                'reviewer' => [
                    'id' => $review->reviewer->id,
                    'name' => $review->reviewer->name,
                    'avatarUrl' => $review->reviewer->avatarReference(),
                ],
                'createdAt' => $review->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'per_page' => $reviews->perPage(), 'total' => $reviews->total()],
        ]);
    }
}
