<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementPlacementSetting extends Model
{
    public const KIND_NATIVE = 'native';
    public const KIND_INTERSTITIAL = 'interstitial';
    public const KIND_REWARDED = 'rewarded';

    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_ADMOB = 'admob';
    public const MAX_NATIVE_ADS_PER_PAGE = 5;
    public const SINGLE_NATIVE_PLACEMENTS = [
        'leaderboard',
        'listing_detail',
        'public_profile',
        'transaction_detail',
        'profile_home',
        'usage_limits',
    ];

    public const NATIVE_SOURCES = [self::SOURCE_DIRECT, self::SOURCE_ADMOB];

    protected $fillable = [
        'key', 'label', 'kind', 'location_label', 'enabled', 'android_enabled', 'ios_enabled', 'locked', 'source_order',
        'first_after', 'repeat_every', 'max_per_session', 'min_items',
        'admob_android_unit_id', 'admob_ios_unit_id', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'android_enabled' => 'boolean',
            'ios_enabled' => 'boolean',
            'locked' => 'boolean',
            'source_order' => 'array',
            'settings' => 'array',
            'first_after' => 'integer',
            'repeat_every' => 'integer',
            'max_per_session' => 'integer',
            'min_items' => 'integer',
        ];
    }

    public function platformEnabled(?string $platform): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return match ($platform) {
            'android' => $this->android_enabled,
            'ios' => $this->ios_enabled,
            default => true,
        };
    }

    public function adMobUnitId(string $platform, string $format): ?string
    {
        if (! $this->platformEnabled($platform)) {
            return null;
        }

        if ($this->adMobMode($platform) === 'test') {
            return config("advertising.admob.test_unit_ids.{$platform}.{$format}");
        }

        return $platform === 'ios'
            ? $this->admob_ios_unit_id
            : $this->admob_android_unit_id;
    }

    public function adMobMode(string $platform): string
    {
        return AdMobRuntimeSetting::modeFor($platform);
    }

    public function nativeAdLimit(): int
    {
        return in_array($this->key, self::SINGLE_NATIVE_PLACEMENTS, true)
            ? 1
            : self::MAX_NATIVE_ADS_PER_PAGE;
    }

    public static function forKey(string $key): self
    {
        return static::query()->where('key', $key)->firstOrFail();
    }
}
