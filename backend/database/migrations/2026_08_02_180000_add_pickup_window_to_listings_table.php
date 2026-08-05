<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('pickup_start_at')->nullable()->after('available_time')->index();
            $table->timestamp('pickup_end_at')->nullable()->after('pickup_start_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['pickup_start_at']);
            $table->dropIndex(['pickup_end_at']);
            $table->dropColumn(['pickup_start_at', 'pickup_end_at']);
        });
    }
};