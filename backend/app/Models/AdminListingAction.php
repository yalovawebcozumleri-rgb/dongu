<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminListingAction extends Model
{
    protected $fillable = ['listing_id', 'listing_report_id', 'admin_id', 'action', 'reason', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
    public function report(): BelongsTo { return $this->belongsTo(ListingReport::class, 'listing_report_id'); }
}
