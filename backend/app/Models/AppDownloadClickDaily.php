<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class AppDownloadClickDaily extends Model
{
    protected $table = 'app_download_click_daily';

    protected $fillable = ['click_date', 'platform', 'destination', 'source', 'clicks'];

    protected function casts(): array
    {
        return ['clicks' => 'integer'];
    }

    public static function record(string $date, string $platform, string $destination, string $source): void
    {
        $attributes = compact('platform', 'destination', 'source');
        $attributes['click_date'] = $date;

        if (static::query()->where($attributes)->increment('clicks') > 0) {
            return;
        }

        try {
            static::query()->create([...$attributes, 'clicks' => 1]);
        } catch (QueryException) {
            // A simultaneous first click may have inserted the daily row first.
            static::query()->where($attributes)->increment('clicks');
        }
    }
}
