<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $moderated = $this->moderated_at !== null;
        return [
            'id' => $this->id,
            'sender' => $moderated || $this->type === 'system'
                ? 'system'
                : ($this->sender_id === $request->user()?->id ? 'me' : 'other'),
            'text' => $moderated ? 'Bu mesaj topluluk kurallarını ihlal ettiği için kaldırıldı.' : $this->body,
            'time' => $this->created_at?->format('H:i'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'clientId' => $this->client_id,
            'moderated' => $moderated,
        ];
    }
}
