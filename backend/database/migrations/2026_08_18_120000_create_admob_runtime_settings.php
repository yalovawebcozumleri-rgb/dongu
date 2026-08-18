<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admob_runtime_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('android_mode', 16);
            $table->string('ios_mode', 16);
            $table->unsignedBigInteger('configuration_version')->default(1);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admob_runtime_setting_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admob_runtime_setting_id')->constrained('admob_runtime_settings')->cascadeOnDelete();
            $table->string('previous_android_mode', 16);
            $table->string('new_android_mode', 16);
            $table->string('previous_ios_mode', 16);
            $table->string('new_ios_mode', 16);
            $table->unsignedBigInteger('configuration_version');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        $normalise = static fn (mixed $mode): string => $mode === 'production' ? 'production' : 'test';

        DB::table('admob_runtime_settings')->insert([
            'id' => 1,
            'android_mode' => $normalise(config('advertising.admob.modes.android', config('advertising.admob.mode', 'test'))),
            'ios_mode' => $normalise(config('advertising.admob.modes.ios', config('advertising.admob.mode', 'test'))),
            'configuration_version' => 1,
            'changed_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admob_runtime_setting_audits');
        Schema::dropIfExists('admob_runtime_settings');
    }
};
