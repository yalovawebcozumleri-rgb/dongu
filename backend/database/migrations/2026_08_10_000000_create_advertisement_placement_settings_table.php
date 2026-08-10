<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_placement_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 100);
            $table->string('kind', 30)->default('native');
            $table->string('location_label', 160)->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('locked')->default(false);
            $table->json('source_order')->nullable();
            $table->unsignedSmallInteger('first_after')->default(0);
            $table->unsignedSmallInteger('repeat_every')->default(0);
            $table->unsignedSmallInteger('max_per_session')->default(1);
            $table->unsignedSmallInteger('min_items')->default(0);
            $table->string('admob_android_unit_id', 80)->nullable();
            $table->string('admob_ios_unit_id', 80)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $now = now();
        $nativeSources = json_encode(['direct', 'admob']);
        $rows = [
            ['key' => 'home_feed', 'label' => 'Ana sayfa ilan akışı', 'location_label' => '3. ilandan sonra, devamında her 8 ilanda', 'enabled' => true, 'first_after' => 3, 'repeat_every' => 8, 'max_per_session' => 1000, 'min_items' => 3, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/4910102351'],
            ['key' => 'listing_detail', 'label' => 'İlan detay sayfası', 'location_label' => 'Sayfanın altında tek reklam', 'enabled' => true, 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/1784586424'],
            ['key' => 'leaderboard', 'label' => 'Döngü sıralaması', 'location_label' => 'İlk 10 kullanıcıdan sonra', 'enabled' => true, 'first_after' => 10, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 10, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/3277897425'],
            ['key' => 'favorites', 'label' => 'Favoriler', 'location_label' => 'Listenin içinde', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/8338652411'],
            ['key' => 'public_profile', 'label' => 'Kullanıcı profil detayı', 'location_label' => 'İlanların altında', 'enabled' => false, 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/9279933062'],
            ['key' => 'my_listings', 'label' => 'İlanlarım', 'location_label' => 'İlan listesinin içinde', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/2961936720'],
            ['key' => 'purchase_requests', 'label' => 'Alım taleplerim', 'location_label' => 'Talep listesinin içinde', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/4027606383'],
            ['key' => 'messages_list', 'label' => 'Mesajlar listesi', 'location_label' => '5. görüşmeden sonra', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/1401443045'],
            ['key' => 'transaction_history', 'label' => 'İşlem geçmişi', 'location_label' => 'İşlem listesinin içinde', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/1097476700'],
            ['key' => 'transaction_detail', 'label' => 'İşlem detay sayfası', 'location_label' => 'Sayfanın altında', 'enabled' => false, 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/8784395033'],
            ['key' => 'notifications', 'label' => 'Bildirimler', 'location_label' => 'Bildirim listesinin içinde', 'enabled' => false, 'first_after' => 5, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 5, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/9387030266'],
            ['key' => 'profile_home', 'label' => 'Profil ana sayfası', 'location_label' => 'Menü öğelerinin arasında', 'enabled' => false, 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/2741730864'],
            ['key' => 'usage_limits', 'label' => 'Limitlerim', 'location_label' => 'Sayfanın altında', 'enabled' => false, 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/2218986689'],
        ];

        foreach ($rows as $row) {
            DB::table('advertisement_placement_settings')->insert(array_merge([
                'kind' => 'native', 'locked' => false, 'source_order' => $nativeSources,
                'admob_ios_unit_id' => null, 'settings' => null, 'created_at' => $now, 'updated_at' => $now,
            ], $row));
        }

        DB::table('advertisement_placement_settings')->insert([
            ['key' => 'pickup_interstitial', 'label' => 'Alım talebi geçiş reklamı', 'kind' => 'interstitial', 'location_label' => '2. ve 4. alım talebinden sonra', 'enabled' => true, 'locked' => false, 'source_order' => json_encode(['admob']), 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 2, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/6820135730', 'admob_ios_unit_id' => null, 'settings' => json_encode(['ordinals' => [2, 4]]), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'listing_rewarded_boost', 'label' => 'Video izle, ilanı öne çıkar', 'kind' => 'rewarded', 'location_label' => '24 saat öne çıkarma', 'enabled' => true, 'locked' => false, 'source_order' => json_encode(['admob']), 'first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 3, 'min_items' => 0, 'admob_android_unit_id' => 'ca-app-pub-6681150378641816/1142247732', 'admob_ios_unit_id' => null, 'settings' => json_encode(['boost_hours' => 24, 'daily_limit' => 3]), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_placement_settings');
    }
};
