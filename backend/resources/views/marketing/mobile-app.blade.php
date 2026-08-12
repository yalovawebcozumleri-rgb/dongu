@extends('layouts.marketing')
@section('title', 'Döngü Mobil Uygulama | Google Play ve App Store')
@section('description', 'Döngü mobil uygulamasını Google Play veya App Store üzerinden indirin; yakındaki depozitolu ambalaj ilanlarını keşfedin, talep gönderin ve teslimatı yönetin.')
@section('content')
<section class="vision-subhero vision-subhero-mobile-app">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> Mobil uygulama</span>
            <h1>Döngü cebinde,<br><em>değer yanında.</em></h1>
            <p>Yakındaki depozitolu ambalaj ilanlarını keşfetmek, talep göndermek, mesajlaşmak ve teslimatı güvenli akışla tamamlamak için Döngü uygulamasını indir.</p>
            <div class="vision-mobile-app-actions">
                <a href="https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu" target="_blank" rel="noopener" aria-label="Döngü uygulamasını Google Play'de aç"><img src="{{ asset('site/download-google-play.webp') }}" alt="Google Play'den indir"></a>
                <a href="https://apps.apple.com/tr/search?term=D%C3%B6ng%C3%BC" target="_blank" rel="noopener" aria-label="Döngü uygulamasını App Store'da ara"><img src="{{ asset('site/download-app-store.png') }}" alt="App Store'dan indirin"></a>
            </div>
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark vision-orbit-store-links">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">Döngü</b>
            <a class="vision-orbit-chip chip-two" href="https://apps.apple.com/tr/search?term=D%C3%B6ng%C3%BC" target="_blank" rel="noopener" aria-label="Döngü uygulamasını App Store'da aç">App Store</a>
            <a class="vision-orbit-chip chip-three" href="https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu" target="_blank" rel="noopener" aria-label="Döngü uygulamasını Google Play'de aç">Play Store</a>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-download-grid">
        <div>
            <span class="vision-section-kicker">Uygulamayı indir</span>
            <h2>Telefonuna uygun mağazadan Döngü’ye ulaş.</h2>
        </div>
        <div class="vision-store-download-list">
            <article>
                <div>
                    <small>Android kullanıcıları için</small>
                    <h3>Google Play’den indir</h3>
                    <p>Android telefonunda Döngü’yü açarak yakındaki ilanları keşfedebilir, alım talebi gönderebilir, mesajlaşabilir ve teslimat sürecini uygulama içinden takip edebilirsin.</p>
                </div>
                <a href="https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu" target="_blank" rel="noopener" aria-label="Döngü uygulamasını Google Play'de aç"><img src="{{ asset('site/download-google-play.webp') }}" alt="Google Play'den indir"></a>
            </article>
            <article>
                <div>
                    <small>iPhone kullanıcıları için</small>
                    <h3>App Store’dan indirin</h3>
                    <p>iPhone’da Döngü ile yakındaki ilanları keşfedebilir, alım talebi gönderebilir, mesajlaşabilir ve teslimat sürecini güvenli akışla tamamlayabilirsin.</p>
                </div>
                <a href="https://apps.apple.com/tr/search?term=D%C3%B6ng%C3%BC" target="_blank" rel="noopener" aria-label="Döngü uygulamasını App Store'da ara"><img src="{{ asset('site/download-app-store.png') }}" alt="App Store'dan indirin"></a>
            </article>
        </div>
    </div>
</section>
@endsection
