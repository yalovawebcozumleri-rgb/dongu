<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_use_full_brand_titles_and_replaceable_social_images(): void
    {
        $pages = [
            '/' => ['Depozitolu Ambalaj Paylaşım Platformu', 'ana-sayfa.png'],
            '/nasil-calisir' => ['Nasıl Çalışır?', 'nasil-calisir.png'],
            '/hakkimizda' => ['Hakkımızda', 'hakkimizda.png'],
            '/sss' => ['Sık Sorulan Sorular', 'sik-sorulan-sorular.png'],
            '/iletisim' => ['İletişim', 'iletisim.png'],
            '/mobil-uygulama' => ['Google Play ve App Store', 'mobil-uygulama.png'],
            '/reklam-ve-isbirligi' => ['Reklam ve Kurumsal İş Birliği', 'reklam-ve-isbirligi.png'],
            '/ilanlar' => ['PET, Cam ve Alüminyum İlanları', 'ilanlar.png'],
            '/hesap-silme' => ['Hesap Silme', 'hesap-silme.png'],
            '/kullanim-sartlari' => ['Kullanıcı Şartları', 'kullanim-sartlari.png'],
            '/gizlilik-politikasi' => ['KVKK Aydınlatma Metni ve Gizlilik Politikası', 'gizlilik-politikasi.png'],
        ];

        foreach ($pages as $path => [$pageTitle, $imageName]) {
            $response = $this->get($path)->assertOk()
                ->assertSee('<title>'.$pageTitle.' | Döngü - Geri Dönüşüm Uygulaması</title>', false)
                ->assertSee('/images/site/social/'.$imageName, false);

            $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), '/images/site/social/'.$imageName));
        }
    }
}
