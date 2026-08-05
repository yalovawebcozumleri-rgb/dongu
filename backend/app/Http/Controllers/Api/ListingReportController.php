<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListingReportController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_if($listing->user_id === $request->user()->id, 422, 'Kendi ilanını bildiremezsin.');
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['misleading', 'prohibited', 'spam', 'duplicate', 'wrong_location', 'other'])],
            'details' => ['nullable', 'string', 'max:500'],
        ]);
        $report = ListingReport::firstOrCreate([
            'listing_id' => $listing->id,
            'reporter_id' => $request->user()->id,
        ], [
            'reason' => $validated['reason'],
            'details' => trim((string) ($validated['details'] ?? '')) ?: null,
        ]);

        return response()->json([
            'data' => ['reported' => true],
            'message' => $report->wasRecentlyCreated ? 'İlan bildirimin inceleme sırasına alındı.' : 'Bu ilanı daha önce bildirdin; mevcut bildirimin inceleniyor.',
        ], $report->wasRecentlyCreated ? 201 : 200);
    }
}
