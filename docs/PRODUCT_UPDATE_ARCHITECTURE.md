# TofiDesk ürün, lisans ve güncelleme mimarisi

Bu belge uygulama kodu, tema dağıtımı ve lisans merkezinin birbirine nasıl bağlanacağını tanımlar. Amaç her müşteride yeniden kurulum yapmak değil; veriyi koruyarak imzalı ve geri alınabilir sürüm geçişi sağlamaktır.

## Sistem sınırları

- `product.tofidesk.com`: ürün kataloğu, tema kataloğu, sürümler, müşteri hesabı, sipariş, indirme, lisans ve güncelleme API'si.
- Müşteri kurulumu: uygulama kodu, müşteri `.env` dosyası, SQLite veritabanı ve `storage/` verisi. Müşteri verisi lisans merkezine gönderilmez.
- Tema paketi: yalnızca doğrulanmış data-only payload; PHP çalıştırma, rastgele dosya yazma ve sunucu komutu yok.
- Güncelleme paketi: imzalı manifest, dosya hash'leri, hedef ürün, minimum PHP sürümü, minimum migration sürümü, changelog ve geri dönüş bilgisi.
- Paket akışı: admin önce imzalı sürüm kontrolü yapar; lisans/alan adı/kurulum doğrulamasından sonra paket ayrı bir geçici dosyaya indirilir ve SHA-256 özeti eşleşmeden uygulama dizinine dokunulmaz.

## Uygulama güncelleme akışı

1. Admin paneli lisans merkezine ürün, kurulu sürüm, PHP sürümü, migration sürümü ve kurulum kimliğiyle `check` isteği gönderir.
2. Merkez yalnızca aktif lisans ve izinli ürün için yayınlanmış daha yeni sürümü döndürür.
3. Yönetici güncellemeyi açıkça başlatır; otomatik sessiz kod değişikliği yapılmaz.
4. Kurulum bakım moduna geçer, SQLite yedeği ve dosya manifesti alır.
5. Paket HTTPS üzerinden indirilir; SHA-256 ve imza doğrulanmadan açılmaz.
6. Paket önce geçici release dizinine çıkarılır. `.env`, `storage/`, medya ve veritabanı release içine kopyalanmaz; mevcut veri yolları korunur.
7. Migration transaction içinde çalıştırılır. Başarısızlıkta eski release ve veritabanı yedeği geri yüklenir.
8. Health-check başarılıysa release atomik olarak etkinleştirilir; olay kaydı ve yeni sürüm bilgisi yazılır.
9. Lisans merkezi geçici olarak erişilemiyorsa son geçerli doğrulama ve grace period kullanılır; grace süresi dolmadan müşteri sitesi kapatılmaz.

## Lisans güvenliği

- Lisans anahtarı veritabanında düz metin tutulmaz; yalnız hash ve gösterim ipucu saklanır.
- Yanıtlar imzalı olur ve ürün, domain, kurulum, sürüm, süre ve yetenekleri kapsar.
- Aktivasyon ve kontrol uçları IP/anahtar/domain bazlı rate limit uygular.
- Domain ve kurulum bağları tekrar kullanım, ani domain değişimi, aşırı kurulum ve iptal durumlarını olay olarak kaydeder.
- Revocation anında zorunlu kapatma yerine önce kontrollü grace/review durumu kullanır; açıkça kötüye kullanım doğrulanırsa lisans kısıtlanır.
- Kurulum kimliği kişisel veri değildir; yine de saklama süresi, amaç, silme ve destek açıklaması belgelenir.
- Sunucu taraması, gizli dosya toplama, arka kapı veya uzaktan komut çalıştırma yoktur.

## Ürün merkezi ekranları

1. Ürünler ve planlar
2. Uygulama sürümleri ve changelog
3. İmzalı release paketleri ve hash manifestleri
4. Temalar, tema sürümleri, önizleme ve fiyat
5. Müşteriler, siparişler, faturalar ve indirmeler
6. Lisanslar, domainler, kurulumlar ve revocation
7. Güncelleme olayları, başarısız kontroller ve destek talepleri

## Veritabanı tabloları

Uygulama sürümleri için `products`, `application_releases`, `release_files`, `installations`, `update_events` ve `license_revocations` tabloları eklenir. Migrations additive olmalı; mevcut `licenses`, `domains`, `themes`, `theme_versions`, `buyers` ve `downloads` verileri silinmemelidir.

## Yayın politikası

- Sürüm önce staging/demo kurulumu olan `kasnakliyat.com` üzerinde denenir.
- Smoke, integration, installer, rollback, signature, rate-limit ve migration testleri geçmeden stable yapılmaz.
- Stable sürüm yayınlanınca müşteriler panelden kontrol eder; güncelleme yönetici onayıyla uygulanır.
- Her yeni sürümde tek ZIP üretilebilir; müşterilerde yeniden kurulum yapılmaz.
