<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CyclePointEntry extends Model
{
    public const ACTIVE = 'active';
    public const PENDING_REVIEW = 'pending_review';
    public const REVOKED = 'revoked';

    protected $fillable = ['user_id', 'pickup_request_id', 'role', 'reason', 'points', 'status', 'earned_at'];
    protected function casts(): array { return ['points' => 'integer', 'earned_at' => 'datetime']; }
}
