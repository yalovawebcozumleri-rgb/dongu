@extends('layouts.marketing')
@section('title', 'Döngü Mobil Uygulama | Google Play ve App Store')
@section('description', 'Döngü mobil uygulamasını Google Play veya App Store üzerinden indirin; yakındaki depozitolu ambalaj ilanlarını keşfedin, talep gönderin ve teslimatı yönetin.')
@section('content')
@php
    $appStoreAvailable = config('stores.app_store_available')
        && filter_var(config('stores.app_store_url'), FILTER_VALIDATE_URL);
@endphp
<section class="vision-subhero vision-subhero-mobile-app">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> Mobil uygulama</span>
            <h1>Döngü cebinde,<br><em>değer yanında.</em></h1>
            <p>Yakındaki depozitolu ambalaj ilanlarını keşfetmek, talep göndermek, mesajlaşmak ve teslimatı güvenli akışla tamamlamak için Döngü uygulamasını indir.</p>
            <x-store-badges class="store-badges-mobile-hero" />
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark vision-orbit-store-links">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">Döngü</b>
            @if ($appStoreAvailable)
                <a class="vision-orbit-chip chip-two" href="{{ config('stores.app_store_url') }}" target="_blank" rel="noopener" aria-label="Döngü uygulamasını App Store'da aç">App Store</a>
            @else
                <span class="vision-orbit-chip chip-two vision-orbit-chip-unavailable">App Store · Yakında</span>
            @endif
            <a class="vision-orbit-chip chip-three" href="{{ config('stores.google_play_url') }}" target="_blank" rel="noopener" aria-label="Döngü uygulamasını Google Play'de aç">Play Store</a>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-download-grid">
        @if (request('platform') === 'ios' && ! $appStoreAvailable)
            <div class="store-availability-notice" role="status">
                <strong>Döngü iOS sürümü çok yakında.</strong>
                <span>App Store incelemesi tamamlandığında indirme bağlantısı burada otomatik olarak açılacak.</span>
            </div>
        @endif
        <div>
            <span class="vision-section-kicker">Uygulamayı indir</span>
            <h2>Telefonuna uygun mağazadan Döngü’ye ulaş.</h2>
        </div>
        <div class="vision-store-download-list">
            <article>
                <div>
                    <small>iPhone kullanıcıları için</small>
                    <h3>{{ $appStoreAvailable ? 'App Store’dan indirin' : 'App Store’da çok yakında' }}</h3>
                    <p>{{ $appStoreAvailable ? 'iPhone’da Döngü ile yakındaki ilanları keşfedebilir, alım talebi gönderebilir, mesajlaşabilir ve teslimat sürecini güvenli akışla tamamlayabilirsin.' : 'Döngü’nün iPhone sürümü App Store incelemesinde. Yayınlandığında bu sayfadaki indirme bağlantısı kullanıma açılacak.' }}</p>
                </div>
                <x-store-badges class="store-badges-single" store="app-store" />
            </article>
            <article>
                <div>
                    <small>Android kullanıcıları için</small>
                    <h3>Google Play’den indir</h3>
                    <p>Android telefonunda Döngü’yü açarak yakındaki ilanları keşfedebilir, alım talebi gönderebilir, mesajlaşabilir ve teslimat sürecini uygulama içinden takip edebilirsin.</p>
                </div>
                <x-store-badges class="store-badges-single" store="google-play" />
            </article>
        </div>
    </div>
</section>
@endsection
