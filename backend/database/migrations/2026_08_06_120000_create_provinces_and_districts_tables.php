<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedTinyInteger('province_id');
            $table->string('name', 80);
            $table->timestamps();

            $table->foreign('province_id')->references('id')->on('provinces')->cascadeOnDelete();
            $table->unique(['province_id', 'name']);
            $table->index(['province_id', 'name']);
        });

        $payload = json_decode(
            file_get_contents(database_path('data/turkey-provinces-districts.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $now = now();
        $provinces = [];
        $districts = [];

        foreach ($payload['data'] as $province) {
            $provinces[] = ['id' => $province['id'], 'name' => $province['name'], 'created_at' => $now, 'updated_at' => $now];
            foreach ($province['districts'] as $district) {
                $districts[] = [
                    'id' => $district['id'],
                    'province_id' => $province['id'],
                    'name' => $district['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('provinces')->insert($provinces);
        foreach (array_chunk($districts, 250) as $chunk) {
            DB::table('districts')->insert($chunk);
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->unsignedTinyInteger('province_id')->nullable()->after('label');
            $table->unsignedSmallInteger('district_id')->nullable()->after('province_id');
            $table->string('neighborhood', 100)->nullable()->after('district_id');
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->restrictOnDelete();
            $table->index(['province_id', 'district_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['district_id']);
            $table->dropIndex(['province_id', 'district_id']);
            $table->dropColumn(['province_id', 'district_id', 'neighborhood']);
        });

        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
