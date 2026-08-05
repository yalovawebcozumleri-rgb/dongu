<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ranking_name_visible')->default(true)->after('completed_transactions');
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('description', 180);
            $table->string('icon', 12);
            $table->unsignedInteger('points_threshold')->default(0);
            $table->unsignedInteger('deliveries_threshold')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cycle_point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pickup_request_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);
            $table->string('reason', 32)->default('delivery_completed');
            $table->unsignedSmallInteger('points');
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['user_id', 'pickup_request_id', 'reason'], 'cycle_entries_delivery_unique');
            $table->index(['earned_at', 'user_id']);
        });

        Schema::create('cycle_score_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 7);
            $table->unsignedBigInteger('points')->default(0);
            $table->unsignedInteger('deliveries')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'period_key']);
            $table->index(['period_key', 'points', 'deliveries'], 'cycle_summaries_ranking_index');
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'achievement_id']);
        });

        $now = now();
        DB::table('achievements')->insert([
            ['code' => 'first_cycle', 'name' => 'İlk Döngü', 'description' => 'İlk teslimatını tamamladı.', 'icon' => '🌱', 'points_threshold' => 0, 'deliveries_threshold' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'cycle_friend', 'name' => 'Döngü Dostu', 'description' => '100 Döngü puanına ulaştı.', 'icon' => '♻️', 'points_threshold' => 100, 'deliveries_threshold' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'nature_ambassador', 'name' => 'Doğa Elçisi', 'description' => '500 Döngü puanına ulaştı.', 'icon' => '🌿', 'points_threshold' => 500, 'deliveries_threshold' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'cycle_leader', 'name' => 'Döngü Lideri', 'description' => '1.000 Döngü puanına ulaştı.', 'icon' => '🏆', 'points_threshold' => 1000, 'deliveries_threshold' => 0, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('pickup_requests')->where('status', 'completed')->orderBy('id')->chunkById(100, function ($requests) use ($now) {
            foreach ($requests as $request) {
                $quantity = (int) DB::table('listing_materials')->where('listing_id', $request->listing_id)->sum('quantity');
                $points = max(1, min($quantity, 500));
                $earnedAt = $request->completed_at ?: $request->updated_at ?: $now;
                foreach ([[$request->buyer_id, 'buyer'], [$request->seller_id, 'seller']] as [$userId, $role]) {
                    DB::table('cycle_point_entries')->insertOrIgnore([
                        'user_id' => $userId, 'pickup_request_id' => $request->id, 'role' => $role,
                        'reason' => 'delivery_completed', 'points' => $points, 'earned_at' => $earnedAt,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });

        $entries = DB::table('cycle_point_entries')->get();
        foreach ($entries->groupBy('user_id') as $userId => $items) {
            $this->insertSummary((int) $userId, 'all', $items->sum('points'), $items->count(), $now);
            foreach ($items->groupBy(fn ($item) => substr((string) $item->earned_at, 0, 7)) as $period => $periodItems) {
                $this->insertSummary((int) $userId, $period, $periodItems->sum('points'), $periodItems->count(), $now);
            }
        }

        $achievements = DB::table('achievements')->orderBy('sort_order')->get();
        foreach (DB::table('cycle_score_summaries')->where('period_key', 'all')->get() as $summary) {
            foreach ($achievements as $achievement) {
                if ($summary->points >= $achievement->points_threshold && $summary->deliveries >= $achievement->deliveries_threshold) {
                    DB::table('user_achievements')->insertOrIgnore([
                        'user_id' => $summary->user_id, 'achievement_id' => $achievement->id,
                        'awarded_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function insertSummary(int $userId, string $period, int $points, int $deliveries, $now): void
    {
        DB::table('cycle_score_summaries')->insert([
            'user_id' => $userId, 'period_key' => $period, 'points' => $points,
            'deliveries' => $deliveries, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('cycle_score_summaries');
        Schema::dropIfExists('cycle_point_entries');
        Schema::dropIfExists('achievements');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ranking_name_visible'));
    }
};
