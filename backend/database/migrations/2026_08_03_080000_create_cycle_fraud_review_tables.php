<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_point_entries', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('points');
            $table->index(['user_id', 'status', 'earned_at'], 'cycle_entries_user_status_index');
        });

        Schema::create('cycle_risk_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('severity', 12);
            $table->unsignedSmallInteger('risk_score');
            $table->json('rules');
            $table->json('evidence')->nullable();
            $table->timestamp('detected_at');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'detected_at']);
        });

        Schema::create('cycle_admin_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cycle_risk_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->json('before_state');
            $table->json('after_state');
            $table->text('reason');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['cycle_risk_case_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_admin_audits');
        Schema::dropIfExists('cycle_risk_cases');
        Schema::table('cycle_point_entries', function (Blueprint $table) {
            $table->dropIndex('cycle_entries_user_status_index');
            $table->dropColumn('status');
        });
    }
};
