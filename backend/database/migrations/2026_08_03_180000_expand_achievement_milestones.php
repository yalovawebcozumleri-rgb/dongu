<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceMilestones([
            ['code' => 'first_cycle', 'name' => 'İlk Döngü', 'description' => 'İlk puanlanan teslimatını tamamladı.', 'icon' => '🌱', 'points_threshold' => 0, 'deliveries_threshold' => 1, 'sort_order' => 1],
            ['code' => 'cycle_friend', 'name' => 'Döngü Dostu', 'description' => '1.000 Döngü puanına ulaştı.', 'icon' => '♻️', 'points_threshold' => 1000, 'deliveries_threshold' => 0, 'sort_order' => 2],
            ['code' => 'nature_ambassador', 'name' => 'Doğa Elçisi', 'description' => '2.500 Döngü puanına ulaştı.', 'icon' => '🌿', 'points_threshold' => 2500, 'deliveries_threshold' => 0, 'sort_order' => 3],
            ['code' => 'cycle_leader', 'name' => 'Döngü Lideri', 'description' => '5.000 Döngü puanına ulaştı.', 'icon' => '🏆', 'points_threshold' => 5000, 'deliveries_threshold' => 0, 'sort_order' => 4],
            ['code' => 'green_pioneer', 'name' => 'Yeşil Öncü', 'description' => '10.000 Döngü puanına ulaştı.', 'icon' => '🌳', 'points_threshold' => 10000, 'deliveries_threshold' => 0, 'sort_order' => 5],
            ['code' => 'earth_friend', 'name' => 'Dünya Dostu', 'description' => '25.000 Döngü puanına ulaştı.', 'icon' => '🌍', 'points_threshold' => 25000, 'deliveries_threshold' => 0, 'sort_order' => 6],
            ['code' => 'cycle_master', 'name' => 'Döngü Ustası', 'description' => '50.000 Döngü puanına ulaştı.', 'icon' => '💚', 'points_threshold' => 50000, 'deliveries_threshold' => 0, 'sort_order' => 7],
            ['code' => 'nature_guardian', 'name' => 'Doğa Koruyucusu', 'description' => '100.000 Döngü puanına ulaştı.', 'icon' => '🛡️', 'points_threshold' => 100000, 'deliveries_threshold' => 0, 'sort_order' => 8],
            ['code' => 'cycle_legend', 'name' => 'Döngü Efsanesi', 'description' => '250.000 Döngü puanına ulaştı.', 'icon' => '👑', 'points_threshold' => 250000, 'deliveries_threshold' => 0, 'sort_order' => 9],
        ]);
    }

    public function down(): void
    {
        DB::table('user_achievements')->delete();
        DB::table('achievements')->whereIn('code', [
            'green_pioneer', 'earth_friend', 'cycle_master', 'nature_guardian', 'cycle_legend',
        ])->delete();
        $this->replaceMilestones([
            ['code' => 'first_cycle', 'name' => 'İlk Döngü', 'description' => 'İlk teslimatını tamamladı.', 'icon' => '🌱', 'points_threshold' => 0, 'deliveries_threshold' => 1, 'sort_order' => 1],
            ['code' => 'cycle_friend', 'name' => 'Döngü Dostu', 'description' => '100 Döngü puanına ulaştı.', 'icon' => '♻️', 'points_threshold' => 100, 'deliveries_threshold' => 0, 'sort_order' => 2],
            ['code' => 'nature_ambassador', 'name' => 'Doğa Elçisi', 'description' => '500 Döngü puanına ulaştı.', 'icon' => '🌿', 'points_threshold' => 500, 'deliveries_threshold' => 0, 'sort_order' => 3],
            ['code' => 'cycle_leader', 'name' => 'Döngü Lideri', 'description' => '1.000 Döngü puanına ulaştı.', 'icon' => '🏆', 'points_threshold' => 1000, 'deliveries_threshold' => 0, 'sort_order' => 4],
        ]);
    }

    private function replaceMilestones(array $milestones): void
    {
        $now = now();
        DB::table('achievements')->upsert(
            array_map(fn (array $milestone) => [...$milestone, 'created_at' => $now, 'updated_at' => $now], $milestones),
            ['code'],
            ['name', 'description', 'icon', 'points_threshold', 'deliveries_threshold', 'sort_order', 'updated_at']
        );

        DB::table('user_achievements')->delete();
        $achievements = DB::table('achievements')->orderBy('sort_order')->get();
        foreach (DB::table('cycle_score_summaries')->where('period_key', 'all')->get() as $summary) {
            foreach ($achievements as $achievement) {
                if ($summary->points < $achievement->points_threshold || $summary->deliveries < $achievement->deliveries_threshold) {
                    continue;
                }
                DB::table('user_achievements')->insert([
                    'user_id' => $summary->user_id,
                    'achievement_id' => $achievement->id,
                    'awarded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
