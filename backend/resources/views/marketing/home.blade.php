@extends('layouts.marketing')
@section('title', 'Döngü')
@section('description', 'DOA işaretli depozitolu PET, cam ve alüminyum ambalajlar için yakındaki ilanları keşfet, güvenli teslimatla döngüye katıl.')
@section('content')
<section class="site-hero">
    <div class="site-hero-glow site-hero-glow-one"></div><div class="site-hero-glow site-hero-glow-two"></div>
    <div class="site-shell site-hero-grid">
        <div class="site-hero-copy">
            <span class="site-kicker"><i></i> Depozitolu ambalajlar için yerel pazar</span>
            <h1>Elindeki ambalaj,<br><em>bir başkasının fırsatı.</em></h1>
            <p>Makineye gidemeyen ilan versin, almak isteyen yakındaki ambalajları teslim alsın. Döngü, iki tarafı güvenli ve şeffaf bir işlem akışında buluşturur.</p>
            <div class="site-hero-actions"><a class="site-button site-button-lime" href="#nasil-calisir">Döngüyü keşfet <span>↗</span></a><a class="site-button site-button-ghost" href="{{ route('marketing.supporters') }}">İşletmeni görünür kıl</a></div>
            <div class="site-trust-row"><span><b>01</b> Komisyonsuz model</span><span><b>02</b> Yakınındaki ilanlar</span><span><b>03</b> Teslimat kodu</span></div>
        </div>
        <div class="site-phone-stage" aria-label="Döngü mobil uygulama ekranları">
            <div class="site-orbit site-orbit-one"></div><div class="site-orbit site-orbit-two"></div>
            <div class="site-floating-note site-floating-note-top"><span>♻</span><b>Döngü puanı</b><small>Katkın görünür olsun</small></div>
            <div class="site-phone site-phone-main"><img src="{{ asset('images/site/app-home.png') }}" alt="Döngü ana sayfa ve yakın ilanlar" width="1080" height="1920"></div>
            <div class="site-phone site-phone-side"><img src="{{ asset('images/site/app-ranking.png') }}" alt="Döngü doğaya katkı sıralaması" width="1080" height="1920"></div>
            <div class="site-floating-note site-floating-note-bottom"><b>50 km'ye kadar</b><small>Yakındaki ilanları keşfet</small></div>
        </div>
    </div>
    <div class="site-hero-ticker" aria-hidden="true"><div><span>PET</span><i>◆</i><span>CAM</span><i>◆</i><span>ALÜMİNYUM</span><i>◆</i><span>YEREL TESLİMAT</span><i>◆</i><span>PET</span><i>◆</i><span>CAM</span><i>◆</i><span>ALÜMİNYUM</span></div></div>
</section>

<section class="site-section site-intro" id="nasil-calisir"><div class="site-shell">
    <div class="site-section-heading site-heading-split"><div><span class="site-eyebrow">YENİ BİR ALIŞKANLIK</span><h2>Makineye uzak olmak,<br>döngünün dışında kalmak değil.</h2></div><p>Bir tarafta biriken ambalajlar, diğer tarafta onları teslim almak isteyen insanlar var. Döngü, aradaki mesafeyi basit bir ilanla kapatır.</p></div>
    <div class="site-process-grid">
        <article><span>01</span><div class="site-process-icon">＋</div><h3>İlanını oluştur</h3><p>PET, cam ve alüminyum adetlerini, fiyatını ve teslimat konumunu ekle.</p></article>
        <article><span>02</span><div class="site-process-icon">⌖</div><h3>Yakınındakini bul</h3><p>Konumuna göre çevrendeki güncel ilanları ve mesafelerini görüntüle.</p></article>
        <article><span>03</span><div class="site-process-icon">···</div><h3>Güvenle anlaş</h3><p>Talep gönder, mesajlaş ve teslimat ayrıntılarını yalnızca ilgili kişiyle paylaş.</p></article>
        <article class="is-highlight"><span>04</span><div class="site-process-icon">✓</div><h3>Teslimatı tamamla</h3><p>Tek kullanımlık teslimat koduyla işlemi doğrula, değerlendir ve katkını büyüt.</p></article>
    </div>
    <a class="site-text-link" href="{{ route('marketing.how-it-works') }}">Tüm süreci ayrıntılı incele <span>→</span></a>
</div></section>

<section class="site-section site-materials"><div class="site-shell">
    <div class="site-section-heading"><span class="site-eyebrow">KABUL EDİLEN AMBALAJLAR</span><h2>Üç malzeme.<br>Tek bir döngü.</h2><p>İlana eklenen ambalajların üzerinde okunabilir DOA işareti bulunmalı; ambalajlar boş, sağlam ve teslim edilebilir durumda olmalıdır.</p></div>
    <div class="site-material-grid">
        <article class="site-material-pet"><div class="site-material-label">01 / PET</div><div class="site-material-shape site-bottle">PET</div><h3>Plastik şişeler</h3><p>Hafif, biriktirmesi kolay ve döngüye yeniden kazandırılmaya hazır.</p></article>
        <article class="site-material-glass"><div class="site-material-label">02 / CAM</div><div class="site-material-shape site-bottle">CAM</div><h3>Cam şişeler</h3><p>Temiz, boş ve üzerindeki depozito işareti okunabilir olmalı.</p></article>
        <article class="site-material-alu"><div class="site-material-label">03 / ALÜMİNYUM</div><div class="site-material-shape site-can">ALÜ</div><h3>Alüminyum kutular</h3><p>Doğru adet ve güncel durum bilgisiyle ilanını dakikalar içinde yayınla.</p></article>
    </div>
</div></section>

