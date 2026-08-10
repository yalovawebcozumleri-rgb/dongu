<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\ConversationMessage;
use App\Models\CycleScoreSummary;
use App\Models\Listing;
use App\Models\ListingFavorite;
use App\Models\ListingMaterial;
use App\Models\ListingPrivateLocation;
use App\Models\PickupRequest;
use App\Models\SupporterBusiness;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StoreShowcaseSeeder extends Seeder
{
    private const EMAIL_DOMAIN = '@store-showcase.dongu.local';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('StoreShowcaseSeeder yalnızca local ortamda çalıştırılabilir.');
        }

        DB::transaction(function (): void {
            $now = now();
            $period = $now->format('Y-m');

            $profiles = [
                ['yesil-dost', 'Yeşil Dost', 215000, 12400, 486, 28, 4.95, 38],
                ['dongu-komsusu', 'Döngü Komşusu', 142500, 10950, 351, 24, 4.90, 29],
                ['mavi-kiyi', 'Mavi Kıyı', 87500, 9200, 228, 19, 4.88, 25],
                ['temiz-yarinlar', 'Temiz Yarınlar', 52000, 7800, 146, 16, 4.83, 18],
                ['geri-kazanimci', 'Geri Kazanımcı', 28500, 6100, 92, 13, 4.80, 15],
                ['cevre-dostu', 'Çevre Dostu', 12500, 4300, 47, 9, 4.75, 12],
            ];

            $users = collect($profiles)->mapWithKeys(function (array $profile) use ($now, $period): array {
                [$key, $name, $allPoints, $monthPoints, $allDeliveries, $monthDeliveries, $rating, $ratingCount] = $profile;
                $user = User::updateOrCreate(
                    ['email' => $key.self::EMAIL_DOMAIN],
                    [
                        'name' => $name,
                        'password' => Hash::make(Str::random(40)),
                        'status' => 'active',
                        'role' => User::ROLE_USER,
                        'email_verified_at' => $now,
                        'profile_completed_at' => $now,
                        'terms_accepted_at' => $now,
                        'terms_version' => config('legal.documents.terms.version'),
                        'privacy_notice_acknowledged_at' => $now,
                        'privacy_notice_version' => config('legal.documents.privacy.version'),
                        'rating' => $rating,
                        'rating_count' => $ratingCount,
                        'completed_transactions' => $allDeliveries,
                        'ranking_name_visible' => true,
                    ]
                );

                CycleScoreSummary::updateOrCreate(
                    ['user_id' => $user->id, 'period_key' => 'all'],
                    ['points' => $allPoints, 'deliveries' => $allDeliveries]
                );
                CycleScoreSummary::updateOrCreate(
                    ['user_id' => $user->id, 'period_key' => $period],
                    ['points' => $monthPoints, 'deliveries' => $monthDeliveries]
                );

                DB::table('user_achievements')->where('user_id', $user->id)->delete();
                Achievement::query()
                    ->where('points_threshold', '<=', $allPoints)
                    ->where('deliveries_threshold', '<=', $allDeliveries)
                    ->pluck('id')
                    ->each(fn (int $achievementId) => DB::table('user_achievements')->insert([
                        'user_id' => $user->id,
                        'achievement_id' => $achievementId,
                        'awarded_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));

                return [$key => $user];
            });

            Listing::withTrashed()
                ->whereIn('user_id', $users->pluck('id'))
                ->get()
                ->each(fn (Listing $listing) => $listing->forceDelete());

            $listingSpecs = [
                ['yesil-dost', 'Karpuzdere, Çınarcık', 40.6420, 29.0940, 'Temiz ve sağlam ambalajlar teslim için hazır.', [['pet', 42, .55], ['glass', 18, .50], ['aluminum', 12, .70]], true],
                ['dongu-komsusu', 'Taşliman, Çınarcık', 40.6452, 29.1110, 'Etiketleri okunaklı, kapakları üzerinde ambalajlar.', [['pet', 65, .50]], false],
                ['mavi-kiyi', 'Harmanlar, Çınarcık', 40.6391, 29.1025, 'Tek seferde teslim alınabilir.', [['glass', 36, .60], ['aluminum', 24, .75]], false],
                ['temiz-yarinlar', 'Çamlık, Çınarcık', 40.6481, 29.0865, 'Ambalajlar ayrıştırılmış ve poşetlenmiştir.', [['pet', 80, .45], ['aluminum', 20, .70]], false],
                ['geri-kazanimci', 'Merkez, Yalova', 40.6550, 29.2780, 'Tamamı temiz durumda, toplu teslim önceliklidir.', [['pet', 54, .55], ['glass', 30, .55]], false],
                ['cevre-dostu', 'Karpuzdere, Çınarcık', 40.6438, 29.0975, 'DOA işaretleri kontrol edildi; teslimata hazır.', [['aluminum', 45, .80]], false],
                ['yesil-dost', 'Taşliman, Çınarcık', 40.6460, 29.1080, 'PET ve cam ambalajlar birlikte teslim edilecektir.', [['pet', 32, .50], ['glass', 22, .55]], false],
                ['dongu-komsusu', 'Karpuzdere, Çınarcık', 40.6414, 29.0918, 'Düzenli şekilde ayrılmış ambalaj paketi.', [['pet', 28, .60], ['glass', 16, .50], ['aluminum', 10, .75]], false],
            ];

            $listings = collect($listingSpecs)->map(function (array $spec, int $index) use ($users, $now): Listing {
                [$ownerKey, $area, $latitude, $longitude, $description, $materials, $boosted] = $spec;
                $listing = Listing::create([
                    'user_id' => $users[$ownerKey]->id,
                    'status' => Listing::STATUS_ACTIVE,
                    'public_area' => $area,
                    'approximate_latitude' => $latitude,
                    'approximate_longitude' => $longitude,
                    'description' => $description,
                    'packaging_condition_confirmed_at' => $now,
                    'packaging_condition_version' => Listing::PACKAGING_CONDITION_VERSION,
                    'published_at' => $now->copy()->subMinutes(($index + 1) * 7),
                    'expires_at' => $now->copy()->addDays(30),
                    'boosted_until' => $boosted ? $now->copy()->addDay() : null,
                ]);

                foreach ($materials as [$type, $quantity, $unitPrice]) {
                    ListingMaterial::create([
                        'listing_id' => $listing->id,
                        'type' => $type,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ]);
                }

                ListingPrivateLocation::create([
                    'listing_id' => $listing->id,
                    'latitude' => (string) $latitude,
                    'longitude' => (string) $longitude,
                    'address' => $area.' teslimat noktası',
                    'delivery_notes' => 'Kesin teslimat bilgisi rezervasyon sonrasında paylaşılır.',
                ]);

                return $listing;
            });

            $localViewers = User::query()
                ->whereIn('email', [
                    'rmustafapolat@gmail.com',
                    'ahmetyalova39@gmail.com',
                    'yalovawebcozumleri@gmail.com',
                ])
                ->get();

            foreach ($localViewers as $viewerIndex => $viewer) {
                ListingFavorite::updateOrCreate([
                    'user_id' => $viewer->id,
                    'listing_id' => $listings[$viewerIndex % $listings->count()]->id,
                ]);
                ListingFavorite::updateOrCreate([
                    'user_id' => $viewer->id,
                    'listing_id' => $listings[($viewerIndex + 2) % $listings->count()]->id,
                ]);

                $seller = $users->values()[$viewerIndex % $users->count()];
                $reserved = $this->createReservedListing($seller, $now, $viewerIndex);
                $request = PickupRequest::create([
                    'listing_id' => $reserved->id,
                    'buyer_id' => $viewer->id,
                    'seller_id' => $seller->id,
                    'status' => PickupRequest::ACCEPTED,
                    'delivery_code' => (string) (4821 + $viewerIndex),
                    'accepted_at' => $now->copy()->subMinutes(18),
                ]);
                ConversationMessage::create([
                    'pickup_request_id' => $request->id,
                    'sender_id' => $viewer->id,
                    'type' => 'user',
                    'body' => 'Merhaba, ambalajların tamamını almak istiyorum. Uygun mudur?',
                    'read_at' => $now->copy()->subMinutes(16),
                    'created_at' => $now->copy()->subMinutes(20),
                    'updated_at' => $now->copy()->subMinutes(20),
                ]);
                ConversationMessage::create([
                    'pickup_request_id' => $request->id,
                    'sender_id' => $seller->id,
                    'type' => 'user',
                    'body' => 'Merhaba, uygundur. Rezervasyonu onayladım; teslimat konumunda görüşebiliriz.',
                    'read_at' => null,
                    'created_at' => $now->copy()->subMinutes(15),
                    'updated_at' => $now->copy()->subMinutes(15),
                ]);
            }

            $this->seedSupporters($now);
        });

        $this->command?->info('Mağaza ekran görüntüleri için yerel tanıtım verileri hazırlandı.');
    }

    private function createReservedListing(User $seller, $now, int $index): Listing
    {
        $listing = Listing::create([
            'user_id' => $seller->id,
            'status' => Listing::STATUS_RESERVED,
            'public_area' => 'Karpuzdere, Çınarcık',
            'approximate_latitude' => 40.6420 + ($index * .0002),
            'approximate_longitude' => 29.0940 + ($index * .0002),
            'description' => 'Rezervasyonu oluşturulan temiz ambalaj paketi.',
            'packaging_condition_confirmed_at' => $now,
            'packaging_condition_version' => Listing::PACKAGING_CONDITION_VERSION,
            'published_at' => $now->copy()->subHours(2),
            'expires_at' => $now->copy()->addDays(30),
        ]);
        ListingMaterial::create([
            'listing_id' => $listing->id,
            'type' => ListingMaterial::PET,
            'quantity' => 48 + $index,
            'unit_price' => .50,
        ]);
        ListingPrivateLocation::create([
            'listing_id' => $listing->id,
            'latitude' => (string) (40.6420 + ($index * .0002)),
            'longitude' => (string) (29.0940 + ($index * .0002)),
            'address' => 'Karpuzdere Mahallesi, Çınarcık / Yalova',
            'delivery_notes' => 'Teslimattan önce sohbet üzerinden haberleşelim.',
        ]);

        return $listing;
    }

    private function seedSupporters($now): void
    {
        $adminId = User::query()->where('role', User::ROLE_ADMIN)->value('id');
        $supporters = [
            ['yesil-market', 'Yeşil Market', 'Günlük ihtiyaçlarınız için yerel ve çevre dostu seçenekler.', 'whatsapp', 'WhatsApp’tan ulaş', '905413342219', 30],
            ['kiyi-kafe', 'Kıyı Kafe', 'Çınarcık sahilinde sıcak içecekler ve keyifli molalar.', 'directions', 'Yol tarifi al', '40.6452,29.1110', 20],
            ['yalova-teknik', 'Yalova Teknik', 'Telefon ve bilgisayarlarınız için hızlı teknik destek.', 'phone', 'Hemen ara', '905413342219', 10],
        ];

        foreach ($supporters as [$slug, $name, $summary, $ctaType, $ctaLabel, $ctaValue, $priority]) {
            $owner = User::updateOrCreate(
                ['email' => 'supporter-'.$slug.self::EMAIL_DOMAIN],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(40)),
                    'status' => 'active',
                    'role' => User::ROLE_SUPPORTER,
                    'email_verified_at' => $now,
                    'profile_completed_at' => $now,
                    'terms_accepted_at' => $now,
                    'terms_version' => config('legal.documents.terms.version'),
                    'privacy_notice_acknowledged_at' => $now,
                    'privacy_notice_version' => config('legal.documents.privacy.version'),
                ]
            );

            SupporterBusiness::updateOrCreate(
                ['slug' => 'store-showcase-'.$slug],
                [
                    'owner_user_id' => $owner->id,
                    'created_by_admin_id' => $adminId,
                    'name' => $name,
                    'card_summary' => $summary,
                    'detail_title' => $name.' ile bölgenizde daha fazlasını keşfedin',
                    'detail_body' => $summary.' İşletmeye tek dokunuşla ulaşabilirsiniz.',
                    'target_scope' => 'district',
                    'province_code' => '77',
                    'province_name' => 'Yalova',
                    'district_code' => '773',
                    'district_name' => 'Çınarcık',
                    'cta_type' => $ctaType,
                    'cta_label' => $ctaLabel,
                    'cta_value' => $ctaValue,
                    'priority' => $priority,
                    'is_active' => true,
                    'starts_at' => $now->copy()->subDay(),
                    'ends_at' => $now->copy()->addMonths(3),
                ]
            );
        }
    }
}
