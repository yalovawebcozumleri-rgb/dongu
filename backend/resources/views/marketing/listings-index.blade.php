@extends('layouts.marketing')
@section('title', 'PET, Cam ve Alüminyum İlanları | Döngü - Geri Dönüşüm Uygulaması')
@section('description', 'Yakınındaki güncel PET, cam ve alüminyum geri dönüşüm ilanlarını Döngü ile keşfet.')
@push('head')
    <link rel="stylesheet" href="{{ asset('site/listing-preview.css') }}?v=20260910-mobile-related-cards">
    <link rel="stylesheet" href="{{ asset('site/listings-index.css') }}?v=20260910-shared-subhero">
@endpush
@section('content')
    <div class="listing-preview-page listings-index-page">
        <section class="vision-subhero vision-subhero-listings" aria-labelledby="listings-title">
            <div class="vision-noise"></div>
            <div class="site-shell vision-subhero-grid">
                <div>
                    <span class="vision-kicker"><i></i> İlanlar</span>
                    <h1 id="listings-title">Yakınındaki ilanları<br><em>tek yerde keşfet.</em></h1>
                    <p>PET, cam ve alüminyum ambalaj ilanlarını güvenli özet bilgileriyle incele; iletişim ve işlem adımlarına Döngü - Geri Dönüşüm Uygulamasında devam et.</p>
                </div>
                <div class="vision-question-orbit vision-orbit-brandmark" aria-hidden="true">
                    <div class="vision-orbit-ring ring-one"></div>
                    <div class="vision-orbit-ring ring-two"></div>
                    <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
                    <b class="vision-orbit-chip chip-one">PET</b>
                    <b class="vision-orbit-chip chip-two">Cam</b>
                    <b class="vision-orbit-chip chip-three">Alüminyum</b>
                </div>
            </div>
        </section>

        <section class="vision-page-section listings-index-content" aria-labelledby="latest-listings-title">
            <div class="site-shell">
                <div class="listings-index-heading">
                    <div>
                        <span>KEŞFET</span>
                        <h2 id="latest-listings-title">Son yayınlanan ilanlar</h2>
                    </div>
                    @if (count($listings))
                        <strong>{{ count($listings) }} güncel ilan</strong>
                    @endif
                </div>

                @if (count($listings))
                    <div class="related-grid listings-index-grid">
                        @foreach ($listings as $listing)
                            <a class="related-listing-card" href="{{ route('listing.preview', ['id' => $listing['id']]) }}" aria-label="{{ number_format($listing['quantity'], 0, ',', '.') }} adet {{ $listing['materials'] }}, {{ $listing['area'] }}, toplam {{ $listing['total'] }} TL">
                                <div class="related-card-head">
                                    <div class="related-material-stack" style="width: {{ 42 + min(max(count($listing['items']) - 1, 0), 2) * 16 }}px" aria-hidden="true">
                                        @foreach (array_slice($listing['items'], 0, 3) as $item)
                                            <span class="related-material-icon material-{{ $item['type'] }}" style="left: {{ $loop->index * 16 }}px; z-index: {{ 3 - $loop->index }}">
                                                @include('marketing.partials.material-icon', ['type' => $item['type']])
                                            </span>
                                        @endforeach
                                    </div>
                                    <div class="related-card-title">
                                        <div><strong>{{ number_format($listing['quantity'], 0, ',', '.') }} adet</strong><i></i><span>Aktif</span></div>
                                        <p>{{ $listing['materials'] }}</p>
                                    </div>
                                    <span class="related-card-arrow" aria-hidden="true">→</span>
                                </div>
                                <div class="related-breakdown">
                                    @foreach ($listing['items'] as $item)
                                        <span><b>{{ $item['label'] }}</b><small>{{ number_format($item['quantity'], 0, ',', '.') }} × {{ $item['price'] }} TL</small></span>
                                    @endforeach
                                </div>
                                <div class="related-location">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                                    <span>{{ $listing['area'] }}</span>
                                </div>
                                <div class="related-money">
                                    <div><small>Toplam satış fiyatı</small><strong>{{ $listing['total'] }} TL</strong></div>
                                    <span>İlanı gör <b aria-hidden="true">→</b></span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="listings-index-empty">
                        <span>YENİ İLANLAR YOLDA</span>
                        <h2>Şu anda görüntülenebilecek aktif ilan yok.</h2>
                        <p>Yeni ilanları ve konumuna özel sonuçları görmek için Döngü - Geri Dönüşüm Uygulamasını kullanabilirsin.</p>
                        <a href="{{ route('marketing.mobile-app') }}">Mobil uygulamayı görüntüle <b aria-hidden="true">→</b></a>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
