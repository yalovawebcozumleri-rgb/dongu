<?php

namespace App\Http\Controllers;

use App\Models\AppDownloadClickDaily;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AppDownloadRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $userAgent = $request->userAgent() ?? '';
        $clientPlatform = strtolower(trim((string) $request->header('Sec-CH-UA-Platform'), '"'));
        $isAndroid = $clientPlatform === 'android' || str_contains(strtolower($userAgent), 'android');
        $isIos = $clientPlatform === 'ios'
            || preg_match('/iphone|ipad|ipod/i', $userAgent) === 1
            || (preg_match('/macintosh/i', $userAgent) === 1 && preg_match('/mobile/i', $userAgent) === 1);

        $target = match (true) {
            $isAndroid && config('stores.google_play_available') => config('stores.google_play_url'),
            $isIos && config('stores.app_store_available') => config('stores.app_store_url'),
            default => null,
        };

        if (! $this->isPreviewBot($userAgent)) {
            try {
                AppDownloadClickDaily::record(
                    CarbonImmutable::now('Europe/Istanbul')->toDateString(),
                    $this->platform($isAndroid, $isIos, $userAgent),
                    $this->destination($target, $isAndroid, $isIos),
                    $this->source($request)
                );
            } catch (Throwable $exception) {
                // Analytics must never prevent a visitor from reaching the store.
                Log::warning('App download click could not be recorded.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $response = is_string($target) && filter_var($target, FILTER_VALIDATE_URL)
            ? redirect()->away($target)
            : redirect()->route('marketing.mobile-app', $isIos ? ['platform' => 'ios'] : []);

        return $response->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Vary' => 'User-Agent, Sec-CH-UA-Platform',
        ]);
    }

    private function platform(bool $isAndroid, bool $isIos, string $userAgent): string
    {
        if ($isAndroid) {
            return 'android';
        }
        if ($isIos) {
            return 'ios';
        }

        return preg_match('/windows|macintosh|linux|cros/i', $userAgent) === 1 ? 'desktop' : 'other';
    }

    private function destination(mixed $target, bool $isAndroid, bool $isIos): string
    {
        if (! is_string($target) || ! filter_var($target, FILTER_VALIDATE_URL)) {
            return 'landing_page';
        }

        return match (true) {
            $isAndroid => 'google_play',
            $isIos => 'app_store',
            default => 'landing_page',
        };
    }

    private function source(Request $request): string
    {
        $provided = $request->query('source', $request->query('utm_source'));
        if (is_string($provided) && trim($provided) !== '') {
            return Str::limit(Str::slug($provided, '_'), 80, '');
        }

        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        return match (true) {
            $host === '' => 'direct',
            $host === 'dongu.yalovawebcozumleri.com' => 'dongu_website',
            str_ends_with($host, 'facebook.com') => 'facebook',
            str_ends_with($host, 'instagram.com') => 'instagram',
            str_ends_with($host, 'youtube.com') || $host === 'youtu.be' => 'youtube',
            str_ends_with($host, 'google.com') => 'google',
            default => Str::limit(Str::slug($host, '_'), 80, ''),
        };
    }

    private function isPreviewBot(string $userAgent): bool
    {
        return preg_match('/bot|crawler|spider|slurp|facebookexternalhit|whatsapp|telegrambot|discordbot|preview/i', $userAgent) === 1;
    }
}
