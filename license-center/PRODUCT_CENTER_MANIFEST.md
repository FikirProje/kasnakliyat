# TofiDesk Product Center dağıtım manifesti

Bu klasör yalnızca `product.tofidesk.com` için kullanılır.

## Kök dosyalar

- `index.php` — ana giriş ve sürüm kontrolü
- `installer.php` — ilk yönetici kurulumu için rewrite bağımsız giriş
- `.htaccess` — `/install`, `/assets` ve güvenlik kuralları
- `.env.example` — özel sunucu ayarları şablonu
- `TOFIDESK_PRODUCT_CENTER.txt` — paket doğrulama işareti

## Uygulama

- `public/index.php` — satış vitrini, müşteri hesabı, sipariş, destek, tema ve lisans yönetimi
- `public/account_routes.php` — hesap tabloları, kayıt/giriş, şifre sıfırlama ve müşteri işlemleri
- `public/updates.php` — lisans kontrollü güncelleme kontrolü ve imzalı paket indirme API’si
- `public/release_admin.php` — sürüm, kurulum, olay ve revocation yönetimi
- `public/installer.php` — token’sız ilk admin kurulumu
- `public/.htaccess` — rewrite bağımsız public kuralları
- `public/assets/` — demo tema görselleri
- `bin/release.php` — imzalı release yayınlama aracı
- `bin/seed-first-party-theme.php` — ilk taraf data-only tema manifestini güvenli ZIP, SHA-256 ve imzalı theme release olarak yayınlama aracı
- `bin/package.py` — product center paketleme aracı

İlk tema release örneği:

```text
LICENSE_PACKAGE_SIGNING_KEY="sunucuda-özel-uzun-rastgele-anahtar" \
php bin/seed-first-party-theme.php --db=/var/lib/tofidesk/licenses.sqlite \
  --preview-video=https://cdn.example.com/nexa-business-preview.mp4
```

Video URL’si verilirse ürün kalite kapısına `video` varlığı eklenir. İmza
anahtarı yoksa komut yayın yapmaz; ürün yanlışlıkla satışa hazır görünmez.

Bu pakette `src/`, `templates/` veya `config/site.php` bulunmaz. Bunlar ana CMS/demo paketine aittir.
