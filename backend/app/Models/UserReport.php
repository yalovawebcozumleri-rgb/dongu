<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'reported_user_id', 'reporter_id', 'reason', 'details', 'status',
        'enforcement_action', 'resolution_note', 'resolved_by_admin_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportedUser(): BelongsTo { return $this->belongsTo(User::class, 'reported_user_id'); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_admin_id'); }
    public function sanctions(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ModerationSanction::class); }
}
