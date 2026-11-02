# Plesk / cPanel canlıya alma kontrolü

## 1. PHP uzantıları

Alan adının PHP ayarlarında PHP 8.2 veya üzerini seçin ve şu uzantıları etkinleştirin:

- `pdo_sqlite`
- `sqlite3`
- `curl`
- `openssl`
- `mbstring`
- `fileinfo`
- `gd` ve WebP desteği
- `zip`

Plesk: **Alan adı → PHP Ayarları → Additional configuration directives / Extensions**.
 cPanel: **MultiPHP Manager / Select PHP Version → Extensions**.

## 2. Document root

Uygulama paketindeki `public/` dizini web kökü olarak tercih edilir. Plesk/cPanel document root değiştirilemiyorsa paketin `httpdocs` kök dağıtımını kullanın; `storage/`, `.env` ve yedekler web üzerinden erişilebilir olmamalıdır.

## 3. İzinler

PHP kullanıcısı yalnızca şu dizinlere yazabilmelidir:

- `storage/`
- `storage/backups/`
- `storage/media/`

Kaynak kodunu herkese yazılabilir (`777`) yapmayın. Önce `hosting-check.php` çalıştırın, testten sonra bu dosyayı sunucudan kaldırın.

## 4. İlk kurulum

1. ZIP’i alan adının document root’una çıkarın.
2. HTTPS’i etkinleştirin.
3. `https://alanadiniz.com/install` adresini bir kez açın.
4. İlk admin hesabını oluşturun.
5. Kurulumdan sonra `/install` adresinin 403 vermesi beklenen kilit davranışıdır; yeniden kurulum yapmayın.
6. Admin panelinden logo, işletme, SMTP, AI ve lisans merkezi ayarlarını girin.

## 5. Güncelleme

Normal sürüm güncellemesi için yeni ZIP’i tekrar yüklemek veya `/install` çalıştırmak gerekmez. Admin → **Güncellemeler** ekranında:

1. Ön kontrolü çalıştırın.
2. İmzalı paketi indirin.
3. Yönetici onayıyla **Yedekle ve uygula** seçin.

Sistem bakım moduna geçer, veritabanı ve dosya yedeği alır, migration’ları çalıştırır ve hata olursa geri alma kaydını kullanır.

## 6. Son doğrulama

`hosting-check.php` çıktısında tüm çekirdek kontroller `PASS` olmalı; ardından `/admin`, lisans kontrolü, tema kataloğu, medya yükleme ve güncelleme kontrolü ayrı ayrı denenmelidir.
