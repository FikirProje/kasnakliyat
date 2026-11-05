# TofiDesk Global Platform — satış teslim bilgisi

## Hazır dosya

Sektör bağımsız satış paketi komutla üretilir:

```sh
python3 bin/package.py --profile marketplace --output=transport-cms-marketplace.zip
```

Bu profil müşteri verisi, `.env`, veritabanı, medya, oturum, yedek, Kas Nakliyat görselleri ve marka referanslarını dışarıda bırakır veya nötrleştirir. Kas Nakliyat demo/canlı sürümü için `--profile deployment` kullanılır.

## Doğrulanmış özellikler

- 14 ziyaretçi dili, RTL (Arapça/Farsça), seçilebilir 14 dilli installer
- 195 ülke pazar profil matrisi
- teklif/randevu, yönetici rolleri, içerik, galeri, medya ve AI iş kuyruğu
- SMTP gönderim/yanıt kuyruğu ve TLS doğrulamalı, isteğe bağlı IMAP toplama
- sitemap, robots, canonical, hreflang, Open Graph, X kartları, Schema.org
- Google, Bing, Yandex, Pinterest ve Facebook alan doğrulama meta noktaları
- yedek/geri yükleme, CSRF, oturum, yükleme ve medya erişim kontrolleri
- tema renkleri ve güvenli özel CSS ayarları

## Test özeti

- Ana HTTP regresyonu: 17 grup geçti
- Installer güvenliği: 8 grup geçti
- SMTP, galeri, yedek/geri yükleme, medya/kuyruk entegrasyonları: geçti
- 195 ülke profil güvenlik matrisi: geçti
- Generic ZIP bütünlüğü ve müşteri tanımlayıcısı taraması: geçti

## Satış öncesi son dış adımlar

CodeCanyon/Envato mağaza hesabı, vergi/ödeme profili, ürün kategori seçimi, demo URL’si ve ekran görüntüsü yükleme işlemleri satıcı hesabında yapılır. Canlı alan adı olmadan Google PageSpeed, Search Console veya mağaza demo URL kontrolü başlatılamaz. Bu dış hesap/doğrulama adımları teknik paketin içine konulamaz.

Detaylar: `docs/CODECANYON_RELEASE.md`, `docs/GO_LIVE.md`, `docs/MARKETPLACE_SCAN.md`.
