<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data ?? [],
            'read' => $this->read_at !== null,
            'createdAt' => $this->created_at?->toIso8601String(),
            'time' => $this->created_at?->locale('tr')->diffForHumans(),
        ];
    }
}
