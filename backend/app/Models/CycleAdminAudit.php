<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleAdminAudit extends Model
{
    protected $fillable = ['admin_user_id', 'cycle_risk_case_id', 'action', 'before_state', 'after_state', 'reason', 'ip_address', 'user_agent'];
    protected function casts(): array { return ['before_state' => 'array', 'after_state' => 'array']; }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_user_id'); }
    public function riskCase(): BelongsTo { return $this->belongsTo(CycleRiskCase::class, 'cycle_risk_case_id'); }
}
