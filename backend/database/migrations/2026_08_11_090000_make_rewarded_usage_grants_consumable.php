<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewarded_usage_grants', function (Blueprint $table) {
            $table->unsignedInteger('remaining_amount')->default(0)->after('amount');
        });

        DB::table('rewarded_usage_grants')->update(['remaining_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('rewarded_usage_grants', function (Blueprint $table) {
            $table->dropColumn('remaining_amount');
        });
    }
};
