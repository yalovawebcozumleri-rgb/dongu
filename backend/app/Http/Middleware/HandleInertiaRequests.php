<?php

namespace App\Http\Middleware;

use App\Models\CycleRiskCase;
use App\Models\ListingReport;
use App\Models\MessageReport;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
            ],
            'adminNavigationCounts' => fn () => $request->user()?->isAdmin() ? [
                'messageReports' => MessageReport::where('status', MessageReport::PENDING)->count(),
                'listingReports' => ListingReport::where('status', ListingReport::PENDING)->count(),
                'userReports' => UserReport::where('status', UserReport::PENDING)->count(),
                'cycleRiskCases' => CycleRiskCase::where('status', CycleRiskCase::PENDING)->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
