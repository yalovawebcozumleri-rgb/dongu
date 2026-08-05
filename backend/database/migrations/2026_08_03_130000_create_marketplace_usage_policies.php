<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_usage_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('new_account_hours')->default(24);
            $table->unsignedSmallInteger('new_account_listing_limit')->default(2);
            $table->unsignedSmallInteger('listing_24h_limit')->default(5);
            $table->unsignedSmallInteger('active_listing_limit')->default(10);
            $table->unsignedSmallInteger('new_account_pickup_limit')->default(2);
            $table->unsignedSmallInteger('pickup_24h_limit')->default(5);
            $table->unsignedSmallInteger('active_pickup_limit')->default(5);
            $table->unsignedSmallInteger('listing_pending_pickup_limit')->default(5);
            $table->unsignedSmallInteger('new_account_contact_limit')->default(3);
            $table->unsignedSmallInteger('contact_24h_limit')->default(6);
            $table->unsignedSmallInteger('new_account_message_conversation_limit')->default(2);
            $table->unsignedSmallInteger('message_conversation_24h_limit')->default(3);
            $table->unsignedSmallInteger('same_seller_contact_24h_limit')->default(1);
            $table->unsignedSmallInteger('contact_cooldown_seconds')->default(30);
            $table->unsignedSmallInteger('messages_per_minute')->default(10);
            $table->unsignedSmallInteger('messages_per_hour')->default(40);
            $table->unsignedSmallInteger('messages_per_24h')->default(100);
            $table->unsignedSmallInteger('unanswered_message_limit')->default(3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('marketplace_usage_policies')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('marketplace_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'event_type', 'created_at'], 'usage_user_event_time_idx');
            $table->index(['user_id', 'target_user_id', 'event_type', 'created_at'], 'usage_user_target_event_time_idx');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->index(['sender_id', 'created_at'], 'messages_sender_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', fn (Blueprint $table) => $table->dropIndex('messages_sender_time_idx'));
        Schema::dropIfExists('marketplace_usage_events');
        Schema::dropIfExists('marketplace_usage_policies');
    }
};
