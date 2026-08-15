<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CycleRiskCase;
use App\Services\CyclePointModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CycleRiskCaseController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'cleared', 'confirmed'])],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);
        $cases = CycleRiskCase::query()
            ->with(['pickupRequest.buyer:id,name,email', 'pickupRequest.seller:id,name,email', 'pickupRequest.listing:id,public_area', 'pointEntries:id,pickup_request_id,points,status'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('pickupRequest', fn ($pickup) => $pickup
                    ->whereHas('buyer', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('seller', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
            })
            ->orderByRaw("FIELD(status, 'pending', 'confirmed', 'cleared')")
            ->orderByDesc('risk_score')->latest('detected_at')->paginate($perPage)->withQueryString()
            ->through(fn (CycleRiskCase $case) => $this->summary($case));

        return Inertia::render('Admin/CycleRiskCases/Index', [
            'cases' => $cases,
            'filters' => ['status' => $filters['status'] ?? '', 'severity' => $filters['severity'] ?? '', 'search' => $filters['search'] ?? '', 'per_page' => $perPage],
            'pageSizes' => self::PAGE_SIZES,
            'counts' => [
                'all' => CycleRiskCase::count(),
                'pending' => CycleRiskCase::where('status', CycleRiskCase::PENDING)->count(),
                'high' => CycleRiskCase::where('status', CycleRiskCase::PENDING)->where('severity', 'high')->count(),
                'confirmed' => CycleRiskCase::where('status', CycleRiskCase::CONFIRMED)->count(),
                'cleared' => CycleRiskCase::where('status', CycleRiskCase::CLEARED)->count(),
            ],
        ]);
    }

    public function show(CycleRiskCase $cycleRiskCase): Response
    {
        $cycleRiskCase->load([
            'pickupRequest.buyer:id,name,email,created_at,completed_transactions',
            'pickupRequest.seller:id,name,email,created_at,completed_transactions',
            'pickupRequest.listing.materials', 'pointEntries', 'reviewedBy:id,name,email',
            'audits.admin:id,name,email',
        ]);
        $pickup = $cycleRiskCase->pickupRequest;
        return Inertia::render('Admin/CycleRiskCases/Show', [
            'riskCase' => [...$this->summary($cycleRiskCase),
                'rules' => $cycleRiskCase->rules, 'evidence' => $cycleRiskCase->evidence,
                'reviewNote' => $cycleRiskCase->review_note,
                'reviewedBy' => $cycleRiskCase->reviewedBy?->only('id', 'name', 'email'),
                'reviewedAt' => $cycleRiskCase->reviewed_at?->format('d.m.Y H:i'),
                'transaction' => [
                    'id' => $pickup->id, 'status' => $pickup->status,
                    'acceptedAt' => $pickup->accepted_at?->format('d.m.Y H:i:s'),
                    'completedAt' => $pickup->completed_at?->format('d.m.Y H:i:s'),
                    'buyer' => $pickup->buyer->only('id', 'name', 'email', 'created_at', 'completed_transactions'),
                    'seller' => $pickup->seller->only('id', 'name', 'email', 'created_at', 'completed_transactions'),
                    'listing' => ['id' => $pickup->listing->id, 'area' => $pickup->listing->public_area,
                        'materials' => $pickup->listing->materials->map(fn ($item) => ['type' => $item->type, 'quantity' => $item->quantity])],
                ],
                'entries' => $cycleRiskCase->pointEntries->map(fn ($entry) => [
                    'id' => $entry->id, 'userId' => $entry->user_id, 'role' => $entry->role,
                    'points' => $entry->points, 'status' => $entry->status,
                ]),
                'audits' => $cycleRiskCase->audits->sortByDesc('id')->values()->map(fn ($audit) => [
                    'id' => $audit->id, 'action' => $audit->action, 'reason' => $audit->reason,
                    'admin' => $audit->admin?->only('id', 'name', 'email'),
                    'before' => $audit->before_state, 'after' => $audit->after_state,
                    'createdAt' => $audit->created_at?->format('d.m.Y H:i:s'),
                ]),
            ],
        ]);
    }

    public function update(Request $request, CycleRiskCase $cycleRiskCase, CyclePointModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['clear', 'revoke', 'restore', 'reopen'])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'action.required' => 'Uygulanacak işlem seçilmelidir.',
            'action.in' => 'Geçerli bir inceleme işlemi seçmelisin.',
            'reason.required' => 'Yönetici notu zorunludur.',
            'reason.string' => 'Yönetici notu geçerli bir metin olmalıdır.',
            'reason.min' => 'Yönetici notu en az 10 karakter olmalıdır.',
            'reason.max' => 'Yönetici notu en fazla 1000 karakter olabilir.',
        ]);
        $moderation->resolve($cycleRiskCase, $validated['action'], trim($validated['reason']), $request->user(), $request);
        return back()->with('success', 'Puan inceleme kararı ve yönetici kaydı oluşturuldu.');
    }

    public function destroy(CycleRiskCase $cycleRiskCase): RedirectResponse
    {
        DB::transaction(function () use ($cycleRiskCase): void {
            $case = CycleRiskCase::query()->lockForUpdate()->findOrFail($cycleRiskCase->id);
            abort_if(
                $case->status === CycleRiskCase::PENDING,
                422,
                'İncelenmesi tamamlanmamış puan denetimi kaydı silinemez.'
            );

            $case->audits()->delete();
            $case->delete();
        });

        return back()->with('success', 'Sonuçlandırılmış puan denetimi kaydı kalıcı olarak silindi. Kullanıcı puanları değiştirilmedi.');
    }

    private function summary(CycleRiskCase $case): array
    {
        $pickup = $case->pickupRequest;
        return [
            'id' => $case->id, 'status' => $case->status, 'severity' => $case->severity,
            'riskScore' => $case->risk_score, 'ruleCount' => count($case->rules ?? []),
            'points' => (int) $case->pointEntries->max('points'),
            'buyer' => $pickup->buyer->only('id', 'name', 'email'),
            'seller' => $pickup->seller->only('id', 'name', 'email'),
            'listingArea' => $pickup->listing?->public_area,
            'detectedAt' => $case->detected_at?->format('d.m.Y H:i'),
        ];
    }
}
