<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\Listing;
use App\Models\MarketplaceUsageEvent;
use App\Models\MarketplaceUsagePolicy;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Exceptions\HttpResponseException;

class MarketplaceUsagePolicyService
{
    public function __construct(
        private ModerationSanctionService $moderation,
        private RewardedUsageGrantService $rewards,
    ) {}

    public function policy(): MarketplaceUsagePolicy { return MarketplaceUsagePolicy::current(); }
    public function isNewAccount(User $user, ?MarketplaceUsagePolicy $policy = null): bool { $policy ??= $this->policy(); return $user->created_at->gt(now()->subHours($policy->new_account_hours)); }

    public function assertListingAllowed(User $user, bool $consumeRewards = true): void
    {
        $policy = $this->policy();
        $new = $this->isNewAccount($user, $policy);
        $limit = $new ? $policy->new_account_listing_limit : $policy->listing_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::LISTING_CREATED, $limit, $new ? 'Yeni hesap dönemindeki ilan oluşturma hakkın doldu.' : 'Son 24 saatteki ilan oluşturma hakkın doldu.', 'listing_daily', $consumeRewards);
        $activeLimit = $policy->active_listing_limit;
        $active = Listing::query()->where('user_id', $user->id)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        if ($active >= $activeLimit) $this->requireReward($user, 'active_listing', "Aynı anda en fazla {$activeLimit} aktif ilanın olabilir.", null, $consumeRewards);
    }

    public function assertContactAllowed(User $user, User $seller, bool $messageOnly, bool $consumeRewards = true): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy();
        $new = $this->isNewAccount($user, $policy);
        $limit = $new ? $policy->new_account_contact_limit : $policy->contact_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::CONTACT_STARTED, $limit, $new ? 'Yeni hesap dönemindeki toplam görüşme hakkın doldu.' : 'Son 24 saatteki yeni görüşme hakkın doldu.', 'contact_daily', $consumeRewards);
        if ($messageOnly) {
            $messageLimit = $new ? $policy->new_account_message_conversation_limit : $policy->message_conversation_24h_limit;
            $this->assertEventLimit($user, MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $messageLimit, $new ? 'Yeni hesap dönemindeki mesaj amaçlı görüşme hakkın doldu.' : 'Son 24 saatteki mesaj amaçlı yeni görüşme hakkın doldu.', 'message_conversation_daily', $consumeRewards);
        }
        $sameSellerLimit = $policy->same_seller_contact_24h_limit;
        $sameSeller = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('target_user_id', $seller->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->where('created_at', '>', now()->subDay())->count();
        if ($sameSeller >= $sameSellerLimit) $this->requireReward($user, 'same_seller_contact_daily', 'Bu satıcıyla son 24 saat içinde yeni bir ilan görüşmesi başlattın. Mevcut sohbetinden devam edebilirsin.', null, $consumeRewards);
        $last = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->latest('created_at')->first();
        if ($last && $policy->contact_cooldown_seconds > 0 && $last->created_at->gt(now()->subSeconds($policy->contact_cooldown_seconds))) {
            $this->requireReward($user, 'contact_cooldown', 'Yeni bir görüşme başlatmadan önce kısa bir süre beklemelisin.', $last->created_at->copy()->addSeconds($policy->contact_cooldown_seconds), $consumeRewards);
        }
    }

    public function assertPickupAllowed(User $user, Listing $listing, bool $consumeRewards = true): void
    {
        $policy = $this->policy();
        $new = $this->isNewAccount($user, $policy);
        $limit = $new ? $policy->new_account_pickup_limit : $policy->pickup_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::PICKUP_REQUESTED, $limit, $new ? 'Yeni hesap dönemindeki alım talebi hakkın doldu.' : 'Son 24 saatteki alım talebi hakkın doldu.', 'pickup_daily', $consumeRewards);
        $activeLimit = $policy->active_pickup_limit;
        $active = PickupRequest::query()->where('buyer_id', $user->id)->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->count();
        if ($active >= $activeLimit) $this->requireReward($user, 'active_pickup', "Aynı anda en fazla {$activeLimit} aktif alım talebin olabilir.", null, $consumeRewards);
        $pendingLimit = $policy->listing_pending_pickup_limit;
        $pending = PickupRequest::query()->where('listing_id', $listing->id)->where('status', PickupRequest::PENDING)->count();
        if ($pending >= $pendingLimit) $this->requireReward($user, 'listing_pending_pickup', 'Bu ilan şu anda yeterli sayıda bekleyen talep aldı. Taleplerden biri sonuçlandığında yeniden deneyebilirsin.', null, $consumeRewards);
    }

    public function assertMessageAllowed(User $user, PickupRequest $pickupRequest, bool $freshConversation = false, bool $consumeRewards = true): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy();
        $windows = [
            [now()->subMinute(), $policy->messages_per_minute, 'Dakikalık mesaj sınırına ulaştın.', 60, 'message_minute'],
            [now()->subHour(), $policy->messages_per_hour, 'Saatlik mesaj sınırına ulaştın.', 3600, 'message_hour'],
            [now()->subDay(), $policy->messages_per_24h, 'Son 24 saatteki mesaj hakkın doldu.', 86400, 'message_daily'],
        ];
        foreach ($windows as [$since, $limit, $message, $windowSeconds, $rewardKey]) {
            $query = ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', $since);
            if ($query->count() >= $limit) {
                $retryAt = $query->oldest('created_at')->first()?->created_at?->copy()->addSeconds($windowSeconds);
                $this->requireReward($user, $rewardKey, $message, $retryAt, $consumeRewards);
            }
        }
        $counterpartId = $pickupRequest->buyer_id === $user->id ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
        $lastReplyId = $freshConversation
            ? (ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->max('id') ?? 0)
            : (ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $counterpartId)->where('type', 'user')->max('id') ?? 0);
        $unanswered = ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $user->id)->where('type', 'user')->where('id', '>', $lastReplyId)->count();
        $unansweredLimit = $policy->unanswered_message_limit;
        if ($unanswered >= $unansweredLimit) $this->requireReward($user, 'unanswered_message', 'Karşı tarafın yanıtını beklemelisin. Yanıt gelmeden daha fazla mesaj gönderemezsin.', null, $consumeRewards);
    }

    public function accountPeriod(User $user): array
    {
        $policy = $this->policy();
        $isNew = $this->isNewAccount($user, $policy);
        return ['isNewAccount' => $isNew, 'newAccountHours' => $policy->new_account_hours, 'newAccountEndsAt' => $isNew ? $user->created_at->copy()->addHours($policy->new_account_hours)->toIso8601String() : null];
    }

    public function interactionEligibility(User $user, Listing $listing): array
    {
        $listing->loadMissing('seller');
        $unavailable = fn (string $reason, ?string $retryAt = null): array => ['allowed' => false, 'action' => 'blocked', 'reason' => $reason, 'retryAt' => $retryAt, 'rewardOffer' => null];
        if ($listing->user_id === $user->id) { $result = $unavailable('Kendi ilanınla mesajlaşamaz veya alım talebi oluşturamazsın.'); return ['message' => $result, 'pickup' => $result]; }
        if ($listing->status !== Listing::STATUS_ACTIVE || ($listing->expires_at && $listing->expires_at->isPast())) { $result = $unavailable('Bu ilan artık yeni görüşme veya alım talebi kabul etmiyor.'); return ['message' => $result, 'pickup' => $result]; }
        if (UserBlock::existsBetween($user->id, $listing->user_id)) { $result = $unavailable('Bu kullanıcıyla ilan veya mesaj etkileşimi kuramazsın.'); return ['message' => $result, 'pickup' => $result]; }
        $existing = PickupRequest::query()->where('listing_id', $listing->id)->where('buyer_id', $user->id)->first();
        if ($existing && $existing->status !== PickupRequest::CLOSED) {
            $message = ['allowed' => true, 'action' => 'open', 'reason' => null, 'retryAt' => null, 'rewardOffer' => null, 'conversationId' => $existing->id];
            if ($existing->status === PickupRequest::PENDING) return ['message' => $message, 'pickup' => $unavailable('Bu ilan için alım talebin satıcının yanıtını bekliyor.')];
            if ($existing->status === PickupRequest::ACCEPTED) return ['message' => $message, 'pickup' => $unavailable('Bu ilan senin için rezerve edildi.')];
            if ($existing->status === PickupRequest::REJECTED) { $result = $unavailable('Satıcı bu ilan için alım talebini kabul etmedi. Bu ilan için yeniden görüşme veya talep başlatılamaz.'); return ['message' => $result, 'pickup' => $result]; }
            if ($existing->status === PickupRequest::COMPLETED) return ['message' => $message, 'pickup' => $unavailable('Bu ilanla ilgili teslimat tamamlandı.')];
        }
        $startsFresh = ! $existing || $existing->status === PickupRequest::CLOSED;
        $message = $this->checkEligibility(fn () => $this->assertContactAllowed($user, $listing->seller, true, false));
        $pickup = $this->checkEligibility(function () use ($user, $listing, $existing, $startsFresh): void {
            if ($startsFresh) $this->assertContactAllowed($user, $listing->seller, false, false);
            $this->assertPickupAllowed($user, $listing, false);
            $probe = $startsFresh ? new PickupRequest(['listing_id' => $listing->id, 'buyer_id' => $user->id, 'seller_id' => $listing->user_id, 'status' => PickupRequest::INQUIRY]) : $existing;
            $this->assertMessageAllowed($user, $probe, false, false);
        });
        return ['message' => $message, 'pickup' => $pickup];
    }

    private function checkEligibility(callable $check): array
    {
        try {
            $check();
            return ['allowed' => true, 'action' => 'start', 'reason' => null, 'retryAt' => null, 'rewardOffer' => null];
        } catch (HttpResponseException $exception) {
            $payload = json_decode($exception->getResponse()->getContent(), true) ?: [];
            return ['allowed' => false, 'action' => 'blocked', 'reason' => $payload['message'] ?? 'Bu işlem şu anda kullanılamıyor.', 'retryAt' => $payload['quota']['retryAt'] ?? null, 'rewardOffer' => $payload['quota']['rewardOffer'] ?? null];
        }
    }

    public function record(User $user, string $type, ?User $target = null, ?Listing $listing = null): void
    {
        MarketplaceUsageEvent::create(['user_id' => $user->id, 'event_type' => $type, 'target_user_id' => $target?->id, 'listing_id' => $listing?->id, 'created_at' => now()]);
    }

    public function usage(User $user): array
    {
        $policy = $this->policy();
        $new = $this->isNewAccount($user, $policy);
        $eventQuota = function (string $type, int $baseLimit, string $rewardKey) use ($user): array {
            $bonus = $this->rewards->bonus($user, $rewardKey);
            $limit = $baseLimit;
            $query = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', $type)->where('created_at', '>', now()->subDay());
            $used = $query->count();
            $oldest = $used >= $baseLimit ? $query->oldest('created_at')->first()?->created_at : null;
            return ['used' => $used, 'baseLimit' => $baseLimit, 'bonus' => $bonus, 'limit' => $limit, 'remaining' => max(0, $baseLimit - $used) + $bonus, 'nextAvailableAt' => $oldest?->copy()->addDay()->toIso8601String(), 'rewardOffer' => $this->rewards->offer($user, $rewardKey)];
        };
        $listingBase = $new ? $policy->new_account_listing_limit : $policy->listing_24h_limit;
        $pickupBase = $new ? $policy->new_account_pickup_limit : $policy->pickup_24h_limit;
        $contactBase = $new ? $policy->new_account_contact_limit : $policy->contact_24h_limit;
        $messageConversationBase = $new ? $policy->new_account_message_conversation_limit : $policy->message_conversation_24h_limit;
        $messageUsed = ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', now()->subDay())->count();
        $messageBonus = $this->rewards->bonus($user, 'message_daily');
        $messageLimit = $policy->messages_per_24h;
        $activeListingsUsed = Listing::query()->where('user_id', $user->id)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        $activeListingBonus = $this->rewards->bonus($user, 'active_listing');
        $activePickupsUsed = PickupRequest::query()->where('buyer_id', $user->id)->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->count();
        $activePickupBonus = $this->rewards->bonus($user, 'active_pickup');

        return [
            'isNewAccount' => $new,
            'newAccountEndsAt' => $new ? $user->created_at->copy()->addHours($policy->new_account_hours)->toIso8601String() : null,
            'listings' => $eventQuota(MarketplaceUsageEvent::LISTING_CREATED, $listingBase, 'listing_daily'),
            'contacts' => $eventQuota(MarketplaceUsageEvent::CONTACT_STARTED, $contactBase, 'contact_daily'),
            'messageConversations' => $eventQuota(MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $messageConversationBase, 'message_conversation_daily'),
            'pickups' => $eventQuota(MarketplaceUsageEvent::PICKUP_REQUESTED, $pickupBase, 'pickup_daily'),
            'activeListings' => ['used' => $activeListingsUsed, 'baseLimit' => $policy->active_listing_limit, 'bonus' => $activeListingBonus, 'limit' => $policy->active_listing_limit, 'remaining' => max(0, $policy->active_listing_limit - $activeListingsUsed) + $activeListingBonus, 'rewardOffer' => $this->rewards->offer($user, 'active_listing')],
            'activePickups' => ['used' => $activePickupsUsed, 'baseLimit' => $policy->active_pickup_limit, 'bonus' => $activePickupBonus, 'limit' => $policy->active_pickup_limit, 'remaining' => max(0, $policy->active_pickup_limit - $activePickupsUsed) + $activePickupBonus, 'rewardOffer' => $this->rewards->offer($user, 'active_pickup')],
            'listingPendingPickupLimit' => $policy->listing_pending_pickup_limit,
            'messages' => [
                'nextAvailableAt' => $messageUsed >= $messageLimit ? ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', now()->subDay())->oldest('created_at')->first()?->created_at?->copy()->addDay()->toIso8601String() : null,
                'used' => $messageUsed, 'baseLimit' => $policy->messages_per_24h, 'bonus' => $messageBonus, 'limit' => $messageLimit, 'remaining' => max(0, $messageLimit - $messageUsed) + $messageBonus,
                'perMinute' => $policy->messages_per_minute + $this->rewards->bonus($user, 'message_minute'),
                'perHour' => $policy->messages_per_hour + $this->rewards->bonus($user, 'message_hour'),
                'unansweredLimit' => $policy->unanswered_message_limit + $this->rewards->bonus($user, 'unanswered_message'),
                'rewardOffer' => $this->rewards->offer($user, 'message_daily'),
                'rewardOffers' => [
                    'minute' => $this->rewards->offer($user, 'message_minute'),
                    'hour' => $this->rewards->offer($user, 'message_hour'),
                    'daily' => $this->rewards->offer($user, 'message_daily'),
                    'unanswered' => $this->rewards->offer($user, 'unanswered_message'),
                ],
            ],
        ];
    }

    private function assertEventLimit(User $user, string $type, int $limit, string $message, string $rewardKey, bool $consumeRewards): void
    {
        $query = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', $type)->where('created_at', '>', now()->subDay());
        if ($query->count() >= $limit) $this->requireReward($user, $rewardKey, $message, $query->oldest('created_at')->first()?->created_at?->copy()->addDay(), $consumeRewards);
    }

    private function requireReward(User $user, string $rewardKey, string $message, $retryAt, bool $consumeRewards): void
    {
        if ($this->rewards->balance($user, $rewardKey) < 1) {
            $this->deny($user, $message, $retryAt, $rewardKey);
        }

        if ($consumeRewards && ! $this->rewards->consume($user, $rewardKey)) {
            $this->deny($user, $message, $retryAt, $rewardKey);
        }
    }


    private function deny(User $user, string $message, $retryAt = null, ?string $rewardKey = null): never
    {
        throw new HttpResponseException(response()->json(['message' => $message, 'quota' => ['retryAt' => $retryAt?->toIso8601String(), 'rewardOffer' => $rewardKey ? $this->rewards->offer($user, $rewardKey) : null]], 429));
    }
}