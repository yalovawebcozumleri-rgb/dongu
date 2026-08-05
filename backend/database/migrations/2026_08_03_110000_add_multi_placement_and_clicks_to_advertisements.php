<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 40);
            $table->unique(['advertisement_id', 'placement'], 'ad_placement_unique');
            $table->index(['placement', 'advertisement_id'], 'ad_placement_lookup_idx');
        });

        DB::table('advertisements')->orderBy('id')->each(function ($advertisement): void {
            DB::table('advertisement_placements')->insert([
                'advertisement_id' => $advertisement->id,
                'placement' => $advertisement->placement,
            ]);
        });

        Schema::table('advertisement_impressions', function (Blueprint $table) {
            $table->dropUnique('ad_session_slot_unique');
            $table->string('placement', 40)->default('home_feed')->after('advertisement_id');
            $table->timestamp('clicked_at')->nullable()->after('viewed_at')->index();
            $table->unique(['advertisement_id', 'session_key', 'placement', 'slot_index'], 'ad_session_place_slot_unique');
            $table->index(['advertisement_id', 'placement', 'viewed_at'], 'ad_campaign_place_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('advertisement_impressions', function (Blueprint $table) {
            $table->dropUnique('ad_session_place_slot_unique');
            $table->dropIndex('ad_campaign_place_viewed_idx');
            $table->dropIndex(['clicked_at']);
            $table->dropColumn(['placement', 'clicked_at']);
            $table->unique(['advertisement_id', 'session_key', 'slot_index'], 'ad_session_slot_unique');
        });
        Schema::dropIfExists('advertisement_placements');
    }
};
