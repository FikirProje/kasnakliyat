<?php
declare(strict_types=1);

/** First-run only: enabled by an out-of-band token, closed after the first account. */
function handle_install(): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $configuredToken = envv('INSTALL_TOKEN');
    if (strlen($configuredToken) < 32) {
        render_error(404, 'Kurulum ekranı etkin değil.');
    }
    $requirements = [
        'PHP 8.2 veya üzeri' => PHP_VERSION_ID >= 80200,
        'SQLite / PDO' => extension_loaded('pdo_sqlite'),
        'Çok dilli metin desteği' => extension_loaded('mbstring'),
        'Dosya türü doğrulaması' => extension_loaded('fileinfo'),
        'Güvenli API bağlantıları' => extension_loaded('curl') && extension_loaded('openssl'),
        'Görsel işleme' => extension_loaded('gd') && function_exists('imagewebp'),
        'Yedekleme / sıkıştırma' => extension_loaded('zlib'),
        'Yazılabilir veri dizini' => is_dir(storage_path()) && is_writable(storage_path()),
        'Güvenli oturum desteği' => session_status() === PHP_SESSION_ACTIVE,
    ];
    $configuredUrl = '';
    try { $configuredUrl = base_url(); }
    catch (Throwable) { /* The form explains that APP_URL must be corrected outside the application. */ }
    $requirements['Geçerli APP_URL yapılandırması'] = $configuredUrl !== '';
    $requirements['Canlı ortamda HTTPS'] = envv('APP_ENV', 'development') !== 'production' || str_starts_with($configuredUrl, 'https://');
    $ready = !in_array(false, $requirements, true);
    $errors = [];
    $adminName = '';
    $adminEmail = (string)(require ROOT . '/config/site.php')['setup_admin_email'];
    $submittedUrl = $configuredUrl;
    if ($ready) {
        try {
            // Explicitly enabled installation may initialize the schema. This also
            // gives token attempts a durable IP rate limit, independent of cookies.
            migrate();
            if ((int) row('SELECT count(*) AS n FROM users')['n'] !== 0) {
                render_error(403, 'İlk kurulum tamamlandı. Kurulum ekranı kapalıdır. Yönetim panelinden giriş yapın.');
            }
        } catch (Throwable $error) {
            error_log('Installer schema initialization failed: '.$error->getMessage());
            $ready = false;
            $errors[] = 'Veritabanı hazırlanamadı. Veri dizini izinlerini ve sunucu günlüklerini kontrol edin.';
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$ready) {
            http_response_code(503);
            $errors[] = 'Kuruluma devam etmeden önce sunucu gereksinimlerini tamamlayın.';
        } else {
            csrf_verify();
            if (!rate_limit('install:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 6, 900)) {
                http_response_code(429);
                $errors[] = 'Kurulum deneme sınırına ulaşıldı. 15 dakika sonra yeniden deneyin.';
            } else {
                $providedToken = is_string($_POST['setup_token'] ?? null) ? $_POST['setup_token'] : '';
                if (!hash_equals($configuredToken, $providedToken)) {
                    http_response_code(403);
                    $errors[] = 'Kurulum anahtarı doğrulanamadı.';
                } else {
                    $adminName = is_string($_POST['admin_name'] ?? null) ? trim($_POST['admin_name']) : '';
                    $adminEmail = is_string($_POST['admin_email'] ?? null) ? mb_strtolower(trim($_POST['admin_email'])) : '';
                    $password = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
                    $confirmation = is_string($_POST['admin_password_confirm'] ?? null) ? $_POST['admin_password_confirm'] : '';
                    $submittedUrl = is_string($_POST['app_url'] ?? null) ? rtrim(trim($_POST['app_url']), '/') : '';
                    if ($adminName === '' || mb_strlen($adminName) > 100) $errors[] = 'Adınız 1–100 karakter olmalı.';
                    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) $errors[] = 'Geçerli bir yönetici e-postası girin.';
                    if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) $errors[] = 'Parola en az 12 karakter ve en fazla 72 bayt olmalı.';
                    if (!hash_equals($password, $confirmation)) $errors[] = 'Parola tekrarı eşleşmiyor.';
                    if ($submittedUrl !== $configuredUrl) $errors[] = 'Site adresi APP_URL ile aynı olmalı. Adresi değiştirmek için önce sunucudaki .env dosyasını düzenleyip bu adresi yeniden açın.';
                    if ($errors) {
                        http_response_code(422);
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        db()->exec('BEGIN IMMEDIATE');
                        try {
                            // The check is repeated under a write lock so two
                            // simultaneous first-run submissions cannot add admins.
                            if ((int) row('SELECT count(*) AS n FROM users')['n'] !== 0) throw new RuntimeException('Installation already completed');
                            query("INSERT INTO users(name,email,password_hash,role,created_at) VALUES(?,?,?,'admin',datetime('now'))", [$adminName,$adminEmail,$hash]);
                            audit('installation.completed', 'İlk yönetici oluşturuldu.');
                            db()->exec('COMMIT');
                        } catch (Throwable $error) {
                            db()->exec('ROLLBACK');
                            error_log('First administrator creation failed: '.get_class($error));
                            render_error(409, 'İlk yönetici oluşturulamadı veya kurulum başka bir oturumda tamamlandı. Yönetim girişini kontrol edin.');
                        }
                        unset($password, $confirmation, $providedToken, $configuredToken);
                        flash('success', 'İlk yönetici oluşturuldu. Giriş yapabilirsiniz. Sunucu ayarlarından INSTALL_TOKEN değerini kaldırın; kurulum ekranı artık kapalı.');
                        redirect('/admin/login');
                    }
                    unset($password, $confirmation);
                }
                unset($providedToken);
            }
        }
    }
    ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>İlk kurulum · Kaş Nakliyat</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="installer-body"><main class="installer-shell"><header class="installer-header"><a class="brand" href="/tr/">KAŞ<span>NAKLİYAT</span></a><span class="installer-label">İLK KURULUM</span></header><div class="installer-intro"><p class="eyebrow">YENİ BİR BAŞLANGIÇ</p><h1>Yönetim alanınızı hazırlayın.</h1><p>Sunucu gereksinimlerini doğrulayın ve ilk yönetici hesabınızı oluşturun.</p></div>
