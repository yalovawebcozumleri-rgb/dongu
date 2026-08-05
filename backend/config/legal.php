<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME', 'Döngü platform işletmecisi'),
    'operator_address' => env('LEGAL_OPERATOR_ADDRESS'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'yalovawebcozumleri@gmail.com'),
    'minimum_age' => (int) env('LEGAL_MINIMUM_AGE', 18),
    'documents' => [
        'terms' => [
            'title' => 'Kullanıcı Şartları',
            'short_title' => 'Kullanım Şartları',
            'version' => '2026-08-05.1',
            'effective_date' => '4 Ağustos 2026',
            'summary' => 'Bu şartlar; bağımsız Döngü platformunda DOA işaretli ambalajlar için ilan verme, alım talebi, mesajlaşma, teslimat, değerlendirme ve güvenlik kurallarını düzenler.',
            'sections' => [
                ['title' => '1. Taraflar, kapsam ve kabul', 'paragraphs' => [
                    'Bu Kullanıcı Şartları, Döngü mobil uygulamasını ve bağlantılı internet hizmetlerini işleten :operator_name ile hizmeti kullanan kişi arasında kurulur. “Platform”, Döngü mobil uygulamasını, web sayfalarını, API hizmetlerini ve yönetim sistemlerini ifade eder.',
                    'Hesap oluştururken kutucuğu işaretleyip doğrulama kodunu tamamladığında bu şartları kabul etmiş olursun. Kabul tarihi, şart sürümü, hesabın ve güvenlik kayıtları denetlenebilir biçimde saklanır.',
                    'Bu şartların herhangi bir hükmünün emredici mevzuata aykırı olması hâlinde ilgili emredici düzenleme uygulanır; diğer hükümler yürürlükte kalır.',
                ]],
                ['title' => '2. Platformun amacı, bağımsızlığı ve rolü', 'paragraphs' => [
                    'Döngü; üzerinde okunabilir DOA işareti bulunan ve platformun desteklediği PET, cam ve alüminyum ambalajları elinde bulunduran kişiler ile bunları teslim almak isteyen kişileri buluşturan bağımsız bir ilan ve iletişim platformudur.',
                    'Döngü; Türkiye Çevre Ajansı, Depozito Yönetim Sistemi, DOA mobil uygulaması, DOA iade makineleri veya bunların işletmecileri tarafından sunulan, işletilen, yetkilendirilen, desteklenen ya da onaylanan bir hizmet değildir. Döngü ile bu kurum, sistem, uygulama ve işletmeciler arasında kurumsal, ticari, teknik veya hukuki bir ortaklık bulunmamaktadır.',
                    'DOA adı ve işareti ilgili hak sahiplerine aittir. Bu ad ve işaret, platformda yalnızca ilan edilebilecek depozitolu ambalajların uygunluk koşulunu ve kullanıcıların başvurabileceği üçüncü taraf iade sistemini açıklamak amacıyla kullanılır.',
                    'Döngü, aksi açıkça belirtilmedikçe ilan konusu ambalajların alıcısı, satıcısı, taşıyıcısı, sahibi, ödeme kuruluşu, depozito bedelini ödeyen taraf veya teslimat tarafı değildir. Kullanıcılar arasındaki teslim, fiyat, ödeme ve buluşma ilişkisi ilgili kullanıcılar arasında kurulur.',
                    'Platform; ilanların doğruluğunu, ambalajların niteliğini veya adedini, DOA işareti bulunsa dahi ambalajların DOA iade makineleri ya da başka kabul noktalarınca kabul edileceğini veya kullanıcının belirli bir gelir, kâr ya da depozito bedeli elde edeceğini garanti etmez. Nihai kabul, ilgili üçüncü tarafın güncel teknik ve fiziksel kontrollerine bağlıdır.',
                ]],
                ['title' => '3. Yaş, hesap ve kimlik güvenliği', 'paragraphs' => [
                    'Platformu kullanmak için en az :minimum_age yaşında ve bu sözleşmeyi kurma ehliyetine sahip olmalısın. Başkası adına yetkisiz hesap açamazsın.',
                    'Doğru, güncel ve sana ait bir e-posta adresi kullanmalı; doğrulama kodunu ve cihaz erişimini korumalısın. Hesabında izinsiz işlem fark edersen gecikmeden bize bildirmelisin.',
                    'Bir kişi, kısıtlamaları aşmak veya puan sistemini manipüle etmek amacıyla birden fazla hesap açamaz. Hesap devredilemez, kiralanamaz veya satılamaz.',
                ]],
                ['title' => '4. İlan oluşturma kuralları', 'paragraphs' => [
                    'Yalnızca gerçekten elinde bulunan, hukuka uygun şekilde edinilmiş, platformun desteklediği malzeme türünde ve üzerinde okunabilir DOA işareti bulunan depozitolu ambalajlar için ilan verebilirsin. Malzeme türü, adet, birim fiyat, fiziksel durum, işaret, açıklama ve teslimat bilgileri doğru ve güncel olmalıdır.',
                    'Birim fiyat ve ilan sayısı gibi sınırlar yönetim panelinde belirlenen güncel kullanım politikasına tabidir. Uygulamada gösterilen sınırları dolanmak, yanlış adet girmek, mükerrer ilan açmak veya yanıltıcı açıklama kullanmak yasaktır.',
                    'DOA işareti bulunmayan veya işareti okunamayan; tehlikeli, kirli, tıbbi atık niteliğinde, çalıntı, sahte, mevzuata aykırı ya da üçüncü kişilerin hakkını ihlal eden ürünler ilan edilemez. Ambalaj boş, teslim edilebilir ve ilanda açıklanan fiziksel durumda olmalıdır. Platform, güvenlik veya uygunluk gerekçesiyle ilanı reddedebilir, görünürlüğünü azaltabilir, süresini sonlandırabilir veya kaldırabilir.',
                    'İlanlar belirli süreyle yayımlanır. Süresi dolan, silinen veya tamamlanan ilanlar uygulamada pasif hâle gelebilir ve saklama politikası sonunda kalıcı olarak temizlenebilir.',
                ]],
                ['title' => '5. Konum, adres ve güvenli teslimat', 'paragraphs' => [
                    'İlan yayınında gerçek teslimat konumu seçilmelidir. Herkese açık listelerde kesin adres yerine yaklaşık bölge gösterilir; kesin adres yalnızca işlem akışında gerekli olduğunda yetkili taraflara sunulur.',
                    'Teslimatı aydınlık ve güvenli bir kamusal alanda yapmanı, tek başına riskli bir buluşmaya gitmemeni ve kişisel güvenliğini önceliklendirmeni öneririz. Platform acil yardım hizmeti değildir. Acil tehlikede 112 ile iletişime geçmelisin.',
                    'Kullanıcıların buluşma, taşıma, ulaşım ve teslim sırasında doğan kişisel kararları ile fiilleri kendi sorumluluklarındadır. Şüpheli davranışı ilan, profil veya mesaj bildirme araçlarıyla iletebilirsin.',
                ]],
                ['title' => '6. Alım talebi, rezervasyon ve teslimat kodu', 'paragraphs' => [
                    'Alım talebi, alıcının ilanla ilgilendiğini bildirir; satıcı kabul edene kadar kesin rezervasyon oluşturmaz. Satıcı aynı anda yalnızca platformun izin verdiği sayıda talebi kabul edebilir.',
                    'Taraflar, işlem tamamlanmadan talebi geri çekebilir veya rezervasyonu iptal edebilir. Sık, kötü niyetli ya da karşı tarafı zarara uğratmaya yönelik iptaller kullanım kısıtlamasına yol açabilir.',
                    'Teslimat kodu yalnızca ambalajlar fiilen teslim edildiğinde paylaşılmalı ve girilmelidir. Kodu teslimden önce istemek, vermek, üçüncü kişiye aktarmak veya sahte teslimat oluşturmak yasaktır.',
                    'Teslimatın sistemde tamamlanması, hukuki veya mali bir garanti oluşturmaz; işlem ve puan denetimi için teknik kayıt niteliğindedir.',
                ]],
                ['title' => '7. Mesajlaşma kuralları', 'paragraphs' => [
                    'Mesajlaşma yalnızca ilgili ilan ve teslimat amacıyla kullanılmalıdır. İstenmeyen reklam, toplu mesaj, taciz, tehdit, nefret söylemi, cinsel içerik, dolandırıcılık, kimlik avı, kişisel veri talebi veya platform dışı kötüye kullanım yasaktır.',
                    'Mesaj, görüşme ve talep sınırları spam ve bot kullanımını azaltmak amacıyla uygulanabilir. Bu sınırlar hesap yaşı, güvenlik sinyalleri ve yönetim politikasına göre değişebilir; güncel sınırlar uygulamada gösterilir.',
                    'Kullanıcılar birbirini engelleyebilir, sohbeti kendi listesinden kaldırabilir ve belirli mesajları bildirebilir. Bildirilen içerik, konuşma bağlamıyla yetkili yöneticilerce incelenebilir.',
                ]],
                ['title' => '8. Puan, sıralama, rozet ve değerlendirme', 'paragraphs' => [
                    'Döngü puanı, satıcının doğrulanmış teslimatla doğaya kazandırdığı katkıyı gösteren platform içi itibar ölçüsüdür. Para, elektronik para, hediye çeki veya alacak hakkı değildir; devredilemez ve nakde çevrilemez.',
                    'Puanlar, rozetler ve sıralamalar şüpheli işlem, tekrar eden taraf eşleşmesi, sahte teslimat, teknik hata veya kural ihlali hâlinde bekletilebilir, düzeltilebilir ya da iptal edilebilir. Yönetici kararları denetim kaydına alınır.',
                    'Değerlendirmeler yalnızca tamamlanan ve puanlama süresi açık işlemler için yapılabilir. Yorumların dürüst, deneyime dayalı ve hakaret içermeyen nitelikte olması gerekir.',
                ]],
                ['title' => '9. Yasaklı davranışlar', 'paragraphs' => [
                    'Bot, otomasyon, sahte hesap, cihaz veya ağ manipülasyonu kullanmak; güvenlik sınırlarını aşmak; API’ye yetkisiz erişmek; tersine mühendislik, veri kazıma veya hizmet engelleme girişiminde bulunmak yasaktır.',
                    'Başkasına ait kişisel verileri izinsiz paylaşmak, kullanıcıları platform dışı zararlı bağlantılara yönlendirmek, yanıltıcı kimlik kullanmak, puan veya değerlendirme ticareti yapmak ve bildirim sistemini kötüye kullanmak yasaktır.',
                    'Yasal mercilerin talebi, kullanıcı güvenliği veya ağır ihlal şüphesi hâlinde ilgili kayıtlar korunabilir ve mevzuata uygun şekilde yetkili mercilerle paylaşılabilir.',
                ]],
                ['title' => '10. İnceleme ve yaptırımlar', 'paragraphs' => [
                    'İhlalin niteliğine göre içerik kaldırma, uyarı, mesajlaşmayı geçici kısıtlama, hesabı geçici veya süresiz askıya alma, puan iptali ve ilgili işlemleri incelemeye alma uygulanabilir.',
                    'Otomatik risk sinyalleri tek başına nihai yaptırım olmayabilir. Uygun durumlarda yönetici incelemesi yapılır; kararın türü, gerekçesi, zamanı ve uygulayan yönetici denetim kaydına alınır.',
                    'Karara itirazını :contact_email adresine, hesabındaki e-posta adresini ve olay bilgisini belirterek iletebilirsin. Güvenlik gereği kimlik doğrulaması istenebilir.',
                ]],
                ['title' => '11. Kullanıcı içeriği ve fikrî haklar', 'paragraphs' => [
                    'İlan, fotoğraf, açıklama, yorum ve mesajlarının hukuka uygunluğundan sen sorumlusun. Başkasının telif, marka, kişilik veya gizlilik hakkını ihlal eden içerik yükleyemezsin.',
                    'İçeriğin üzerindeki hakların sende kalır. İçeriği platformda barındırmamız, teknik olarak çoğaltmamız, uygun boyuta getirmemiz, ilgili kullanıcılara göstermemiz ve hizmeti tanıtmamız için, yalnızca hizmetin işletilmesiyle sınırlı, bedelsiz ve geri alınabilir bir kullanım izni verirsin.',
                    'Döngü adı, tasarımı, yazılımı, veri tabanı düzeni ve platforma ait diğer unsurlar izin olmadan kopyalanamaz veya ticari olarak kullanılamaz. DOA ve diğer üçüncü taraf adları, logoları ve işaretleri kendi hak sahiplerine aittir; platformdaki açıklayıcı kullanım Döngü’ye bu işaretler üzerinde hak veya resmî bağlantı kazandırmaz.',
                ]],
                ['title' => '12. Reklamlar, duyurular ve ücretler', 'paragraphs' => [
                    'Platformda açıkça reklam veya sponsorlu içerik olarak ayrıştırılmış alanlar bulunabilir. Reklam verenlerin teklif ve iddialarından ilgili reklam veren sorumludur.',
                    'Pazarlama amaçlı telefon bildirimleri ayrı tercihe bağlıdır ve varsayılan olarak kapalıdır. İşlem, güvenlik ve hesap bildirimleri hizmetin çalışması için gönderilebilir. Tercihlerini profilindeki Bildirim Tercihleri bölümünden değiştirebilirsin.',
                    'Platform şu anda ilanlar arasında komisyon tahsil etmeyebilir. İleride ücretli özellik sunulursa kapsam, bedel ve onay koşulları işlemden önce ayrıca gösterilir; geriye dönük ücret uygulanmaz.',
                    'İlan yayınlama veya alım talebi gibi temel işlemler reklam izlemeye bağlanmaz. İsteğe bağlı ödüllü reklam teklifini kabul edersen, reklam tamamlandıktan ve gerekli doğrulama yapıldıktan sonra ilanın belirli süreyle öne çıkarılması gibi açıkça belirtilen bir uygulama içi avantaj kazanabilirsin. Avantaj para, alacak veya garanti edilen işlem sonucu değildir.',
                ]],
                ['title' => '13. Hizmet sürekliliği ve sorumluluğun sınırı', 'paragraphs' => [
                    'Bakım, güvenlik, internet kesintisi, üçüncü taraf hizmeti veya mücbir sebepler nedeniyle platform geçici olarak kullanılamayabilir. Makul teknik ve idari tedbirler alınsa da kesintisiz ve hatasız hizmet garanti edilmez.',
                    'Emredici hukuk saklı kalmak üzere platform, kullanıcıların birbirine karşı davranışından, ilan içeriğinden, teslimatın gerçekleşmemesinden, taşıma masrafından, DOA işaretli bir ambalajın üçüncü taraf iade makinesi veya kabul noktası tarafından reddedilmesinden ya da bu sistemlerin kural, bedel veya hizmet değişikliklerinden sorumlu değildir.',
                    'Hiçbir hüküm, ağır kusur veya kasttan doğan sorumluluğu ya da mevzuatla sınırlandırılması yasaklanan tüketici ve kişilik haklarını ortadan kaldırmaz.',
                ]],
                ['title' => '14. Hesabın kapatılması ve veriler', 'paragraphs' => [
                    'Hesabını uygulamadaki Profil > Hesabımı sil bölümünden doğrudan silebilirsin. Uygulamaya erişemiyorsan herkese açık /hesap-silme sayfasında e-posta doğrulama koduyla silme isteğini tamamlayabilirsin.',
                    'Hesap silindiğinde aktif ilan ve talepler sonlandırılır; ad, e-posta, telefon, avatar, kayıtlı adres, kesin teslimat konumu, cihaz bildirim anahtarı ve erişim anahtarları kaldırılır. Tamamlanmış işlem ile güvenlik ve ihlal kayıtları, diğer kullanıcıların işlem bütünlüğünü korumak ve sahtekârlığı önlemek için kimlikten arındırılmış biçimde sınırlı süre tutulabilir.',
                ]],
                ['title' => '15. Değişiklikler, uygulanacak hukuk ve iletişim', 'paragraphs' => [
                    'Şartlarda esaslı değişiklik olduğunda yeni sürüm ve yürürlük tarihi uygulamada yayımlanır; gerekli hâllerde yeniden kabul istenir. Geçmiş sürümlere ilişkin kabul kayıtları ispat ve denetim amacıyla korunur.',
                    'Bu şartlara Türkiye Cumhuriyeti hukuku uygulanır. Tüketici sıfatının bulunduğu hâllerde tüketici hakem heyeti ve tüketici mahkemesine ilişkin zorunlu yetki kuralları saklıdır.',
                    'Soru, itiraz ve destek talepleri için :contact_email adresinden iletişime geçebilirsin.',
                ]],
            ],
        ],
        'privacy' => [
            'title' => 'KVKK Aydınlatma Metni ve Gizlilik Politikası',
            'short_title' => 'Gizlilik Politikası',
            'version' => '2026-08-05.1',
            'effective_date' => '4 Ağustos 2026',
            'summary' => 'Bu metin, bağımsız Döngü platformunun hangi kişisel verileri hangi amaç ve hukuki sebeplerle işlediğini, kimlerle paylaşabileceğini, saklama yaklaşımını ve KVKK kapsamındaki haklarını açıklar.',
            'sections' => [
                ['title' => '1. KVKK aydınlatması: veri sorumlusu ve iletişim', 'paragraphs' => [
                    '6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında kişisel verilerin işleme amaç ve vasıtalarını belirleyen veri sorumlusu, Döngü platformunu işleten :operator_name tarafıdır. Döngü; Türkiye Çevre Ajansı, Depozito Yönetim Sistemi ve DOA’dan ayrı ve bağımsız bir veri sorumlusudur.',
                    'Kişisel verilerinle ilgili sorularını ve KVKK kapsamındaki başvurularını :contact_email adresine iletebilirsin. Veri sorumlusunun tam yasal unvanı ile tebligata elverişli adresi canlı hizmete açılmadan önce bu metinde ve uygulamanın iletişim alanında yayımlanacaktır.',
                ]],
                ['title' => '2. İşlediğimiz kişisel veri kategorileri', 'paragraphs' => [
                    'Kimlik ve hesap: ad-soyad/görünen ad, isteğe bağlı profil fotoğrafı, hesap numarası, üyelik tarihi, hesap durumu, doğrulama ve kabul kayıtları. Profil fotoğrafı herkese açık profil, ilan ve mesajlaşma alanlarında; sıralama görünürlüğü açıksa Döngü sıralamasında gösterilir; kimlik doğrulaması veya biyometrik tanıma amacıyla kullanılmaz.',
                    'İletişim: e-posta adresi ve kullanıcı tarafından eklenmesi hâlinde telefon numarası.',
                    'Konum ve teslimat: izin verdiğinde cihazın ön plan konumu; kaydettiğin adres adı, açık adres, koordinatlar, yaklaşık bölge ve teslimat notları. Kesin koordinat ve açık adres veritabanında şifreli tutulur; herkese açık ilanlarda yaklaşık konum gösterilir.',
                    'İlan ve işlem: malzeme türü, adet, fiyat, açıklama, fotoğraf, favori, alım talebi, rezervasyon, teslimat kodu durumu, tamamlanma, iptal ve işlem geçmişi.',
                    'İletişim ve topluluk: kullanıcılar arası mesajlar, okundu bilgisi, engelleme, değerlendirme, yorum, puan, rozet, sıralama gizlilik tercihi, ilan/kullanıcı/mesaj bildirimleri.',
                    'Cihaz ve teknik: IP adresi, kullanıcı aracısı, oturum ve erişim anahtarları, cihaz/platform bilgisi, push tokenı, hata ve güvenlik kayıtları.',
                    'Reklam ve kullanım: reklam gösterimi/tıklaması, oturum içi reklam sıklığı, ödüllü reklamın tamamlanma ve doğrulama kaydı, reklam rıza tercihi; Google Mobile Ads tarafından işlenebilecek reklam kimliği, cihaz/uygulama bilgisi, IP adresi ve reklam etkileşimi verileri.',
                ]],
                ['title' => '3. İşleme amaçlarımız', 'paragraphs' => [
                    'Hesap oluşturmak, e-posta doğrulamak, oturum güvenliğini sağlamak ve profilini yönetmek.',
                    'Yakındaki ilanları mesafeye göre göstermek, teslimat adresi kaydetmek, ilan ve alım talebi akışlarını yürütmek.',
                    'Mesajlaşma, bildirim, rezervasyon, teslimat kodu, değerlendirme, favori, sıralama, puan ve rozet özelliklerini sağlamak.',
                    'Spam, bot, dolandırıcılık, taciz, sahte teslimat ve puan manipülasyonunu tespit etmek; bildirimleri incelemek ve yaptırım uygulamak.',
                    'Hizmet performansını ölçmek, hataları gidermek, kapasite planlamak, reklam sıklığını sınırlamak ve toplulaştırılmış istatistik üretmek.',
                    'Hukuki yükümlülüklere uymak, haklarımızı kurmak/kullanmak/korumak ve yetkili makam taleplerini yerine getirmek.',
                ]],
                ['title' => '4. Hukuki sebepler', 'paragraphs' => [
                    'Veriler; KVKK’nın 5. maddesindeki sözleşmenin kurulması veya ifası için gerekli olma, hukuki yükümlülüğün yerine getirilmesi, bir hakkın tesisi/kullanılması/korunması ve temel haklarına zarar vermemek kaydıyla meşru menfaat sebeplerine dayanılarak işlenir.',
                    'Bu KVKK aydınlatması bir açık rıza talebi değildir. Açık rıza gerektiren bir işlem varsa rıza; kullanım şartlarının kabulünden ve aydınlatma metninin okunduğunun teyidinden ayrı, belirli ve bilgilendirilmiş şekilde alınır. Rıza vermemek, hizmetin rızaya bağlı olmayan bölümlerini kullanmana engel olmaz.',
                    'Konum ve telefon bildirimi izinleri işletim sistemi üzerinden senin kontrolündedir. Konum izni uygulama açılışında zorunlu tutulmaz; yakındaki ilan veya adres özelliğini istediğinde talep edilir.',
                    'Pazarlama amaçlı push bildirimleri varsayılan olarak kapalıdır ve ayrı tercihinle açılır. Bu tercihi her zaman geri alabilirsin.',
                    'Üçüncü taraf reklam gösterimi gerekli olduğunda Google Mobile Ads rıza aracı bölge ve mevzuat koşullarına göre bilgilendirme veya tercih ekranı gösterebilir. Rıza gerektiren kişiselleştirme kabul edilmezse kişiselleştirilmemiş reklam istenir; reklam izlememek temel ilan, talep ve mesajlaşma işlevlerini engellemez.',
                ]],
                ['title' => '5. Konum verisinin özel korunması', 'paragraphs' => [
                    'Döngü arka planda sürekli konum takibi yapmaz. Uygulama açıkken ve sen ilgili özelliği kullandığında konum alınır.',
                    'Yakındaki ilan sorgusunda cihaz koordinatın, seçtiğin yarıçap içindeki kayıtları hesaplamak için sunucuya iletilebilir. Herkese açık sonuçlarda ilan sahibinin kesin konumu yerine yuvarlatılmış yaklaşık koordinat ve bölge bilgisi kullanılır.',
                    'Kayıtlı adreslerin ile ilan teslimatının kesin adres ve koordinatları şifreli alanlarda saklanır. Bunlara yalnızca hesabın, yetkili işlem tarafları ve görev gereği yetkilendirilmiş yöneticiler erişebilir.',
                ]],
                ['title' => '6. Mesajlar, bildirimler ve denetim', 'paragraphs' => [
                    'Mesajlar işlem iletişimini sağlamak, okunma durumunu göstermek, şikâyetleri incelemek ve güvenliği korumak için saklanır. Bir sohbeti listenden kaldırman, kaydı yalnızca senin görünümünden gizleyebilir; hukuki veya güvenlik saklama süresini hemen sona erdirmez.',
                    'Bir mesaj bildirildiğinde bildirilen mesaj, yakın konuşma bağlamı, bildiren ve bildirilen hesap ile yönetici kararı yetkili güvenlik ekibince incelenebilir. İhlalli mesaj kullanıcı ekranlarında kaldırılmış olarak gösterilebilir.',
                    'Uygulama içi bildirim kayıtları hesabına özeldir. Telefon push bildirimi için cihaz tokenı kullanılır; işletim sistemi önizleme ayarlarına bağlı olarak bildirim içeriği kilit ekranında görünebilir.',
                ]],
                ['title' => '7. Verilerin aktarıldığı taraflar', 'paragraphs' => [
                    'Veriler yalnızca amaçla sınırlı olarak barındırma/sunucu, e-posta gönderimi, push bildirimi, harita-konum, uygulama mağazası, hata izleme ve güvenlik hizmeti sağlayıcılarıyla paylaşılabilir.',
                    'İşlemin gerektirdiği profil, yaklaşık bölge, ilan, değerlendirme ve teslimat bilgileri diğer kullanıcılarla; kesin teslimat bilgileri ise yalnızca yetkili işlem taraflarıyla paylaşılır. Döngü’yü kullanman, kişisel verilerinin DOA, Türkiye Çevre Ajansı, Depozito Yönetim Sistemi veya iade makinesi işletmecileriyle otomatik olarak paylaşılması anlamına gelmez; bu taraflara veri aktarımı ancak ayrıca açıklanan belirli bir amaç ve KVKK’ya uygun hukuki sebep bulunması hâlinde yapılabilir.',
                    'Hukuki zorunluluk veya usulüne uygun talep hâlinde mahkemeler, kolluk, düzenleyici kurumlar ve diğer yetkili kamu mercileriyle paylaşım yapılabilir.',
                    'Reklam verenlere kural olarak toplulaştırılmış gösterim ve tıklama istatistiği sağlanır. Açıkça bilgilendirilmeden reklam verene kimlik, mesaj veya kesin konum bilgisi verilmez.',
                    'Kurumsal kampanya bulunmadığında reklam sunumu, ölçümü, sahteciliğin önlenmesi ve ödüllü reklam doğrulaması için Google Mobile Ads/AdMob hizmeti kullanılabilir. Bu sağlayıcı kendi hizmet koşulları ve gizlilik politikası uyarınca cihaz, reklam kimliği, IP ve etkileşim verilerini işleyebilir; mesajlar ve kesin teslimat adresi reklam sağlayıcısına gönderilmez.',
                ]],
                ['title' => '8. Yurt dışına aktarım', 'paragraphs' => [
                    'Push bildirimleri, mobil işletim sistemleri, uygulama mağazaları, Google Mobile Ads veya seçilen teknik hizmet sağlayıcıların altyapısı Türkiye dışında bulunabilir. Böyle bir aktarım yapılmadan önce KVKK’nın yurt dışı aktarıma ilişkin güncel 9. maddesine uygun yeterlilik kararı, uygun güvence veya mevzuatta izin verilen istisnai aktarım mekanizması değerlendirilir.',
                    'Kullanılan sağlayıcılar ve aktarım mekanizması canlı sistem mimarisi kesinleştiğinde bu metinde güncellenir. Zorunlu olmayan yurt dışı aktarım açık rızaya dayanıyorsa rızanı geri çekebilirsin.',
                ]],
                ['title' => '9. Saklama ve silme', 'paragraphs' => [
                    'Aktif hesap verileri hesabın sürdüğü müddetçe; işlem, güvenlik ve denetim kayıtları ilgili amaç ile yasal zamanaşımı süreleri boyunca saklanır. Süre sona erdiğinde veriler silinir, yok edilir veya anonimleştirilir.',
                    'Mevcut teknik politikada giriş kodları sürelerinin dolmasından sonra 7 gün; günlük uygulama logları 14 gün; iptal edilmiş push tokenları 30 gün; 120 gün kullanılmayan push tokenları; reklam gösterim/tıklama kayıtları 90 gün sonunda temizlenmek üzere planlanmıştır.',
                    'İlanlar normalde 30 gün yayımlanır. Süresi dolmuş ilanlar 90 gün, kullanıcı tarafından silinmiş ilanlar 30 gün sonra ilişkili dosyalarıyla kalıcı olarak temizlenmek üzere planlanmıştır. Açık işlem, bildirim, güvenlik incelemesi veya hukuki yükümlülük varsa ilgili kayıt daha uzun süre korunabilir.',
                    'Saklama süreleri mevzuat, teknik gereklilik veya hizmet değişikliğine göre güncellenebilir. Yeni süre, amacı için gerekli olandan uzun belirlenmez.',
                ]],
                ['title' => '10. Güvenlik tedbirleri', 'paragraphs' => [
                    'Erişim yetkilendirmesi, tek kullanımlık doğrulama kodu, hız sınırları, işlem kayıtları, veritabanı indeksleri, şifreli kesin konum/adres alanları, kullanıcı engelleme ve yönetici denetim kayıtları gibi teknik ve idari tedbirler uygulanır.',
                    'Hiçbir internet hizmeti sıfır risk garanti edemez. Şüpheli hesap erişimini, veri sızıntısı ihtimalini veya güvenlik açığını :contact_email adresine bildirmeni isteriz.',
                ]],
                ['title' => '11. KVKK kapsamındaki hakların', 'paragraphs' => [
                    'KVKK’nın 11. maddesi kapsamında kişisel verilerinin işlenip işlenmediğini öğrenme; işlenmişse bilgi isteme; işleme amacını ve amaca uygun kullanılıp kullanılmadığını öğrenme; yurt içinde veya yurt dışında aktarılan üçüncü kişileri bilme haklarına sahipsin.',
                    'Eksik veya yanlış işlenen verilerin düzeltilmesini; şartları oluşmuşsa silinmesini veya yok edilmesini; düzeltme ve silme işlemlerinin verinin aktarıldığı üçüncü kişilere bildirilmesini isteyebilirsin.',
                    'Münhasıran otomatik sistemlerle analiz sonucu aleyhine bir sonucun ortaya çıkmasına itiraz edebilir ve kanuna aykırı işleme nedeniyle zarara uğrarsan giderilmesini talep edebilirsin.',
                    'Başvurunda ad-soyad, hesabındaki e-posta, talebin ve kimliğini doğrulamaya yeterli bilgiyi belirtmelisin. Başvurular kural olarak en geç 30 gün içinde ücretsiz sonuçlandırılır; mevzuatın izin verdiği maliyet doğarsa ilan edilen tarife uygulanabilir.',
                ]],
                ['title' => '12. Tercihler, hesap silme ve değişiklikler', 'paragraphs' => [
                    'Bildirim ve sıralama gizlilik tercihlerini uygulama içinden değiştirebilirsin. İşletim sistemi konum ve push izinlerini cihaz ayarlarından kapatabilirsin.',
                    'Hesabını uygulamadaki Profil > Hesabımı sil bölümünden veya uygulamaya erişemiyorsan /hesap-silme web sayfasındaki e-posta doğrulama akışından silebilirsin. Silme işlemi aktif ilan ve talepleri kapatır, oturumları sonlandırır ve kişisel verileri kaldırır.',
                    'Bu metin değiştiğinde sürüm ve yürürlük tarihi güncellenir. İşleme amacı veya hukuki sebepte esaslı değişiklik varsa uygulama içinde ayrıca bilgilendirme yapılır; gerekiyorsa yeni onay alınır.',
                ]],
            ],
        ],
    ],
];
