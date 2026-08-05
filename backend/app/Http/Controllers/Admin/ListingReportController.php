<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingReport;
use App\Services\ListingReportModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ListingReportController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'reason' => ['nullable', Rule::in(['misleading', 'prohibited', 'spam', 'duplicate', 'wrong_location', 'other'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);
        $reports = ListingReport::query()
            ->with(['listing.seller:id,name,email', 'listing.materials', 'reporter:id,name,email', 'resolvedBy:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = trim($search);
                $query->where(fn ($query) => $query->where('id', 'like', "%{$term}%")->orWhere('listing_id', 'like', "%{$term}%")
                    ->orWhereHas('listing', fn ($listing) => $listing->where('public_area', 'like', "%{$term}%")->orWhereHas('seller', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                    ->orWhereHas('reporter', fn ($user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
            })
            ->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/ListingReports/Index', [
            'reports' => $reports,
            'filters' => ['status' => $filters['status'] ?? '', 'reason' => $filters['reason'] ?? '', 'search' => $filters['search'] ?? '', 'per_page' => $perPage],
            'pageSizes' => self::PAGE_SIZES,
            'enforcementActions' => [
                ['value' => ListingReportModerationService::RECORD_ONLY, 'label' => 'Yalnızca denetim kaydı oluştur'],
                ['value' => ListingReportModerationService::WARN_SELLER, 'label' => 'Satıcıyı uyar'],
                ['value' => ListingReportModerationService::REMOVE_LISTING, 'label' => 'İlanı yayından kaldır'],
            ],
            'counts' => [
                'all' => ListingReport::count(),
                'pending' => ListingReport::where('status', ListingReport::PENDING)->count(),
                'confirmed' => ListingReport::where('status', ListingReport::CONFIRMED)->count(),
                'dismissed' => ListingReport::where('status', ListingReport::DISMISSED)->count(),
            ],
        ]);
    }

    public function update(Request $request, ListingReport $listingReport, ListingReportModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['pending', 'confirmed', 'dismissed'])],
            'enforcement_action' => ['nullable', Rule::requiredIf(fn () => $request->string('resolution')->toString() === ListingReport::CONFIRMED), Rule::in(ListingReportModerationService::ACTIONS)],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->string('resolution')->toString() === ListingReport::CONFIRMED)],
        ]);
        $moderation->resolve($listingReport, $request->user(), $validated['resolution'], $validated['enforcement_action'] ?? null, trim((string) ($validated['note'] ?? '')));
        return back()->with('success', $validated['resolution'] === ListingReport::PENDING ? 'Bildirim yeniden incelemeye alındı ve önceki yaptırım geri alındı.' : 'İlan bildirimi kararı ve uygulanacak işlem kaydedildi.');
    }
}
