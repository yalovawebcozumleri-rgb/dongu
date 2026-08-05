<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_report_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('applied_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'action', 'revoked_at', 'ends_at'], 'sanctions_user_action_active_idx');
            $table->index(['message_report_id', 'created_at'], 'sanctions_report_history_idx');
        });

        Schema::table('message_reports', function (Blueprint $table) {
            $table->string('enforcement_action', 50)->nullable()->after('status');
            $table->boolean('remove_message')->default(false)->after('enforcement_action');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->timestamp('moderated_at')->nullable()->after('read_at');
            $table->foreignId('moderated_by_admin_id')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->foreignId('moderation_report_id')->nullable()->after('moderated_by_admin_id')->constrained('message_reports')->nullOnDelete();
            $table->index('moderated_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropIndex(['moderated_at']);
            $table->dropConstrainedForeignId('moderation_report_id');
            $table->dropConstrainedForeignId('moderated_by_admin_id');
            $table->dropColumn('moderated_at');
        });
        Schema::table('message_reports', fn (Blueprint $table) => $table->dropColumn(['enforcement_action', 'remove_message']));
        Schema::dropIfExists('moderation_sanctions');
    }
};
