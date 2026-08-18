<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->boolean('android_enabled')->default(true)->after('image_path');
            $table->boolean('ios_enabled')->default(true)->after('android_enabled');
            $table->index(['format', 'is_active', 'starts_at', 'ends_at'], 'ads_banner_active_idx');
        });

        DB::table('advertisement_placement_settings')
            ->where('kind', 'native')
            ->update(['source_order' => json_encode(['admob'])]);
    }

    public function down(): void
    {
        DB::table('advertisement_placement_settings')
            ->where('kind', 'native')
            ->update(['source_order' => json_encode(['direct', 'admob'])]);

        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropIndex('ads_banner_active_idx');
            $table->dropColumn(['android_enabled', 'ios_enabled']);
        });
    }
};