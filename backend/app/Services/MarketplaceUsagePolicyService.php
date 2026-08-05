<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\Listing;
use App\Models\MarketplaceUsageEvent;
use App\Models\MarketplaceUsagePolicy;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class MarketplaceUsagePolicyService
{
    public function __construct(private ModerationSanctionService $moderation) {}

    public function policy(): MarketplaceUsagePolicy { return MarketplaceUsagePolicy::current(); }
    public function isNewAccount(User $user, ?MarketplaceUsagePolicy $policy = null): bool { $policy ??= $this->policy(); return $user->created_at->gt(now()->subHours($policy->new_account_hours)); }

    public function assertListingAllowed(User $user): void
    {
        $policy = $this->policy(); $limit = $this->isNewAccount($user, $policy) ? $policy->new_account_listing_limit : $policy->listing_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::LISTING_CREATED, $limit, 'Son 24 saatteki ilan oluşturma hakkın doldu.');
        $active = Listing::query()->where('user_id', $user->id)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        if ($active >= $policy->active_listing_limit) $this->deny("Aynı anda en fazla {$policy->active_listing_limit} aktif ilanın olabilir.");
    }

    public function assertContactAllowed(User $user, User $seller, bool $messageOnly): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy(); $limit = $this->isNewAccount($user, $policy) ? $policy->new_account_contact_limit : $policy->contact_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::CONTACT_STARTED, $limit, 'Son 24 saatteki yeni görüşme hakkın doldu.');
        if ($messageOnly) { $messageLimit = $this->isNewAccount($user, $policy) ? $policy->new_account_message_conversation_limit : $policy->message_conversation_24h_limit; $this->assertEventLimit($user, MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $messageLimit, 'Son 24 saatteki mesaj amaçlı yeni görüşme hakkın doldu.'); }
        $sameSeller = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('target_user_id', $seller->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->where('created_at', '>', now()->subDay())->count();
        if ($sameSeller >= $policy->same_seller_contact_24h_limit) $this->deny('Bu satıcıyla son 24 saat içinde yeni bir ilan görüşmesi başlattın. Mevcut sohbetinden devam edebilirsin.');
        $last = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', MarketplaceUsageEvent::CONTACT_STARTED)->latest('created_at')->first();
        if ($last && $policy->contact_cooldown_seconds > 0 && $last->created_at->gt(now()->subSeconds($policy->contact_cooldown_seconds))) $this->deny('Yeni bir görüşme başlatmadan önce kısa bir süre beklemelisin.', $last->created_at->copy()->addSeconds($policy->contact_cooldown_seconds));
    }

    public function assertPickupAllowed(User $user, Listing $listing): void
    {
        $policy = $this->policy(); $limit = $this->isNewAccount($user, $policy) ? $policy->new_account_pickup_limit : $policy->pickup_24h_limit;
        $this->assertEventLimit($user, MarketplaceUsageEvent::PICKUP_REQUESTED, $limit, 'Son 24 saatteki alım talebi hakkın doldu.');
        $active = PickupRequest::query()->where('buyer_id', $user->id)->whereIn('status', [PickupRequest::PENDING, PickupRequest::ACCEPTED])->count();
        if ($active >= $policy->active_pickup_limit) $this->deny("Aynı anda en fazla {$policy->active_pickup_limit} aktif alım talebin olabilir.");
        $pending = PickupRequest::query()->where('listing_id', $listing->id)->where('status', PickupRequest::PENDING)->count();
        if ($pending >= $policy->listing_pending_pickup_limit) $this->deny('Bu ilan şu anda yeterli sayıda bekleyen talep aldı. Taleplerden biri sonuçlandığında yeniden deneyebilirsin.');
    }

    public function assertMessageAllowed(User $user, PickupRequest $pickupRequest): void
    {
        $this->moderation->assertMessagingAllowed($user);
        $policy = $this->policy();
        foreach ([[now()->subMinute(), $policy->messages_per_minute, 'Dakikalık mesaj sınırına ulaştın.'], [now()->subHour(), $policy->messages_per_hour, 'Saatlik mesaj sınırına ulaştın.'], [now()->subDay(), $policy->messages_per_24h, 'Son 24 saatteki mesaj hakkın doldu.']] as [$since, $limit, $message]) {
            if (ConversationMessage::query()->where('sender_id', $user->id)->where('type', 'user')->where('created_at', '>', $since)->count() >= $limit) $this->deny($message);
        }
        $counterpartId = $pickupRequest->buyer_id === $user->id ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
        $lastReplyId = ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $counterpartId)->where('type', 'user')->max('id') ?? 0;
        $unanswered = ConversationMessage::query()->where('pickup_request_id', $pickupRequest->id)->where('sender_id', $user->id)->where('type', 'user')->where('id', '>', $lastReplyId)->count();
        if ($unanswered >= $policy->unanswered_message_limit) $this->deny('Karşı tarafın yanıtını beklemelisin. Yanıt gelmeden daha fazla mesaj gönderemezsin.');
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
            'messages' => ['used' => $messageUsed, 'limit' => $policy->messages_per_24h, 'remaining' => max(0, $policy->messages_per_24h - $messageUsed), 'perMinute' => $policy->messages_per_minute, 'perHour' => $policy->messages_per_hour, 'unansweredLimit' => $policy->unanswered_message_limit],
        ];
    }

    private function assertEventLimit(User $user, string $type, int $limit, string $message): void
    {
        $query = MarketplaceUsageEvent::query()->where('user_id', $user->id)->where('event_type', $type)->where('created_at', '>', now()->subDay());
        if ($query->count() >= $limit) $this->deny($message, $query->oldest('created_at')->first()?->created_at?->copy()->addDay());
    }
    private function deny(string $message, $retryAt = null): never { throw new HttpResponseException(response()->json(['message' => $message, 'quota' => ['retryAt' => $retryAt?->toIso8601String()]], 429)); }
}
