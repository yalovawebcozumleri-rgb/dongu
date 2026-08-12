@extends('layouts.marketing')
@section('title', 'Nasıl Çalışır? | Döngü')
@section('description', 'Döngü’de ilan oluşturma, alım talebi gönderme, satıcıyla mesajlaşma, rezervasyon ve teslimat kodu sürecinin nasıl çalıştığını öğrenin.')
@section('content')
<section class="vision-subhero vision-subhero-flow">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> Nasıl çalışır?</span>
            <h1>İlandan teslimata,<br><em>akış net ilerler.</em></h1>
            <p>Döngü’de ambalaj paylaşmak ya da yakındaki bir ilana talep göndermek karmaşık değildir. İlan, talep, mesajlaşma ve teslimat kodu aynı güvenli akışta toplanır.</p>
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark" aria-hidden="true">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">İlan</b>
            <b class="vision-orbit-chip chip-two">Talep</b>
            <b class="vision-orbit-chip chip-three">Teslimat</b>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-page-grid">
        <div>
            <span class="vision-section-kicker">İlan verenler için</span>
            <h2>Elindeki ambalajlar görünür bir değere dönüşür.</h2>
        </div>
        <div class="vision-step-stack">
            <article><span>01</span><div><h3>Ambalajlarını ekle</h3><p>PET, cam ve alüminyum adetlerini ayrı ayrı gir; her malzeme için birim fiyatı belirle.</p></div></article>
            <article><span>02</span><div><h3>Teslimat adresini seç</h3><p>Kayıtlı teslimat adreslerinden birini kullan veya yeni teslimat adresi oluştur.</p></div></article>
            <article><span>03</span><div><h3>Talebi değerlendir</h3><p>Gelen alım taleplerini incele, uygun kullanıcıyı kabul et ve mesajlaşmada ayrıntıları netleştir.</p></div></article>
            <article><span>04</span><div><h3>Teslimatı doğrula</h3><p>Ambalajlar teslim edildiğinde alıcının teslimat koduyla işlemi tamamla.</p></div></article>
        </div>
    </div>
</section>

<section class="vision-page-section is-soft">
    <div class="site-shell vision-page-grid">
        <div>
            <span class="vision-section-kicker">Alıcılar için</span>
            <h2>Yakınındaki ilanı bul, talep gönder, teslimatı güvenle yönet.</h2>
        </div>
        <div class="vision-step-stack">
            <article><span>01</span><div><h3>Yakındaki ilanları keşfet</h3><p>Konum ve mesafe seçimine göre yakınındaki aktif ilanları gör; malzeme türü, güncellik, mesafe ve favorilerle aradığın ilana daha hızlı ulaş.</p></div></article>
            <article><span>02</span><div><h3>Satıcıyla iletişime geç</h3><p>İlgilendiğin ilana alım talebi gönderebilir veya satıcıya yazabilirsin. Böylece işlem başlamadan önce detayları netleştirirsin.</p></div></article>
            <article><span>03</span><div><h3>Rezervasyonu takip et</h3><p>Satıcı talebini kabul ettiğinde süreç rezerve olur. İlgili teslimat bilgileri yalnızca işlem tarafları arasında görünür.</p></div></article>
            <article><span>04</span><div><h3>Teslimatı güvenle tamamla</h3><p>Ambalajları görmeden teslimat kodunu paylaşma. Mümkün oldukça bilinen, aydınlık ve kamusal bir noktada buluş.</p></div></article>
        </div>
    </div>
</section>
@endsection
