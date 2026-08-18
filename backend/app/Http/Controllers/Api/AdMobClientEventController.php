<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdMobClientEventController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'environment' => ['required', Rule::in(['test', 'production', 'unknown'])],
            'format' => ['required', Rule::in(['sdk', 'consent', 'native', 'interstitial', 'rewarded'])],
            'placement' => ['nullable', 'string', 'max:100'],
            'unitId' => ['nullable', 'string', 'max:100'],
            'appVersion' => ['nullable', 'string', 'max:30'],
            'buildVersion' => ['nullable', 'string', 'max:30'],
            'errorCode' => ['nullable', 'string', 'max:100'],
            'errorDomain' => ['nullable', 'string', 'max:120'],
            'errorMessage' => ['nullable', 'string', 'max:500'],
            'consentStatus' => ['nullable'],
            'canRequestAds' => ['nullable', 'boolean'],
            'adapterCount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'slotIndex' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $context = array_merge($validated, [
            'userId' => $request->user()?->id,
            'ipHash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'userAgent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        $isFailure = str_contains($validated['event'], 'failed')
            || str_contains($validated['event'], 'blocked')
            || str_contains($validated['event'], 'unavailable')
            || str_contains($validated['event'], 'timeout');

        Log::channel('admob')->log($isFailure ? 'warning' : 'info', 'mobile_ad_event', $context);

        return response()->json(['accepted' => true], 202);
    }
}