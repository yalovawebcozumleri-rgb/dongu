<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7f2">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="application-name" content="Döngü">
    <meta name="apple-mobile-web-app-title" content="Döngü">
    <meta name="description" content="@yield('description', 'Döngü, DOA işaretli depozitolu PET, cam ve alüminyum ambalajlar için insanları yakındaki ilanlarla buluşturan bağımsız platformdur.')">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Döngü">
    <meta property="og:title" content="@yield('title', 'Döngü')">
    <meta property="og:description" content="@yield('description', 'Depozitolu ambalajlar için yerel ve güvenli buluşma platformu.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/images/site/dongu-social.png') }}">
    <meta property="og:image:secure_url" content="{{ url('/images/site/dongu-social.png') }}">
    <meta property="og:image:alt" content="Döngü mobil uygulaması ve depozitolu ambalaj paylaşım platformu">
    <meta property="og:locale" content="tr_TR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Döngü')">
    <meta name="twitter:description" content="@yield('description', 'Depozitolu ambalajlar için yerel ve güvenli buluşma platformu.')">
    <meta name="twitter:image" content="{{ url('/images/site/dongu-social.png') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/site/dongu-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('site/marketing.css') }}">
    <link rel="stylesheet" href="{{ asset('site/marketing-v2.css') }}">
    <link rel="stylesheet" href="{{ asset('site/marketing-mobile-fix.css') }}">
    <link rel="stylesheet" href="{{ asset('site/marketing-vision.css') }}?v=20260819-partnerships">
    <link rel="stylesheet" href="{{ asset('site/marketing-vision-responsive.css') }}?v=20260819-partnerships">
    <link rel="stylesheet" href="{{ asset('site/marketing-app-carousel.css') }}?v=20260815-app-carousel">
    <link rel="stylesheet" href="{{ asset('site/store-badges.css') }}?v=20260815-official-badges">
    <title>@yield('title', 'Döngü')</title>
    <script type="application/ld+json">
        {!! json_encode([
            chr(64).'context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Döngü',
            'url' => url('/'),
            'description' => 'Depozitolu PET, cam ve alüminyum ambalajlar için yerel ilan, talep, mesajlaşma ve teslimat platformu.',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Döngü',
                'url' => url('/'),
                'logo' => url('/images/site/dongu-icon.png'),
            ],
            'potentialAction' => [
                '@type' => 'ViewAction',
                'target' => route('marketing.mobile-app'),
                'name' => 'Döngü mobil uygulamasını indir',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body class="site-body">
<a class="site-skip" href="#icerik">İçeriğe geç</a>

<header class="site-header">
    <div class="site-shell site-header-inner">
        <a class="site-brand" href="{{ route('marketing.home') }}" aria-label="Döngü ana sayfa">
            <img class="site-brand-mark" src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="1024" height="1024">
            <span>döngü<span>.</span></span>
        </a>
        <nav class="site-nav" aria-label="Ana menü">
            <a class="{{ request()->routeIs('marketing.how-it-works') ? 'is-active' : '' }}" href="{{ route('marketing.how-it-works') }}">Nasıl çalışır?</a>
            <a class="{{ request()->routeIs('marketing.about') ? 'is-active' : '' }}" href="{{ route('marketing.about') }}">Hakkımızda</a>
            <a class="{{ request()->routeIs('marketing.faq') ? 'is-active' : '' }}" href="{{ route('marketing.faq') }}">SSS</a>
            <a class="{{ request()->routeIs('marketing.contact') ? 'is-active' : '' }}" href="{{ route('marketing.contact') }}">İletişim</a>
        </nav>
        <a class="site-header-cta {{ request()->routeIs('marketing.mobile-app') ? 'is-active' : '' }}" href="{{ route('marketing.mobile-app') }}">Uygulamayı İndir</a>
        <details class="site-mobile-menu">
            <summary aria-label="Menüyü aç"><span></span><span></span><span></span></summary>
            <nav aria-label="Mobil menü">
                <a href="{{ route('marketing.how-it-works') }}">Nasıl çalışır?</a>
                <a href="{{ route('marketing.about') }}">Hakkımızda</a>
                <a href="{{ route('marketing.faq') }}">Sık sorulanlar</a>
                <a href="{{ route('marketing.contact') }}">İletişim</a>
                <a href="{{ route('marketing.mobile-app') }}">Uygulamayı İndir</a>
            </nav>
        </details>
    </div>
</header>

<main id="icerik">@yield('content')</main>

@unless(request()->routeIs('marketing.mobile-app'))
<section id="mobil-uygulama" class="site-download-band" aria-labelledby="download-dongu-title">
    <div class="site-shell site-download-inner">
        <div class="site-download-copy">
            <span>Mobil uygulama</span>
            <h2 id="download-dongu-title">Döngü cebinde, ambalajların değeri yanında.</h2>
            <p>Yakındaki ilanları keşfetmek, talep göndermek, mesajlaşmak ve teslimatı güvenli akışla tamamlamak için Döngü uygulamasını indir.</p>
        </div>
        <div class="download-app-card">
            <div class="download-app-card-heading">
                <img src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="54" height="54">
                <div>
                    <strong>Döngü mobil uygulaması</strong>
                    <span>iPhone ve Android için</span>
                </div>
            </div>
            <x-store-badges class="store-badges-download-band" />
        </div>
    </div>
</section>
@endunless

<footer class="site-footer">
    <div class="site-shell">
        <div class="site-footer-main">
            <div>
                <a class="site-brand site-brand-light" href="{{ route('marketing.home') }}">
                    <img class="site-brand-mark" src="{{ asset('images/site/dongu-icon.png') }}" alt="" width="1024" height="1024">
                    <span>döngü<span>.</span></span>
                </a>
                <p>Döngü, depozitolu ambalajları yakındaki kullanıcılarla buluşturan; ilan, talep, mesajlaşma ve teslimat sürecini tek akışta toparlayan yerel paylaşım platformudur.</p>
            </div>
            <div class="site-footer-links">
                <div><strong>Keşfet</strong><a href="{{ route('marketing.how-it-works') }}">Nasıl çalışır?</a><a href="{{ route('marketing.about') }}">Hakkımızda</a></div>
                <div><strong>Destek</strong><a href="{{ route('marketing.faq') }}">Sık sorulanlar</a><a href="{{ route('marketing.contact') }}">İletişim</a><a href="{{ route('marketing.partnerships') }}">Reklam ve İş Birliği</a><a href="{{ route('account-deletion.create') }}">Hesap silme</a></div>
                <div><strong>Yasal</strong><a href="{{ route('legal.terms') }}">Kullanım Şartları</a><a href="{{ route('legal.privacy') }}">Gizlilik ve KVKK</a></div>
            </div>
        </div>
        <div class="site-footer-bottom">
            <span>© {{ date('Y') }} Döngü. Tüm hakları saklıdır.</span>
        </div>
    </div>
</footer>
</body>
</html>
