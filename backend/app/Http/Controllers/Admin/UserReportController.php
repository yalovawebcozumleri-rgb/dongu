<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Services\ModerationSanctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserReportController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'reason' => ['nullable', Rule::in(['fake_profile', 'harassment', 'fraud', 'spam', 'inappropriate', 'other'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);
        $reports = UserReport::query()
            ->with(['reportedUser:id,name,email,status,rating,rating_count,completed_transactions', 'reporter:id,name,email', 'resolvedBy:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = trim($search);
                $query->where(fn ($query) => $query->where('id', 'like', "%{$term}%")
                    ->orWhereHas('reportedUser', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('reporter', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
            })
            ->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/UserReports/Index', [
            'reports' => $reports,
            'filters' => ['status' => $filters['status'] ?? '', 'reason' => $filters['reason'] ?? '', 'search' => $filters['search'] ?? '', 'per_page' => $perPage],
            'pageSizes' => self::PAGE_SIZES,
            'enforcementActions' => collect(ModerationSanctionService::ACTIONS)->map(fn (string $action) => ['value' => $action, 'label' => ModerationSanctionService::LABELS[$action]])->values(),
            'counts' => [
                'all' => UserReport::count(),
                'pending' => UserReport::where('status', UserReport::PENDING)->count(),
                'confirmed' => UserReport::where('status', UserReport::CONFIRMED)->count(),
                'dismissed' => UserReport::where('status', UserReport::DISMISSED)->count(),
            ],
        ]);
    }

    public function update(Request $request, UserReport $userReport, ModerationSanctionService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'enforcement_action' => ['nullable', Rule::requiredIf(fn () => $request->string('resolution')->toString() === UserReport::CONFIRMED), Rule::in(ModerationSanctionService::ACTIONS)],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->string('resolution')->toString() === UserReport::CONFIRMED)],
        ]);
        $moderation->resolveUserReport($userReport, $request->user(), $validated['resolution'], $validated['enforcement_action'] ?? null, trim((string) ($validated['note'] ?? '')));
        return back()->with('success', $validated['resolution'] === UserReport::PENDING ? 'Bildirim yeniden incelemeye alındı ve bu bildirime bağlı yaptırım kaldırıldı.' : 'Kullanıcı bildirimi kararı ve yaptırımı kaydedildi.');
    }
}
