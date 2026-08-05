<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 120);
            $table->string('body', 500);
            $table->json('data')->nullable();
            $table->string('group_key', 120)->nullable();
            $table->string('dedupe_key', 160)->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['user_id', 'group_key', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('messages_enabled')->default(true);
            $table->boolean('pickup_requests_enabled')->default(true);
            $table->boolean('delivery_enabled')->default(true);
            $table->boolean('reviews_enabled')->default(true);
            $table->boolean('listing_updates_enabled')->default(true);
            $table->boolean('marketing_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_notifications');
    }
};
