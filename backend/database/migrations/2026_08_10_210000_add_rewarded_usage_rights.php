<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewarded_ad_claims', function (Blueprint $table) {
            $table->foreignId('listing_id')->nullable()->change();
            $table->string('reward_key', 80)->nullable()->after('reward_type')->index();
            $table->unsignedInteger('reward_amount')->default(1)->after('reward_key');
            $table->string('expected_ad_unit_id', 100)->nullable()->after('reward_amount');
            $table->string('expected_reward_item', 100)->nullable()->after('expected_ad_unit_id');
        });

        Schema::create('rewarded_usage_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rewarded_ad_claim_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reward_key', 80)->index();
            $table->unsignedInteger('amount')->default(1);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'reward_key', 'expires_at']);
        });

        $now = now();
        DB::table('advertisement_placement_settings')->updateOrInsert(
            ['key' => 'rewarded_extra_rights'],
            [
                'label' => 'Video izle, ek kullanım hakkı kazan',
                'kind' => 'rewarded',
                'location_label' => 'Limit dolduğunda ilgili işlemde ve Limitlerim ekranında',
                'enabled' => true,
                'locked' => false,
                'source_order' => json_encode(['admob']),
                'first_after' => 0,
                'repeat_every' => 0,
                'max_per_session' => 20,
                'min_items' => 0,
                'admob_android_unit_id' => 'ca-app-pub-6681150378641816/6596149732',
                'admob_ios_unit_id' => null,
                'settings' => json_encode([
                    'reward_item' => 'ek_hak',
                    'rewards' => [
                        'listing_daily' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'active_listing' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'pickup_daily' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'active_pickup' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'listing_pending_pickup' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'contact_daily' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'message_conversation_daily' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'same_seller_contact_daily' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                        'contact_cooldown' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 3, 'valid_hours' => 1],
                        'message_minute' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 1],
                        'message_hour' => ['enabled' => true, 'amount' => 5, 'daily_limit' => 2, 'valid_hours' => 1],
                        'message_daily' => ['enabled' => true, 'amount' => 10, 'daily_limit' => 2, 'valid_hours' => 24],
                        'unanswered_message' => ['enabled' => true, 'amount' => 1, 'daily_limit' => 2, 'valid_hours' => 24],
                    ],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('advertisement_placement_settings')->where('key', 'rewarded_extra_rights')->delete();
        Schema::dropIfExists('rewarded_usage_grants');
        Schema::table('rewarded_ad_claims', function (Blueprint $table) {
            $table->dropIndex(['reward_key']);
            $table->dropColumn(['reward_key', 'reward_amount', 'expected_ad_unit_id', 'expected_reward_item']);
            $table->foreignId('listing_id')->nullable(false)->change();
        });
    }
};