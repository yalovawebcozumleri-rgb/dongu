<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^(Exponent|Expo)PushToken\[[^\]]+\]$/'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);
        $token = PushToken::updateOrCreate(['token' => $validated['token']], [
            'user_id' => $request->user()->id,
            'platform' => $validated['platform'],
            'device_id' => $validated['device_id'] ?? null,
            'last_used_at' => now(),
            'revoked_at' => null,
        ]);
        return response()->json(['data' => ['registered' => true]], $token->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:255']]);
        PushToken::query()->where('user_id', $request->user()->id)->where('token', $validated['token'])->update(['revoked_at' => now()]);
        return response()->json(['data' => ['registered' => false]]);
    }
}
