# Entegrasyon doğrulaması

Python 3 standart kütüphanesi ve projeyi çalıştırabilen PHP ile:

```sh
PHP_BIN=php python3 tests/smoke.py
```

Bulut ortamındaki kurulu PHP için:

```sh
PHP_BIN=/workspace/.runtime/bin/php python3 tests/smoke.py
```

Test kendi geçici SQLite veritabanını, depolama dizinini, rastgele test parolasını ve boş yerel portta PHP sunucusunu oluşturur. Normal uygulama verilerine yazmaz. Sunucuyu kapatıp geçici dosyalarını siler. Yerel soket bağlantısına izin verilen bir ortam gerektirir.

Kontroller:

- 14 dilde ana sayfa, hizmet, blog, teklif, randevu ve gizlilik sayfaları; `lang`, RTL/LTR ve çeviri anahtarı eşitliği.
- XML sitemap, yayın/dil alternatifleri, geliştirme ortamının indeksleme engeli.
- Özel kaynak/ayar/veritabanı dosyalarının erişim dışında tutulması.
- CSRF, geçersiz tarih, onay eksikliği ve sahte görsel dosyasının reddi.
- Teklif ve randevuların benzersiz referansla, onay bekleyen durumda saklanması.
- Gerçek PNG yükleme, orijinal ve optimize görsellerin korunması; müşteri fotoğraflarının anonim erişime kapanması. Henüz talebe bağlanmamış müşteri medyası da özel kalır, editörün kütüphanesine girmez ve yetkili erişimde `no-store` döner. Kütüphane görseli yayınlanmış kapak olana kadar kamuya açılmaz.
- Yönetici, editör ve operasyon oturumları ve rol sınırları.
- Editör ekranından içerik oluşturma, taslak kaydetme, açık yayın onayı, dil metinlerinin korunması, eşzamanlı eski sürüm kaydının engellenmesi ve 301 yönlendirmesi.
- Aynı tarih/saatte iki randevunun kesinleştirilememesi ve hatalı onay e-postası kuyruğu oluşmaması.
- Tamamlanmış AI işinin otomatik yayın yapmaması; açık editör onayı ve sürüm kontrolüyle yalnızca taslağa uygulanması.
- AI/SMTP kimlik bilgileri eksik olduğunda hatanın saklanması ve mevcut içerik/e-postanın korunması. Toplu üretimde kapasite aşımı veya geçersiz dil varsa grubun kısmen kuyruğa alınmaması.
- Taslak ve ileri tarihe planlanan içeriklerin kamuya açılmaması; içerik metninin HTML olarak çalıştırılmaması. Yönlendirme döngülerinin durması, doğru türde yayınlanmış hedef gerekliliği ve taslağa dönen AI önerisine eski URL üzerinden erişilememesi.
- SQLite yedek bütünlüğü, medya dosyalarının SHA-256 kontrolü ve ortam/oturum dosyalarının arşive alınmaması.
- RFC 6238 TOTP referans vektörü ve istek sınırlama davranışı.

Her grup için `PASS`/`FAIL` ve sonunda sayılar yazılır. Hata olduğunda çıkış kodu sıfır değildir. Test OpenAI veya SMTP sağlayıcısına istek göndermez. Tamamlanmış AI sonucu, yalnızca editoryal onay akışını doğrulamak için açıkça tanımlanan bir test verisidir; gerçek üretim başarısı olarak raporlanmaz. Gerçek API ve SMTP teslimatı, ilgili sunucu ayarlarıyla ayrıca doğrulanmalıdır.

## İlk yönetici kurulum ekranı

```sh
PHP_BIN=php python3 tests/installer_smoke.py
```

Bu test ayrı, boş bir veritabanı kullanır. Kurulumun varsayılan olarak kapalı olması, kısa anahtarla açılmaması, CSRF/kurulum anahtarı/site adresi doğrulaması, sırların HTML'e geri yazılmaması, ilk hesabın parola özetiyle oluşturulması, ekranın sonraki kullanımlara kapanması ve hesabın giriş yapabilmesi doğrulanır. Canlı ortamı veya gerçek hesapları değiştirmez.

## Galeri

```sh
PHP_BIN=php python3 tests/gallery_smoke.py
```

## Ülke profil matrisi

```sh
php tests/country_profiles_smoke.php
```

Bu test 195 pazar profilinin kod, varsayılan para birimi, saat dilimi, tarih biçimi ve geçersiz kod reddi davranışını doğrular. Bir profil için varsayılan `USD`/`UTC` görülmesi, o ülke için yerel mali/vergisel uygunluk veya tam ülke yerelleştirmesi iddiası değildir.

İzole bir ortamda gerçek dosya doğrulama/yükleme hattıyla kütüphane görseli oluşturur. Yayınlanan kartın ziyaretçi galerisinde, AJAX JSON yanıtında ve herkese açık WebP tesliminde göründüğünü doğrular. Müşteri ekleri bu akışta yayımlanamaz.
