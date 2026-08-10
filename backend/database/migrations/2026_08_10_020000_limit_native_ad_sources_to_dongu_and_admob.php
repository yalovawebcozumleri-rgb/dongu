<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('advertisement_placement_settings')
            ->where('kind', 'native')
            ->update(['source_order' => json_encode(['direct', 'admob'])]);
    }

    public function down(): void
    {
        DB::table('advertisement_placement_settings')
            ->where('kind', 'native')
            ->update(['source_order' => json_encode(['direct', 'admob', 'house'])]);
    }
};
