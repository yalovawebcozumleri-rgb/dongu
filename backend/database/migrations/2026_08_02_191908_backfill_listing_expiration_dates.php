<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('listings')
            ->whereNull('expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($listings) {
                foreach ($listings as $listing) {
                    $publishedAt = $listing->published_at ?: $listing->created_at;
                    DB::table('listings')->where('id', $listing->id)->update([
                        'expires_at' => Carbon::parse($publishedAt)
                            ->addDays(config('marketplace.listing_lifetime_days')),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data backfills are intentionally not reversed.
    }
};