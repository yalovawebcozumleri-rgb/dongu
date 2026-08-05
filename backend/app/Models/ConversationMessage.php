<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = ['pickup_request_id', 'sender_id', 'type', 'body', 'client_id', 'read_at', 'moderated_at', 'moderated_by_admin_id', 'moderation_report_id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'moderated_at' => 'datetime'];
    }

    public function pickupRequest(): BelongsTo { return $this->belongsTo(PickupRequest::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
