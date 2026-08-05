<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_reports', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('details');
            $table->foreignId('resolved_by_admin_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_admin_id');
            $table->timestamp('resolved_at')->nullable()->after('resolution_note');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('message_reports', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('resolved_by_admin_id');
            $table->dropColumn(['status', 'resolution_note', 'resolved_at']);
        });
    }
};
