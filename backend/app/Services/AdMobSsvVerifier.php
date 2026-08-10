<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdMobSsvVerifier
{
    public function verify(Request $request): bool
    {
        $raw = (string) $request->server('QUERY_STRING', '');
        $signaturePosition = strpos($raw, '&signature=');
        if ($signaturePosition === false || ! preg_match('/&signature=([^&]+)&key_id=([^&]+)$/', $raw, $matches)) return false;
        $signedQuery = substr($raw, 0, $signaturePosition);
        $encodedSignature = strtr(rawurldecode($matches[1]), '-_', '+/');
        $encodedSignature .= str_repeat('=', (4 - strlen($encodedSignature) % 4) % 4);
        $signature = base64_decode($encodedSignature, true);
        $keyId = rawurldecode($matches[2]);
        if ($signature === false || $keyId === '') return false;
        try {
            $keys = Cache::remember('admob.ssv.verifier_keys', now()->addHours(12), fn () => Http::timeout(5)->get('https://www.gstatic.com/admob/reward/verifier-keys.json')->throw()->json('keys', []));
        } catch (\Throwable) {
            return false;
        }
        $key = collect($keys)->first(fn ($item) => (string) ($item['keyId'] ?? '') === $keyId);

        return is_array($key) && isset($key['pem']) && openssl_verify($signedQuery, $signature, $key['pem'], OPENSSL_ALGO_SHA256) === 1;
    }
}