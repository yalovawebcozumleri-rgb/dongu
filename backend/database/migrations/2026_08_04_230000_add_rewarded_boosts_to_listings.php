<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('boosted_until')->nullable()->after('expires_at')->index();
        });

        Schema::create('rewarded_ad_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('reward_type', 40)->default('listing_boost_24h');
            $table->string('status', 24)->default('pending')->index();
            $table->string('transaction_id', 160)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewarded_ad_claims');
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn('boosted_until'));
    }
};
