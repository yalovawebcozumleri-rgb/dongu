<?php

use App\Models\CyclePointEntry;
use App\Services\CyclePointService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $buyerIds = CyclePointEntry::query()
            ->where('role', 'buyer')
            ->where('reason', 'delivery_completed')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        if ($buyerIds === []) {
            return;
        }

        CyclePointEntry::query()
            ->where('role', 'buyer')
            ->where('reason', 'delivery_completed')
            ->update(['status' => CyclePointEntry::REVOKED]);

        app(CyclePointService::class)->rebuildUsers($buyerIds);
    }

    public function down(): void
    {
        // Geçmişte çift yazılan puanlar veri doğruluğu için geri yüklenmez.
    }
};
