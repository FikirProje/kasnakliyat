# Kaş Nakliyat

Bağımsız, sunucu tarafında HTML üreten PHP 8.2+ nakliyat sitesi ve yönetim sistemi. TofiDesk veya başka projeye bağımlı değildir. Composer/Node derlemesi, haricî CDN veya ayrı veritabanı sunucusu gerektirmez.

## Yerel çalıştırma

Gerekenler: PHP 8.2+ (`pdo_sqlite`, `mbstring`, `fileinfo`, `curl`, `openssl`, `gd`, `zlib`); testler ve çevrimdışı geri yükleme için Python 3.12+. GD WebP desteği gerekir; AVIF çıktısı destek varsa üretilir. Yedekleme için haricî komut çalıştırma izni gerekmez.

```sh
cd /workspace/kasnakliyat
# Bu bulut makinesinde PHP kurulumu:
sh bin/install-cloud-runtime.sh
export PATH="/workspace/.runtime/bin:$PATH"
cp -n .env.example .env
php bin/console.php migrate
php bin/console.php check
php bin/console.php user:create --email=yonetici@example.com --name=Yönetici --role=admin
# Parola terminalden, gizli girişle alınır. Örnek adresi kendi adresinizle değiştirin.
php -S 127.0.0.1:8080 -t public public/index.php
```

İlk yönetici otomatik oluşturulmaz, varsayılan parola yoktur. `user:create` komutunu kendi e-postanızla çalıştırın; parolayı komut satırı argümanına veya Git'e koymayın. İki aşamalı giriş: `php bin/console.php user:2fa --email=...`; doğrulayıcı uygulamanın geçerli kodu doğrulanınca etkinleşir. Parola yenileme: `user:password --email=...`.

Sunucuya ZIP yükleyerek tarayıcıdan ilk kurulum yapmak için [KURULUM.txt](KURULUM.txt) adımlarını izleyin. `/install` modern token’sız açılır, ilk hesap oluşturulduğunda otomatik kapanır. Yönetici adresi kurulum ekranında belirlenir; varsayılan parola yoktur. Paket üretimi: `python3 bin/package.py --output=/hedef/kasnakliyat.zip`. Canlı veritabanı, sırlar ve oturumlar pakete dahil edilmez.

Bu görev zaten yalıtılmıştır. Mevcut checkout'u kullanın; kullanıcı özellikle istemedikçe Git worktree oluşturmayın.

## Başka bilgisayarda devam

Güncel kaynak kodu GitHub'dan alın:

```sh
git clone https://github.com/FikirProje/kasnakliyat.git
cd kasnakliyat
git pull origin main
```

Yerel önizleme için XAMPP/Apache yerine doğrudan PHP built-in sunucusu da
kullanılabilir. Kas uygulaması `127.0.0.1:8080`, Product Center ise
`127.0.0.1:8090` üzerinde çalışır; bu adresler yalnızca çalıştıran bilgisayarda
görünür. Hazır dağıtım paketleri `outputs/` altında ayrıdır:

- `product-tofidesk-app-deployment.zip`: müşteri Kas Nakliyat uygulaması/demo paketi.
- `product-tofidesk-marketplace.zip`: genel sektörler için marketplace uygulaması.
- `product-tofidesk-license-center.zip`: Product Center katalog, satış ve lisans merkezi.

Plesk/FileZilla yükleme eşlemesi için
[`docs/FILEZILLA_FINAL_UPLOAD.md`](docs/FILEZILLA_FINAL_UPLOAD.md) dosyasını
izleyin. Paketler birbirinin içine birleştirilmemelidir.

## Kullanım

