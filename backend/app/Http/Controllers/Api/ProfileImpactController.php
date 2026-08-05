<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\CyclePointEntry;
use App\Models\CycleScoreSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileImpactController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $allTime = CycleScoreSummary::where('user_id', $user->id)->where('period_key', 'all')->first();
        $monthly = CycleScoreSummary::where('user_id', $user->id)->where('period_key', now()->format('Y-m'))->first();
        $earned = DB::table('user_achievements')
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $user->id)
            ->orderBy('achievements.sort_order')
            ->get([
                'achievements.id', 'achievements.code', 'achievements.name', 'achievements.description',
                'achievements.icon', 'user_achievements.awarded_at',
            ]);
        $earnedIds = $earned->pluck('id');
        $nextAchievement = Achievement::query()
            ->when($earnedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $earnedIds))
            ->orderBy('sort_order')
            ->first();
        $points = (int) ($allTime?->points ?? 0);
        $verifiedDeliveries = (int) ($allTime?->deliveries ?? 0);

        return response()->json(['data' => [
            'rating' => [
                'average' => $user->rating !== null ? (float) $user->rating : null,
                'count' => (int) $user->rating_count,
            ],
            'completedDeliveries' => (int) $user->completed_transactions,
            'cycle' => [
                'points' => $points,
                'monthlyPoints' => (int) ($monthly?->points ?? 0),
                'verifiedDeliveries' => $verifiedDeliveries,
                'pendingReviewPoints' => (int) CyclePointEntry::where('user_id', $user->id)
                    ->where('status', CyclePointEntry::PENDING_REVIEW)->sum('points'),
                'rule' => 'Satıcının teslim ettiği her ambalaj 1 puan; işlem başına en fazla 500 puan.',
            ],
            'badges' => $earned->map(fn ($badge) => [
                'code' => $badge->code,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'awardedAt' => $badge->awarded_at,
            ])->values(),
            'nextBadge' => $nextAchievement ? $this->nextBadge($nextAchievement, $points, $verifiedDeliveries) : null,
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
}
