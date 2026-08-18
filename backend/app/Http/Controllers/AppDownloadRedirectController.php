<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppDownloadRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $userAgent = $request->userAgent() ?? '';
        $clientPlatform = strtolower(trim((string) $request->header('Sec-CH-UA-Platform'), '"'));

        $target = match (true) {
            $clientPlatform === 'android',
            str_contains(strtolower($userAgent), 'android') => config('stores.google_play_url'),
            $clientPlatform === 'ios',
            preg_match('/iphone|ipad|ipod/i', $userAgent) === 1,
            preg_match('/macintosh/i', $userAgent) === 1 && preg_match('/mobile/i', $userAgent) === 1 => config('stores.app_store_url'),
            default => null,
        };

        $response = is_string($target) && filter_var($target, FILTER_VALIDATE_URL)
            ? redirect()->away($target)
            : redirect()->route('marketing.mobile-app');

        return $response->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Vary' => 'User-Agent, Sec-CH-UA-Platform',
        ]);
    }
}
