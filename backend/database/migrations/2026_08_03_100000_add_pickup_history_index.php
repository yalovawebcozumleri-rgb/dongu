<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->index(['buyer_id', 'status', 'updated_at'], 'pickup_buyer_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pickup_requests', fn (Blueprint $table) => $table->dropIndex('pickup_buyer_history_idx'));
    }
};
