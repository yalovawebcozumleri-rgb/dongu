<?php

namespace App\Services;

use App\Models\AdvertisementPlacementSetting;
use App\Models\RewardedAdClaim;
use App\Models\RewardedUsageGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RewardedUsageGrantService
{
    public const DEFINITIONS = [
        'listing_daily' => ['label' => 'İlan oluşturma', 'unit' => 'ilan hakkı'],
        'active_listing' => ['label' => 'Aktif ilan kontenjanı', 'unit' => 'aktif ilan yeri'],
        'pickup_daily' => ['label' => 'Alım talebi gönderme', 'unit' => 'talep hakkı'],
        'active_pickup' => ['label' => 'Aktif alım talebi kontenjanı', 'unit' => 'aktif talep yeri'],
        'listing_pending_pickup' => ['label' => 'Dolu ilana alım talebi', 'unit' => 'talep hakkı'],
        'contact_daily' => ['label' => 'Yeni görüşme başlatma', 'unit' => 'görüşme hakkı'],
        'message_conversation_daily' => ['label' => 'Mesaj amaçlı görüşme', 'unit' => 'görüşme hakkı'],
        'same_seller_contact_daily' => ['label' => 'Aynı satıcıyla yeni görüşme', 'unit' => 'görüşme hakkı'],
        'contact_cooldown' => ['label' => 'Görüşme bekleme süresini geç', 'unit' => 'bekleme muafiyeti'],
        'message_minute' => ['label' => 'Dakikalık mesajlaşma', 'unit' => 'mesaj hakkı'],
        'message_hour' => ['label' => 'Saatlik mesajlaşma', 'unit' => 'mesaj hakkı'],
        'message_daily' => ['label' => 'Günlük mesajlaşma', 'unit' => 'mesaj hakkı'],
        'unanswered_message' => ['label' => 'Yanıt beklerken mesajlaşma', 'unit' => 'mesaj hakkı'],
    ];

    public function setting(): AdvertisementPlacementSetting
    {
        return AdvertisementPlacementSetting::forKey('rewarded_extra_rights');
    }

    public function rewardConfig(string $rewardKey, bool $requireEnabled = true): ?array
    {
        if (! isset(self::DEFINITIONS[$rewardKey])) return null;
        $setting = $this->setting();
        $config = data_get($setting->settings, "rewards.{$rewardKey}");
        if (! is_array($config)) return null;
        if ($requireEnabled && (! $setting->enabled || ! ($config['enabled'] ?? false))) return null;

        return [
            'enabled' => true,
            'amount' => max(1, (int) ($config['amount'] ?? 1)),
            'dailyLimit' => max(1, (int) ($config['daily_limit'] ?? 1)),
            'validHours' => max(1, (int) ($config['valid_hours'] ?? 24)),
            ...self::DEFINITIONS[$rewardKey],
        ];
    }

    public function balance(User $user, string $rewardKey): int
    {
        return (int) RewardedUsageGrant::query()
            ->where('user_id', $user->id)
            ->where('reward_key', $rewardKey)
            ->where('expires_at', '>', now())
            ->sum('remaining_amount');
    }

    public function bonus(User $user, string $rewardKey): int
    {
        return $this->balance($user, $rewardKey);
    }


    public function consume(User $user, string $rewardKey, int $amount = 1): bool
    {
        $remaining = max(1, $amount);
        $grants = RewardedUsageGrant::query()
            ->where('user_id', $user->id)
            ->where('reward_key', $rewardKey)
            ->where('remaining_amount', '>', 0)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($grants->sum('remaining_amount') < $remaining) return false;

        foreach ($grants as $grant) {
            if ($remaining === 0) break;
            $used = min($remaining, $grant->remaining_amount);
            $grant->update(['remaining_amount' => $grant->remaining_amount - $used]);
            $remaining -= $used;
        }

        return true;
    }


    public function offer(User $user, string $rewardKey): ?array
    {
        $config = $this->rewardConfig($rewardKey);
        if (! $config) return null;
        $query = RewardedAdClaim::query()
            ->where('user_id', $user->id)
            ->where('reward_key', $rewardKey)
            ->whereIn('status', [RewardedAdClaim::REWARDED, RewardedAdClaim::VERIFIED])
            ->where('rewarded_at', '>', now()->subDay());
        $used = $query->count();
        $oldest = $used >= $config['dailyLimit'] ? $query->oldest('rewarded_at')->first()?->rewarded_at : null;

        return [
            'rewardKey' => $rewardKey,
            'label' => $config['label'],
            'unit' => $config['unit'],
            'amount' => $config['amount'],
            'dailyLimit' => $config['dailyLimit'],
            'adsUsed' => $used,
            'adsRemaining' => max(0, $config['dailyLimit'] - $used),
            'validHours' => $config['validHours'],
            'activeBonus' => $this->balance($user, $rewardKey),
            'available' => $used < $config['dailyLimit'],
            'nextAvailableAt' => $oldest?->copy()->addDay()->toIso8601String(),
        ];
    }

    public function grant(RewardedAdClaim $claim, bool $verified, ?string $transactionId = null): RewardedUsageGrant
    {
        return DB::transaction(function () use ($claim, $verified, $transactionId): RewardedUsageGrant {
            $locked = RewardedAdClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $existing = RewardedUsageGrant::query()->where('rewarded_ad_claim_id', $locked->id)->first();
            if ($existing) {
                if ($verified && $locked->status !== RewardedAdClaim::VERIFIED) {
                    $locked->update([
                        'status' => RewardedAdClaim::VERIFIED,
                        'transaction_id' => $transactionId ?: $locked->transaction_id,
                        'verified_at' => now(),
                    ]);
                }

                return $existing;
            }
            // Once Google confirms the ad, a later admin toggle must not invalidate the earned reward.
            $config = $this->rewardConfig((string) $locked->reward_key, false);
            abort_unless($config, 422, 'Bu ek hak artık kullanılamıyor.');
            // Serialize reward grants per user so parallel completions cannot bypass the admin daily cap.
            User::query()->lockForUpdate()->findOrFail($locked->user_id);

            $rewardedInLastDay = RewardedAdClaim::query()
                ->where('user_id', $locked->user_id)
                ->where('reward_key', $locked->reward_key)
                ->where('id', '!=', $locked->id)
                ->whereIn('status', [RewardedAdClaim::REWARDED, RewardedAdClaim::VERIFIED])
                ->where('rewarded_at', '>', now()->subDay())
                ->count();
            abort_if(
                $rewardedInLastDay >= $config['dailyLimit'],
                429,
                'Bu hak için son 24 saatte izleyebileceğin reklam sınırına ulaştın.',
            );
            $locked->update([
                'status' => $verified ? RewardedAdClaim::VERIFIED : RewardedAdClaim::REWARDED,
                'transaction_id' => $transactionId ?: $locked->transaction_id,
                'rewarded_at' => $locked->rewarded_at ?: now(),
                'verified_at' => $verified ? now() : $locked->verified_at,
            ]);

            return RewardedUsageGrant::create([
                'user_id' => $locked->user_id,
                'rewarded_ad_claim_id' => $locked->id,
                'reward_key' => $locked->reward_key,
                'amount' => max(1, (int) $locked->reward_amount),
                'remaining_amount' => max(1, (int) $locked->reward_amount),
                'expires_at' => now()->addHours($config['validHours']),
            ]);
        });
    }
}