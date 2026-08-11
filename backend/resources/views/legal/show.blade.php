@extends('layouts.marketing')
@section('title', $document['title'])
@section('description', $document['summary'])
@section('content')
<section class="vision-legal"><div class="site-shell">
    <header class="vision-legal-hero"><span class="vision-legal-kicker">HUKUK VE GİZLİLİK</span><h1>{{ $document['title'] }}</h1><p class="vision-legal-summary">{{ $document['summary'] }}</p><div class="vision-legal-meta"><span>Sürüm {{ $document['version'] }}</span><span>Yürürlük: {{ $document['effective_date'] }}</span></div></header>
    <div class="vision-legal-layout">
        <nav class="vision-legal-toc" aria-label="İçindekiler"><strong>İÇİNDEKİLER</strong>@foreach($document['sections'] as $index => $section)<a href="#bolum-{{ $index + 1 }}">{{ $section['title'] }}</a>@endforeach</nav>
        <article class="vision-legal-content">@foreach($document['sections'] as $index => $section)<section class="vision-legal-section" id="bolum-{{ $index + 1 }}"><h2>{{ $section['title'] }}</h2>@foreach($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>@endforeach
            <aside class="vision-legal-contact"><strong>Veri sorumlusu ve iletişim</strong><br>{{ $document['operator']['name'] }}<br>{{ $document['operator']['address'] }}<br><a href="mailto:{{ $document['operator']['email'] }}">{{ $document['operator']['email'] }}</a> · <a href="tel:{{ $document['operator']['phone_uri'] }}">{{ $document['operator']['phone'] }}</a></aside>
        </article>
    </div>
</div></section>
@endsection
