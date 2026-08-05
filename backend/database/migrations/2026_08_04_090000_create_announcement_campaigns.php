<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title', 100);
            $table->string('body', 500);
            $table->string('audience', 30)->default('all_active');
            $table->json('target_user_ids')->nullable();
            $table->boolean('push_enabled')->default(true);
            $table->string('recurrence', 20)->default('none');
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedBigInteger('total_in_app_deliveries')->default(0);
            $table->unsignedBigInteger('total_push_eligible')->default(0);
            $table->timestamps();
            $table->index(['status', 'next_send_at']);
            $table->index(['type', 'last_sent_at']);
        });

        Schema::create('announcement_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('run_key', 50);
            $table->timestamp('scheduled_for');
            $table->string('status', 20)->default('processing');
            $table->unsignedBigInteger('recipients_count')->default(0);
            $table->unsignedBigInteger('push_eligible_count')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_campaign_id', 'run_key'], 'announcement_dispatch_run_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dispatches');
        Schema::dropIfExists('announcement_campaigns');
    }
};
