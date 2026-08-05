<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupporterEvent extends Model
{
    protected $fillable = ['supporter_business_id', 'event_type', 'event_key', 'visitor_hash', 'occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    public function business(): BelongsTo { return $this->belongsTo(SupporterBusiness::class, 'supporter_business_id'); }
}
