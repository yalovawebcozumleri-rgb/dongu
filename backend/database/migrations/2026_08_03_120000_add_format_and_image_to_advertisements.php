<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->string('format', 20)->default('native')->after('placement');
            $table->string('image_path', 500)->nullable()->after('background_color');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', fn (Blueprint $table) => $table->dropColumn(['format', 'image_path']));
    }
};
