<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $document['summary'] }}">
    <title>{{ $document['title'] }} · Döngü</title>
    <style>
        :root{color-scheme:light;--ink:#15382a;--green:#257451;--lime:#dff35b;--muted:#65766d;--line:#dce5df;--bg:#f6f8f5}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.7}.top{background:#123326;color:white}.top-inner,.page{width:min(900px,calc(100% - 32px));margin:auto}.top-inner{padding:26px 0}.brand{font-size:24px;font-weight:900;letter-spacing:-1px}.brand b{color:var(--lime)}.page{padding:42px 0 70px}.hero{background:white;border:1px solid var(--line);border-radius:26px;padding:clamp(24px,5vw,48px);box-shadow:0 18px 60px rgba(21,56,42,.07)}.eyebrow{color:var(--green);font-size:12px;font-weight:900;letter-spacing:1.6px}.hero h1{font-size:clamp(30px,6vw,48px);line-height:1.08;letter-spacing:-1.8px;margin:8px 0 16px}.summary{font-size:17px;color:var(--muted);max-width:720px}.meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:22px}.pill{background:#eff5f1;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:800}.toc{margin:22px 0 0;padding:20px;background:#f7faf8;border-radius:18px}.toc strong{display:block;margin-bottom:7px}.toc a{display:block;color:var(--green);text-decoration:none;padding:3px 0}.section{padding:30px 4px;border-bottom:1px solid var(--line);scroll-margin-top:20px}.section:last-child{border-bottom:0}.section h2{font-size:21px;line-height:1.3;margin:0 0 13px}.section p{color:#344b40;margin:0 0 13px}.contact{margin-top:28px;padding:20px;border-radius:18px;background:#15382a;color:white}.contact a{color:var(--lime);font-weight:800}.note{margin-top:18px;color:var(--muted);font-size:13px}@media(max-width:560px){.top-inner{padding:18px 0}.page{padding-top:18px}.hero{border-radius:20px;padding:22px}.hero h1{letter-spacing:-1px}.section{padding:25px 0}}
    </style>
</head>
<body>
<header class="top"><div class="top-inner"><div class="brand">döngü<b>.</b></div></div></header>
<main class="page"><article class="hero">
    <div class="eyebrow">HUKUK VE GİZLİLİK</div>
    <h1>{{ $document['title'] }}</h1>
    <p class="summary">{{ $document['summary'] }}</p>
    <div class="meta"><span class="pill">Sürüm {{ $document['version'] }}</span><span class="pill">Yürürlük: {{ $document['effective_date'] }}</span></div>
    <nav class="toc" aria-label="İçindekiler"><strong>İçindekiler</strong>@foreach($document['sections'] as $index => $section)<a href="#bolum-{{ $index + 1 }}">{{ $section['title'] }}</a>@endforeach</nav>
    @foreach($document['sections'] as $index => $section)<section class="section" id="bolum-{{ $index + 1 }}"><h2>{{ $section['title'] }}</h2>@foreach($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>@endforeach
    <aside class="contact">
        <strong>Veri sorumlusu ve iletişim</strong><br>
        {{ $document['operator']['name'] }}<br>
        {{ $document['operator']['address'] }}<br>
        <a href="mailto:{{ $document['operator']['email'] }}">{{ $document['operator']['email'] }}</a> ·
        <a href="tel:{{ $document['operator']['phone_uri'] }}">{{ $document['operator']['phone'] }}</a>
    </aside>
    <p class="note">Bu belgenin güncel sürümü her zaman bu sayfada yayımlanır.</p>
</article></main>
</body>
</html>
