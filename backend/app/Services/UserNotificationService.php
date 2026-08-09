<?php

namespace App\Services;

use App\Events\NotificationChanged;
use App\Jobs\SendConversationMessagePush;
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

        $updatedExistingMessage = false;
        if ($type === 'new_message' && $groupKey) {
            $existingMessage = UserNotification::withTrashed()
                ->where('user_id', $userId)
                ->where('type', 'new_message')
                ->where('group_key', $groupKey)
                ->latest('id')
                ->first();
            if ($existingMessage) {
                $previousData = $existingMessage->data ?? [];
                $messageCount = $existingMessage->trashed() || $existingMessage->read_at !== null
                    ? 1
                    : max(1, (int) ($previousData['messageCount'] ?? 1)) + 1;
                $attributes['data'] = array_merge($data, ['messageCount' => $messageCount]);
                if ($existingMessage->trashed()) {
                    $existingMessage->restore();
                }
                $existingMessage->forceFill($attributes + [
                    'read_at' => null,
                    'created_at' => now(),
                ])->save();
                $notification = $existingMessage;
                $updatedExistingMessage = true;
            }
        }
        if (! isset($notification)) {
            if ($type === 'new_message') {
                $attributes['data'] = array_merge($data, ['messageCount' => 1]);
            }
            $notification = $dedupeKey
                ? UserNotification::firstOrCreate(['dedupe_key' => $dedupeKey], $attributes)
                : UserNotification::create($attributes);
        }

        if ($notification->wasRecentlyCreated || $updatedExistingMessage) {
            $this->broadcastCount($userId);
            if ($allowPush && config('services.expo.push_enabled')) {
                if ($type === 'new_message') {
                    SendConversationMessagePush::dispatch($userId, $title, $body, $data)->afterCommit();
                } else {
                    SendUserNotificationPush::dispatch($notification->id)->afterCommit();
                }
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

    public function clearConversationNotifications(int $userId, int $conversationId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['new_message', 'new_conversation'])
            ->where('group_key', 'conversation:'.$conversationId)
            ->delete();

        $this->broadcastCount($userId);
    }
}
