<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporter_businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->string('logo_path')->nullable();
            $table->string('card_summary', 180);
            $table->string('detail_title', 160);
            $table->text('detail_body');
            $table->enum('target_scope', ['district', 'province', 'nationwide'])->default('district');
            $table->string('province_code', 10)->nullable()->index();
            $table->string('province_name', 80)->nullable();
            $table->string('district_code', 20)->nullable()->index();
            $table->string('district_name', 100)->nullable();
            $table->enum('cta_type', ['whatsapp', 'phone', 'website', 'instagram', 'directions']);
            $table->string('cta_label', 40);
            $table->string('cta_value', 500);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'target_scope', 'priority'], 'supporters_active_scope_priority');
        });

        Schema::create('supporter_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supporter_business_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('unique_reach')->default(0);
            $table->unsignedBigInteger('detail_views')->default(0);
            $table->unsignedBigInteger('cta_clicks')->default(0);
            $table->timestamps();
            $table->unique(['supporter_business_id', 'stat_date'], 'supporter_daily_stats_unique');
        });

        Schema::create('supporter_daily_visitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supporter_business_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->char('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['supporter_business_id', 'visit_date', 'visitor_hash'], 'supporter_daily_visitor_unique');
        });

        Schema::create('supporter_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supporter_business_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', ['impression', 'detail_view', 'cta_click']);
            $table->char('event_key', 64)->unique();
            $table->char('visitor_hash', 64)->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['supporter_business_id', 'event_type', 'occurred_at'], 'supporter_events_reporting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporter_events');
        Schema::dropIfExists('supporter_daily_visitors');
        Schema::dropIfExists('supporter_daily_stats');
        Schema::dropIfExists('supporter_businesses');
    }
};
