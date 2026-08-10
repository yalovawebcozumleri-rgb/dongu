<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_placement_settings')) {
            DB::table('advertisement_placement_settings')
                ->whereIn('key', ['supporters_list', 'supporter_profile'])
                ->delete();
        }

        Schema::dropIfExists('business_subscriptions');
        Schema::dropIfExists('business_packages');
        Schema::dropIfExists('business_feature_settings');
        Schema::dropIfExists('supporter_events');
        Schema::dropIfExists('supporter_daily_visitors');
        Schema::dropIfExists('supporter_daily_stats');
        Schema::dropIfExists('supporter_businesses');
    }

    public function down(): void
    {
        // Özellik bilinçli olarak kaldırıldı. Eski işletme verileri otomatik geri oluşturulmaz.
    }
};
