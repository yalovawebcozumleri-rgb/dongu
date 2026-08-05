# Döngü Google Play yayın kontrol listesi

## Sabit uygulama kimliği

- Paket adı: `com.yalovawebcozumleri.dongu`
- Uygulama adı: `Döngü`
- İlk mağaza sürümü: `1.0.0`
- Üretim çıktısı: Android App Bundle (`.aab`)

Paket adı ilk Play yüklemesinden sonra değiştirilemez. Play Console'da oluşturulan uygulamada aynı paket adı kullanılmalıdır.

## Hesap ve kapalı test

- Play Console kimlik ve Android cihaz doğrulamasını tamamla.
- Uygulamayı oluştururken varsayılan dili Türkçe, uygulama türünü Uygulama ve fiyatı Ücretsiz seç.
- Kişisel geliştirici hesabının kapalı test ekranında istenen güncel test koşullarını tamamla.
- Test kullanıcılarının e-posta listesini sakla ve test geri bildirimlerini kayıt altına al.

## Dış servisler

- Expo/EAS hesabında projeyi oluştur ve `EXPO_PROJECT_ID` değerini al.
- Firebase'de Android uygulamasını aynı paket adıyla oluştur.
- Firebase `google-services.json` dosyasını EAS dosya sırrı olarak tanımla.
- Firebase Cloud Messaging hizmet hesabı anahtarını EAS bildirim kimlik bilgilerine yükle.
- AdMob'da Android uygulaması ile native, geçiş ve ödüllü reklam birimlerini oluştur.
- Gerçek AdMob kimliklerini yalnızca EAS production ortamına ekle; kaynak koda veya Git'e yazma.

## Alan adı ve yasal sayfalar

- `dongu.yalovawebcozumleri.com` DNS kaydını Ubuntu sunucusuna yönlendir.
- HTTPS sertifikasını etkinleştir.
- Aşağıdaki sayfaların giriş yapmadan açıldığını doğrula:
  - `https://dongu.yalovawebcozumleri.com/kullanim-sartlari`
  - `https://dongu.yalovawebcozumleri.com/gizlilik-politikasi`
- Yasal işletmeci adı ve adresini production `.env` içinde gerçek bilgilerle doldur.

## Play mağaza içeriği

- Kısa açıklama, uzun açıklama ve destek e-postasını gir.
- 512 × 512 uygulama simgesi hazırla.
- 1024 × 500 özellik grafiği hazırla.
- En az iki güncel telefon ekran görüntüsü yükle.
- Reklam içerdiğini doğru şekilde beyan et.
- Uygulama erişimi incelemesi için çalışan bir test hesabı ve inceleme notu ver.
- Hedef kitlenin çocuklar olmadığını ve yaş sınırını doğru beyan et.

## Veri güvenliği beyanı

Uygulamanın gerçek davranışıyla eşleşecek biçimde en az şu kategorileri değerlendir:

- Hesap bilgileri: ad, e-posta ve isteğe bağlı profil fotoğrafı.
- Konum: yakındaki ilanlar ve teslimat adresi için hassas/yaklaşık konum.
- Kullanıcı içeriği: ilan, mesaj, yorum, bildirim ve fotoğraf.
- Cihaz tanımlayıcıları: push bildirim tokenı ve reklam SDK verileri.
- Uygulama etkinliği: reklam gösterimi/tıklaması ve güvenlik kayıtları.

Formu tahminle doldurma; yayınlanacak sürüm, backend kayıtları ve kullanılan SDK'larla son kez karşılaştır.

## Son teknik kontrol

```powershell
npm install
npx expo-doctor
npx tsc --noEmit
npx eas-cli build --platform android --profile production
```

Production derlemesi eksik API, Reverb, Firebase veya AdMob değeri varsa otomatik olarak durdurulur.

