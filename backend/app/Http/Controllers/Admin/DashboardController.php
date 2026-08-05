<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementCampaign;
use App\Models\CycleRiskCase;
use App\Models\Listing;
use App\Models\ListingMaterial;
use App\Models\ListingReport;
use App\Models\MessageReport;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserReport;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $listings = Listing::query()
            ->with([
                'seller:id,name,email', 'materials',
                'pickupRequests' => fn ($query) => $query
                    ->whereIn('status', [PickupRequest::ACCEPTED, PickupRequest::COMPLETED])
                    ->with('buyer:id,name,email')->latest('id'),
            ])
            ->latest('id')->limit(10)->get()
            ->map(function (Listing $listing) {
                $transaction = $listing->pickupRequests->first();
                return [
                    'id' => $listing->id,
                    'seller' => $listing->seller?->only('id', 'name', 'email'),
                    'buyer' => $transaction?->buyer?->only('id', 'name', 'email'),
                    'status' => $listing->status,
                    'public_area' => $listing->public_area,
                    'materials' => $listing->materials->map(fn ($material) => [
                        'type' => $material->type, 'quantity' => $material->quantity,
                    ]),
                    'published_at' => $listing->published_at?->format('d.m.Y H:i'),
                ];
            });

        $statusCounts = Listing::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $pendingModeration = MessageReport::where('status', MessageReport::PENDING)->count()
            + ListingReport::where('status', ListingReport::PENDING)->count()
            + UserReport::where('status', UserReport::PENDING)->count()
            + CycleRiskCase::where('status', CycleRiskCase::PENDING)->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::where('role', User::ROLE_USER)->count(),
                'activeListings' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'completedTransactions' => PickupRequest::where('status', PickupRequest::COMPLETED)->count(),
                'materials' => ListingMaterial::sum('quantity'),
                'pendingModeration' => $pendingModeration,
                'scheduledAnnouncements' => AnnouncementCampaign::whereIn('status', [AnnouncementCampaign::STATUS_SCHEDULED, AnnouncementCampaign::STATUS_SENDING])->count(),
            ],
            'listingStatusCounts' => [
                'active' => (int) ($statusCounts[Listing::STATUS_ACTIVE] ?? 0),
                'reserved' => (int) ($statusCounts[Listing::STATUS_RESERVED] ?? 0),
                'completed' => (int) ($statusCounts[Listing::STATUS_COMPLETED] ?? 0),
                'cancelled' => (int) ($statusCounts[Listing::STATUS_CANCELLED] ?? 0),
            ],
            'listings' => $listings,
        ]);
    }
}
