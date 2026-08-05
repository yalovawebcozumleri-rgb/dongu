<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginCode extends Model
{
    public const INTENT_LOGIN = 'login';
    public const INTENT_REGISTER = 'register';
    public const INTENT_DELETE = 'delete';
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'email', 'intent', 'pending_name', 'terms_accepted', 'terms_version', 'privacy_notice_version', 'code_hash',
        'attempts', 'expires_at', 'consumed_at', 'requested_ip',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'terms_accepted' => 'boolean',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
