@extends('layouts.marketing')
@section('title', 'Döngü')
@section('description', 'DOA işaretli depozitolu PET, cam ve alüminyum ambalajlar için yakındaki ilanları keşfet; güvenli teslimatla döngüye katıl.')
@section('content')
<section class="site-hero">
    <div class="site-hero-orb site-hero-orb-one"></div><div class="site-hero-orb site-hero-orb-two"></div>
    <div class="site-shell site-hero-grid">
        <div class="site-hero-copy">
            <span class="site-kicker"><i></i> Depozitolu ambalajlar için yerel buluşma noktası</span>
            <h1>Elindeki ambalajı<br><em>değere dönüştür.</em></h1>
            <p>Yakınındaki ilanları keşfet, talep gönder ve teslimatı güvenli işlem akışıyla tamamla.</p>
            <div class="site-hero-actions"><a class="site-button site-button-lime" href="#nasil-calisir">Döngü’yü keşfet <span>↗</span></a><a class="site-button site-button-ghost" href="{{ route('marketing.how-it-works') }}">Nasıl çalışır?</a></div>
            <div class="site-trust-row" aria-label="Döngü özellikleri"><span><b>✓</b> Komisyonsuz</span><span><b>✓</b> Konuma göre ilanlar</span><span><b>✓</b> Teslimat kodu</span></div>
        </div>
        <div class="site-app-showcase" aria-label="Döngü mobil uygulaması">
            <div class="site-app-halo"></div>
            <div class="site-phone-frame"><img src="{{ asset('images/site/app-home.png') }}" alt="Döngü uygulaması ana sayfası" width="1080" height="1920"></div>
            <div class="site-app-chip site-app-chip-top"><span>72</span><small>ambalajlık ilan</small></div>
            <div class="site-app-chip site-app-chip-bottom"><span>100 m</span><small>yakınında</small></div>
        </div>
    </div>
</section>

<section class="site-material-strip" aria-label="İlan verilebilen ambalaj türleri">
    <div class="site-shell site-material-strip-inner">
        <div class="site-material-intro"><span>Tek ilanda</span><strong>Üç ambalaj türü</strong></div>
        <div class="site-material-mini"><div class="site-material-icon is-pet" aria-hidden="true"><svg viewBox="0 0 24 32"><path d="M9 2h6v4.5l2.1 3.1c.6.9.9 1.9.9 3V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V12.6c0-1.1.3-2.1.9-3L9 6.5V2Z"/><path d="M9 5h6M8 15h8v7H8z"/></svg></div><div><strong>PET</strong><span>Plastik şişeler</span></div></div>
        <div class="site-material-mini"><div class="site-material-icon is-glass" aria-hidden="true"><svg viewBox="0 0 24 32"><path d="M9 2h6v8.1c0 .9.3 1.7 1 2.3l1 1c.7.7 1 1.5 1 2.5V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V15.9c0-1 .3-1.8 1-2.5l1-1c.7-.6 1-1.4 1-2.3V2Z"/><path d="M9 6h6M9 16v9"/></svg></div><div><strong>Cam</strong><span>Cam şişeler</span></div></div>
        <div class="site-material-mini"><div class="site-material-icon is-alu" aria-hidden="true"><svg viewBox="0 0 24 32"><path d="M7 5.5v21c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-21"/><ellipse cx="12" cy="5.5" rx="5" ry="2.5"/><path d="M10.1 5.3c.8-.7 2.7-.7 3.8 0l-1.3 1.2h-2.5V5.3ZM7 25.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5"/></svg></div><div><strong>Alüminyum</strong><span>İçecek kutuları</span></div></div>
    </div>
    <p class="site-material-note">Ambalajların boş, sağlam ve üzerindeki DOA işaretinin okunabilir olması gerekir.</p>
</section>

<section class="site-section site-process" id="nasil-calisir"><div class="site-shell">
    <div class="site-section-heading site-heading-split"><div><span class="site-eyebrow">NASIL ÇALIŞIR?</span><h2>İlandan teslimata,<br>üç net adım.</h2></div><p>Döngü; ilan, iletişim ve teslimat sürecini tek yerde toplar.</p></div>
    <div class="site-process-grid site-process-grid-three"><article><span>01</span><div class="site-process-icon">＋</div><h3>İlanını oluştur</h3><p>Ambalaj türünü, adedini, fiyatını ve teslimat konumunu ekle.</p></article><article><span>02</span><div class="site-process-icon">···</div><h3>Talebi değerlendir</h3><p>Talep gönder, mesajlaş ve uygun kullanıcıyla rezervasyon oluştur.</p></article><article class="is-highlight"><span>03</span><div class="site-process-icon">✓</div><h3>Teslimatı doğrula</h3><p>Tek kullanımlık kodla işlemi tamamla; değerlendirme ve puan süreci başlasın.</p></article></div>
</div></section>

<section class="site-showcase-section"><div class="site-shell site-showcase-grid">
    <div class="site-showcase-copy"><span class="site-eyebrow site-eyebrow-light">GÜVENLİ İŞLEM AKIŞI</span><h2>İlanı gör.<br>Talep gönder.<br><em>Güvenle tamamla.</em></h2><p>Kesin adres herkese açık değildir. Rezervasyon, mesajlaşma ve teslimat kodu yalnızca ilgili taraflar arasında ilerler.</p><div class="site-feature-pills"><span>Yaklaşık konum</span><span>Güvenli mesajlaşma</span><span>Teslimat kodu</span></div></div>
    <div class="site-showcase-phones"><div class="site-screen-card is-back"><img src="{{ asset('images/site/app-ranking.png') }}" alt="Döngü sıralaması" loading="lazy" width="1080" height="1920"></div><div class="site-screen-card is-front"><img src="{{ asset('images/site/app-listing.png') }}" alt="Döngü ilan detayı" loading="lazy" width="1080" height="1920"></div></div>
</div></section>

<section class="site-section site-two-worlds"><div class="site-shell site-two-worlds-grid">
    <article class="site-world-card site-world-ranking"><span class="site-eyebrow">DOĞAYA KATKI</span><h2>Katkın görünür olsun.</h2><p>Tamamlanan teslimatlarla puan kazan, sıralamadaki yerini ve rozetlerini gör.</p><a class="site-text-link" href="{{ route('marketing.how-it-works') }}">Döngü’yü tanı <span>→</span></a><div class="site-rank-preview"><b>01</b><div><strong>Döngü Lideri</strong><small>Bu ayın katkısı</small></div><span>12.500</span></div></article>
    <article class="site-world-card site-world-supporters"><span class="site-eyebrow site-eyebrow-light">DÖNGÜ DESTEKÇİLERİ</span><h2>Bölgenizdeki işletmeleri keşfedin.</h2><p>Döngü’yü destekleyen yerel işletmeler, kullanıcılarla kendi bölgelerinde buluşur.</p><a class="site-button site-button-lime" href="{{ route('marketing.supporters') }}">Destekçileri keşfet <span>↗</span></a></article>
</div></section>

<section class="site-final-cta" id="uygulama"><div class="site-shell"><img class="site-final-logo" src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="1024" height="1024"><span class="site-kicker"><i></i> DÖNGÜ BAŞLIYOR</span><h2>Ambalajlar el değiştirir.<br><em>Katkı büyür.</em></h2><p>Döngü çok yakında Google Play’de.</p><div class="site-hero-actions"><span class="site-store-button"><b>▶</b><span><small>ÇOK YAKINDA</small>Google Play</span></span><a class="site-button site-button-ghost-light" href="{{ route('marketing.contact') }}">Bize ulaş</a></div></div></section>
@endsection
