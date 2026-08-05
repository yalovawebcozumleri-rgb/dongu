<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPrivateLocation extends Model
{
    protected $fillable = ['listing_id', 'latitude', 'longitude', 'address', 'delivery_notes'];

    protected $hidden = ['latitude', 'longitude', 'address', 'delivery_notes'];

    protected function casts(): array
    {
        return [
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'address' => 'encrypted',
            'delivery_notes' => 'encrypted',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
