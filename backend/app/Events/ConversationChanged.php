<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $userId, public readonly int $conversationId, public readonly string $kind) {}
    public function broadcastOn(): array { return [new PrivateChannel('users.'.$this->userId)]; }
    public function broadcastAs(): string { return 'conversation.changed'; }
    public function broadcastWith(): array { return ['conversationId' => $this->conversationId, 'kind' => $this->kind]; }
}
