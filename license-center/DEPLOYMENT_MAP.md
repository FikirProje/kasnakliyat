# TofiDesk dağıtım haritası

## 1. Satış ve lisans merkezi

`product.tofidesk.com` alan adının belge köküne bu paketin içeriği yüklenir.

```text
httpdocs/
├── index.php
├── installer.php
├── .htaccess
├── public/
│   ├── index.php
│   ├── account_routes.php
│   ├── updates.php
│   └── assets/
├── bin/
├── storage/
└── .env
```

`storage/` web dışı veya sunucu kurallarıyla engelli ve yazılabilir olmalıdır. `.env` yüklenir; gerçek sırlar ZIP içine konulmaz.

## 2. Müşteri uygulaması

`kasnakliyat.com` veya müşterinin alan adına yalnızca uygulama dağıtım paketi yüklenir. Satış merkezi dosyaları uygulama paketine karıştırılmaz. Uygulama, lisans API'sine HTTPS üzerinden ürün, alan adı ve kurulum kimliğiyle bağlanır.

## 3. Yayın akışı

1. Admin panelinde ürün/tema ve sürüm oluşturulur.
2. Tema vitrinden `theme-checkout` akışına alınır; müşteri hesabı, sipariş ve ürün adıyla ilişkilendirilir.
3. Doğrulanmış ödeme webhook’u siparişi paid yapar; fatura, lisans ve indirme kaydı oluşturur.
4. Paket SHA-256 ve imza manifestiyle hazırlanır.
5. Sürüm `stable` kanalında yayınlanır.
6. Kurulu uygulama lisans, domain, kurulum kimliği ve sürüm bilgisini göndererek güncelleme kontrolü yapar.
7. Uygulama paketi indirmeden önce imzayı ve SHA-256 değerini doğrular.
8. Güncelleme öncesi yedek alınır, bakım bayrağı açılır, migration uygulanır; hata olursa rollback yapılır.

## 4. Güvenlik sınırları

- Alan adı ve kurulum sinyalleri yalnızca lisans doğrulama amacıyla kullanılır.
- Merkezi servis geçici olarak erişilemezse kısa cache/grace period kullanılır; müşterinin kurulumu tek arızada kilitlenmez.
- Revocation yalnızca yetkili admin incelemesiyle yapılır ve olay kaydı bırakır.
- Kişisel veri yerine teknik kurulum kimliği, domain ve sürüm bilgisi tutulur.

## 5. Güncelleme ve lisans politikası

- Her lisansın domain kapasitesi aynı zamanda yeni kurulum koltuğu kapasitesi olarak uygulanır; mevcut yetkili kurulumlar kesilmez.
- Lisans doğrulaması başarılı olduğunda kısa süreli istemci önbelleği ve grace bilgisi döner. Uygulama merkezi servis kısa süreli erişilemezse bu güvenli önbelleği kullanır.
- Uygulama güncellemesi; imza, SHA-256, PHP sürümü, migration sürümü ve yönetici onayı olmadan uygulanmaz.
- Güncelleme öncesi yedek, bakım bayrağı ve başarısızlıkta rollback zorunludur.
- Şüpheli durumlar yalnızca lisans, domain, kurulum ve sürüm sinyallerinden oluşturulur; `/review-cases` kuyruğunda teknik, destek, sözleşme veya hukuki inceleme olarak tutulur.

## 6. Final dağıtım kontrolü

Canlıya almadan önce `tests/license_center_smoke.py`, `tests/update_security_smoke.php` ve `tests/release_audit.py` çalıştırılır. Gerçek ödeme anahtarları, SMTP bilgileri, lisans veritabanı ve müşteri kayıtları dağıtım arşivine dahil edilmez.
