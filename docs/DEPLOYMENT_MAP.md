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

## Release operations

The sales-centre admin uses these protected routes:

- `/releases` — published application releases and compatibility requirements
- `/installations` — authorised installation/domain telemetry
- `/update-events` — update checks, downloads and applied/failed events

Deployment order:

1. Upload the license-centre archive to `product.tofidesk.com` and configure its private `.env` and SQLite storage.
2. Upload the deployment archive to the demo domain and run its installer once.
3. Configure the demo's license server URL, license key and `RELEASE_SIGNING_PUBLIC_KEY`.
4. Publish releases from the license centre CLI; customer installations check, download and apply them only after admin approval.
5. For a buyer, upload only the marketplace archive to the buyer's `httpdocs`/document root and configure the buyer's own branding in `/install`.

Never upload the license-centre database, private signing key, `.env`, backups or customer media into a buyer archive.

## Dağıtım öncesi kontrol

- PHP 8.2+, PDO SQLite/SQLite3, cURL, OpenSSL, mbstring, fileinfo ve GD/WebP etkin.
- HTTPS ve doğru DNS aktif.
- Özel storage ve SQLite yolu web kökünün dışında ve yazılabilir.
- Cron/worker yapılandırılmış.
- SMTP ve gerçek satın alma callback'i staging üzerinde denenmiş.
- SHA256SUMS.txt ile yüklenen ZIP doğrulanmış.
