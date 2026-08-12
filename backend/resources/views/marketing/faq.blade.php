@extends('layouts.marketing')
@section('title', 'Sık Sorulan Sorular | Döngü')
@section('description', 'Döngü’de ilan verme, alım talebi, fiyat, konum, teslimat kodu, güvenlik, puan, hesap silme ve KVKK hakkında sık sorulan sorular.')
@section('content')
<section class="vision-subhero vision-subhero-faq">
    <div class="vision-noise"></div>
    <div class="site-shell vision-subhero-grid">
        <div>
            <span class="vision-kicker"><i></i> Sık sorulan sorular</span>
            <h1>Merak ettiğin<br><em>her şey net.</em></h1>
            <p>Döngü’nün çalışma biçimi, güvenlik yaklaşımı, desteklediği ambalajlar ve hesap işlemleri hakkında temel cevapları burada bulabilirsin.</p>
        </div>
        <div class="vision-question-orbit vision-orbit-brandmark" aria-hidden="true">
            <div class="vision-orbit-ring ring-one"></div>
            <div class="vision-orbit-ring ring-two"></div>
            <div class="vision-orbit-logo-mark"><img src="{{ asset('images/site/dongu-icon.png') }}" alt=""></div>
            <b class="vision-orbit-chip chip-one">SSS</b>
            <b class="vision-orbit-chip chip-two">Güvenlik</b>
            <b class="vision-orbit-chip chip-three">Hesap</b>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-faq-layout">
        <div>
            <span class="vision-section-kicker">Platform</span>
            <h2>Temel bilgiler</h2>
            <p>Aradığın cevap burada yoksa bize doğrudan ulaşabilirsin.</p>
        </div>
        <div class="vision-accordion">
            <details open><summary>Döngü nedir?<span>＋</span></summary><p>Depozitolu ambalajı bulunan kişilerle bu ambalajları teslim almak isteyen kişileri yakındaki ilanlar üzerinden buluşturan bağımsız bir mobil platformdur.</p></details>
            <details><summary>Döngü resmî DOA uygulaması mı?<span>＋</span></summary><p>Hayır. Döngü; DOA, Türkiye Çevre Ajansı, Depozito Yönetim Sistemi veya iade makinesi işletmecilerinden bağımsızdır.</p></details>
            <details><summary>Döngü ambalajları satın alıyor mu?<span>＋</span></summary><p>Hayır. Fiyat, teslimat ve ödeme ilişkisi ilan sahibi ile alıcı arasında kurulur. Döngü bu ilişki için ilan ve iletişim altyapısı sağlar.</p></details>
            <details><summary>Uygulama komisyon alıyor mu?<span>＋</span></summary><p>Mevcut modelde kullanıcılar arasındaki işlemlerden komisyon alınmaz.</p></details>
        </div>
    </div>
</section>

<section class="vision-page-section is-soft">
    <div class="site-shell vision-faq-layout">
        <div>
            <span class="vision-section-kicker">İlan ve teslimat</span>
            <h2>İşlem süreci</h2>
        </div>
        <div class="vision-accordion">
            <details><summary>Hangi ambalajlar ilan edilebilir?<span>＋</span></summary><p>Desteklenen PET, cam ve alüminyum ambalajlar ilan edilebilir. Ambalajların boş ve teslim edilebilir durumda olması gerekir.</p></details>
            <details><summary>Fiyatı kim belirliyor?<span>＋</span></summary><p>İlan sahibi her malzeme için birim fiyatı belirler. Uygulama, birim fiyatın belirlenen üst sınırı aşmasına izin vermez.</p></details>
            <details><summary>Kesin adresim herkese görünür mü?<span>＋</span></summary><p>Hayır. İlan kartlarında yaklaşık bölge gösterilir. Kesin teslimat bilgisi yalnızca ilgili işlem tarafları arasında görünür.</p></details>
            <details><summary>Teslimat kodu ne işe yarar?<span>＋</span></summary><p>Ambalajların gerçekten teslim edildiğini tarafların birlikte doğrulamasını sağlar. Kod yalnızca fiziksel teslimat sırasında paylaşılmalıdır.</p></details>
        </div>
    </div>
</section>

<section class="vision-page-section">
    <div class="site-shell vision-faq-layout">
        <div>
            <span class="vision-section-kicker">Güvenlik ve hesap</span>
            <h2>Kontrol sende</h2>
        </div>
        <div class="vision-accordion">
            <details><summary>Rahatsız eden kullanıcıyı engelleyebilir miyim?<span>＋</span></summary><p>Evet. Kullanıcıyı engelleyebilir, ilgili mesajı, ilanı veya profili bildirebilirsin.</p></details>
            <details><summary>Hesabımı nasıl silebilirim?<span>＋</span></summary><p>Uygulamadaki profil alanından veya web sitesindeki hesap silme sayfasından e-posta doğrulamasıyla talep oluşturabilirsin.</p></details>
            <details><summary>Döngü puanı para yerine geçer mi?<span>＋</span></summary><p>Hayır. Döngü puanı yalnızca doğrulanmış çevresel katkıyı ve platform içi itibarı gösterir; nakde çevrilemez.</p></details>
        </div>
    </div>
</section>
@endsection
