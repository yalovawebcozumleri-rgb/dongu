@extends('layouts.marketing')
@section('title', 'Hakkımızda | Döngü')
@section('description', 'Döngü’nün depozitolu PET, cam ve alüminyum ambalajlar için kurduğu yerel ilan, talep, mesajlaşma ve teslimat modelini keşfedin.')
@section('content')
<section class="vision-subhero vision-subhero-about">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> Hakkımızda</span>
            <h1>Yakındaki değeri<br><em>görünür kılıyoruz.</em></h1>
            <p>Döngü, depozitolu ambalajları olan kullanıcılarla bu ambalajları almak isteyen kullanıcıları aynı bölgede buluşturan bağımsız bir teknoloji platformudur.</p>
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark" aria-hidden="true">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">Döngü</b>
            <b class="vision-orbit-chip chip-two">Ambalaj</b>
            <b class="vision-orbit-chip chip-three">Topluluk</b>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-page-grid">
        <div>
            <span class="vision-section-kicker">Neden Döngü?</span>
            <h2>Ambalajın bir yerde beklemesi yerine doğru kişiye ulaşmasını sağlıyoruz.</h2>
        </div>
        <div class="vision-prose-card">
            <p>Kullanıcılar PET, cam ve alüminyum ambalajlarını tek ilanda paylaşır. Çevresindeki kişiler ilanları keşfeder, talep gönderir, mesajlaşır ve teslimatı güvenli işlem akışıyla tamamlar.</p>
            <p>Döngü ambalajların alıcısı, satıcısı veya taşıyıcısı değildir. İlan, mesajlaşma, rezervasyon, teslimat doğrulaması ve kullanıcı güvenliği için dijital altyapı sunar.</p>
            <div class="vision-note">Döngü; Türkiye Çevre Ajansı, Depozito Yönetim Sistemi veya DOA tarafından işletilen ya da yetkilendirilen resmî bir hizmet değildir. DOA ifadesi yalnızca uygun ambalaj koşulunu açıklamak amacıyla kullanılır.</div>
        </div>
    </div>
</section>
@endsection