- `/tr/`: gerçek teklif formu içeren One Page ana sayfa. Türkçe, İngilizce, Almanca, Fransızca, İspanyolca, İtalyanca, Portekizce, Hollandaca, Rusça, Arapça, Çince, Japonca, Korece ve Farsça arayüz. Arapça/Farsça RTL.
- `/{dil}/hizmetler/yuk-tasimaciligi/`, `/{dil}/blog/`, `/{dil}/galeri/`, `/{dil}/teklif-al/`, `/{dil}/randevu/`, `/{dil}/gizlilik/`.
- Blog yazıları `/{dil}/blog/{slug}/`, ek hizmetler `/{dil}/hizmetler/{slug}/`, ek sayfalar `/{dil}/sayfa/{slug}/`.
- İlk kurulumda, yönetim panelinden düzenlenebilen üç Türkçe ve İngilizce örnek rehber yazısı eklenir: yük hazırlığı, teklif bilgileri ve randevu değerlendirmesi. Bunlar işletme başarısı veya müşteri referansı iddia etmez; içerik stüdyosundan değiştirilebilir ya da taslağa alınabilir.
- `/admin/login`: yönetim merkezi. `admin` tüm modülleri; `editor` içerik, medya, SEO ve AI işlerini; `operator` müşteri talepleri ve randevuları yönetir.
- Teklifler/randevular önce **Yeni talep** olarak kaydedilir. İdareci fiyat/tarih önerebilir, randevuyu kesinleştirebilir veya reddedebilir. Aynı tarih ve saatte iki randevu kesinleşmez. Bu ilk sürüm tek eşzamanlı randevu kapasitesi kullanır; araç/ekip bazlı süre ve kapasite planlaması yoktur.
- İçerik stüdyosunda içerik türü/kaynak dili oluşturun, her dilde metin/slug/SEO/kapak/yayın durumunu ayrı kaydedin. Diğer dillere metin kopyalanmaz. Yayın tarihleri İstanbul saatinde girilir, UTC saklanır. Yayın zamanı gelen içerik sonraki istekte görünür.
- AI işleri sonuçları ayrı tutulur. İş tamamlanınca editör çıktıyı inceler, açıkça **Taslağa al** ile uygular ve dil editöründe yayınlar. Kaynak güncellemesinde diğer dillerin metinleri korunur; kaynak sürümü uyarısı gösterilir. Eşzamanlı eski editör kaydı reddedilir.
- Fotoğraflar 8 MB/16 megapiksel sınırı ve gerçek MIME/decode kontrolünden geçer. Orijinal, WebP ve desteklenirse AVIF saklanır. Müşteri ekleri herkese açık medya kütüphanesinden ayrılır; yalnız yönetici/operatör erişir. Başlık/alt metin/açıklama her dilde ayrı tutulur.
- Galeri, medya kütüphanesine yüklenen gerçek araç, ofis ve çalışma görsellerinden yönetilir. Editör/yönetici her görseli ayrı ayrı taslakta tutar veya yayımlar, yayın sırasını belirler ve her dil için alt metin/açıklama girer. Ziyaretçi galerisi sayfa sayfa AJAX ile ek görseller yükler; başlangıçta örnek veya yapay iş fotoğrafı bulunmaz.
- Müşteri bildirimleri SMTP kuyruğuna alınır. Başarısız işler ve denemeler görünür, açık yeniden deneme yeni kayıt oluşturur. Kuyruk çalıştırılmadan e-posta gönderilmiş sayılmaz.

## AI ve e-posta

`.env` veya sunucu ortamına `AI_API_KEY` ekleyin. Anahtar tarayıcıya veya ayarlar tablosuna yazılmaz. Yönetim panelinde sağlayıcı, model ve içerik yönergeleri seçilebilir. `write`, `improve`, `localize` ve makaleye özgü kapak üretimi desteklenir. Metin için Chat Completions JSON çıktısı, görsel için `gpt-image-1` kullanılır. Ücretli API çağrıları yalnız iş işlenirken yapılır; anahtar yoksa başarısız iş kaydı oluşur. Çıktı ve sağlayıcının kullanım bilgisi korunur; hesaplanan maliyet bilinmiyorsa sıfır gibi gösterilmez. Bağlantı/hız sınırı hataları yayındaki metinleri silmez.

OpenAI uyumlu servisler için yönetim panelinden `compatible` sağlayıcısını seçin ve sunucu ortamında `AI_BASE_URL` tanımlayın. Örnekler: `https://openrouter.ai/api`, `https://api.together.xyz` veya yerel Ollama için `http://127.0.0.1:11434/v1`. Gerekirse `AI_API_KEY` sağlayıcının anahtarıdır; yerel anahtarsız servislerde boş bırakılabilir. `AI_BASE_URL` yalnızca güvenilen bir yönetici tarafından yapılandırılmalı ve üretimde HTTPS tercih edilmelidir.

