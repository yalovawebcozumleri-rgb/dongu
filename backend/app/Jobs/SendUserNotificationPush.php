<?php

namespace App\Jobs;

use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Models\UserNotification;
use App\Services\ExpoPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendUserNotificationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public array $backoff = [15, 60, 180];

    public function __construct(public int $notificationId) {}

    public function handle(ExpoPushService $push): void
    {
        $notification = UserNotification::find($this->notificationId);
        if (! $notification || $notification->push_processed_at) return;

        $batch = $this->batchFor($notification);
        $ids = $batch->pluck('id')->all();
        $preference = NotificationPreference::firstOrNew(['user_id' => $notification->user_id]);
        if (! $this->isEnabled($preference, $notification->type)) {
            UserNotification::whereIn('id', $ids)->update(['push_processed_at' => now(), 'push_error' => 'preference_disabled']);
            return;
        }

        $tokens = PushToken::query()->where('user_id', $notification->user_id)->whereNull('revoked_at')->get()->all();
        if (! $tokens) {
            UserNotification::whereIn('id', $ids)->update(['push_processed_at' => now(), 'push_error' => 'no_active_token']);
            return;
        }

        $latest = $batch->last();
        $count = $batch->count();
        $title = $count > 1 && $notification->type === 'new_message' ? $count.' yeni mesajın var' : $latest->title;
        $body = $count > 1 && $notification->type === 'new_message' ? 'Mesajlarını görmek için dokun.' : $latest->body;
        $data = array_merge($latest->data ?? [], ['notificationId' => $latest->id, 'route' => ($latest->data['route'] ?? 'notifications')]);

        try {
            $sent = $push->send($tokens, $title, $body, $data, $notification->type === 'new_message' ? 'messages' : 'default');
            UserNotification::whereIn('id', $ids)->update([
                'push_processed_at' => now(),
                'push_sent_at' => $sent > 0 ? now() : null,
                'push_error' => $sent > 0 ? null : 'all_tokens_failed',
            ]);
        } catch (Throwable $error) {
            UserNotification::whereIn('id', $ids)->update(['push_error' => mb_substr($error->getMessage(), 0, 160)]);
            throw $error;
        }
    }

    private function batchFor(UserNotification $notification)
    {
        if ($notification->type !== 'new_message' || ! $notification->group_key) {
            return collect([$notification]);
        }
        return UserNotification::query()
            ->where('user_id', $notification->user_id)
            ->where('type', $notification->type)
            ->where('group_key', $notification->group_key)
            ->whereNull('push_processed_at')
            ->where('created_at', '>=', $notification->created_at->copy()->subSeconds(30))
            ->where('created_at', '<=', now())
            ->orderBy('id')
            ->get();
    }

    private function isEnabled(NotificationPreference $preference, string $type): bool
    {
        return match (true) {
            in_array($type, ['new_message', 'new_conversation'], true) => $preference->messages_enabled ?? true,
            str_starts_with($type, 'pickup_') => $preference->pickup_requests_enabled ?? true,
            str_starts_with($type, 'delivery_') => $preference->delivery_enabled ?? true,
            str_starts_with($type, 'review_') => $preference->reviews_enabled ?? true,
            str_starts_with($type, 'listing_') => $preference->listing_updates_enabled ?? true,
            $type === 'admin_marketing' => $preference->marketing_enabled ?? false,
            $type === 'admin_system' => true,
            default => true,
        };
    }
}
