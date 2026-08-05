<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationUserState extends Model
{
    protected $fillable = ['pickup_request_id', 'user_id', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }
}