<section class="site-section site-product-section"><div class="site-shell site-product-grid">
    <div class="site-product-visual"><div class="site-product-card site-product-card-back"><img src="{{ asset('images/site/app-supporters.png') }}" alt="Bölgedeki Döngü Destekçileri" loading="lazy" width="1080" height="1920"></div><div class="site-product-card site-product-card-front"><img src="{{ asset('images/site/app-listing.png') }}" alt="Döngü ilan detayı" loading="lazy" width="1080" height="1920"></div></div>
    <div class="site-product-copy"><span class="site-eyebrow site-eyebrow-light">GÜVENLİ İŞLEM AKIŞI</span><h2>Sadece ilan değil.<br><em>Baştan sona kontrollü teslimat.</em></h2><p>Kesin adres herkese açık değildir. Talep, rezervasyon ve teslimat kodu adımları sayesinde taraflar ne zaman hangi aşamada olduğunu bilir.</p><ul class="site-feature-list"><li><b>Yaklaşık konum gizliliği</b><span>Açık adres yalnızca yetkili işlem taraflarıyla paylaşılır.</span></li><li><b>Gerçek mesajlaşma</b><span>Engelleme, bildirme ve kullanım sınırlarıyla daha güvenli iletişim.</span></li><li><b>Doğrulanmış tamamlanma</b><span>Teslimat kodu, değerlendirme ve işlem geçmişi aynı akışta.</span></li></ul></div>
</div></section>

<section class="site-section site-ranking-section"><div class="site-shell site-ranking-grid">
    <div><span class="site-eyebrow">DOĞAYA KATKI</span><h2>Her teslimat,<br>görünür bir katkıya dönüşür.</h2><p>Satıcılar tamamlanan teslimatlarla Döngü puanı kazanır. Aylık ve tüm zamanlar sıralamasında katkılar görünür olur; başarılar rozetlerle büyür.</p><div class="site-mini-stats"><div><strong>50</strong><span>kişilik sıralama</span></div><div><strong>9</strong><span>başarı rozeti</span></div><div><strong>1</strong><span>ortak amaç</span></div></div></div>
    <div class="site-ranking-card"><div class="site-ranking-head"><span>Bu ayın katkı liderleri</span><b>İlk 3</b></div><div class="site-podium"><div class="site-podium-item second"><span>2</span><b>Doğa Dostu</b><small>2.850 puan</small></div><div class="site-podium-item first"><span>1</span><b>Döngü Lideri</b><small>4.120 puan</small></div><div class="site-podium-item third"><span>3</span><b>Yeşil Öncü</b><small>2.140 puan</small></div></div><p class="site-demo-note">Görsel temsilidir; gerçek sıralama uygulamada tamamlanan işlemlerle oluşur.</p></div>
</div></section>

<section class="site-section site-supporter-banner"><div class="site-shell"><div class="site-supporter-panel">
    <div class="site-supporter-copy"><span class="site-eyebrow site-eyebrow-light">DÖNGÜ DESTEKÇİLERİ</span><h2>Bölgenizde görünür,<br>döngünün içinde olun.</h2><p>Yerel işletmenizi yakınınızdaki kullanıcılara tanıtın. Görüntülenme ve yönlendirme istatistiklerinizi kendi panelinizden takip edin.</p><a class="site-button site-button-lime" href="{{ route('marketing.supporters') }}">Destekçi modelini keşfet <span>↗</span></a></div>
    <div class="site-supporter-mosaic"><div class="site-logo-tile">KAFE</div><div class="site-logo-tile">SERVİS</div><div class="site-logo-tile">MARKET</div><div class="site-logo-tile site-logo-tile-accent">SİZİN<br>İŞLETMENİZ</div></div>
</div></div></section>

<section class="site-section site-faq-preview"><div class="site-shell site-faq-grid">
    <div><span class="site-eyebrow">AKLINDA KALMASIN</span><h2>Sade, şeffaf<br>ve anlaşılır.</h2><p>Döngü'nün ne yaptığı kadar ne yapmadığını da açıkça anlatıyoruz.</p><a class="site-text-link" href="{{ route('marketing.faq') }}">Tüm soruları görüntüle <span>→</span></a></div>
    <div class="site-accordion"><details open><summary>Döngü ambalajları satın alıyor mu?<span>＋</span></summary><p>Hayır. Döngü, ilan verenlerle ambalajları teslim almak isteyen kullanıcıları buluşturan bağımsız bir platformdur.</p></details><details><summary>Hangi ambalajlar ilan edilebilir?<span>＋</span></summary><p>Üzerinde okunabilir DOA işareti bulunan, desteklenen PET, cam ve alüminyum ambalajlar ilan edilebilir.</p></details><details><summary>Kesin adresim herkese görünür mü?<span>＋</span></summary><p>Hayır. Herkese açık alanlarda yaklaşık bölge gösterilir; kesin teslimat bilgisi yalnızca işlem akışındaki yetkili taraflarla paylaşılır.</p></details></div>
</div></section>

<section class="site-final-cta" id="uygulama"><div class="site-shell"><span class="site-kicker"><i></i> DÖNGÜ BAŞLIYOR</span><h2>Bir şişeyle başlar.<br><em>Bir şehirle büyür.</em></h2><p>Döngü yakında Google Play'de. İlk kullanıcılar arasında yerini almak için bizi takip et.</p><div class="site-hero-actions"><span class="site-store-button"><b>▶</b><span><small>ÇOK YAKINDA</small>Google Play</span></span><a class="site-button site-button-ghost-light" href="{{ route('marketing.contact') }}">Bize ulaş</a></div></div></section>
@endsection
