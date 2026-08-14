<?php

use App\Models\AdvertisementPlacementSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AdvertisementPlacementSetting::query()
            ->where('kind', AdvertisementPlacementSetting::KIND_NATIVE)
            ->whereIn('key', AdvertisementPlacementSetting::SINGLE_NATIVE_PLACEMENTS)
            ->update(['max_per_session' => 1]);

        AdvertisementPlacementSetting::query()
            ->where('kind', AdvertisementPlacementSetting::KIND_NATIVE)
            ->whereNotIn('key', AdvertisementPlacementSetting::SINGLE_NATIVE_PLACEMENTS)
            ->where('max_per_session', '>', AdvertisementPlacementSetting::MAX_NATIVE_ADS_PER_PAGE)
            ->update(['max_per_session' => AdvertisementPlacementSetting::MAX_NATIVE_ADS_PER_PAGE]);
    }

    public function down(): void
    {
        // The previous per-placement values cannot be recovered safely.
    }
};
