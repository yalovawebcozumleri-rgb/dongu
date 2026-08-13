<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisement_placement_settings', function (Blueprint $table): void {
            $table->boolean('android_enabled')->default(true)->after('enabled');
            $table->boolean('ios_enabled')->default(true)->after('android_enabled');
        });

        DB::table('advertisement_placement_settings')
            ->where('enabled', false)
            ->update(['android_enabled' => false, 'ios_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('advertisement_placement_settings', function (Blueprint $table): void {
            $table->dropColumn(['android_enabled', 'ios_enabled']);
        });
    }
};