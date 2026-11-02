# TofiDesk dağıtım haritası

## Hangi paket nereye yüklenir?

| Paket | Hedef | Kullanım |
|---|---|---|
| product-tofidesk-license-center.zip | product.tofidesk.com | Ürün/tema satış merkezi, müşteri hesabı, sipariş, fatura, lisans, domain ve tema yönetimi |
| product-tofidesk-app-deployment.zip | kasnakliyat.com | Ana transport CMS uygulaması ve demo/test kurulumu |
| product-tofidesk-marketplace.zip | Yeni müşterinin kendi domaini | Marka bilgileri temizlenmiş, genel müşteri dağıtım paketi |

## Plesk/cPanel kurulumu

1. İlgili domainin httpdocs kökünü açın.
2. Yalnızca o domaine ait ZIP'i httpdocs içine çıkarın; ZIP dosyasının kendisini web kökünde bırakmayın.
3. Uygulamanın .env.example dosyasını özel .env dosyasına kopyalayın. Gerçek sırları ZIP'e veya Git'e koymayın.
4. APP_URL, özel SQLite yolu ve yazılabilir özel storage yolunu ayarlayın.
5. Ana CMS için /install, lisans merkezi için /installer.php adresini açın.
6. İlk yönetici hesabını oluşturun. Tokenless installer ilk hesap oluşturulduktan sonra kilitlenir.
7. Admin panelinden SMTP, bildirim, AI, ödeme ve doğrulama ayarlarını test edin.

## Önemli ayrım

product.tofidesk.com satış/lisans merkezidir; müşterinin çalıştıracağı CMS değildir. kasnakliyat.com demo CMS'tir. Bir müşteriye teslim edilecek temiz paket marketplace ZIP'idir. Canlı ödeme ve sağlayıcı anahtarları yalnızca sunucu ortamında tanımlanır.

## Dağıtım öncesi kontrol

- PHP 8.2+, PDO SQLite/SQLite3, cURL, OpenSSL, mbstring, fileinfo ve GD/WebP etkin.
- HTTPS ve doğru DNS aktif.
- Özel storage ve SQLite yolu web kökünün dışında ve yazılabilir.
- Cron/worker yapılandırılmış.
- SMTP ve gerçek satın alma callback'i staging üzerinde denenmiş.
- SHA256SUMS.txt ile yüklenen ZIP doğrulanmış.
