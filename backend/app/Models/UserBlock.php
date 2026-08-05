<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocker(): BelongsTo { return $this->belongsTo(User::class, 'blocker_id'); }
    public function blocked(): BelongsTo { return $this->belongsTo(User::class, 'blocked_id'); }

    public static function relatedUserIds(int $userId): array
    {
        return static::query()
            ->where('blocker_id', $userId)
            ->orWhere('blocked_id', $userId)
            ->get(['blocker_id', 'blocked_id'])
            ->map(fn (UserBlock $block) => $block->blocker_id === $userId ? $block->blocked_id : $block->blocker_id)
            ->unique()
            ->values()
            ->all();
    }

    public static function existsBetween(int $firstUserId, int $secondUserId): bool
    {
        return static::query()
            ->where(fn ($query) => $query
                ->where('blocker_id', $firstUserId)
                ->where('blocked_id', $secondUserId))
            ->orWhere(fn ($query) => $query
                ->where('blocker_id', $secondUserId)
                ->where('blocked_id', $firstUserId))
            ->exists();
    }
}