<?php foreach ($errors as $error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endforeach ?>
<div class="installer-grid"><section class="panel"><h2>Sunucu kontrolü</h2><ul class="installer-checks"><?php foreach ($requirements as $label => $passed): ?><li><span><?= e($label) ?></span><strong class="<?= $passed ? 'check-pass' : 'check-fail' ?>"><?= $passed ? 'Hazır' : 'Eksik' ?></strong></li><?php endforeach ?></ul><p class="muted small">Web sunucusunun kök dizini <code>public/</code> olmalı. Veri dizini web üzerinden erişilebilir olmamalıdır.</p><div class="notice"><strong>Site adresi önce sunucuda ayarlanır.</strong><p><code>.env</code> dosyasındaki <code>APP_URL</code> gerçek HTTPS adresiniz olmalı. Bu ekran ortam dosyanızı değiştirmez.</p></div><p class="muted small">Bu ekran, ilk hesap oluşturulduğunda kapanır. API ve e-posta hizmetleri yönetim panelinden ayrıca yapılandırılır.</p></section>
<section class="panel"><h2>İlk yönetici</h2><form method="post" action="/install"><?= csrf_field() ?><label>Site adresi<input type="url" name="app_url" value="<?= e($submittedUrl) ?>" required autocomplete="url"></label><label>Ad soyad<input name="admin_name" value="<?= e($adminName) ?>" maxlength="100" required autocomplete="name"></label><label>Yönetici e-postası<input type="email" name="admin_email" value="<?= e($adminEmail) ?>" maxlength="254" required autocomplete="username"></label><label>Yeni parola<input type="password" name="admin_password" minlength="12" maxlength="72" required autocomplete="new-password"></label><label>Yeni parola tekrar<input type="password" name="admin_password_confirm" minlength="12" maxlength="72" required autocomplete="new-password"></label><label>Kurulum anahtarı<input type="password" name="setup_token" required autocomplete="off"></label><p class="muted small">Sunucudaki <code>INSTALL_TOKEN</code> değerini girin. Kurulum anahtarı ve parolalar bu sayfada tekrar gösterilmez.</p><button type="submit" <?= !$ready ? 'disabled' : '' ?>>İlk yöneticiyi oluştur ↗</button></form></section></div><footer class="installer-footer">Kaş Nakliyat · Bağımsız web platformu</footer></main></body></html><?php
}
