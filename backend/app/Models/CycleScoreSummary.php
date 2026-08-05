<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleScoreSummary extends Model
{
    protected $fillable = ['user_id', 'period_key', 'points', 'deliveries'];
    protected function casts(): array { return ['points' => 'integer', 'deliveries' => 'integer']; }
}
