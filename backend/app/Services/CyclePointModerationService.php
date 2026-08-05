<?php

namespace App\Services;

use App\Models\CycleAdminAudit;
use App\Models\CycleRiskCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CyclePointModerationService
{
    public function __construct(private CyclePointService $points) {}

    public function resolve(CycleRiskCase $riskCase, string $action, string $reason, User $admin, Request $request): CycleRiskCase
    {
        return DB::transaction(function () use ($riskCase, $action, $reason, $admin, $request) {
            $case = CycleRiskCase::query()->lockForUpdate()->findOrFail($riskCase->id);
            $entries = $case->pointEntries()->lockForUpdate()->get();
            $before = $this->snapshot($case, $entries);

            match ($action) {
                'clear' => $case->pointEntries()->where('role', 'seller')->where('status', 'pending_review')->update(['status' => 'active']),
                'revoke' => $case->pointEntries()->where('role', 'seller')->whereIn('status', ['active', 'pending_review'])->update(['status' => 'revoked']),
                'restore' => $case->pointEntries()->where('role', 'seller')->where('status', 'revoked')->update(['status' => 'active']),
                'reopen' => $case->pointEntries()->where('role', 'seller')->whereIn('status', ['active', 'revoked'])->update(['status' => 'pending_review']),
                default => throw new RuntimeException('Geçersiz moderasyon işlemi.'),
            };

            $case->update([
                'status' => match ($action) {
                    'clear', 'restore' => CycleRiskCase::CLEARED,
                    'revoke' => CycleRiskCase::CONFIRMED,
                    'reopen' => CycleRiskCase::PENDING,
                },
                'reviewed_by_admin_id' => $admin->id,
                'review_note' => $reason,
                'reviewed_at' => now(),
            ]);

            $this->points->rebuildUsers($entries->pluck('user_id')->unique()->all());
            $case->refresh();
            CycleAdminAudit::create([
                'admin_user_id' => $admin->id,
                'cycle_risk_case_id' => $case->id,
                'action' => $action,
                'before_state' => $before,
                'after_state' => $this->snapshot($case, $case->pointEntries()->get()),
                'reason' => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            ]);
            return $case;
        });
    }

    private function snapshot(CycleRiskCase $case, $entries): array
    {
        return [
            'caseStatus' => $case->status,
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id, 'userId' => $entry->user_id,
                'points' => $entry->points, 'status' => $entry->status,
            ])->values()->all(),
        ];
    }
}
