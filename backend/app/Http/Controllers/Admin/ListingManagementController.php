<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminListingAction;
use App\Models\Listing;
use App\Models\PickupRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ListingManagementController extends Controller
{
    private const PAGE_SIZES = [50, 100, 250, 500];
    private const STATUSES = [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED, Listing::STATUS_COMPLETED, Listing::STATUS_CANCELLED];
    private const MATERIALS = ['pet', 'glass', 'aluminum'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'material' => ['nullable', Rule::in(self::MATERIALS)],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);

        $query = Listing::query()
            ->with(['seller:id,name,email', 'materials', 'pickupRequests' => fn ($query) => $query
                ->whereIn('status', [PickupRequest::ACCEPTED, PickupRequest::COMPLETED])
                ->with('buyer:id,name,email')
                ->latest('id')])
            ->withCount(['pickupRequests as open_pickup_requests_count' => fn ($query) => $query
                ->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])]);

        $this->applyFilters($query, $filters);

        $listings = $query->latest('id')->paginate($perPage)->withQueryString()->through(fn (Listing $listing) => $this->summary($listing));
        $statusCounts = Listing::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('Admin/Listings/Index', [
            'listings' => $listings,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'region' => $filters['region'] ?? '',
                'status' => $filters['status'] ?? '',
                'material' => $filters['material'] ?? '',
                'per_page' => $perPage,
            ],
            'counts' => [
                'all' => Listing::count(),
                'active' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'reserved' => (int) ($statusCounts[Listing::STATUS_RESERVED] ?? 0),
                'completed' => (int) ($statusCounts[Listing::STATUS_COMPLETED] ?? 0),
                'cancelled' => (int) ($statusCounts[Listing::STATUS_CANCELLED] ?? 0),
            ],
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    public function show(Listing $listing): Response
    {
        $listing->load([
            'seller:id,name,email,phone,status,rating,rating_count,completed_transactions',
            'materials', 'privateLocation',
            'pickupRequests' => fn ($query) => $query->with(['buyer:id,name,email', 'seller:id,name,email'])->latest('id'),
            'reports' => fn ($query) => $query->latest('id'),
        ]);

        return Inertia::render('Admin/Listings/Show', [
            'listing' => [
                ...$this->summary($listing),
                'description' => $listing->description,
                'exact_address' => $listing->privateLocation?->address,
                'delivery_notes' => $listing->privateLocation?->delivery_notes,
                'expires_at' => $listing->expires_at?->format('d.m.Y H:i'),
                'condition_confirmed_at' => $listing->packaging_condition_confirmed_at?->format('d.m.Y H:i'),
                'seller' => $listing->seller->only('id', 'name', 'email', 'phone', 'status', 'rating', 'rating_count', 'completed_transactions'),
                'requests' => $listing->pickupRequests->map(fn (PickupRequest $pickup) => [
                    'id' => $pickup->id,
                    'status' => $pickup->status,
                    'buyer' => $pickup->buyer?->only('id', 'name', 'email'),
                    'accepted_at' => $pickup->accepted_at?->format('d.m.Y H:i'),
                    'completed_at' => $pickup->completed_at?->format('d.m.Y H:i'),
                ]),
                'reports' => $listing->reports->map(fn ($report) => [
                    'id' => $report->id, 'reason' => $report->reason, 'status' => $report->status,
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        abort_if($listing->pickupRequests()->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->exists(), 422, 'Açık alım talebi veya rezervasyonu bulunan ilan kaldırılamaz.');

        DB::transaction(function () use ($listing, $request, $validated) {
            AdminListingAction::create([
                'listing_id' => $listing->id,
                'admin_id' => $request->user()->id,
                'action' => 'removed',
                'reason' => trim($validated['reason']),
                'snapshot' => [
                    'status' => $listing->status,
                    'seller_id' => $listing->user_id,
                    'public_area' => $listing->public_area,
                    'description' => $listing->description,
                ],
            ]);
            $listing->delete();
        });

        return back()->with('success', "İlan #{$listing->id} kullanıcı ekranlarından kaldırıldı.");
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where('public_area', 'like', '%' . trim($region) . '%'))
            ->when($filters['material'] ?? null, fn (Builder $query, string $material) => $query->whereHas('materials', fn (Builder $materialQuery) => $materialQuery->where('type', $material)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term) {
                    if (ctype_digit($term)) $query->orWhereKey((int) $term);
                    $query->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('seller', fn (Builder $seller) => $seller->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                        ->orWhereHas('pickupRequests.buyer', fn (Builder $buyer) => $buyer->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            });
    }

    private function summary(Listing $listing): array
    {
        $transaction = $listing->pickupRequests->first();
        return [
            'id' => $listing->id,
            'seller' => $listing->seller?->only('id', 'name', 'email'),
            'buyer' => $transaction?->buyer?->only('id', 'name', 'email'),
            'transaction_status' => $transaction?->status,
            'status' => $listing->status,
            'public_area' => $listing->public_area,
            'materials' => $listing->materials->map(fn ($material) => [
                'type' => $material->type,
                'quantity' => $material->quantity,
                'unit_price' => (float) $material->unit_price,
            ]),
            'published_at' => $listing->published_at?->format('d.m.Y H:i'),
            'can_remove' => (int) ($listing->open_pickup_requests_count ?? 0) === 0,
        ];
    }
}
