<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDispatch extends Model
{
    protected $fillable = ['announcement_campaign_id', 'run_key', 'scheduled_for', 'status', 'recipients_count', 'push_eligible_count', 'error', 'completed_at'];
    protected function casts(): array { return ['scheduled_for' => 'datetime', 'completed_at' => 'datetime', 'recipients_count' => 'integer', 'push_eligible_count' => 'integer']; }
    public function campaign(): BelongsTo { return $this->belongsTo(AnnouncementCampaign::class, 'announcement_campaign_id'); }
}
