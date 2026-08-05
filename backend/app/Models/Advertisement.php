<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertisement extends Model
{
    public const PLACEMENT_HOME_FEED = 'home_feed';
    public const PLACEMENT_LEADERBOARD = 'leaderboard';
    public const PLACEMENT_LISTING_DETAIL = 'listing_detail';

    public const FORMAT_NATIVE = 'native';
    public const FORMAT_IMAGE = 'image';
    public const FORMAT_COMPACT = 'compact';
    public const FORMATS = [self::FORMAT_NATIVE, self::FORMAT_IMAGE, self::FORMAT_COMPACT];

    public const PLACEMENTS = [
        self::PLACEMENT_HOME_FEED,
        self::PLACEMENT_LEADERBOARD,
        self::PLACEMENT_LISTING_DETAIL,
    ];

    public const PLACEMENT_LABELS = [
        self::PLACEMENT_HOME_FEED => 'Ana sayfa',
        self::PLACEMENT_LEADERBOARD => 'Döngü sıralaması',
        self::PLACEMENT_LISTING_DETAIL => 'İlan detayı',
    ];

    protected $fillable = [
        'placement', 'format', 'sponsor_name', 'headline', 'body', 'cta_label', 'target_url',
        'background_color', 'image_path', 'is_active', 'starts_at', 'ends_at', 'priority',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'priority' => 'integer'];
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdvertisementPlacement::class);
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdvertisementImpression::class);
    }
}