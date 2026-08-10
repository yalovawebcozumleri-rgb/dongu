<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\AdvertisementPlacementSetting;
use App\Models\Listing;
use App\Models\RewardedAdClaim;
use App\Services\RewardedListingBoostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RewardedListingBoostController extends Controller
{
    public function challenge(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->user_id === $request->user()->id, 403, 'Yalnızca kendi ilanını öne çıkarabilirsin.');
        abort_unless($listing->status === Listing::STATUS_ACTIVE && (! $listing->expires_at || $listing->expires_at->isFuture()), 422, 'Yalnızca yayındaki ilanlar öne çıkarılabilir.');
        abort_if($listing->boosted_until?->isFuture(), 422, 'İlanın zaten öne çıkarılmış durumda.');

        $setting = AdvertisementPlacementSetting::forKey('listing_rewarded_boost');
        abort_unless($setting->enabled, 422, 'İlan öne çıkarma şu anda kullanıma kapalı.');
        $dailyLimit = max(1, (int) data_get($setting->settings, 'daily_limit', 3));
        $boostHours = max(1, (int) data_get($setting->settings, 'boost_hours', 24));
        abort_if(
            RewardedAdClaim::where('user_id', $request->user()->id)->where('created_at', '>=', now()->subDay())->count() >= $dailyLimit,
            429,
            "24 saat içinde en fazla {$dailyLimit} öne çıkarma reklamı başlatabilirsin.",
        );

        $token = Str::random(64);
        RewardedAdClaim::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json(['data' => [
            'token' => $token,
            'expiresAt' => now()->addMinutes(30)->toIso8601String(),
            'clientCompletionAllowed' => app()->environment(['local', 'testing']),
            'testMode' => config('advertising.admob.mode') === 'test',
            'boostHours' => $boostHours,
            'dailyLimit' => $dailyLimit,
            'adMobAndroidUnitId' => $setting->admob_android_unit_id,
            'adMobIosUnitId' => $setting->admob_ios_unit_id,
        ]]);
    }

    public function complete(Request $request, Listing $listing, RewardedListingBoostService $boosts): ListingResource
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Canlı ortamda reklam ödülü Google tarafından doğrulanır.');
        abort_unless($listing->user_id === $request->user()->id, 403);
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $claim = RewardedAdClaim::where('token_hash', hash('sha256', $validated['token']))
            ->where('user_id', $request->user()->id)->where('listing_id', $listing->id)->firstOrFail();
        abort_if($claim->expires_at->isPast(), 422, 'Reklam ödülü süresi doldu. Lütfen yeniden dene.');

        return new ListingResource($boosts->grant($claim, false));
    }

    public function status(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->user_id === $request->user()->id, 403);
        $setting = AdvertisementPlacementSetting::forKey('listing_rewarded_boost');

        return response()->json(['data' => [
            'isBoosted' => $listing->boosted_until?->isFuture() ?? false,
            'boostedUntil' => $listing->boosted_until?->toIso8601String(),
            'enabled' => $setting->enabled,
            'boostHours' => max(1, (int) data_get($setting->settings, 'boost_hours', 24)),
            'dailyLimit' => max(1, (int) data_get($setting->settings, 'daily_limit', 3)),
        ]]);
    }

    public function callback(Request $request, RewardedListingBoostService $boosts): JsonResponse
    {
        abort_unless($this->validGoogleSignature($request), 403, 'Geçersiz reklam doğrulama imzası.');
        $token = (string) $request->query('custom_data');
        $claim = RewardedAdClaim::where('token_hash', hash('sha256', $token))->first();
        if (! $claim) {
            return response()->json(['data' => ['verified' => true, 'rewardGranted' => false]]);
        }
        abort_if($claim->expires_at->isPast(), 422, 'Ödül isteğinin süresi dolmuş.');
        $transactionId = (string) $request->query('transaction_id');
        if ($transactionId !== '' && RewardedAdClaim::where('transaction_id', $transactionId)->whereKeyNot($claim->id)->exists()) {
            return response()->json(['data' => ['verified' => true]]);
        }
        $listing = $boosts->grant($claim, true, $transactionId ?: null);

        return response()->json(['data' => ['verified' => true, 'listingId' => $listing->id]]);
    }

    private function validGoogleSignature(Request $request): bool
    {
        $raw = (string) $request->server('QUERY_STRING', '');
        $signaturePosition = strpos($raw, '&signature=');
        if ($signaturePosition === false || ! preg_match('/&signature=([^&]+)&key_id=([^&]+)$/', $raw, $signatureMatch)) return false;
        $signedQuery = substr($raw, 0, $signaturePosition);
        $encodedSignature = strtr(rawurldecode($signatureMatch[1]), '-_', '+/');
        $encodedSignature .= str_repeat('=', (4 - strlen($encodedSignature) % 4) % 4);
        $signature = base64_decode($encodedSignature, true);
        $keyId = (string) $request->query('key_id');
        if ($signature === false || $keyId === '') return false;
        $keys = Cache::remember('admob.ssv.verifier_keys', now()->addHours(12), fn () => Http::timeout(5)->get('https://www.gstatic.com/admob/reward/verifier-keys.json')->throw()->json('keys', []));
        $key = collect($keys)->first(fn ($item) => (string) ($item['keyId'] ?? '') === $keyId);

        return is_array($key) && isset($key['pem']) && openssl_verify($signedQuery, $signature, $key['pem'], OPENSSL_ALGO_SHA256) === 1;
    }
}
