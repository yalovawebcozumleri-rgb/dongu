@php
    $welcomeListingContext = request()->routeIs('listing.preview') && ! empty($preview);
    $welcomeDownloadUrl = route('app.download', ['source' => $welcomeListingContext ? 'welcome_listing' : 'welcome_modal']);
@endphp
<div class="welcome-modal" data-welcome-modal data-session-key="dongu_welcome_seen_v3" hidden>
    <div class="welcome-modal-backdrop" data-welcome-close></div>
    <section class="welcome-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="welcome-modal-title" aria-describedby="welcome-modal-description" tabindex="-1">
        <button class="welcome-modal-close" type="button" data-welcome-close aria-label="Tanıtım penceresini kapat">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>

        <div class="welcome-modal-copy">
            <div class="welcome-modal-brand">
                <img src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="44" height="44">
                <span>DÖNGÜ - GERİ DÖNÜŞÜM UYGULAMASI</span>
            </div>
            <h2 id="welcome-modal-title">Ambalajların değerini<br><em>Döngü ile keşfet.</em></h2>
            <p id="welcome-modal-description">PET, cam ve alüminyum ambalajlarını değerlendir veya yakınındaki güncel ilanları keşfet.</p>
            <ul>
                <li><span>01</span>Ambalajlarını ilana dönüştür</li>
                <li><span>02</span>Yakınındaki ilanları keşfet</li>
                <li><span>03</span>Teslimatı güvenle tamamla</li>
            </ul>
        </div>

        <div class="welcome-modal-download">
            <div class="welcome-download-icon">
                <img src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="104" height="104">
            </div>
            <span class="welcome-download-kicker">{{ $welcomeListingContext ? 'İLANA UYGULAMADA DEVAM ET' : 'DÖNGÜ MOBİL UYGULAMASI' }}</span>
            <h3>{{ $welcomeListingContext ? 'İlanı uygulamada görüntüle' : 'Döngü’yü hemen indir' }}</h3>
            <p>{{ $welcomeListingContext ? 'Güncel ilan durumunu incele ve güvenli iletişim için uygulamada devam et.' : 'Yakınındaki ilanlara ulaş, ambalajlarını değerlendir ve teslimat sürecini tek yerden yönet.' }}</p>
            <div class="welcome-modal-actions">
                @if ($welcomeListingContext)
                    <a class="welcome-modal-primary" data-open-dongu data-download-url="{{ $welcomeDownloadUrl }}" href="dongu://home">
                        İlanı Uygulamada Görüntüle <b aria-hidden="true">→</b>
                    </a>
                @else
                    <a class="welcome-modal-primary" href="{{ $welcomeDownloadUrl }}">
                        Döngü Uygulamasını İndir <b aria-hidden="true">→</b>
                    </a>
                @endif
                <a class="welcome-modal-secondary" href="{{ route('listings.index') }}">İlanları Keşfet</a>
            </div>
            <small><i aria-hidden="true"></i> iPhone ve Android için</small>
        </div>
    </section>
</div>