Kök adrese (`/`) gelen ziyaretçiler için dil önceliği güvenilir proxy’nin `CF-IPCountry` veya `GEOIP_COUNTRY_CODE` başlığı, ardından tarayıcının `Accept-Language` tercihi ve son olarak Türkçe varsayılandır. Kullanıcı dil seçicisiyle bu otomatik tercihi her zaman değiştirebilir.

Sunucuda `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` sağlayın. Parola dışındaki alanlar yönetim panelinde de ayarlanabilir; ortam değişkenleri önceliklidir. Uzak SMTP için doğrulanmış STARTTLS veya 465/TLS zorunludur. Şifresiz yerel relay yalnız `SMTP_ALLOW_INSECURE_LOCAL=1` ile loopback üzerinde kullanılabilir. Gönderimler UTF-8 düz metindir.

SMS/WhatsApp için `.env.example` içindeki sağlayıcı değişkenlerini sunucu ortamına ekleyin ve Yönetim > Entegrasyonlar bölümünden sağlayıcıyı seçin. NetGSM, iletiMerkezi, Twilio SMS/WhatsApp ve Meta WhatsApp Cloud API kuyruk işçisiyle gönderilir. Anahtarlar eksikse bildirim `failed` olarak kaydedilir; canlı gönderim yapılmaz.

```sh
php bin/worker.php all  # veya ai / mail / notifications; tek sefer, sınırlı iş grubu
```

Üretimde süreç yöneticisi veya zamanlayıcıyla her dakika çalıştırın. Aynı anda çalışan işçiler işi atomik sahiplenir. Kesilen işlerin 10 dakikalık kilidi kurtarılır. Otomatik sınırsız tekrar yoktur; başarısız işleri panelden inceleyin.

Bulut ağında `api.openai.com` için HTTPS izni ve API anahtarı, SMTP/SMS/WhatsApp için sağlayıcının alan adı/portuna desteklenen HTTPS erişimi gerekir. Ortamın proxy/TLS ayarlarını koruyun. Gerçek sağlayıcı bilgileri sağlanmadığından canlı gönderim bu pakette denenmez; test ve hata koruma akışları yerel olarak doğrulanır.

## Yayın ve SEO

`APP_URL` gerçek HTTPS kök alan adınız olmalı; yol eklemeyin. İlk kurulumda boş veya `localhost` değeri varsa uygulama, ziyaret edilen güvenli adresi otomatik algılar; kurulum tamamlanmadan önce gerçek alan adını `.env` içinde sabitleyin. `APP_ENV=production` ile indekslemeyi açın. Geliştirmede robots ve `X-Robots-Tag` indekslemeyi kapatır. Sitemap, canonical, karşılıklı hreflang/x-default, OG/X ve sayfa türüne uygun JSON-LD üretilir; taslaklar, geleceğe planlı içerikler ve eksik dil sürümleri eklenmez. Slug değişiklikleri 301 ile yönlendirilir. Yerel işletme şeması ancak gerçek adres ve telefon girilince eklenir; sahte puan/yorum yoktur.

İşletme/telefon/e-posta/WhatsApp, gerçek hizmet kapsamı, görseller ve 14 dilin KVKK/veri sorumlusu alanlarını yönetim panelinden tamamlayın. Mevcut gizlilik metinleri açıkça başlangıç taslağıdır; hukuk veya mevzuat uygunluğu onayı değildir. Vergi levhası/kimlik bilgisi yüklenmemiştir ve kamuya yayımlanmaz. Doğrulanmış fotoğraf, yetki belgesi veya işletme geçmişi sağlanmadığından bunlara ilişkin iddia eklenmedi. 14 dil arayüz çevirileri var; hedef pazar araştırması ve ana dili konuşan editör kontrolü ayrı canlıya hazırlık işidir.

Google Search Console, GA4 ve Google Business Profile hesap bağlama/doğrulaması yapılmadı. Arama sıralaması veya zengin sonuç garantisi verilmez. Otomatik analitik/çerez yüklenmez.

## Uzak masaüstü / VS Code ile geliştirme

Uzak Windows masaüstü, Linux sunucu veya VS Code Remote SSH kullanırken aynı Git deposunu kullanın. Tek seferlik akış:

