# Döngü üretim sunucusu kontrol listesi

## Sunucu bileşenleri

- Ubuntu güvenlik güncellemeleri
- Nginx
- Desteklenen PHP-FPM sürümü ve Laravel uzantıları
- MySQL
- Composer 2
- Node.js ve npm (yalnızca frontend derlemesi için)
- Supervisor veya systemd
- Certbot ya da Cloudflare üzerinden geçerli HTTPS

## Uygulama kurulumu

1. Projeyi ayrı bir deploy kullanıcısıyla `/var/www/dongu` altında çalıştır.
2. `backend/.env.production.example` dosyasını `.env` olarak kopyala ve yalnızca sunucuda gerçek sırlarla doldur.
3. `APP_KEY`, MySQL parolası, Reverb sırları, SMTP uygulama parolası ve Expo erişim anahtarını Git'e ekleme.
4. Laravel bağımlılıklarını production modunda kur.
5. Migrationları bakım planıyla çalıştır.
6. `php artisan storage:link` ile herkese açık dosya bağlantısını oluştur.
7. Frontend varlıklarını üretim için derle.
8. Laravel yapılandırma, rota ve görünüm önbelleklerini oluştur.

## Sürekli süreçler

- Queue worker: bildirimler ve arka plan işleri için Supervisor/systemd altında sürekli çalışmalı.
- Reverb: yalnızca `127.0.0.1:8080` dinlemeli; Nginx üzerinden WSS olarak yayınlanmalı.
- Scheduler: `php artisan schedule:run` her dakika çalışmalı.
- Workerlar yeni deploy sonrasında kontrollü biçimde yeniden başlatılmalı.

## Nginx ve güvenlik

- Web kökü Laravel `public` dizini olmalı.
- `/api`, `/admin`, yasal sayfalar ve statik dosyalar aynı HTTPS alan adında sunulmalı.
- WebSocket yükseltme başlıkları Reverb'e aktarılmalı.
- `.env`, depo dosyaları ve loglar web üzerinden erişilememeli.
- Dosya yükleme boyutu uygulamadaki avatar/ilan sınırlarıyla uyumlu olmalı.
- MySQL ve Reverb portları internete açılmamalı.

## Yedekleme ve izleme

- Günlük şifreli MySQL yedeği al ve sunucu dışına kopyala.
- Yüklenen kullanıcı dosyalarını yedekle.
- Yedek geri yükleme denemesini yayın öncesinde yap.
- Nginx, Laravel, queue ve Reverb logları için rotasyon kur.
- Disk doluluk, başarısız queue işleri, 5xx oranı ve servis çalışma durumunu izle.

## Yayın öncesi doğrulama

- Sağlık kontrolü ve API HTTPS üzerinden yanıt veriyor.
- Kayıt, giriş kodu e-postası ve oturum açma çalışıyor.
- İlan oluşturma, fotoğraf/avatar yükleme ve konum sorgusu çalışıyor.
- Mesajlaşma WSS üzerinden gerçek zamanlı çalışıyor.
- Queue üzerinden uygulama içi ve push bildirim testi başarılı.
- Admin paneli ile destekçi paneli yalnızca yetkili hesaplara açılıyor.
- Kullanım Şartları ve Gizlilik Politikası oturumsuz erişilebiliyor.
- Android production build yerel IP veya Google test reklam kimliği içermiyor.

