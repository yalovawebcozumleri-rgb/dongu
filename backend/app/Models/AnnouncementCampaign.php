<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementCampaign extends Model
{
    use SoftDeletes;
    public const TYPE_MARKETING = 'marketing';
    public const TYPE_SYSTEM = 'system';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'created_by_admin_id', 'type', 'title', 'body', 'audience', 'target_user_ids',
        'push_enabled', 'recurrence', 'status', 'scheduled_at', 'next_send_at', 'ends_at',
        'last_sent_at', 'runs_count', 'total_in_app_deliveries', 'total_push_eligible',
    ];

    protected function casts(): array
    {
        return [
            'target_user_ids' => 'array', 'push_enabled' => 'boolean',
            'scheduled_at' => 'datetime', 'next_send_at' => 'datetime', 'ends_at' => 'datetime',
            'last_sent_at' => 'datetime', 'runs_count' => 'integer',
            'total_in_app_deliveries' => 'integer', 'total_push_eligible' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_admin_id'); }
    public function dispatches(): HasMany { return $this->hasMany(AnnouncementDispatch::class); }
}
