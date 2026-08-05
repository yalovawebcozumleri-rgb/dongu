<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'group_key', 'dedupe_key', 'read_at', 'push_processed_at', 'push_sent_at', 'push_error'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'push_processed_at' => 'datetime', 'push_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
