<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIVE_PLACEMENTS = ['home_feed', 'leaderboard', 'listing_detail'];

    public function up(): void
    {
        DB::table('advertisement_placements')
            ->whereNotIn('placement', self::ACTIVE_PLACEMENTS)
            ->delete();

        DB::table('advertisements')
            ->whereNotIn('placement', self::ACTIVE_PLACEMENTS)
            ->update(['placement' => 'home_feed']);

        DB::table('advertisements')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('advertisement_placements')
                ->whereColumn('advertisement_placements.advertisement_id', 'advertisements.id'))
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Kaldırılan yayın alanları geçmiş kampanyalara tahminen geri eklenemez.
    }
};
