<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CycleScoreSummary;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['period' => ['nullable', Rule::in(['monthly', 'all'])]]);
        $period = $validated['period'] ?? 'monthly';
        $periodKey = $period === 'monthly' ? now()->format('Y-m') : 'all';
        $currentUser = $request->user('sanctum');

        $base = CycleScoreSummary::query()
            ->join('users', 'users.id', '=', 'cycle_score_summaries.user_id')
            ->where('cycle_score_summaries.period_key', $periodKey)
            ->where('cycle_score_summaries.points', '>', 0)
            ->where('users.status', 'active');

        $top = (clone $base)
            ->select('cycle_score_summaries.*', 'users.name', 'users.ranking_name_visible', 'users.avatar_path', 'users.avatar_key')
            ->orderByDesc('cycle_score_summaries.points')
            ->orderByDesc('cycle_score_summaries.deliveries')
            ->orderBy('cycle_score_summaries.user_id')
            ->limit(50)
            ->get();

        $ownSummary = $currentUser
            ? (clone $base)->where('cycle_score_summaries.user_id', $currentUser->id)
                ->select('cycle_score_summaries.*', 'users.name', 'users.ranking_name_visible', 'users.avatar_path', 'users.avatar_key')->first()
            : null;
        $ownRank = $ownSummary ? $this->rankFor($periodKey, $ownSummary) : null;

        $userIds = $top->pluck('user_id')->when($currentUser, fn ($ids) => $ids->push($currentUser->id))->unique();
        $badges = DB::table('user_achievements')
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->whereIn('user_achievements.user_id', $userIds)
            ->orderBy('achievements.sort_order')
            ->get(['user_achievements.user_id', 'achievements.code', 'achievements.name', 'achievements.icon'])
            ->groupBy('user_id');

        $rows = $top->values()->map(fn ($row, $index) => $this->row($row, $index + 1, $currentUser, $badges));
        $own = $ownSummary ? $this->row($ownSummary, $ownRank, $currentUser, $badges) : [
            'rank' => null, 'userId' => $currentUser?->id, 'name' => $currentUser?->name,
            'avatarUrl' => $currentUser?->avatarReference(),
            'anonymous' => false, 'points' => 0, 'deliveries' => 0, 'badges' => [],
        ];

        return response()->json([
            'data' => $rows,
            'own' => $currentUser ? $own : null,
            'meta' => [
                'period' => $period,
                'periodLabel' => $period === 'monthly'
                    ? Carbon::now()->locale('tr')->translatedFormat('F Y')
                    : 'Tüm zamanlar',
                'totalParticipants' => (clone $base)->count(),
                'nameVisible' => $currentUser ? (bool) $currentUser->ranking_name_visible : null,
                'pointsRule' => 'Satıcının teslim ettiği her ambalaj 1 puan; işlem başına en fazla 500 puan.',
            ],
        ]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $validated = $request->validate(['nameVisible' => ['required', 'boolean']]);
        $request->user()->update(['ranking_name_visible' => $validated['nameVisible']]);
        return response()->json(['data' => ['nameVisible' => (bool) $request->user()->ranking_name_visible]]);
    }

    private function rankFor(string $periodKey, $own): int
    {
        return CycleScoreSummary::query()
            ->join('users', 'users.id', '=', 'cycle_score_summaries.user_id')
            ->where('period_key', $periodKey)
            ->where('users.status', 'active')
            ->where(function ($query) use ($own) {
                $query->where('points', '>', $own->points)
                    ->orWhere(function ($query) use ($own) {
                        $query->where('points', $own->points)->where('deliveries', '>', $own->deliveries);
                    })->orWhere(function ($query) use ($own) {
                        $query->where('points', $own->points)->where('deliveries', $own->deliveries)
                            ->where('cycle_score_summaries.user_id', '<', $own->user_id);
                    });
            })->count() + 1;
    }

    private function row($row, int $rank, ?User $currentUser, $badges): array
    {
        $isOwn = $currentUser?->id === $row->user_id;
        $anonymous = ! $row->ranking_name_visible && ! $isOwn;
        return [
            'rank' => $rank,
            'userId' => $row->user_id,
            'name' => $anonymous ? 'Döngü üyesi' : $row->name,
            'avatarUrl' => ! $anonymous ? User::avatarReferenceFromKey($row->avatar_key) : null,
            'anonymous' => $anonymous,
            'isOwn' => $isOwn,
            'points' => (int) $row->points,
            'deliveries' => (int) $row->deliveries,
            'badges' => collect($badges->get($row->user_id, []))->map(fn ($badge) => [
                'code' => $badge->code, 'name' => $badge->name, 'icon' => $badge->icon,
            ])->values(),
        ];
    }
}
