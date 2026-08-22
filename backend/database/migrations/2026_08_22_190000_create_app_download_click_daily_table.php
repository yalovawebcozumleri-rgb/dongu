<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_download_click_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('click_date');
            $table->string('platform', 16);
            $table->string('destination', 24);
            $table->string('source', 80);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->unique(['click_date', 'platform', 'destination', 'source'], 'download_click_daily_unique');
            $table->index('click_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_download_click_daily');
    }
};
