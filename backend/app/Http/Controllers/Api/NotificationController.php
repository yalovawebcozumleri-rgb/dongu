<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\NotificationPreference;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $query = $request->user()->userNotifications()->latest('id');
        if ($request->boolean('unread')) $query->whereNull('read_at');
        $page = $query->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => UserNotificationResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'unreadCount' => $request->user()->userNotifications()->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'unreadCount' => $request->user()->userNotifications()->whereNull('read_at')->count(),
        ]]);
    }

    public function markRead(Request $request, UserNotification $notification, UserNotificationService $service): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        if ($notification->read_at === null) $notification->update(['read_at' => now()]);
        $service->broadcastCount($request->user()->id);

        return response()->json(['data' => ['id' => $notification->id, 'read' => true]]);
    }

    public function markAllRead(Request $request, UserNotificationService $service): JsonResponse
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        $service->broadcastCount($request->user()->id);

        return response()->json(['data' => ['read' => true, 'unreadCount' => 0]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferenceData(
            NotificationPreference::firstOrCreate(['user_id' => $request->user()->id], [
                'messages_enabled' => true,
                'pickup_requests_enabled' => true,
                'delivery_enabled' => true,
                'reviews_enabled' => true,
                'listing_updates_enabled' => true,
                'marketing_enabled' => false,
            ])        )]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messagesEnabled' => ['required', 'boolean'],
            'pickupRequestsEnabled' => ['required', 'boolean'],
            'deliveryEnabled' => ['required', 'boolean'],
            'reviewsEnabled' => ['required', 'boolean'],
            'listingUpdatesEnabled' => ['required', 'boolean'],
            'marketingEnabled' => ['required', 'boolean'],
        ]);
        $preferences = NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], [
            'messages_enabled' => $validated['messagesEnabled'],
            'pickup_requests_enabled' => $validated['pickupRequestsEnabled'],
            'delivery_enabled' => $validated['deliveryEnabled'],
            'reviews_enabled' => $validated['reviewsEnabled'],
            'listing_updates_enabled' => $validated['listingUpdatesEnabled'],
            'marketing_enabled' => $validated['marketingEnabled'],
        ]);

        return response()->json(['data' => $this->preferenceData($preferences)]);
    }

    private function preferenceData(NotificationPreference $preferences): array
    {
        return [
            'messagesEnabled' => $preferences->messages_enabled,
            'pickupRequestsEnabled' => $preferences->pickup_requests_enabled,
            'deliveryEnabled' => $preferences->delivery_enabled,
            'reviewsEnabled' => $preferences->reviews_enabled,
            'listingUpdatesEnabled' => $preferences->listing_updates_enabled,
            'marketingEnabled' => $preferences->marketing_enabled,
        ];
    }
}
