# Marka varlıkları

Ana renkler: lacivert `#0D2E42`, turuncu `#FF9149`. Yardımcı Akdeniz tonu `#087F8C`; açık zemin `#F2F4F2`. Kamyon simgesi sahibin sağladığı `favicon.svg` temel alınarak korunmuştur.

- `public/assets/logo.svg`, `logo-light.svg`, `logo-monochrome.svg`, `logo-white.svg`: yatay, ölçeklenebilir marka.
- `favicon.svg`, `favicon-32.png`, `icon-192.png`, `apple-touch-icon.png`: tarayıcı ve uygulama simgeleri.
- `share-card.png`: 1200×630 paylaşım görseli.
- `social-square.png`: 1080×1080; `social-story.png`: 1080×1920 sosyal şablonlar.
- `hero.webp`, `hero-mobile.webp`: masaüstü ve mobil için optimize edilmiş temsili Kaş görseli. İşletmeye ait gerçek araç/ekip fotoğrafı olduğu iddia edilmez.

PNG marka seti `php bin/generate-brand.php` ile yeniden üretilir. Web sitesinde sistem fontları ve kendi sunucusundan yüklenen varlıklar kullanılır; haricî font/CDN gerekmez. Google Maps yalnız kullanıcı haritayı yükleme düğmesine bastığında açılır.
