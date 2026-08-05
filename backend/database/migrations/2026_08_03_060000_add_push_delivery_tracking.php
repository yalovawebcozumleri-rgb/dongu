<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->timestamp('push_processed_at')->nullable()->after('read_at');
            $table->timestamp('push_sent_at')->nullable()->after('push_processed_at');
            $table->string('push_error', 160)->nullable()->after('push_sent_at');
            $table->index(['user_id', 'group_key', 'push_processed_at'], 'notifications_push_group_index');
        });

        Schema::table('push_tokens', function (Blueprint $table) {
            $table->unsignedSmallInteger('failure_count')->default(0)->after('last_used_at');
            $table->string('last_error', 160)->nullable()->after('failure_count');
            $table->timestamp('last_failed_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_push_group_index');
            $table->dropColumn(['push_processed_at', 'push_sent_at', 'push_error']);
        });
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropColumn(['failure_count', 'last_error', 'last_failed_at']);
        });
    }
};
