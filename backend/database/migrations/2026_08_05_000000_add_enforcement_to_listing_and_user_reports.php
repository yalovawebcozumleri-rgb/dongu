<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_reports', function (Blueprint $table) {
            $table->string('enforcement_action', 50)->nullable()->after('status');
        });
        Schema::table('user_reports', function (Blueprint $table) {
            $table->string('enforcement_action', 50)->nullable()->after('status');
        });
        Schema::table('moderation_sanctions', function (Blueprint $table) {
            $table->foreignId('user_report_id')->nullable()->after('message_report_id')->constrained()->cascadeOnDelete();
            $table->index(['user_report_id', 'created_at'], 'sanctions_user_report_history_idx');
        });
        Schema::table('admin_listing_actions', function (Blueprint $table) {
            $table->foreignId('listing_report_id')->nullable()->after('listing_id')->constrained()->nullOnDelete();
            $table->index(['listing_report_id', 'created_at'], 'listing_actions_report_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('admin_listing_actions', function (Blueprint $table) {
            $table->dropIndex('listing_actions_report_history_idx');
            $table->dropConstrainedForeignId('listing_report_id');
        });
        Schema::table('moderation_sanctions', function (Blueprint $table) {
            $table->dropIndex('sanctions_user_report_history_idx');
            $table->dropConstrainedForeignId('user_report_id');
        });
        Schema::table('user_reports', fn (Blueprint $table) => $table->dropColumn('enforcement_action'));
        Schema::table('listing_reports', fn (Blueprint $table) => $table->dropColumn('enforcement_action'));
    }
};
