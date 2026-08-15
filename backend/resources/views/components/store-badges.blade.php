@props(['class' => '', 'store' => 'both'])

<div {{ $attributes->class(['store-badges', $class]) }}>
    @if ($store === 'both' || $store === 'app-store')
    <a
        class="store-badge store-badge-app-store"
        href="{{ config('stores.app_store_url') }}"
        target="_blank"
        rel="noopener"
        aria-label="Döngü uygulamasını App Store'da aç"
    >
        <img
            src="{{ asset('site/app-store-badge-tr.svg') }}"
            alt="App Store'dan indirin"
            width="151"
            height="40"
        >
    </a>
    @endif
    @if ($store === 'both' || $store === 'google-play')
    <a
        class="store-badge store-badge-google-play"
        href="{{ config('stores.google_play_url') }}"
        target="_blank"
        rel="noopener"
        aria-label="Döngü uygulamasını Google Play'de aç"
    >
        <img
            src="{{ asset('site/google-play-badge-tr.png') }}"
            alt="Google Play'den alın"
            width="646"
            height="250"
        >
    </a>
    @endif
</div>
