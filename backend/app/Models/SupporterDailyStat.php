<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupporterDailyStat extends Model
{
    protected $fillable = ['supporter_business_id', 'stat_date', 'impressions', 'unique_reach', 'detail_views', 'cta_clicks'];
    protected function casts(): array { return ['stat_date' => 'date', 'impressions' => 'integer', 'unique_reach' => 'integer', 'detail_views' => 'integer', 'cta_clicks' => 'integer']; }
    public function business(): BelongsTo { return $this->belongsTo(SupporterBusiness::class, 'supporter_business_id'); }
}
