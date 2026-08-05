<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\CyclePointEntry;
use App\Models\CycleRiskCase;
use App\Models\CycleScoreSummary;
use App\Models\PickupRequest;
use Illuminate\Support\Facades\DB;

class CyclePointService
{
    public function __construct(private CycleFraudDetectionService $fraud) {}

    public function awardDelivery(PickupRequest $pickupRequest): int
    {
        return DB::transaction(function () use ($pickupRequest) {
            $quantity = (int) $pickupRequest->listing()->withTrashed()->firstOrFail()->materials()->sum('quantity');
            $points = max(1, min($quantity, (int) config('marketplace.max_cycle_points_per_delivery', 500)));
            $earnedAt = $pickupRequest->completed_at ?? now();
            $assessment = $this->fraud->assess($pickupRequest, $points);
            $status = $assessment['reviewRequired'] ? CyclePointEntry::PENDING_REVIEW : CyclePointEntry::ACTIVE;

            $entry = CyclePointEntry::firstOrCreate([
                'user_id' => $pickupRequest->seller_id,
                'pickup_request_id' => $pickupRequest->id,
                'reason' => 'delivery_completed',
            ], [
                'role' => 'seller',
                'points' => $points,
                'status' => $status,
                'earned_at' => $earnedAt,
            ]);
            if ($entry->wasRecentlyCreated && $status === CyclePointEntry::ACTIVE) {
                $this->incrementSummary($pickupRequest->seller_id, 'all', $points);
                $this->incrementSummary($pickupRequest->seller_id, $earnedAt->format('Y-m'), $points);
                $this->unlockAchievements($pickupRequest->seller_id);
            }

            if ($assessment['reviewRequired']) {
                CycleRiskCase::firstOrCreate(['pickup_request_id' => $pickupRequest->id], [
                    'status' => CycleRiskCase::PENDING, 'severity' => $assessment['severity'],
                    'risk_score' => $assessment['score'], 'rules' => $assessment['rules'],
                    'evidence' => $assessment['evidence'], 'detected_at' => now(),
                ]);
            }
            return $points;
        });
    }

    public function rebuildUsers(array $userIds): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) $this->rebuildUser($userId);
    }

    private function rebuildUser(int $userId): void
    {
        CycleScoreSummary::where('user_id', $userId)->delete();
        DB::table('user_achievements')->where('user_id', $userId)->delete();
        $entries = CyclePointEntry::where('user_id', $userId)->where('status', CyclePointEntry::ACTIVE)->orderBy('earned_at')->get();
        if ($entries->isEmpty()) return;
        CycleScoreSummary::create(['user_id' => $userId, 'period_key' => 'all', 'points' => $entries->sum('points'), 'deliveries' => $entries->count()]);
        foreach ($entries->groupBy(fn ($entry) => $entry->earned_at->format('Y-m')) as $period => $items) {
            CycleScoreSummary::create(['user_id' => $userId, 'period_key' => $period, 'points' => $items->sum('points'), 'deliveries' => $items->count()]);
        }
        $this->unlockAchievements($userId);
    }

    private function incrementSummary(int $userId, string $period, int $points): void
    {
        $summary = CycleScoreSummary::query()->where('user_id', $userId)->where('period_key', $period)->lockForUpdate()->first();
        if ($summary) {
            CycleScoreSummary::query()->whereKey($summary->id)->update([
                'points' => DB::raw('points + '.(int) $points), 'deliveries' => DB::raw('deliveries + 1'), 'updated_at' => now(),
            ]);
        } else {
            CycleScoreSummary::create(['user_id' => $userId, 'period_key' => $period, 'points' => $points, 'deliveries' => 1]);
        }
    }

    private function unlockAchievements(int $userId): void
    {
        $summary = CycleScoreSummary::where('user_id', $userId)->where('period_key', 'all')->first();
        if (! $summary) return;
        $eligible = Achievement::query()->where('points_threshold', '<=', $summary->points)
            ->where('deliveries_threshold', '<=', $summary->deliveries)->pluck('id');
        foreach ($eligible as $achievementId) {
            DB::table('user_achievements')->insertOrIgnore([
                'user_id' => $userId, 'achievement_id' => $achievementId, 'awarded_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
