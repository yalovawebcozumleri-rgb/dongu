<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageReport extends Model
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'conversation_message_id', 'reporter_id', 'reason', 'details', 'status', 'enforcement_action', 'remove_message',
        'resolved_by_admin_id', 'resolution_note', 'resolved_at',
    ];

    protected function casts(): array { return ['resolved_at' => 'datetime', 'remove_message' => 'boolean']; }
    public function message(): BelongsTo { return $this->belongsTo(ConversationMessage::class, 'conversation_message_id'); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_admin_id'); }
    public function sanctions(): HasMany { return $this->hasMany(ModerationSanction::class); }
}
