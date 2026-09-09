<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Listing;
use App\Models\Province;
use App\Services\ModerationSanctionService;
use Illuminate\Http\Response;

class ListingPreviewController extends Controller
{
    public function index(ModerationSanctionService $sanctions): Response
    {
        $labels = ['pet' => 'PET', 'glass' => 'Cam', 'aluminum' => 'Alüminyum'];
        $listings = Listing::query()->with(['materials', 'seller:id,status'])
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('published_at')
            ->limit(60)
            ->get(['id', 'user_id', 'province_id', 'district_id', 'status', 'published_at', 'expires_at'])
            ->filter(fn (Listing $listing) => $listing->seller?->status === 'active'
                && ! $sanctions->activeFor($listing->seller, 'account_suspension_'))
            ->take(24)
            ->map(fn (Listing $listing) => $this->safeSummary($listing, $labels))
            ->values()
            ->all();

        return response()->view('marketing.listings-index', ['listings' => $listings])
            ->header('Cache-Control', 'public, max-age=60')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function __invoke(string $id, ModerationSanctionService $sanctions): Response
    {
        $listing = Listing::query()->with(['materials', 'seller:id,status'])
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->find($id, ['id', 'user_id', 'province_id', 'district_id', 'status', 'published_at', 'expires_at']);

        $preview = null;
        $relatedListings = [];
        if ($listing && $listing->seller?->status === 'active'
            && ! $sanctions->activeFor($listing->seller, 'account_suspension_')) {
            $labels = ['pet' => 'PET', 'glass' => 'Cam', 'aluminum' => 'Alüminyum'];
            $preview = $this->safeSummary($listing, $labels);

            $relatedListings = Listing::query()->with(['materials', 'seller:id,status'])
                ->whereKeyNot($listing->id)
                ->where('province_id', $listing->province_id)
                ->where('status', Listing::STATUS_ACTIVE)
                ->whereNotNull('published_at')->where('published_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByRaw('CASE WHEN district_id = ? THEN 0 ELSE 1 END', [$listing->district_id ?? 0])
                ->latest('published_at')
                ->limit(12)
                ->get(['id', 'user_id', 'province_id', 'district_id', 'status', 'published_at', 'expires_at'])
                ->filter(fn (Listing $candidate) => $candidate->seller?->status === 'active'
                    && ! $sanctions->activeFor($candidate->seller, 'account_suspension_'))
                ->take(3)
                ->map(fn (Listing $candidate) => $this->safeSummary($candidate, $labels))
                ->values()
                ->all();
        }

        // Explicit allowlist; never pass the Eloquent listing/user to the view.
        return response()->view('marketing.listing-preview', [
            'preview' => $preview,
            'relatedListings' => $relatedListings,
        ], $preview ? 200 : 404)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, noarchive')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function safeSummary(Listing $listing, array $labels): array
    {
        $items = $listing->materials->map(fn ($item) => [
            'type' => $item->type,
            'label' => $labels[$item->type] ?? 'Ambalaj',
            'quantity' => (int) $item->quantity,
            'price' => number_format((float) $item->unit_price, 2, ',', '.'),
            'total' => number_format($item->quantity * (float) $item->unit_price, 2, ',', '.'),
        ]);

        // Only structured province/district names. Never expose the seller,
        // public_area, description or photos: these may contain private data.
        $province = Province::find($listing->province_id);
        $district = $province ? District::where('province_id', $province->id)->find($listing->district_id) : null;

        return [
            'id' => $listing->id,
            'quantity' => $items->sum('quantity'),
            'materials' => $items->pluck('label')->join(' · '),
            'title_materials' => $items->pluck('label')->join(', ', ' ve '),
            'area' => collect([$district?->name, $province?->name])->filter()->join(' / ') ?: 'Bölge bilgisi uygulamada',
            'items' => $items->all(),
            'total' => number_format($listing->materials->sum(fn ($item) => $item->quantity * (float) $item->unit_price), 2, ',', '.'),
        ];
    }
}
