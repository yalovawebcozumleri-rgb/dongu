<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
        });

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id')->nullable()->change();
            $table->foreign('listing_id')->references('id')->on('listings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('pickup_requests')->whereNull('listing_id')->delete();

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
        });

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id')->nullable(false)->change();
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
        });
    }
};
