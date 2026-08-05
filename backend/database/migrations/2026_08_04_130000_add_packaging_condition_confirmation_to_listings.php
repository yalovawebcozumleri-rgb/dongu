<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('packaging_condition_confirmed_at')->nullable()->after('description');
            $table->string('packaging_condition_version', 32)->nullable()->after('packaging_condition_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['packaging_condition_confirmed_at', 'packaging_condition_version']);
        });
    }
};
