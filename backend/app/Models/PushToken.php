<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'device_id', 'last_used_at', 'failure_count', 'last_error', 'last_failed_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'last_failed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
