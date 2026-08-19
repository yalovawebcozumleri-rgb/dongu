@extends('layouts.marketing')
@section('title', 'İletişim | Döngü')
@section('description', 'Döngü kullanıcı desteği, teknik destek, hesap silme, KVKK başvuruları, reklam ve iş birliği iletişim kanallarına ulaşın.')
@section('content')
<section class="vision-subhero vision-subhero-contact">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> İletişim</span>
            <h1>Bir konu varsa,<br><em>doğru yerdesin.</em></h1>
            <p>Kullanıcı desteği, gizlilik talepleri, reklam veya iş birliği fikirleri için doğru kanaldan bize ulaşabilirsin.</p>
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark" aria-hidden="true">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">E-posta</b>
            <b class="vision-orbit-chip chip-two">Destek</b>
            <b class="vision-orbit-chip chip-three">KVKK</b>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-contact-grid">
        <article><small>Kullanıcı desteği</small><h3>E-posta</h3><p>Hesap, ilan, güvenlik ve teknik destek taleplerini e-posta ile iletebilirsin.</p><a href="mailto:yalovawebcozumleri@gmail.com">yalovawebcozumleri@gmail.com →</a></article>
        <article><small>İş birliği ve reklam</small><h3>WhatsApp</h3><p>Reklam veya iş birliği teklifin için doğrudan iletişime geçebilirsin.</p><a href="https://wa.me/905413342219?text=Merhaba%2C%20D%C3%B6ng%C3%BC%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum." target="_blank" rel="noopener">+90 541 334 22 19 →</a></article>
        <article><small>Hesap ve veri</small><h3>Hesap silme</h3><p>Uygulamaya erişemiyorsan e-posta doğrulamasıyla hesap silme talebini tamamlayabilirsin.</p><a href="{{ route('account-deletion.create') }}">Hesap silme sayfası →</a></article>
        <article><small>Hukuk ve gizlilik</small><h3>KVKK başvuruları</h3><p>Verilerinin işlenmesiyle ilgili taleplerini hesabındaki e-posta adresi üzerinden iletebilirsin.</p><a href="{{ route('legal.privacy') }}">Gizlilik ve KVKK metni →</a></article>
    </div>
</section>
<x-partnership-cta />
@endsection
