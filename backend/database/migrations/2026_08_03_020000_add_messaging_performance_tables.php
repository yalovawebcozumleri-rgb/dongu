<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('body');
            $table->unique(['sender_id', 'client_id']);
            $table->index(['pickup_request_id', 'id']);
        });

        Schema::create('conversation_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->unique(['pickup_request_id', 'user_id']);
            $table->index(['user_id', 'hidden_at']);
        });

        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->string('details', 500)->nullable();
            $table->timestamps();
            $table->unique(['conversation_message_id', 'reporter_id']);
            $table->index(['reason', 'created_at']);
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 20);
            $table->string('device_id', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('conversation_user_states');
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropUnique(['sender_id', 'client_id']);
            $table->dropIndex(['pickup_request_id', 'id']);
            $table->dropColumn('client_id');
        });
    }
};
