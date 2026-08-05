<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementImpression extends Model
{
    public $timestamps = false;

    protected $fillable = ['advertisement_id', 'placement', 'user_id', 'session_key', 'slot_index', 'viewed_at', 'clicked_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime', 'clicked_at' => 'datetime'];
    }
}