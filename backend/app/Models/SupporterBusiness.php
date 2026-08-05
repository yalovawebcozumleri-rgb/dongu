<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupporterBusiness extends Model
{
    use SoftDeletes;

    public const SCOPES = ['district', 'province', 'nationwide'];
    public const CTA_TYPES = ['whatsapp', 'phone', 'website', 'instagram', 'directions'];

    protected $fillable = [
        'owner_user_id', 'created_by_admin_id', 'name', 'slug', 'logo_path', 'card_summary',
        'detail_title', 'detail_body', 'target_scope', 'province_code', 'province_name',
        'district_code', 'district_name', 'cta_type', 'cta_label', 'cta_value', 'priority',
        'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_admin_id'); }
    public function dailyStats(): HasMany { return $this->hasMany(SupporterDailyStat::class); }
    public function events(): HasMany { return $this->hasMany(SupporterEvent::class); }
}
