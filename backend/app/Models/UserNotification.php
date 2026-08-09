<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotification extends Model
{
    use SoftDeletes;

    public const CATEGORY_LISTINGS = 'listings';
    public const CATEGORY_MESSAGES = 'messages';
    public const CATEGORY_ANNOUNCEMENTS = 'announcements';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'group_key', 'dedupe_key', 'read_at', 'push_processed_at', 'push_sent_at', 'push_error'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'push_processed_at' => 'datetime', 'push_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public static function categoryForType(string $type): string
    {
        if (in_array($type, ['new_message', 'new_conversation'], true)) {
            return self::CATEGORY_MESSAGES;
        }

        if (str_starts_with($type, 'admin_') || str_starts_with($type, 'moderation_')) {
            return self::CATEGORY_ANNOUNCEMENTS;
        }

        return self::CATEGORY_LISTINGS;
    }

    public function getCategoryAttribute(): string
    {
        return self::categoryForType($this->type);
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return match ($category) {
            self::CATEGORY_MESSAGES => $query->whereIn('type', ['new_message', 'new_conversation']),
            self::CATEGORY_ANNOUNCEMENTS => $query->whereIn('type', [
                'admin_system', 'admin_marketing', 'moderation_action', 'moderation_report_result',
            ]),
            self::CATEGORY_LISTINGS => $query
                ->whereNotIn('type', [
                    'new_message', 'new_conversation', 'admin_system', 'admin_marketing',
                    'moderation_action', 'moderation_report_result',
                ]),
            default => $query,
        };
    }
}
