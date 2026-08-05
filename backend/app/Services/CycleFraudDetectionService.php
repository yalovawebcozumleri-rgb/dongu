<?php

namespace App\Services;

use App\Models\PickupRequest;

class CycleFraudDetectionService
{
    public function assess(PickupRequest $pickupRequest, int $points): array
    {
        $rules = [];
        $pairBase = PickupRequest::query()->whereKeyNot($pickupRequest->id)->where('status', PickupRequest::COMPLETED)
            ->where('buyer_id', $pickupRequest->buyer_id)->where('seller_id', $pickupRequest->seller_id);
        $pair24h = (clone $pairBase)->where('completed_at', '>=', now()->subDay())->count();
        $pair7d = (clone $pairBase)->where('completed_at', '>=', now()->subDays(7))->count();
        if ($pair24h >= 2) $rules[] = $this->rule('same_pair_24h_high', 'Aynı alıcı ve satıcı 24 saatte en az 3 işlem tamamladı.', 70);
        elseif ($pair24h >= 1) $rules[] = $this->rule('same_pair_24h', 'Aynı alıcı ve satıcı 24 saat içinde işlemi tekrarladı.', 35);
        if ($pair7d >= 4) $rules[] = $this->rule('same_pair_7d', 'Aynı alıcı ve satıcı 7 günde en az 5 işlem tamamladı.', 50);

        $buyer24h = $this->userVelocity($pickupRequest->buyer_id, $pickupRequest->id);
        $seller24h = $this->userVelocity($pickupRequest->seller_id, $pickupRequest->id);
        $velocity = max($buyer24h, $seller24h);
        if ($velocity >= 5) $rules[] = $this->rule('user_velocity_24h_high', 'Bir kullanıcı 24 saatte 6 veya daha fazla teslimata ulaştı.', 55);
        elseif ($velocity >= 3) $rules[] = $this->rule('user_velocity_24h', 'Bir kullanıcı 24 saatte 4 veya daha fazla teslimata ulaştı.', 25);

        if ($points >= 500) $rules[] = $this->rule('maximum_points', 'İşlem tek teslimat için azami puana ulaştı.', 40);
        elseif ($points >= 400) $rules[] = $this->rule('high_points', 'İşlem olağan dışı yüksek ambalaj adedi içeriyor.', 25);

        $secondsToComplete = $pickupRequest->accepted_at && $pickupRequest->completed_at
            ? $pickupRequest->accepted_at->diffInSeconds($pickupRequest->completed_at)
            : null;
        if ($secondsToComplete !== null && $secondsToComplete <= 60) {
            $rules[] = $this->rule('instant_completion', 'Rezervasyon kabul edildikten sonraki 60 saniye içinde tamamlandı.', 30);
        }

        $score = min(100, array_sum(array_column($rules, 'score')));
        return [
            'score' => $score,
            'severity' => $score >= 70 ? 'high' : ($score >= 30 ? 'medium' : 'low'),
            'reviewRequired' => $score >= (int) config('marketplace.cycle_risk_review_score', 30),
            'rules' => $rules,
            'evidence' => [
                'pairCompleted24hBefore' => $pair24h, 'pairCompleted7dBefore' => $pair7d,
                'buyerCompleted24hBefore' => $buyer24h, 'sellerCompleted24hBefore' => $seller24h,
                'points' => $points, 'secondsFromAcceptToComplete' => $secondsToComplete,
            ],
        ];
    }

    private function userVelocity(int $userId, int $excludeId): int
    {
        return PickupRequest::query()->whereKeyNot($excludeId)->where('status', PickupRequest::COMPLETED)
            ->where('completed_at', '>=', now()->subDay())
            ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))->count();
    }

    private function rule(string $code, string $label, int $score): array
    {
        return ['code' => $code, 'label' => $label, 'score' => $score];
    }
}
