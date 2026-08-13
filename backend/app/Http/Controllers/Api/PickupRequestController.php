<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationMessageResource;
use App\Models\AdvertisementPlacementSetting;
use App\Http\Resources\PickupRequestResource;
use App\Models\ConversationMessage;
use App\Models\ConversationUserState;
use App\Models\Listing;
use App\Models\MessageReport;
use App\Models\MarketplaceUsageEvent;
use App\Models\PickupRequest;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\CyclePointService;
use App\Services\ListingConversationClosureService;
use App\Services\MarketplaceUsagePolicyService;
use App\Services\ModerationSanctionService;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PickupRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = PickupRequest::query()
            ->where(fn ($query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->whereDoesntHave('userStates', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('hidden_at'))
            ->with([
                'buyer:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
                'seller:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
                'listing.seller',
                'listing.materials',
                'listing.photos',
                'listing.privateLocation',
                'latestMessage',
                'reviews:id,pickup_request_id,reviewer_id',
            ])
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->whereNotNull('sender_id')
                ->where('sender_id', '!=', $user->id)])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $this->hydrateBlockingState($items, $user);

        return PickupRequestResource::collection($items);
    }

    public function purchaseHistory(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['active', 'history', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $activeStatuses = [PickupRequest::PENDING, PickupRequest::ACCEPTED];
        $historyStatuses = [PickupRequest::REJECTED, PickupRequest::CANCELLED, PickupRequest::COMPLETED, PickupRequest::CLOSED];
        $scope = $filters['scope'] ?? 'active';

        $query = PickupRequest::query()
            ->where('buyer_id', $user->id)
            ->where('status', '!=', PickupRequest::INQUIRY)
            ->when($scope === 'active', fn ($query) => $query->whereIn('status', $activeStatuses))
            ->when($scope === 'history', fn ($query) => $query->whereIn('status', $historyStatuses))
            ->with([
                'buyer:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
                'seller:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
                'listing.seller', 'listing.materials', 'listing.photos', 'listing.privateLocation',
                'latestMessage', 'reviews:id,pickup_request_id,reviewer_id',
                'userStates' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->whereNull('read_at')->whereNotNull('sender_id')->where('sender_id', '!=', $user->id),
                'messages as user_messages_count' => fn ($query) => $query->where('type', 'user'),
            ])
            ->latest('updated_at');

        $page = $query->paginate($filters['per_page'] ?? 20);
        $this->hydrateBlockingState($page->getCollection(), $user);

        return PickupRequestResource::collection($page)->additional(['summary' => [
            'active' => PickupRequest::where('buyer_id', $user->id)->whereIn('status', $activeStatuses)->count(),
            'history' => PickupRequest::where('buyer_id', $user->id)->whereIn('status', $historyStatuses)->count(),
        ]]);
    }

    public function eligibility(Request $request, Listing $listing, MarketplaceUsagePolicyService $usagePolicy): JsonResponse
    {
        return response()->json(['data' => [...$usagePolicy->interactionEligibility($request->user(), $listing), 'account' => $usagePolicy->accountPeriod($request->user())]]);
    }
    public function store(Request $request, Listing $listing, MarketplaceUsagePolicyService $usagePolicy, ModerationSanctionService $sanctions): PickupRequestResource
    {
        $validated = $request->validate([
            'intent' => ['required', Rule::in(['message', 'pickup'])],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $buyer = $request->user();
        if ($validated['intent'] === 'message' || trim((string) ($validated['message'] ?? '')) !== '') {
            $sanctions->assertMessagingAllowed($buyer);
        }

        abort_if($listing->user_id === $buyer->id, 422, 'Kendi ilanına alım talebi gönderemezsin.');
        abort_if(UserBlock::existsBetween($buyer->id, $listing->user_id), 422, 'Bu kullanıcıyla ilan veya mesaj etkileşimi kuramazsın.');
        abort_unless($listing->status === Listing::STATUS_ACTIVE && (! $listing->expires_at || $listing->expires_at->isFuture()), 422, 'Bu ilan artık alım talebi kabul etmiyor.');

        $notificationKind = null;
        $initialMessageId = null;
        $pickupRequest = DB::transaction(function () use ($buyer, $listing, $validated, &$notificationKind, &$initialMessageId, $usagePolicy) {
            $lockedBuyer = User::query()->lockForUpdate()->findOrFail($buyer->id);
            $lockedListing = Listing::query()->with(['seller', 'materials'])->lockForUpdate()->findOrFail($listing->id);
            abort_unless($lockedListing->status === Listing::STATUS_ACTIVE && (! $lockedListing->expires_at || $lockedListing->expires_at->isFuture()), 422, 'Bu ilan artık alım talebi kabul etmiyor.');
            $pickupRequest = PickupRequest::lockForUpdate()
                ->firstOrNew(['listing_id' => $lockedListing->id, 'buyer_id' => $lockedBuyer->id]);

            if ($pickupRequest->exists && $pickupRequest->status === PickupRequest::REJECTED) {
                abort(422, 'Satıcı bu ilan için alım talebini reddetti. Aynı ilan için yeniden talep gönderemezsin.');
            }
            if ($pickupRequest->exists && in_array($pickupRequest->status, [PickupRequest::PENDING, PickupRequest::ACCEPTED, PickupRequest::COMPLETED], true)) {
                return $pickupRequest;
            }

            $nextStatus = $validated['intent'] === 'pickup' ? PickupRequest::PENDING : PickupRequest::INQUIRY;
            $wasNew = ! $pickupRequest->exists;
            $previousStatus = $pickupRequest->status;
            $canCreatePickupEvent = $wasNew || in_array($previousStatus, [
                PickupRequest::INQUIRY,
                PickupRequest::CANCELLED,
                PickupRequest::CLOSED,
            ], true);
            $startsFresh = $wasNew || in_array($previousStatus, [PickupRequest::CLOSED, PickupRequest::CANCELLED], true);
            if ($startsFresh) {
                $usagePolicy->assertContactAllowed($lockedBuyer, $lockedListing->seller, $validated['intent'] === 'message');
                $usagePolicy->record($lockedBuyer, MarketplaceUsageEvent::CONTACT_STARTED, $lockedListing->seller, $lockedListing);
                if ($validated['intent'] === 'message') $usagePolicy->record($lockedBuyer, MarketplaceUsageEvent::MESSAGE_CONVERSATION_STARTED, $lockedListing->seller, $lockedListing);
            }
            if ($validated['intent'] === 'pickup' && $canCreatePickupEvent) {
                $usagePolicy->assertPickupAllowed($lockedBuyer, $lockedListing);
                $usagePolicy->record($lockedBuyer, MarketplaceUsageEvent::PICKUP_REQUESTED, $lockedListing->seller, $lockedListing);
            }
            $pickupRequest->fill([
                'seller_id' => $lockedListing->user_id,
                'status' => $nextStatus,
                'listing_snapshot' => $this->listingSnapshot($lockedListing),
                'closed_reason' => null,
                'closed_at' => null,
                'delivery_code' => null,
                'accepted_at' => null,
                'completed_at' => null,
                'cancelled_by_user_id' => null,
                'cancelled_at' => null,
            ])->save();
            ConversationUserState::query()
                ->where('pickup_request_id', $pickupRequest->id)
                ->where('user_id', $lockedBuyer->id)
                ->update(['hidden_at' => null]);

            $message = trim((string) ($validated['message'] ?? ''));
            if ($message !== '') {
                $usagePolicy->assertMessageAllowed($lockedBuyer, $pickupRequest, $startsFresh);
                $initialMessageId = $pickupRequest->messages()->create(['sender_id' => $buyer->id, 'type' => 'user', 'body' => $message])->id;
            } elseif ($validated['intent'] === 'pickup' && $canCreatePickupEvent) {
                $usagePolicy->assertMessageAllowed($lockedBuyer, $pickupRequest, $startsFresh);
                $initialMessageId = $pickupRequest->messages()->create([
                    'sender_id' => $buyer->id,
                    'type' => 'user',
                    'body' => 'Bu ilanı almak istiyorum. Uygun olduğunda talebimi onaylayabilir misin?',
                ])->id;
            }

            if ($validated['intent'] === 'pickup' && $canCreatePickupEvent) {
                $this->systemMessage($pickupRequest, 'Alım talebi satıcıya gönderildi.');
                $notificationKind = 'pickup_request';
            }

            if ($initialMessageId) {
                ConversationUserState::query()
                    ->where('pickup_request_id', $pickupRequest->id)
                    ->where('user_id', $lockedListing->user_id)
                    ->update(['hidden_at' => null]);
            }

            return $pickupRequest->touch() ? $pickupRequest : $pickupRequest;
        });

        $this->broadcastConversationChange($pickupRequest, 'status');
        if ($notificationKind) {
            $this->notify(
                $pickupRequest->seller_id,
                $notificationKind,
                $notificationKind === 'pickup_request' ? 'Yeni alım talebi' : 'Yeni ilan görüşmesi',
                $notificationKind === 'pickup_request'
                    ? "{$buyer->name}, ilanını almak istiyor."
                    : "{$buyer->name}, ilanın hakkında görüşme başlattı.",
                $pickupRequest,
                "pickup:{$pickupRequest->id}:{$notificationKind}",
            );
        }

        if ($initialMessageId && $notificationKind !== 'pickup_request') {
            $this->notify(
                $pickupRequest->seller_id,
                'new_message',
                'Yeni mesaj',
                $buyer->name.': '.Str::limit((string) ($validated['message'] ?? ''), 90),
                $pickupRequest,
                'message:'.$initialMessageId,
                'conversation:'.$pickupRequest->id,
            );
        }
        $dailyOrdinal = $notificationKind === 'pickup_request'
            ? MarketplaceUsageEvent::where('user_id', $buyer->id)
                ->where('event_type', MarketplaceUsageEvent::PICKUP_REQUESTED)
                ->where('created_at', '>=', now()->subDay())
                ->count()
            : 0;

        $interstitial = AdvertisementPlacementSetting::forKey('pickup_interstitial');
        $interstitialOrdinals = collect(data_get($interstitial->settings, 'ordinals', []))->map(fn ($value) => (int) $value)->all();

        return (new PickupRequestResource($this->loadConversation($pickupRequest, $buyer)))->additional([
            'monetization' => [
                'showInterstitial' => $interstitial->enabled
                    && ($interstitial->platformEnabled('android') || $interstitial->platformEnabled('ios'))
                    && $notificationKind === 'pickup_request'
                    && in_array($dailyOrdinal, $interstitialOrdinals, true),
                'adMobAndroidUnitId' => $interstitial->adMobUnitId('android', 'interstitial'),
                'adMobIosUnitId' => $interstitial->adMobUnitId('ios', 'interstitial'),
                'dailyPickupOrdinal' => $dailyOrdinal,
            ],
        ]);
    }

    public function show(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->ensureParticipant($request, $pickupRequest);

        return new PickupRequestResource($this->loadConversation($pickupRequest, $request->user()));
    }
    public function messages(Request $request, PickupRequest $pickupRequest): JsonResponse
    {
        $this->ensureParticipant($request, $pickupRequest);
        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 30);
        $query = $pickupRequest->messages()->latest('id');
        if (isset($validated['before_id'])) {
            $query->where('id', '<', $validated['before_id']);
        }
        $page = $query->limit($perPage + 1)->get();
        $hasMore = $page->count() > $perPage;
        $messages = $page->take($perPage)->reverse()->values();

        return response()->json([
            'data' => ConversationMessageResource::collection($messages)->resolve($request),
            'meta' => [
                'hasMore' => $hasMore,
                'nextCursor' => $hasMore ? $messages->first()?->id : null,
            ],
        ]);
    }

    public function markRead(Request $request, PickupRequest $pickupRequest): JsonResponse
    {
        $this->ensureParticipant($request, $pickupRequest);
        $validated = $request->validate(['last_message_id' => ['required', 'integer', 'min:1']]);
        $updated = $pickupRequest->messages()
            ->where('id', '<=', $validated['last_message_id'])
            ->whereNotNull('sender_id')
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        if ($updated > 0) {
            $senderId = $pickupRequest->buyer_id === $request->user()->id ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
            ConversationChanged::dispatch($senderId, $pickupRequest->id, 'read');
        }

        return response()->json(['data' => ['read' => true]]);
    }

    public function sendMessage(Request $request, PickupRequest $pickupRequest, MarketplaceUsagePolicyService $usagePolicy, ModerationSanctionService $sanctions): ConversationMessageResource
    {
        $this->ensureParticipant($request, $pickupRequest);
        $sanctions->assertMessagingAllowed($request->user());
        $this->ensureNotBlocked($pickupRequest);
        abort_if($pickupRequest->status === PickupRequest::COMPLETED, 422, 'Teslimat tamamlandığı için bu görüşme yeni mesajlara kapatıldı.');
        abort_if($pickupRequest->status === PickupRequest::REJECTED, 422, 'Satıcı talebi reddettiği için bu görüşme yeni mesajlara kapatıldı.');
        abort_if($pickupRequest->status === PickupRequest::CANCELLED, 422, 'İptal edilen işlem için bu görüşme kapatıldı. İlan hâlâ yayındaysa ilan üzerinden yeni bir görüşme başlatabilirsin.');
        abort_if($pickupRequest->status === PickupRequest::CLOSED, 422, 'İlan artık alım taleplerine açık olmadığı için bu görüşme yeni mesajlara kapatıldı.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'client_id' => ['nullable', 'uuid'],
        ]);
        $message = DB::transaction(function () use ($request, $pickupRequest, $validated, $usagePolicy) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! empty($validated['client_id'])) {
                $existing = ConversationMessage::query()->where('sender_id', $user->id)->where('client_id', $validated['client_id'])->first();
                if ($existing) {
                    abort_unless($existing->pickup_request_id === $pickupRequest->id, 422, 'Mesaj kimliği başka bir görüşmede kullanılmış.');
                    return $existing;
                }
            }
            $usagePolicy->assertMessageAllowed($user, $pickupRequest);
            return ConversationMessage::create(['sender_id' => $user->id, 'client_id' => $validated['client_id'] ?? (string) Str::uuid(), 'pickup_request_id' => $pickupRequest->id, 'type' => 'user', 'body' => trim($validated['message'])]);
        });
        abort_unless($message->pickup_request_id === $pickupRequest->id, 422, 'Mesaj kimliği başka bir görüşmede kullanılmış.');
        if ($message->wasRecentlyCreated) {
            $pickupRequest->touch();
            $recipientId = $pickupRequest->buyer_id === $request->user()->id ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
            if ($pickupRequest->status === PickupRequest::INQUIRY) {
                ConversationUserState::query()
                    ->where('pickup_request_id', $pickupRequest->id)
                    ->where('user_id', $recipientId)
                    ->update(['hidden_at' => null]);
            }
            ConversationChanged::dispatch($recipientId, $pickupRequest->id, 'message', [
                'message' => [
                    'id' => $message->id,
                    'sender' => 'other',
                    'text' => $message->moderated_at ? 'Bu mesaj topluluk kuralları nedeniyle kaldırıldı.' : $message->body,
                    'time' => $message->created_at?->format('H:i'),
                    'createdAt' => $message->created_at?->toIso8601String(),
                    'readAt' => $message->read_at?->toIso8601String(),
                    'clientId' => $message->client_id,
                    'moderated' => $message->moderated_at !== null,
                ],
            ]);
            $this->notify(
                $recipientId,
                'new_message',
                'Yeni mesaj',
                $request->user()->name.': '.Str::limit($message->body, 90),
                $pickupRequest,
                'message:'.$message->id,
                'conversation:'.$pickupRequest->id,
            );
        }

        return new ConversationMessageResource($message);
    }

    public function hide(Request $request, PickupRequest $pickupRequest, UserNotificationService $notifications): JsonResponse
    {
        $this->ensureParticipant($request, $pickupRequest);
        $isBlocked = UserBlock::existsBetween($pickupRequest->buyer_id, $pickupRequest->seller_id);
        $isInquiry = $pickupRequest->status === PickupRequest::INQUIRY;
        abort_unless($isBlocked || $isInquiry || in_array($pickupRequest->status, [PickupRequest::REJECTED, PickupRequest::CANCELLED, PickupRequest::COMPLETED, PickupRequest::CLOSED], true), 422,
            'Aktif görüşme listeden kaldırılamaz. Önce talep veya rezervasyon sonuçlanmalıdır.');
        ConversationUserState::updateOrCreate([
            'pickup_request_id' => $pickupRequest->id,
            'user_id' => $request->user()->id,
        ], ['hidden_at' => now()]);
        $notifications->clearConversationNotifications($request->user()->id, $pickupRequest->id);

        return response()->json(['data' => ['hidden' => true]]);
    }

    public function reportMessage(Request $request, PickupRequest $pickupRequest, ConversationMessage $message): JsonResponse
    {
        $this->ensureParticipant($request, $pickupRequest);
        abort_unless($message->pickup_request_id === $pickupRequest->id, 422, 'Mesaj kimliği başka bir görüşmede kullanılmış.');
        abort_if($message->sender_id === null || $message->sender_id === $request->user()->id, 422, 'Yalnızca karşı taraftan gelen kullanıcı mesajları bildirilebilir.');
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment', 'fraud', 'personal_data', 'other'])],
            'details' => ['nullable', 'string', 'max:500'],
        ]);
        $report = MessageReport::firstOrCreate([
            'conversation_message_id' => $message->id,
            'reporter_id' => $request->user()->id,
        ], [
            'reason' => $validated['reason'],
            'details' => trim((string) ($validated['details'] ?? '')) ?: null,
        ]);

        return response()->json(['data' => ['reported' => true]], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function accept(Request $request, PickupRequest $pickupRequest, ListingConversationClosureService $closures): PickupRequestResource
    {
        abort_unless($pickupRequest->seller_id === $request->user()->id, 403);
        abort_unless($pickupRequest->status === PickupRequest::PENDING, 422, 'Yalnızca bekleyen bir talep kabul edilebilir.');

        $closedRequests = DB::transaction(function () use ($pickupRequest, $closures) {
            $listing = Listing::lockForUpdate()->findOrFail($pickupRequest->listing_id);
        abort_unless($listing->status === Listing::STATUS_ACTIVE && (! $listing->expires_at || $listing->expires_at->isFuture()), 422, 'Bu ilan artık alım talebi kabul etmiyor.');

            $pickupRequest->update([
                'status' => PickupRequest::ACCEPTED,
                'delivery_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'accepted_at' => now(),
                'cancelled_by_user_id' => null,
                'cancelled_at' => null,
            ]);
            $listing->update(['status' => Listing::STATUS_RESERVED]);
            $closedRequests = $closures->closeOpenWithinTransaction($listing, ListingConversationClosureService::LISTING_UNAVAILABLE, $pickupRequest->id);
            $this->systemMessage($pickupRequest, 'Satıcı talebi kabul etti. İlan senin için rezerve edildi.');

            return $closedRequests;
        });
        $closures->announce($closedRequests, ListingConversationClosureService::LISTING_UNAVAILABLE);

        $this->broadcastConversationChange($pickupRequest, 'status');
        $this->notify(
            $pickupRequest->buyer_id,
            'pickup_accepted',
            'Talebin kabul edildi',
            'Satıcı talebini kabul etti. Teslimat ayrıntılarını görüşebilirsin.',
            $pickupRequest,
            'pickup:'.$pickupRequest->id.':accepted',
        );

        return new PickupRequestResource($this->loadConversation($pickupRequest->fresh(), $request->user()));
    }

    public function reject(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        abort_unless($pickupRequest->seller_id === $request->user()->id, 403);
        abort_unless($pickupRequest->status === PickupRequest::PENDING, 422, 'Yalnızca bekleyen bir talep reddedilebilir.');

        $pickupRequest->update(['status' => PickupRequest::REJECTED]);
        $this->systemMessage($pickupRequest, 'Satıcı alım talebini kabul etmedi.');

        $this->broadcastConversationChange($pickupRequest, 'status');
        $this->notify(
            $pickupRequest->buyer_id,
            'pickup_rejected',
            'Talebin kabul edilmedi',
            'Satıcı bu ilan için alım talebini kabul etmedi.',
            $pickupRequest,
            'pickup:'.$pickupRequest->id.':rejected',
        );

        return new PickupRequestResource($this->loadConversation($pickupRequest, $request->user()));
    }

    public function cancel(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->ensureParticipant($request, $pickupRequest);
        $user = $request->user();
        $isBuyer = $pickupRequest->buyer_id === $user->id;

        if ($isBuyer) {
            abort_unless(in_array($pickupRequest->status, [PickupRequest::PENDING, PickupRequest::ACCEPTED], true), 422, 'Yalnızca bekleyen veya kabul edilmiş bir alım talebi geri çekilebilir.');
        } else {
            abort_unless($pickupRequest->status === PickupRequest::ACCEPTED, 422, 'Satıcı yalnızca kabul edilmiş bir rezervasyonu iptal edebilir.');
        }

        DB::transaction(function () use ($pickupRequest, $user, $isBuyer) {
            if ($pickupRequest->status === PickupRequest::ACCEPTED) {
                $pickupRequest->listing()->update(['status' => Listing::STATUS_ACTIVE]);
            }
            $pickupRequest->update([
                'status' => PickupRequest::CANCELLED,
                'delivery_code' => null,
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => now(),
            ]);
            $message = $isBuyer
                ? 'Alıcı alım talebini geri çekti. Mesajlaşmaya devam edebilirsiniz.'
                : 'Satıcı rezervasyonu iptal etti. İlan yeniden aktif hale getirildi.';
            $this->systemMessage($pickupRequest, $message);
        });

        $this->broadcastConversationChange($pickupRequest, 'status');
        $recipientId = $isBuyer ? $pickupRequest->seller_id : $pickupRequest->buyer_id;
        $this->notify(
            $recipientId,
            'pickup_cancelled',
            $isBuyer ? 'Alıcı talebini geri çekti' : 'Rezervasyon iptal edildi',
            $isBuyer ? 'Alıcı alım talebini geri çekti.' : 'Satıcı rezervasyonu iptal etti; ilan yeniden aktif.',
            $pickupRequest,
            'pickup:'.$pickupRequest->id.':cancelled:'.$pickupRequest->updated_at?->timestamp,
        );

        return new PickupRequestResource($this->loadConversation($pickupRequest, $user));
    }

    public function complete(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        abort_unless($pickupRequest->seller_id === $request->user()->id, 403);
        abort_unless($pickupRequest->status === PickupRequest::ACCEPTED, 422, 'Bu işlem teslimata hazır değil.');
        $validated = $request->validate(['code' => ['required', 'digits:4']]);

        if (! hash_equals((string) $pickupRequest->delivery_code, (string) $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Teslim kodu hatalı. Alıcının ekranındaki 4 haneli kodu kontrol et.']);
        }

        DB::transaction(function () use ($pickupRequest) {
            $pickupRequest->update(['status' => PickupRequest::COMPLETED, 'delivery_code' => null, 'completed_at' => now()]);
            $pickupRequest->listing()->update(['status' => Listing::STATUS_COMPLETED]);
            User::whereKey([$pickupRequest->buyer_id, $pickupRequest->seller_id])->increment('completed_transactions');
            app(CyclePointService::class)->awardDelivery($pickupRequest);
            $this->systemMessage($pickupRequest, 'Teslimat tamamlandı. Görüşme yeni mesajlara kapatıldı; değerlendirme için 24 saatiniz var.');
        });

        $this->broadcastConversationChange($pickupRequest, 'status');
        foreach ([$pickupRequest->buyer_id, $pickupRequest->seller_id] as $participantId) {
            $this->notify(
                $participantId,
                'delivery_completed',
                'Teslimat tamamlandı',
                'İşlem tamamlandı. Deneyimini 24 saat içinde değerlendirebilirsin.',
                $pickupRequest,
                'pickup:'.$pickupRequest->id.':completed:'.$participantId,
            );
        }

        return new PickupRequestResource($this->loadConversation($pickupRequest, $request->user()));
    }

    public function review(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->ensureParticipant($request, $pickupRequest);
        abort_unless($pickupRequest->status === PickupRequest::COMPLETED, 422, 'Yalnızca tamamlanan işlemler değerlendirilebilir.');
        $reviewDeadline = $pickupRequest->completed_at?->copy()->addHours(config('marketplace.review_window_hours'));
        abort_if(! $reviewDeadline || now()->greaterThan($reviewDeadline), 422, '24 saatlik değerlendirme süresi sona erdi.');

        abort_if($pickupRequest->reviews()->where('reviewer_id', $request->user()->id)->exists(), 422, 'Bu işlem için daha önce değerlendirme yaptın.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $revieweeId = $pickupRequest->buyer_id === $request->user()->id
            ? $pickupRequest->seller_id
            : $pickupRequest->buyer_id;

        DB::transaction(function () use ($pickupRequest, $request, $validated, $revieweeId) {
            Review::create([
                'pickup_request_id' => $pickupRequest->id,
                'reviewer_id' => $request->user()->id,
                'reviewee_id' => $revieweeId,
                'rating' => $validated['rating'],
                'comment' => trim((string) ($validated['comment'] ?? '')) ?: null,
            ]);
            $reviewee = User::findOrFail($revieweeId);
            $reviewee->forceFill([
                'rating' => round((float) $reviewee->receivedReviews()->avg('rating'), 2),
                'rating_count' => $reviewee->receivedReviews()->count(),
            ])->save();
        });

        $this->notify(
            $revieweeId,
            'review_received',
            'Yeni değerlendirme aldın',
            $request->user()->name.' sana '.$validated['rating'].' yıldız verdi.',
            $pickupRequest,
            'review:'.$pickupRequest->id.':'.$request->user()->id,
        );

        return new PickupRequestResource($this->loadConversation($pickupRequest, $request->user()));
    }

    private function notify(
        int $userId,
        string $type,
        string $title,
        string $body,
        PickupRequest $pickupRequest,
        string $dedupeKey,
        ?string $groupKey = null,
    ): void {
        app(UserNotificationService::class)->create(
            $userId,
            $type,
            $title,
            $body,
            [
                'route' => 'chat',
                'conversationId' => $pickupRequest->id,
                'listingId' => $pickupRequest->listing_id,
            ],
            $dedupeKey,
            $groupKey ?? 'conversation:'.$pickupRequest->id,
        );
    }

    private function broadcastConversationChange(PickupRequest $pickupRequest, string $kind): void
    {
        ConversationChanged::dispatch($pickupRequest->buyer_id, $pickupRequest->id, $kind);
        ConversationChanged::dispatch($pickupRequest->seller_id, $pickupRequest->id, $kind);
    }

    private function ensureNotBlocked(PickupRequest $pickupRequest): void
    {
        abort_if(UserBlock::existsBetween($pickupRequest->buyer_id, $pickupRequest->seller_id), 422, 'Bu kullanıcıyla iletişim engellendi.');
    }

    private function ensureParticipant(Request $request, PickupRequest $pickupRequest): void
    {
        abort_unless($pickupRequest->involves($request->user()), 403);
    }

    private function systemMessage(PickupRequest $pickupRequest, string $body): ConversationMessage
    {
        return $pickupRequest->messages()->create(['sender_id' => null, 'type' => 'system', 'body' => $body]);
    }

    private function listingSnapshot(Listing $listing): array
    {
        $labels = ['pet' => 'PET', 'glass' => 'Cam', 'aluminum' => 'Alüminyum'];

        return [
            'id' => $listing->id,
            'sellerId' => $listing->user_id,
            'seller' => $listing->seller->name,
            'district' => $listing->public_area,
            'items' => $listing->materials->map(fn ($material) => [
                'material' => $labels[$material->type] ?? $material->type,
                'type' => $material->type,
                'count' => (int) $material->quantity,
                'unitPrice' => (float) $material->unit_price,
            ])->values()->all(),
        ];
    }
    private function hydrateBlockingState($items, User $user): void
    {
        $counterpartIds = $items->map(fn (PickupRequest $item) => $item->buyer_id === $user->id ? $item->seller_id : $item->buyer_id)->unique();
        $blocks = UserBlock::query()
            ->where(fn ($query) => $query->where('blocker_id', $user->id)->whereIn('blocked_id', $counterpartIds))
            ->orWhere(fn ($query) => $query->where('blocked_id', $user->id)->whereIn('blocker_id', $counterpartIds))
            ->get();
        foreach ($items as $item) {
            $counterpartId = $item->buyer_id === $user->id ? $item->seller_id : $item->buyer_id;
            $item->setAttribute('blocked_by_me', $blocks->contains(fn (UserBlock $block) => $block->blocker_id === $user->id && $block->blocked_id === $counterpartId));
            $item->setAttribute('is_blocked', $blocks->contains(fn (UserBlock $block) => ($block->blocker_id === $user->id && $block->blocked_id === $counterpartId) || ($block->blocked_id === $user->id && $block->blocker_id === $counterpartId)));
        }
    }

    private function loadConversation(PickupRequest $pickupRequest, User $user): PickupRequest
    {
        $loaded = $pickupRequest->load([
            'buyer:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
            'seller:id,name,rating,rating_count,avatar_path,avatar_key,updated_at',
            'listing.seller',
            'listing.materials',
            'listing.photos',
            'listing.privateLocation',
            'latestMessage',
            'reviews:id,pickup_request_id,reviewer_id',
            'userStates' => fn ($query) => $query->where('user_id', $user->id),
        ])->loadCount([
            'messages as unread_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->whereNotNull('sender_id')
                ->where('sender_id', '!=', $user->id),
            'messages as user_messages_count' => fn ($query) => $query->where('type', 'user'),
        ]);
        $this->hydrateBlockingState(collect([$loaded]), $user);

        return $loaded;
    }
}