```sh
git clone https://github.com/FikirProje/kasnakliyat.git
cd kasnakliyat
cp .env.example .env
# APP_URL ve APP_ENV değerlerini yalnız bu sunucuda düzenleyin; installer ilk yöneticiyle birlikte otomatik kilitlenir.
php bin/console.php migrate
php bin/console.php check
```

Windows için [VS Code](https://code.visualstudio.com/) ve **Remote - SSH** eklentisini kurun; uzak bilgisayarda projeyi açın. Uzak Linux sunucuda PHP, SQLite, GD/WebP, cURL, OpenSSL, mbstring, fileinfo ve zlib etkin olmalıdır. Yerel geliştirme için `php -S 127.0.0.1:8080 -t public public/index.php` yeterlidir; canlı sistemde `public/` web kökü olan HTTPS web sunucusu kullanın.

Her çalışma başlangıcında `git pull --ff-only` çalıştırın; değişiklikten önce `git status`, doğrulamadan sonra testler ve `git push origin main` kullanın. `.env`, `storage/`, yedekler ve API/SMTP sırları Git’e eklenmez. Aynı veritabanını iki ayrı bilgisayarda eşzamanlı düzenlemeyin; ortak canlı veritabanı gerekiyorsa önceden yedek alın ve tek bakım penceresi belirleyin.

Üretimde PHP geliştirme sunucusunu kullanmayın. Web kökü **yalnız `public/`** olmalı. SQLite ve medya web kökünün dışındaki `storage/` dizininde kalır. Örnek Nginx yapılandırması: `docs/nginx.conf`. PHP süreç sahibine `storage/` yazma, uygulama koduna okuma yetkisi verin. `.env` dosyasını yalnız uygulama sahibinin okuyabildiğinden emin olun. HTTPS sonlandırması ve FPM ayrı sunucu kurulumu gerektirir.

## Yedekleme ve geri yükleme

```sh
php bin/console.php backup
# Günlük zamanlayıcıyla çalıştırın; yedekleri ayrıca güvenli haricî alana taşıyın.
# Web/işçi süreçlerini durdurduktan sonra, bakımda:
python3 bin/restore.py --archive=/tam/yol/yedek.tar.gz --maintenance-confirmed
```

Yedek SQLite'in tutarlı anlık görüntüsünü, medya orijinallerini/türevlerini ve SHA-256 manifestini içerir; `.env` ve API/SMTP sırları dahil edilmez. Geri yükleme yol/link/sağlama toplamı ve SQLite bütünlük kontrolleri yapar, mevcut verinin kurtarma kopyasını korur ve kullanıcı ayar/sır dosyalarına dokunmaz. Yedekler müşteri verisi ve kullanıcı parola özetleri içerir; erişimi kısıtlı tutun ve saklama süresini işletmenize göre belirleyin. Kod sürümlerini Git ile yönetin; veri yedeği uygulama kodu dağıtımının yerine geçmez.

İki aşamalı giriş kullanılıyorsa TOTP sırları da korumalı veritabanı yedeğindedir. Özel veri yolları kullanıyorsanız geri yüklemeye `--storage=/veri/dizini --database=/veri/app.sqlite` argümanlarını açıkça verin; bu araç `.env` dosyasını okumaz.

## Testler

```sh
PHP_BIN=php python3 tests/smoke.py
php tests/integrations.php
PHP_BIN=php python3 tests/smtp_smoke.py
PHP_BIN=php python3 tests/installer_smoke.py
PHP_BIN=php python3 tests/gallery_smoke.py
PHP_BIN=php python3 tests/restore_smoke.py
```

Testler geçici veritabanı/depolama ve ayrı yerel sunucu kullanır; gerçek müşteri verisi veya dış AI/SMTP sağlayıcısı kullanmaz. Kapsam ve sonuçları `tests/README.md` açıklar. Bulutun komut sandbox'ında yerel soket açılması için desteklenen ağ izni gerekebilir; doğrulamayı veya TLS kontrolünü kapatmayın.

Marka PNG çıktılarını yeniden üretmek için `php bin/generate-brand.php`. Kamyon simgesi işletme sahibinin sağladığı SVG'dir. Rota çizimleri ve paylaşım şablonları bu projeye özgü kodla, hero görseli verilen referanslardan yapay zekâyla temsili olarak üretildi; görseldeki araç gerçek filo kanıtı olarak sunulmaz.
