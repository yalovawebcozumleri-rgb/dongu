@extends('layouts.marketing')
@section('title', 'İletişim')
@section('description', 'Döngü kullanıcı desteği, işletme başvuruları, KVKK talepleri ve genel iletişim kanalları.')
@section('content')
<section class="site-page-hero"><div class="site-shell site-page-hero-inner"><span class="site-kicker"><i></i> İLETİŞİM</span><h1>Bir sorun varsa,<br>buradayız.</h1><p>Kullanıcı desteği, gizlilik talepleri, reklam veya iş birliği fikirleri için doğru kanaldan bize ulaşın.</p></div></section>
<section class="site-content-section"><div class="site-shell"><div class="site-contact-grid">
<article class="site-contact-card"><small>KULLANICI DESTEĞİ</small><h3>E-posta</h3><p>Hesap, ilan, güvenlik ve teknik destek taleplerini e-posta ile iletebilirsin.</p><a href="mailto:yalovawebcozumleri@gmail.com">yalovawebcozumleri@gmail.com →</a></article>
<article class="site-contact-card"><small>İŞ BİRLİĞİ VE REKLAM</small><h3>WhatsApp</h3><p>Reklam veya iş birliği teklifiniz için doğrudan iletişime geçebilirsiniz.</p><a href="https://wa.me/905413342219?text=Merhaba%2C%20D%C3%B6ng%C3%BC%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum." target="_blank" rel="noopener">+90 541 334 22 19 →</a></article>
<article class="site-contact-card"><small>HESAP VE VERİ</small><h3>Hesap silme</h3><p>Uygulamaya erişemiyorsan e-posta doğrulamasıyla hesap silme talebini tamamlayabilirsin.</p><a href="{{ route('account-deletion.create') }}">Hesap silme sayfası →</a></article>
<article class="site-contact-card"><small>HUKUK VE GİZLİLİK</small><h3>KVKK başvuruları</h3><p>Verilerinin işlenmesiyle ilgili taleplerini hesabındaki e-posta adresi üzerinden iletebilirsin.</p><a href="{{ route('legal.privacy') }}">Gizlilik ve KVKK metni →</a></article>
</div></div></section>
@endsection
