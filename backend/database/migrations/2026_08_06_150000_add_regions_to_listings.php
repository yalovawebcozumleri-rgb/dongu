<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedTinyInteger('province_id')->nullable()->after('user_id');
            $table->unsignedSmallInteger('district_id')->nullable()->after('province_id');
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->restrictOnDelete();
            $table->index(['province_id', 'status', 'published_at'], 'listings_province_feed_index');
        });

        $provinces = DB::table('provinces')->get(['id', 'name']);
        DB::table('listings')->whereNull('province_id')->orderBy('id')->chunkById(250, function ($listings) use ($provinces): void {
            foreach ($listings as $listing) {
                $area = Str::lower(Str::ascii((string) $listing->public_area));
                $province = $provinces->first(
                    fn ($province) => str_ends_with($area, Str::lower(Str::ascii((string) $province->name)))
                );
                if ($province) {
                    DB::table('listings')->where('id', $listing->id)->update(['province_id' => $province->id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['province_id']);
            $table->dropIndex('listings_province_feed_index');
            $table->dropColumn(['province_id', 'district_id']);
        });
    }
};
