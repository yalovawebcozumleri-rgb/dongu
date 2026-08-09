<?php

namespace App\Jobs;

use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Services\ExpoPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendConversationMessagePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data,
    ) {}

    public function handle(ExpoPushService $push): void
    {
        $preference = NotificationPreference::firstOrNew(['user_id' => $this->userId]);
        if (! ($preference->messages_enabled ?? true)) return;

        $tokens = PushToken::query()
            ->where('user_id', $this->userId)
            ->whereNull('revoked_at')
            ->get()
            ->all();
        if (! $tokens) return;

        $push->send(
            $tokens,
            $this->title,
            $this->body,
            array_merge($this->data, ['route' => $this->data['route'] ?? 'notifications']),
            'messages',
            isset($this->data['conversationId']) ? 'conversation-'.$this->data['conversationId'] : null,
        );
    }
}