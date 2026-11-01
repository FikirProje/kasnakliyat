<?php
declare(strict_types=1);

/** First-run only: enabled by an out-of-band token, closed after the first account. */
function handle_install(): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $installLang = installer_locale();
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
    $requirements['Erişim adresi algılandı'] = $configuredUrl !== '';
    $requirements['Canlı ortamda HTTPS'] = envv('APP_ENV', 'development') !== 'production' || str_starts_with($configuredUrl, 'https://');
    $ready = !in_array(false, $requirements, true);
    $errors = [];
    $adminName = '';
    $businessName = '';
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
                    $businessName = is_string($_POST['business_name'] ?? null) ? trim($_POST['business_name']) : '';
                    $adminEmail = is_string($_POST['admin_email'] ?? null) ? mb_strtolower(trim($_POST['admin_email'])) : '';
                    $password = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
                    $confirmation = is_string($_POST['admin_password_confirm'] ?? null) ? $_POST['admin_password_confirm'] : '';
                    $submittedUrl = is_string($_POST['app_url'] ?? null) ? rtrim(trim($_POST['app_url']), '/') : '';
                    if ($adminName === '' || mb_strlen($adminName) > 100) $errors[] = 'Name must be 1–100 characters.';
                    if ($businessName === '' || mb_strlen($businessName) > 120) $errors[] = 'Business name must be 1–120 characters.';
                    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) $errors[] = 'Geçerli bir yönetici e-postası girin.';
                    if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) $errors[] = 'Parola en az 12 karakter ve en fazla 72 bayt olmalı.';
                    if (!hash_equals($password, $confirmation)) $errors[] = 'Parola tekrarı eşleşmiyor.';
                    if ($submittedUrl !== $configuredUrl) $errors[] = 'Site adresi tarayıcıdan algılanan adresle aynı olmalı. Farklı bir alan adı kullanacaksanız önce .env içindeki APP_URL değerini düzenleyin.';
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
                            save_setting('business_name',$businessName);
                            save_setting('admin_locale',$installLang);
                            save_setting('default_locale',$installLang);
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
<html lang="<?=e($installLang)?>" dir="<?=e(locale_direction($installLang))?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e(installer_t('title',$installLang))?></title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="installer-body"><main class="installer-shell"><header class="installer-header"><a class="brand" href="/<?=e($installLang)?>/">TRANSPORT<span>CMS</span></a><span class="installer-label"><?=e(installer_t('label',$installLang))?></span></header><div class="installer-intro"><p class="eyebrow"><?=e(installer_t('eyebrow',$installLang))?></p><h1><?=e(installer_t('heading',$installLang))?></h1><p><?=e(installer_t('intro',$installLang))?></p></div>
<?php foreach ($errors as $error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endforeach ?>
<div class="installer-grid"><section class="panel"><h2><?=e(installer_t('server',$installLang))?></h2><ul class="installer-checks"><?php foreach ($requirements as $label => $passed): ?><li><span><?= e($label) ?></span><strong class="<?= $passed ? 'check-pass' : 'check-fail' ?>"><?= e(installer_t($passed?'ready':'missing',$installLang)) ?></strong></li><?php endforeach ?></ul><p class="muted small"><?=e(installer_t('storage_hint',$installLang))?></p><div class="notice"><p><?=e(installer_t('url_notice',$installLang))?></p></div><p class="muted small"><?=e(installer_t('closed',$installLang))?></p></section>
<section class="panel"><h2><?=e(installer_t('first_admin',$installLang))?></h2><form method="post" action="/install"><input type="hidden" name="installer_lang" value="<?=e($installLang)?>"><?= csrf_field() ?><label><?=e(installer_t('language',$installLang))?><select onchange="location.search='?lang='+encodeURIComponent(this.value)"><?php foreach(core_locales() as $code=>$name): ?><option value="<?=e($code)?>" <?=$code===$installLang?'selected':''?>><?=e($name)?></option><?php endforeach ?></select></label><label><?=e(installer_t('website_url',$installLang))?><input type="url" name="app_url" value="<?= e($submittedUrl) ?>" required autocomplete="url"></label><label><?=e(installer_t('business',$installLang))?><input name="business_name" value="<?= e($businessName) ?>" maxlength="120" required autocomplete="organization"></label><label><?=e(installer_t('your_name',$installLang))?><input name="admin_name" value="<?= e($adminName) ?>" maxlength="100" required autocomplete="name"></label><label><?=e(installer_t('admin_email',$installLang))?><input type="email" name="admin_email" value="<?= e($adminEmail) ?>" maxlength="254" required autocomplete="username"></label><label><?=e(installer_t('password',$installLang))?><input type="password" name="admin_password" minlength="12" maxlength="72" required autocomplete="new-password"></label><label><?=e(installer_t('confirm',$installLang))?><input type="password" name="admin_password_confirm" minlength="12" maxlength="72" required autocomplete="new-password"></label><label><?=e(installer_t('token',$installLang))?><input type="password" name="setup_token" required autocomplete="off"></label><p class="muted small"><?=e(installer_t('token_hint',$installLang))?></p><button type="submit" <?= !$ready ? 'disabled' : '' ?>><?=e(installer_t('submit',$installLang))?> ↗</button></form></section></div><footer class="installer-footer"><?=e(installer_t('footer',$installLang))?></footer></main></body></html><?php
}
