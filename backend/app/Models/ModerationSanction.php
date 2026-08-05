<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationSanction extends Model
{
    public const WARNING = 'warning';
    public const MESSAGE_24H = 'message_restriction_24h';
    public const MESSAGE_7D = 'message_restriction_7d';
    public const MESSAGE_30D = 'message_restriction_30d';
    public const ACCOUNT_24H = 'account_suspension_24h';
    public const ACCOUNT_7D = 'account_suspension_7d';
    public const ACCOUNT_30D = 'account_suspension_30d';
    public const ACCOUNT_INDEFINITE = 'account_suspension_indefinite';
    public const ACCOUNT_CLOSED = 'account_closed';
    public const RECORD_ONLY = 'record_only';

    protected $fillable = ['user_id', 'message_report_id', 'user_report_id', 'action', 'reason', 'starts_at', 'ends_at', 'applied_by_admin_id', 'revoked_at', 'revoked_by_admin_id', 'revoke_reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function report(): BelongsTo { return $this->belongsTo(MessageReport::class, 'message_report_id'); }
    public function userReport(): BelongsTo { return $this->belongsTo(UserReport::class); }
    public function appliedBy(): BelongsTo { return $this->belongsTo(User::class, 'applied_by_admin_id'); }
    public function revokedBy(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by_admin_id'); }

    public function isActive(): bool
    {
        $restrictive = str_starts_with($this->action, 'message_restriction_')
            || str_starts_with($this->action, 'account_suspension_')
            || $this->action === self::ACCOUNT_CLOSED;

        return $restrictive && $this->revoked_at === null && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
