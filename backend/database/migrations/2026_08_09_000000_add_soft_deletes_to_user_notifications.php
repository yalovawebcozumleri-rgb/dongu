<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->softDeletes()->after('read_at');
            $table->index(['user_id', 'deleted_at', 'created_at'], 'notifications_user_deleted_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_deleted_created_index');
            $table->dropSoftDeletes();
        });
    }
};
