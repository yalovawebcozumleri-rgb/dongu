<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('status', 20)->default('active')->index()->after('avatar_path');
            $table->unsignedInteger('completed_transactions')->default(0)->after('status');
            $table->decimal('rating', 2, 1)->default(5.0)->after('completed_transactions');
            $table->timestamp('profile_completed_at')->nullable()->after('rating');
            $table->timestamp('terms_accepted_at')->nullable()->after('profile_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['status']);
            $table->dropColumn(['phone', 'avatar_path', 'status', 'completed_transactions', 'rating', 'profile_completed_at', 'terms_accepted_at']);
        });
    }
};
