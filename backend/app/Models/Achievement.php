<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'points_threshold', 'deliveries_threshold', 'sort_order'];
}
