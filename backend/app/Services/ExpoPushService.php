<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExpoPushService
{
    /** @param array<int, PushToken> $tokens */
    public function send(array $tokens, string $title, string $body, array $data, string $channelId, ?string $collapseId = null): int
    {
        $sent = 0;
        foreach (array_chunk($tokens, 100) as $chunk) {
            $messages = array_map(fn (PushToken $token) => [
                'to' => $token->token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'channelId' => $channelId,
                'priority' => 'high',
            ] + ($collapseId ? [
                'collapseId' => $collapseId,
                'tag' => $collapseId,
            ] : []), $chunk);

            $request = Http::acceptJson()->timeout(12)->retry(2, 400, throw: false);
            if ($accessToken = config('services.expo.access_token')) {
                $request = $request->withToken($accessToken);
            }
            $response = $request->post(config('services.expo.endpoint'), $messages);
            if (! $response->successful()) {
                throw new RuntimeException('Expo push servisi HTTP '.$response->status().' döndürdü.');
            }

            $tickets = $response->json('data');
            if (! is_array($tickets) || count($tickets) !== count($chunk)) {
                throw new RuntimeException('Expo push servisi geçersiz yanıt döndürdü.');
            }
            foreach ($chunk as $index => $token) {
                $ticket = $tickets[$index] ?? [];
                if (($ticket['status'] ?? null) === 'ok') {
                    $token->update(['failure_count' => 0, 'last_error' => null, 'last_failed_at' => null, 'last_used_at' => now()]);
                    $sent++;
                    continue;
                }
                $error = (string) ($ticket['details']['error'] ?? $ticket['message'] ?? 'unknown_push_error');
                $failures = $token->failure_count + 1;
                $token->update([
                    'failure_count' => $failures,
                    'last_error' => mb_substr($error, 0, 160),
                    'last_failed_at' => now(),
                    'revoked_at' => $error === 'DeviceNotRegistered' || $failures >= 3 ? now() : null,
                ]);
            }
        }
        return $sent;
    }
}
