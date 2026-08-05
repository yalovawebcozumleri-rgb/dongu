<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable()->after('terms_accepted');
            $table->string('privacy_notice_version', 20)->nullable()->after('terms_version');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable()->after('terms_accepted_at');
            $table->timestamp('privacy_notice_acknowledged_at')->nullable()->after('terms_version');
            $table->string('privacy_notice_version', 20)->nullable()->after('privacy_notice_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('login_codes', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'privacy_notice_version']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'privacy_notice_acknowledged_at', 'privacy_notice_version']));
    }
};
