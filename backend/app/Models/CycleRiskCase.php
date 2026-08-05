<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleRiskCase extends Model
{
    public const PENDING = 'pending';
    public const CLEARED = 'cleared';
    public const CONFIRMED = 'confirmed';

    protected $fillable = ['pickup_request_id', 'status', 'severity', 'risk_score', 'rules', 'evidence', 'detected_at', 'reviewed_by_admin_id', 'review_note', 'reviewed_at'];
    protected function casts(): array { return ['risk_score' => 'integer', 'rules' => 'array', 'evidence' => 'array', 'detected_at' => 'datetime', 'reviewed_at' => 'datetime']; }
    public function pickupRequest(): BelongsTo { return $this->belongsTo(PickupRequest::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_admin_id'); }
    public function pointEntries(): HasMany { return $this->hasMany(CyclePointEntry::class, 'pickup_request_id', 'pickup_request_id'); }
    public function audits(): HasMany { return $this->hasMany(CycleAdminAudit::class); }
}
