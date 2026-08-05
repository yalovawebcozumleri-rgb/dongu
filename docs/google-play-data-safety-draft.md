# Google Play veri güvenliği çalışma taslağı

Bu dosya Play Console formuna doğrudan ve kontrolsüz kopyalanmamalıdır. Production sürümündeki SDK'lar, backend kayıtları ve gerçek saklama süreleriyle yayın öncesinde yeniden doğrulanmalıdır.

## Muhtemel veri kategorileri

| Kategori | Kullanım amacı | Zorunluluk | Paylaşım değerlendirmesi |
| --- | --- | --- | --- |
| Ad ve e-posta | Hesap, profil, iletişim ve güvenlik | Hesap açmak için zorunlu | Hizmet sağlayıcıları ayrıca değerlendirilmeli |
| Profil fotoğrafı | Herkese açık isteğe bağlı profil | İsteğe bağlı | Kullanıcının seçimiyle herkese açık |
| Yaklaşık ve hassas konum | Yakındaki ilanlar, harita ve teslimat adresi | Özelliğe bağlı | Harita/konum sağlayıcısının SDK davranışı doğrulanmalı |
| Açık adres ve telefon | Teslimat koordinasyonu | Özelliğe bağlı | Yalnızca yetkili işlem taraflarına gösterim doğrulanmalı |
| İlan ve malzeme bilgisi | Pazaryeri hizmeti | İlan veren için zorunlu | İlan içeriği herkese açık olabilir |
| Mesajlar | Kullanıcılar arası iletişim ve güvenlik | Mesajlaşma için zorunlu | Bildirim/moderasyon süreci nedeniyle backend'de işlenir |
| Yorum ve puan | İtibar sistemi | İsteğe bağlı | Herkese açık profile yansıyabilir |
| Push tokenı | Bildirim gönderimi | Bildirim açılırsa | Expo/Firebase işleyişi doğrulanmalı |
| Reklam tanımlayıcıları ve etkinliği | AdMob reklam sunumu/ölçümü | Reklamlı sürümde | Google Mobile Ads veri açıklamasıyla eşleştirilmeli |
| IP, güvenlik ve denetim kayıtları | Dolandırıcılık, kötüye kullanım ve yönetici denetimi | Güvenlik için | Altyapı sağlayıcıları ayrıca değerlendirilmeli |
| Hata ve performans verisi | Teknik kararlılık | Kullanılan izleme aracına göre | Production'da kullanılan araçlarla yeniden değerlendirilmeli |

## Form öncesi doğrulanacak sorular

- Veriler cihaz ile sunucu arasında yalnızca HTTPS/WSS üzerinden mi taşınıyor?
- Kullanıcı hesabını ve ilişkili kişisel verileri silme talebi oluşturabiliyor mu?
- Hesap silme için mağazada yayınlanacak, oturum açmadan erişilebilen bir web URL'si gerekiyor mu?
- Hangi kayıtlar hukuki yükümlülük, güvenlik veya uyuşmazlık nedeniyle hemen silinmiyor?
- Expo, Firebase, Google Mobile Ads, harita ve sunucu sağlayıcısı hangi verileri bağımsız olarak topluyor?
- Reklam kişiselleştirme ve iOS takip izni tercihleri gerçek SDK yapılandırmasıyla uyumlu mu?
- İsteğe bağlı ve zorunlu alanlar formda doğru ayrıldı mı?
- Veriler yalnızca uygulama işlevi, güvenlik, iletişim, analiz veya reklam amaçlarından gerçekten kullanılanlarla mı işaretlendi?

## Hesap silme yayın engeli

Uygulama içinde Profil → Hesabımı sil akışı bulunur. Uygulamaya erişemeyen kullanıcılar için `https://dongu.yalovawebcozumleri.com/hesap-silme` adresinde e-posta koduyla doğrulanan ikinci silme yolu bulunur. Production yayında iki yol da gerçek hesapla yeniden test edilmelidir.

