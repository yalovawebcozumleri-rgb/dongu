<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdMobRuntimeSetting extends Model
{
    protected $table = 'admob_runtime_settings';

    public const MODE_TEST = 'test';
    public const MODE_PRODUCTION = 'production';
    public const MODES = [self::MODE_TEST, self::MODE_PRODUCTION];
    public const SINGLETON_ID = 1;
    private const CACHE_KEY = 'advertising:admob-runtime-setting:v1';

    protected $fillable = [
        'android_mode',
        'ios_mode',
        'configuration_version',
        'changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'configuration_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            static::clearCache();
        });
        static::deleted(static function (): void {
            static::clearCache();
        });
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public static function current(): self
    {
        return static::query()->with('changedBy')->findOrFail(self::SINGLETON_ID);
    }

    public static function modeFor(string $platform): string
    {
        $snapshot = static::snapshot();

        return $platform === 'ios' ? $snapshot['iosMode'] : $snapshot['androidMode'];
    }

    public static function configurationVersion(): int
    {
        return static::snapshot()['configurationVersion'];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinute(), static function (): array {
            try {
                if (Schema::hasTable('admob_runtime_settings')) {
                    $setting = static::query()->find(self::SINGLETON_ID);
                    if ($setting) {
                        return [
                            'androidMode' => static::normaliseMode($setting->android_mode),
                            'iosMode' => static::normaliseMode($setting->ios_mode),
                            'configurationVersion' => max(1, (int) $setting->configuration_version),
                        ];
                    }
                }
            } catch (Throwable) {
                // During a zero-downtime migration, keep serving the existing env configuration.
            }

            return [
                'androidMode' => static::normaliseMode(config('advertising.admob.modes.android', config('advertising.admob.mode', 'test'))),
                'iosMode' => static::normaliseMode(config('advertising.admob.modes.ios', config('advertising.admob.mode', 'test'))),
                'configurationVersion' => 1,
            ];
        });
    }

    private static function normaliseMode(mixed $mode): string
    {
        return $mode === self::MODE_PRODUCTION ? self::MODE_PRODUCTION : self::MODE_TEST;
    }
}
