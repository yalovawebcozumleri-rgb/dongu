@extends('layouts.marketing')
@section('title', 'Hesap silindi')
@section('content')
<section class="vision-legal"><div class="site-shell"><article class="vision-legal-content" style="max-width:720px;margin:0 auto;text-align:center"><div style="width:72px;height:72px;border-radius:50%;background:#c9f35b;display:grid;place-items:center;font-size:32px;margin:0 auto 24px">✓</div><span class="vision-legal-kicker">İŞLEM TAMAMLANDI</span><h1 style="font-size:clamp(40px,7vw,66px)">Hesabın silindi</h1><p class="vision-legal-summary" style="margin:0 auto 30px">Kişisel bilgilerin kaldırıldı ve tüm cihaz oturumları kapatıldı. Güvenlik ve tamamlanmış işlem kayıtları yalnızca kimlikten arındırılmış biçimde korunabilir.</p><a class="vision-primary" href="{{ route('marketing.home') }}">Ana sayfaya dön <b>→</b></a></article></div></section>
@endsection
