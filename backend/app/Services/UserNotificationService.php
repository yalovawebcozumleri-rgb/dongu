<?php

namespace App\Services;

use App\Events\NotificationChanged;
use App\Jobs\SendUserNotificationPush;
use App\Models\UserNotification;

class UserNotificationService
{
    public function create(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $dedupeKey = null,
        ?string $groupKey = null,
        bool $allowPush = true,
    ): UserNotification {
        $attributes = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'group_key' => $groupKey,
        ];

        $notification = $dedupeKey
            ? UserNotification::firstOrCreate(['dedupe_key' => $dedupeKey], $attributes)
            : UserNotification::create($attributes);

        if ($notification->wasRecentlyCreated) {
            $this->broadcastCount($userId);
            if ($allowPush && config('services.expo.push_enabled')) {
                SendUserNotificationPush::dispatch($notification->id)
                    ->delay($type === 'message_received' ? now()->addSeconds(8) : now())
                    ->afterCommit();
            }
        }

        return $notification;
    }

    public function broadcastCount(int $userId): void
    {
        NotificationChanged::dispatch(
            $userId,
            UserNotification::query()->where('user_id', $userId)->whereNull('read_at')->count(),
        );
    }
}
