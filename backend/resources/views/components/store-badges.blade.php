@props(['class' => '', 'store' => 'both'])

@php
    $appStoreAvailable = config('stores.app_store_available')
        && filter_var(config('stores.app_store_url'), FILTER_VALIDATE_URL);
    $googlePlayAvailable = config('stores.google_play_available')
        && filter_var(config('stores.google_play_url'), FILTER_VALIDATE_URL);
@endphp

<div {{ $attributes->class(['store-badges', $class]) }}>
    @if ($store === 'both' || $store === 'app-store')
    @if ($appStoreAvailable)
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
    @else
    <span class="store-badge-wrap">
        <span class="store-badge store-badge-app-store store-badge-unavailable" aria-disabled="true">
            <img
                src="{{ asset('site/app-store-badge-tr.svg') }}"
                alt="App Store'da yakında"
                width="151"
                height="40"
            >
        </span>
        <small class="store-badge-note">Yakında</small>
    </span>
    @endif
    @endif
    @if ($store === 'both' || $store === 'google-play')
    @if ($googlePlayAvailable)
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
    @else
    <span class="store-badge-wrap">
        <span class="store-badge store-badge-google-play store-badge-unavailable" aria-disabled="true">
            <img
                src="{{ asset('site/google-play-badge-tr.png') }}"
                alt="Google Play'de yakında"
                width="646"
                height="250"
            >
        </span>
        <small class="store-badge-note">Yakında</small>
    </span>
    @endif
    @endif
</div>
