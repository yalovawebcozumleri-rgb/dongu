<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->json('listing_snapshot')->nullable()->after('status');
            $table->string('closed_reason', 40)->nullable()->after('listing_snapshot')->index();
            $table->timestamp('closed_at')->nullable()->after('closed_reason');
        });

        DB::table('pickup_requests')->orderBy('id')->chunkById(100, function ($requests): void {
            foreach ($requests as $request) {
                $listing = DB::table('listings')->where('id', $request->listing_id)->first();
                if (! $listing) continue;

                $seller = DB::table('users')->where('id', $listing->user_id)->value('name');
                $materials = DB::table('listing_materials')
                    ->where('listing_id', $listing->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($material) => [
                        'material' => match ($material->type) {
                            'pet' => 'PET',
                            'glass' => 'Cam',
                            'aluminum' => 'Alüminyum',
                            default => $material->type,
                        },
                        'type' => $material->type,
                        'count' => (int) $material->quantity,
                        'unitPrice' => (float) $material->unit_price,
                    ])->values()->all();

                DB::table('pickup_requests')->where('id', $request->id)->update([
                    'listing_snapshot' => json_encode([
                        'id' => (int) $listing->id,
                        'sellerId' => (int) $listing->user_id,
                        'seller' => (string) ($seller ?? ''),
                        'district' => (string) $listing->public_area,
                        'items' => $materials,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropIndex(['closed_reason']);
            $table->dropColumn(['listing_snapshot', 'closed_reason', 'closed_at']);
        });
    }
};
