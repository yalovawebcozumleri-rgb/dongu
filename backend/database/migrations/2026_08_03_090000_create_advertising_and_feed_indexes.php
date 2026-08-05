<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 40);
            $table->string('sponsor_name', 100);
            $table->string('headline', 140);
            $table->string('body', 240);
            $table->string('cta_label', 40)->nullable();
            $table->string('target_url', 500)->nullable();
            $table->string('background_color', 7)->default('#E8F4E9');
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['placement', 'is_active', 'starts_at', 'ends_at'], 'ads_active_placement_idx');
        });

        Schema::create('advertisement_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 80);
            $table->unsignedTinyInteger('slot_index');
            $table->timestamp('viewed_at')->index();
            $table->unique(['advertisement_id', 'session_key', 'slot_index'], 'ad_session_slot_unique');
            $table->index(['advertisement_id', 'viewed_at'], 'ad_campaign_viewed_idx');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->index(['status', 'approximate_latitude', 'approximate_longitude', 'published_at'], 'listing_feed_geo_idx');
            $table->index(['status', 'expires_at', 'published_at'], 'listing_feed_recent_idx');
        });

        Schema::table('listing_materials', function (Blueprint $table) {
            $table->index(['type', 'listing_id'], 'listing_material_filter_idx');
        });
    }

    public function down(): void
    {
        Schema::table('listing_materials', fn (Blueprint $table) => $table->dropIndex('listing_material_filter_idx'));
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listing_feed_geo_idx');
            $table->dropIndex('listing_feed_recent_idx');
        });
        Schema::dropIfExists('advertisement_impressions');
        Schema::dropIfExists('advertisements');
    }
};
