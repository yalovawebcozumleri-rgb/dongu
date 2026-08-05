<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReportController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'Kendi profilini bildiremezsin.');
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['fake_profile', 'harassment', 'fraud', 'spam', 'inappropriate', 'other'])],
            'details' => ['nullable', 'string', 'max:500'],
        ]);
        $report = UserReport::firstOrCreate(
            ['reported_user_id' => $user->id, 'reporter_id' => $request->user()->id],
            ['reason' => $validated['reason'], 'details' => trim((string) ($validated['details'] ?? '')) ?: null]
        );

        return response()->json([
            'message' => $report->wasRecentlyCreated ? 'Kullanıcı bildirimin güvenlik ekibine iletildi.' : 'Bu kullanıcıyı daha önce bildirdin.',
            'data' => ['reported' => true],
        ], $report->wasRecentlyCreated ? 201 : 200);
    }
}
