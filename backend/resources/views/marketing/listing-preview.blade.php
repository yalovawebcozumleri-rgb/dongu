@extends('layouts.marketing')
@section('title', $preview ? $preview['quantity'].' Adet '.$preview['title_materials'].' İlanı | Döngü - Geri Dönüşüm Uygulaması' : 'İlan artık yayında değil | Döngü - Geri Dönüşüm Uygulaması')
@section('description', $preview ? $preview['area'].' · '.$preview['total'].' TL. Döngü ile yakınındaki ambalaj ilanlarını keşfet.' : 'Bu ilan artık görüntülenemiyor. Yakınındaki ilanları Döngü uygulamasında keşfet.')
@section('robots', 'noindex, noarchive')
@push('head')
    <link rel="stylesheet" href="{{ asset('site/listing-preview.css') }}?v=20260910-mobile-related-cards">
    <script src="{{ asset('site/listing-preview-page.js') }}?v=20260909-scroll-top" defer></script>
@endpush
@section('content')
    <div class="listing-preview-page" style="padding-top: 120px;">
    <div class="preview-shell">
        <div class="preview-layout">
            <div class="preview-main">
                @if ($preview)
                    <section class="preview-hero" aria-labelledby="listing-title">
                        <div class="preview-hero-top">
                            <span class="status">AKTİF İLAN</span>
                            <span class="listing-number">İlan #{{ $preview['id'] }}</span>
                        </div>
                        <p class="preview-materials">{{ $preview['materials'] }}</p>
                        <h1 id="listing-title"><strong>{{ number_format($preview['quantity'], 0, ',', '.') }}</strong> adet ambalaj</h1>
                        <div class="preview-hero-meta">
                            <div class="region">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                                {{ $preview['area'] }}
                            </div>
                            <div class="hero-total"><small>Toplam ilan değeri</small><strong>{{ $preview['total'] }} TL</strong></div>
                        </div>
                    </section>

                    <section class="preview-card material-card" aria-labelledby="materials-title">
                        <div class="section-heading">
                            <div><span>İLAN DETAYI</span><h2 id="materials-title">Ambalajlar ve fiyatlar</h2></div>
                            <strong>{{ number_format($preview['quantity'], 0, ',', '.') }} adet</strong>
                        </div>
                        <div class="material-list">
                            @foreach ($preview['items'] as $item)
                                <div class="material-row">
                                    <div class="material-icon material-{{ $item['type'] }}" aria-hidden="true">
                                        @include('marketing.partials.material-icon', ['type' => $item['type']])
                                    </div>
                                    <div class="material-copy">
                                        <strong>{{ number_format($item['quantity'], 0, ',', '.') }} adet {{ $item['label'] }}</strong>
                                        <small>{{ $item['price'] }} TL / adet</small>
                                    </div>
                                    <strong class="material-total">{{ $item['total'] }} TL</strong>
                                </div>
                            @endforeach
                        </div>
                        <div class="total-row"><span>Toplam satış bedeli</span><strong>{{ $preview['total'] }} TL</strong></div>
                        <p class="privacy-note">Gizliliği korumak için yalnızca il ve ilçe gösterilir. Satıcı bilgileri, tam adres ve iletişim bilgileri web sayfasında paylaşılmaz.</p>
                    </section>
                @else
                    <section class="preview-hero unavailable"><span class="status">İLAN GÖRÜNTÜLENEMİYOR</span><h1>Bu ilan artık yayında değil.</h1><p>İlan kaldırılmış, tamamlanmış veya süresi dolmuş olabilir. Yakınındaki ilanları Döngü’de keşfedebilirsin.</p></section>
                @endif
            </div>

            <aside class="preview-card next-step app-promo-card" aria-labelledby="next-title">
                <span class="app-promo-kicker"><i aria-hidden="true"></i>MOBİLDE DEVAM ET</span>
                <h2 id="next-title">{{ $preview ? 'İlanı Döngü - Geri Dönüşüm Uygulamasında Görüntüle' : 'Yakınındaki ilanları keşfet' }}</h2>
                <p class="app-promo-copy">{{ $preview ? 'Güncel ilan durumunu incele ve güvenli iletişim için uygulamada devam et.' : 'Konumuna göre güncel ilanları bulmak için uygulamada devam et.' }}</p>
                <a class="primary-action preview-app-open" data-open-dongu data-download-url="{{ route('app.download', ['source' => 'listing_share']) }}" href="dongu://home">
                    <img class="primary-action-logo" src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="42" height="42">
                    <span class="primary-action-copy"><strong>Döngü - Geri Dönüşüm Uygulamasını Aç</strong></span>
                    <span class="primary-action-arrow" aria-hidden="true">→</span>
                </a>
            </aside>
        </div>

        @if ($preview && count($relatedListings))
            <section class="related-listings" aria-labelledby="related-title">
                <div class="related-heading">
                    <div><span>YAKINLARDAKİLER</span><h2 id="related-title">Dikkatini çekebilecek diğer ilanlar</h2></div>
                    <p>Aynı ildeki güncel ilanlardan seçildi.</p>
                </div>
                <div class="related-grid">
                    @foreach ($relatedListings as $related)
                        <a class="related-listing-card" href="{{ route('listing.preview', ['id' => $related['id']]) }}" aria-label="{{ number_format($related['quantity'], 0, ',', '.') }} adet {{ $related['materials'] }}, {{ $related['area'] }}, toplam {{ $related['total'] }} TL">
                            <div class="related-card-head">
                                <div class="related-material-stack" style="width: {{ 42 + min(max(count($related['items']) - 1, 0), 2) * 16 }}px" aria-hidden="true">
                                    @foreach (array_slice($related['items'], 0, 3) as $relatedItem)
                                        <span class="related-material-icon material-{{ $relatedItem['type'] }}" style="left: {{ $loop->index * 16 }}px; z-index: {{ 3 - $loop->index }}">
                                            @include('marketing.partials.material-icon', ['type' => $relatedItem['type']])
                                        </span>
                                    @endforeach
                                </div>
                                <div class="related-card-title">
                                    <div><strong>{{ number_format($related['quantity'], 0, ',', '.') }} adet</strong><i></i><span>Aktif</span></div>
                                    <p>{{ $related['materials'] }}</p>
                                </div>
                                <span class="related-card-arrow" aria-hidden="true">→</span>
                            </div>
                            <div class="related-breakdown">
                                @foreach ($related['items'] as $relatedItem)
                                    <span><b>{{ $relatedItem['label'] }}</b><small>{{ number_format($relatedItem['quantity'], 0, ',', '.') }} × {{ $relatedItem['price'] }} TL</small></span>
                                @endforeach
                            </div>
                            <div class="related-location">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                                <span>{{ $related['area'] }}</span>
                            </div>
                            <div class="related-money">
                                <div><small>Toplam satış fiyatı</small><strong>{{ $related['total'] }} TL</strong></div>
                                <span>İlanı gör <b aria-hidden="true">→</b></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
    </div>
@endsection
