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
    public function __construct(private ModerationSanctionService $moderation) {}

    public function policy(): MarketplaceUsagePolicy { return MarketplaceUsagePolicy::current(); }
    public function isNewAccount(User $user, ?MarketplaceUsagePolicy $policy = null): bool { $policy ??= $this->policy(); return $user->created_at->gt(now()->subHours($policy->new_account_hours)); }

    public function assertListingAllowed(User $user): void
    {
        $policy = $this->policy(); $new = $this->isNewAccount($user, $policy); $limit = $new ? $policy->new_account_listing_limit : $policy->listing_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::LISTING_CREATED, $limit, $new ? 'Yeni hesap dönemindeki ilan oluşturma hakkın doldu.' : 'Son 24 saatteki ilan oluşturma hakkın doldu.');
        $active = Listing::query()->where('user_id', $user->id)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        if ($active >= $policy->active_listing_limit) $this->deny("Aynı anda en fazla {$policy->active_listing_limit} aktif ilanın olabilir.");
    }

    public function assertContactAllowed(User $user, User $seller, bool $messageOnly): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy(); $new = $this->isNewAccount($user, $policy); $limit = $new ? $policy->new_account_contact_limit : $policy->contact_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::CONTACT_STARTED, $limit, $new ? 'Yeni hesap dönemindeki toplam görüşme hakkın doldu.' : 'Son 24 saatteki yeni görüşme hakkın doldu.');
        if ($messageOnly) { $messageLimit = $new ? $policy->new_account_message_conversation_limit : $policy->message_conversation_24h_limit; $this->assertEventLimit($user, MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $messageLimit, $new ? 'Yeni hesap dönemindeki mesaj amaçlı görüşme hakkın doldu.' : 'Son 24 saatteki mesaj amaçlı yeni görüşme hakkın doldu.'); }
        $sameSeller = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('target_user_id', $seller->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->where('created_at', '>', now()->subDay())->count();
        if ($sameSeller >= $policy->same_seller_contact_24h_limit) $this->deny('Bu satıcıyla son 24 saat içinde yeni bir ilan görüşmesi başlattın. Mevcut sohbetinden devam edebilirsin.');
        $last = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->latest('created_at')->first();
        if ($last && $policy->contact_cooldown_seconds > 0 && $last->created_at->gt(now()->subSeconds($policy->contact_cooldown_seconds))) $this->deny('Yeni bir görüşme başlatmadan önce kısa bir süre beklemelisin.', $last->created_at->copy()->addSeconds($policy->contact_cooldown_seconds));
    }

    public function assertPickupAllowed(User $user, Listing $listing): void
    {
        $policy = $this->policy(); $new = $this->isNewAccount($user, $policy); $limit = $new ? $policy->new_account_pickup_limit : $policy->pickup_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::PICKUP_REQUESTED, $limit, $new ? 'Yeni hesap dönemindeki alım talebi hakkın doldu.' : 'Son 24 saatteki alım talebi hakkın doldu.');
        $active = PickupRequest::query()->where('buyer_id', $user->id)->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->count();
        if ($active >= $policy->active_pickup_limit) $this->deny("Aynı anda en fazla {$policy->active_pickup_limit} aktif alım talebin olabilir.");
        $pending = PickupRequest::query()->where('listing_id', $listing->id)->where('status', PickupRequest::PENDING)->count();
        if ($pending >= $policy->listing_pending_pickup_limit) $this->deny('Bu ilan şu anda yeterli sayıda bekleyen talep aldı. Taleplerden biri sonuçlandığında yeniden deneyebilirsin.');
    }

    public function assertMessageAllowed(User $user, PickupRequest $pickupRequest, bool $freshConversation = false): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy();
        foreach ([[now()->subMinute(), $policy->messages_per_minute, 'Dakikalık mesaj sınırına ulaştın.', 60], [now()->subHour(), $policy->messages_per_hour, 'Saatlik mesaj sınırına ulaştın.', 3600], [now()->subDay(), $policy->messages_per_24h, 'Son 24 saatteki mesaj hakkın doldu.', 86400]] as [$since, $limit, $message, $windowSeconds]) {
            $query = ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', $since);
            if ($query->count() >= $limit) {
                $retryAt = $query->oldest('created_at')->first()?->created_at?->copy()->addSeconds($windowSeconds);
                $this->deny($message, $retryAt);
            }
        }
        $counterpartId = $pickupRequest->buyer_id === $user->id ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
        $lastReplyId = $freshConversation
            ? (ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->max('id') ?? 0)
            : (ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $counterpartId)->where('type', 'user')->max('id') ?? 0);
        $unanswered = ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $user->id)->where('type', 'user')->where('id', '>', $lastReplyId)->count();
        if ($unanswered >= $policy->unanswered_message_limit) $this->deny('Karşı tarafın yanıtını beklemelisin. Yanıt gelmeden daha fazla mesaj gönderemezsin.');
    }

    public function accountPeriod(User $user): array
    {
        $policy = $this->policy();
        $isNew = $this->isNewAccount($user, $policy);

        return [
            'isNewAccount' => $isNew,
            'newAccountHours' => $policy->new_account_hours,
            'newAccountEndsAt' => $isNew ? $user->created_at->copy()->addHours($policy->new_account_hours)->toIso8601String() : null,
        ];
    }
    public function interactionEligibility(User $user, Listing $listing): array
    {
        $listing->loadMissing('seller');
        $unavailable = function (string $reason, ?string $retryAt = null): array {
            return ['allowed' => false, 'action' => 'blocked', 'reason' => $reason, 'retryAt' => $retryAt];
        };

        if ($listing->user_id === $user->id) {
            $result = $unavailable('Kendi ilanınla mesajlaşamaz veya alım talebi oluşturamazsın.');
            return ['message' => $result, 'pickup' => $result];
        }
        if ($listing->status !== Listing::STATUS_ACTIVE || ($listing->expires_at && $listing->expires_at->isPast())) {
            $result = $unavailable('Bu ilan artık yeni görüşme veya alım talebi kabul etmiyor.');
            return ['message' => $result, 'pickup' => $result];
        }
        if (UserBlock::existsBetween($user->id, $listing->user_id)) {
            $result = $unavailable('Bu kullanıcıyla ilan veya mesaj etkileşimi kuramazsın.');
            return ['message' => $result, 'pickup' => $result];
        }

        $existing = PickupRequest::query()
            ->where('listing_id', $listing->id)
            ->where('buyer_id', $user->id)
            ->first();
        if ($existing && $existing->status !== PickupRequest::CLOSED) {
            $message = ['allowed' => true, 'action' => 'open', 'reason' => null, 'retryAt' => null, 'conversationId' => $existing->id];
            if ($existing->status === PickupRequest::PENDING) return ['message' => $message, 'pickup' => $unavailable('Bu ilan için alım talebin satıcının yanıtını bekliyor.')];
            if ($existing->status === PickupRequest::ACCEPTED) return ['message' => $message, 'pickup' => $unavailable('Bu ilan senin için rezerve edildi.')];
            if ($existing->status === PickupRequest::REJECTED) { $result = $unavailable('Satıcı bu ilan için alım talebini kabul etmedi. Bu ilan için yeniden görüşme veya talep başlatılamaz.'); return ['message' => $result, 'pickup' => $result]; }
            if ($existing->status === PickupRequest::COMPLETED) return ['message' => $message, 'pickup' => $unavailable('Bu ilanla ilgili teslimat tamamlandı.')];
        }

        $startsFresh = ! $existing || $existing->status === PickupRequest::CLOSED;
        $message = $this->checkEligibility(fn () => $this->assertContactAllowed($user, $listing->seller, true));
        $pickup = $this->checkEligibility(function () use ($user, $listing, $existing, $startsFresh): void {
            if ($startsFresh) $this->assertContactAllowed($user, $listing->seller, false);
            $this->assertPickupAllowed($user, $listing);
            $probe = $startsFresh ? new PickupRequest([
                'listing_id' => $listing->id,
                'buyer_id' => $user->id,
                'seller_id' => $listing->user_id,
                'status' => PickupRequest::INQUIRY,
            ]) : $existing;
            $this->assertMessageAllowed($user, $probe);
        });

        return ['message' => $message, 'pickup' => $pickup];
    }

    private function checkEligibility(callable $check): array
    {
        try {
            $check();
            return ['allowed' => true, 'action' => 'start', 'reason' => null, 'retryAt' => null];
        } catch (HttpResponseException $exception) {
            $payload = json_decode($exception->getResponse()->getContent(), true) ?: [];
            return [
                'allowed' => false,
                'action' => 'blocked',
                'reason' => $payload['message'] ?? 'Bu işlem şu anda kullanılamıyor.',
                'retryAt' => $payload['quota']['retryAt'] ?? null,
            ];
        }
    }
    public function record(User $user, string $type, ?User $target = null, ?Listing $listing = null): void
    {
        MarketplaceUsageEvent::create(['user_id' => $user->id, 'event_type' => $type, 'target_user_id' => $target?->id, 'listing_id' => $listing?->id, 'created_at' => now()]);
    }

    public function usage(User $user): array
    {
        $policy = $this->policy(); $new = $this->isNewAccount($user, $policy);
        $eventQuota = function (string $type, int $limit) use ($user): array { $query = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', $type)->where('created_at', '>', now()->subDay()); $used = $query->count(); $oldest = $used >= $limit ? $query->oldest('created_at')->first()?->created_at : null; return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used), 'nextAvailableAt' => $oldest?->copy()->addDay()->toIso8601String()]; };
        $listingLimit = $new ? $policy->new_account_listing_limit : $policy->listing_24h_limit; $pickupLimit = $new ? $policy->new_account_pickup_limit : $policy->pickup_24h_limit; $contactLimit = $new ? $policy->new_account_contact_limit : $policy->contact_24h_limit; $messageConversationLimit = $new ? $policy->new_account_message_conversation_limit : $policy->message_conversation_24h_limit;
        $messageUsed = ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', now()->subDay())->count();
        return ['isNewAccount' => $new, 'newAccountEndsAt' => $new ? $user->created_at->copy()->addHours($policy->new_account_hours)->toIso8601String() : null,
            'listings' => $eventQuota(MarketplaceUsageEvent::LISTING_CREATED, $listingLimit), 'contacts' => $eventQuota(MarketplaceUsageEvent::CONTACT_STARTED, $contactLimit), 'messageConversations' => $eventQuota(MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $messageConversationLimit), 'pickups' => $eventQuota(MarketplaceUsageEvent::PICKUP_REQUESTED, $pickupLimit),
            'activeListings' => ['used' => Listing::where('user_id', $user->id)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(), 'limit' => $policy->active_listing_limit],
            'activePickups' => ['used' => PickupRequest::where('buyer_id', $user->id)->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->count(), 'limit' => $policy->active_pickup_limit],
            'listingPendingPickupLimit' => $policy->listing_pending_pickup_limit,
            'messages' => ['nextAvailableAt' => $messageUsed >= $policy->messages_per_24h ? ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', now()->subDay())->oldest('created_at')->first()?->created_at?->copy()->addDay()->toIso8601String() : null, 'used' => $messageUsed, 'limit' => $policy->messages_per_24h, 'remaining' => max(0, $policy->messages_per_24h - $messageUsed), 'perMinute' => $policy->messages_per_minute, 'perHour' => $policy->messages_per_hour, 'unansweredLimit' => $policy->unanswered_message_limit],
        ];
    }

    private function assertEventLimit(User $user, string $type, int $limit, string $message): void
    {
        $query = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', $type)->where('created_at', '>', now()->subDay());
        if ($query->count() >= $limit) $this->deny($message, $query->oldest('created_at')->first()?->created_at?->copy()->addDay());
    }
    private function deny(string $message, $retryAt = null): never { throw new HttpResponseException(response()->json(['message' => $message, 'quota' => ['retryAt' => $retryAt?->toIso8601String()]], 429)); }
}
