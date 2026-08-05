<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\CycleScoreSummary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoLeaderboardSeeder extends Seeder
{
    private const EMAIL_PREFIX = 'demo.rank.';
    private const EMAIL_DOMAIN = '@doa.local';

    public function run(): void
    {
        $now = now();
        $period = $now->format('Y-m');
        $profiles = [
            ['Zeynep Kaya', 260000, 12500, 620, 31, true],
            ['Emre Demir', 125000, 11800, 330, 29, true],
            ['Elif Yılmaz', 75000, 9900, 205, 25, true],
            ['Can Aydın', 50000, 8600, 142, 22, true],
            ['Deniz Şahin', 32000, 7400, 96, 19, true],
            ['Selin Arslan', 25000, 6300, 78, 17, false],
            ['Mert Koç', 18000, 5200, 61, 15, true],
            ['Ece Çelik', 12500, 5000, 48, 14, true],
            ['Burak Aksoy', 10000, 4600, 41, 13, true],
            ['Derya Yıldız', 9800, 4200, 39, 12, true],
            ['Kerem Öz', 9800, 4200, 36, 10, true],
            ['Sude Kurt', 7200, 3500, 29, 9, true],
            ['Arda Güneş', 5000, 3000, 23, 8, true],
            ['İrem Polat', 4100, 2500, 19, 7, false],
            ['Ozan Kılıç', 2500, 1900, 14, 6, true],
            ['Melis Eren', 1900, 1500, 11, 5, true],
            ['Bora Tunç', 1000, 1000, 7, 4, true],
            ['Nehir Acar', 850, 700, 5, 3, true],
            ['Umut Tekin', 420, 300, 3, 2, true],
            ['Ada Yalçın', 90, 90, 1, 1, true],
        ];

        DB::transaction(function () use ($now, $period, $profiles) {
            foreach ($profiles as $index => [$name, $allPoints, $monthlyPoints, $allDeliveries, $monthlyDeliveries, $nameVisible]) {
                $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $user = User::updateOrCreate(
                    ['email' => self::EMAIL_PREFIX.$number.self::EMAIL_DOMAIN],
                    [
                        'name' => $name,
                        'password' => null,
                        'status' => 'active',
                        'role' => User::ROLE_USER,
                        'email_verified_at' => $now,
                        'profile_completed_at' => $now,
                        'terms_accepted_at' => $now,
                        'terms_version' => config('legal.documents.terms.version'),
                        'privacy_notice_acknowledged_at' => $now,
                        'privacy_notice_version' => config('legal.documents.privacy.version'),
                        'completed_transactions' => $allDeliveries,
                        'ranking_name_visible' => $nameVisible,
                    ]
                );

                CycleScoreSummary::updateOrCreate(
                    ['user_id' => $user->id, 'period_key' => 'all'],
                    ['points' => $allPoints, 'deliveries' => $allDeliveries]
                );
                CycleScoreSummary::updateOrCreate(
                    ['user_id' => $user->id, 'period_key' => $period],
                    ['points' => $monthlyPoints, 'deliveries' => $monthlyDeliveries]
                );

                DB::table('user_achievements')->where('user_id', $user->id)->delete();
                $achievementIds = Achievement::query()
                    ->where('points_threshold', '<=', $allPoints)
                    ->where('deliveries_threshold', '<=', $allDeliveries)
                    ->pluck('id');
                foreach ($achievementIds as $achievementId) {
                    DB::table('user_achievements')->insert([
                        'user_id' => $user->id,
                        'achievement_id' => $achievementId,
                        'awarded_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        $this->command?->info('20 demo sıralama hesabı aylık ve tüm zamanlar puanlarıyla hazırlandı.');
    }
}
