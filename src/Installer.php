<?php
declare(strict_types=1);

/** First-run only: open on a fresh database and closed after the first account. */
function handle_install(): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $installLang = installer_locale();
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
    $countryCode = strtoupper((string)($_POST['country_code'] ?? 'TR'));
    if (!isset(country_profiles()[$countryCode])) $countryCode = 'TR';
    $countryDefaults = country_profile($countryCode);
    $timezone = (string)($_POST['timezone'] ?? $countryDefaults['timezone']);
    $currency = strtoupper((string)($_POST['currency_code'] ?? $countryDefaults['currency']));
    $units = (string)($_POST['unit_system'] ?? 'metric');
    if (!in_array($units, ['metric','imperial'], true)) $units = 'metric';
    $adminEmail = (string)(require ROOT . '/config/site.php')['setup_admin_email'];
    $submittedUrl = $configuredUrl;
    if ($ready) {
        try {
            // Explicitly enabled installation may initialize the schema. This also
            // gives token attempts a durable IP rate limit, independent of cookies.
            migrate();
            if (is_file(storage_path('installed.lock')) || (int) row('SELECT count(*) AS n FROM users')['n'] !== 0) {
                render_error(403, installer_error_t('closed', $installLang));
            }
        } catch (Throwable $error) {
            error_log('Installer schema initialization failed: '.$error->getMessage());
            $ready = false;
            $errors[] = installer_error_t('database', $installLang);
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$ready) {
            http_response_code(503);
            $errors[] = installer_error_t('requirements', $installLang);
        } else {
            csrf_verify();
            if (!rate_limit('install:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 6, 900)) {
                http_response_code(429);
                $errors[] = installer_error_t('rate', $installLang);
            } else {
                {
                    $adminName = is_string($_POST['admin_name'] ?? null) ? trim($_POST['admin_name']) : '';
                    $businessName = is_string($_POST['business_name'] ?? null) ? trim($_POST['business_name']) : '';
                    $countryCode = strtoupper(is_string($_POST['country_code'] ?? null) ? trim($_POST['country_code']) : 'TR');
                    $countryDefaults = country_profiles()[$countryCode] ?? country_profiles()['TR'];
                    $timezone = is_string($_POST['timezone'] ?? null) ? trim($_POST['timezone']) : $countryDefaults['timezone'];
                    $currency = strtoupper(is_string($_POST['currency_code'] ?? null) ? trim($_POST['currency_code']) : $countryDefaults['currency']);
                    $units = is_string($_POST['unit_system'] ?? null) ? trim($_POST['unit_system']) : 'metric';
                    $adminEmail = is_string($_POST['admin_email'] ?? null) ? mb_strtolower(trim($_POST['admin_email'])) : '';
                    $password = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
                    $confirmation = is_string($_POST['admin_password_confirm'] ?? null) ? $_POST['admin_password_confirm'] : '';
                    $submittedUrl = is_string($_POST['app_url'] ?? null) ? rtrim(trim($_POST['app_url']), '/') : '';
                    if ($adminName === '' || mb_strlen($adminName) > 100) $errors[] = installer_error_t('name', $installLang);
                    if ($businessName === '' || mb_strlen($businessName) > 120) $errors[] = installer_error_t('business', $installLang);
                    try { $country = country_profile($countryCode); } catch (Throwable) { $errors[] = installer_error_t('country', $installLang); $country=[]; }
                    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) $errors[] = installer_error_t('timezone', $installLang);
                    if (!preg_match('/^[A-Z]{3}$/', $currency)) $errors[] = installer_error_t('currency', $installLang);
                    if (!in_array($units, ['metric','imperial'], true)) $errors[] = installer_error_t('units', $installLang);
                    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) $errors[] = installer_error_t('email', $installLang);
                    if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) $errors[] = installer_error_t('password', $installLang);
                    if (!hash_equals($password, $confirmation)) $errors[] = installer_error_t('confirm', $installLang);
                    if ($submittedUrl !== $configuredUrl) $errors[] = installer_error_t('url', $installLang);
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
                            foreach ($country as $key=>$value) if ($key !== 'code') save_setting('country_'.$key,(string)$value);
                            save_setting('country_code',$countryCode);
                            save_setting('timezone',$timezone);
                            save_setting('currency_code',$currency);
                            save_setting('unit_system',$units);
                            audit('installation.completed', 'İlk yönetici oluşturuldu.');
                            db()->exec('COMMIT');
                            file_put_contents(storage_path('installed.lock'), gmdate('c')."\n", LOCK_EX);
                        } catch (Throwable $error) {
                            db()->exec('ROLLBACK');
                            error_log('First administrator creation failed: '.get_class($error));
                            render_error(409, installer_error_t('conflict', $installLang));
                        }
                        unset($password, $confirmation);
                        flash('success', installer_error_t('success', $installLang));
                        redirect('/admin/login');
                    }
                    unset($password, $confirmation);
                }
            }
        }
    }
    ob_start(static function (string $html) use ($installLang, $countryCode, $timezone, $currency, $units): string {
        $countryOptions = '';
        foreach (array_keys(country_profiles()) as $code) $countryOptions .= '<option value="'.e($code).'"'.($code === $countryCode ? ' selected' : '').'>'.e($code).'</option>';
        $profile = '<fieldset class="installer-profile"><legend>'.e(installer_profile_t('country',$installLang)).'</legend>'
            .'<label>'.e(installer_profile_t('country',$installLang)).'<select name="country_code">'.$countryOptions.'</select></label>'
            .'<p class="muted small">'.e(installer_profile_t('profile_note',$installLang)).'</p>'
            .'<label>'.e(installer_profile_t('timezone',$installLang)).'<input name="timezone" value="'.e($timezone).'" required></label>'
            .'<label>'.e(installer_profile_t('currency',$installLang)).'<input name="currency_code" value="'.e($currency).'" maxlength="3" required></label>'
            .'<label>'.e(installer_profile_t('units',$installLang)).'<select name="unit_system"><option value="metric"'.($units === 'metric' ? ' selected' : '').'>'.e(installer_profile_t('metric',$installLang)).'</option><option value="imperial"'.($units === 'imperial' ? ' selected' : '').'>'.e(installer_profile_t('imperial',$installLang)).'</option></select></label></fieldset>';
        $marker = '<label>'.e(installer_t('website_url',$installLang));
        return str_replace($marker, $profile.$marker, $html);
    });
    ?><!doctype html>
<html lang="<?=e($installLang)?>" dir="<?=e(locale_direction($installLang))?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e(installer_t('title',$installLang))?></title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="installer-body"><main class="installer-shell"><header class="installer-header"><a class="brand" href="/<?=e($installLang)?>/">TOFI<span>DESK</span></a><span class="installer-label"><?=e(installer_t('label',$installLang))?></span></header><div class="installer-intro"><p class="eyebrow"><?=e(installer_t('eyebrow',$installLang))?></p><h1><?=e(installer_t('heading',$installLang))?></h1><p><?=e(installer_t('intro',$installLang))?></p></div>
<?php foreach ($errors as $error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endforeach ?>
<div class="installer-grid"><section class="panel"><h2><?=e(installer_t('server',$installLang))?></h2><ul class="installer-checks"><?php foreach ($requirements as $label => $passed): ?><li><span><?= e($label) ?></span><strong class="<?= $passed ? 'check-pass' : 'check-fail' ?>"><?= e(installer_t($passed?'ready':'missing',$installLang)) ?></strong></li><?php endforeach ?></ul><p class="muted small"><?=e(installer_t('storage_hint',$installLang))?></p><div class="notice"><p><?=e(installer_t('url_notice',$installLang))?></p></div><p class="muted small"><?=e(installer_t('closed',$installLang))?></p></section>
<section class="panel"><h2><?=e(installer_t('first_admin',$installLang))?></h2><form method="post" action="/install"><input type="hidden" name="installer_lang" value="<?=e($installLang)?>"><?= csrf_field() ?><label><?=e(installer_t('language',$installLang))?><select onchange="location.search='?lang='+encodeURIComponent(this.value)"><?php foreach(core_locales() as $code=>$name): ?><option value="<?=e($code)?>" <?=$code===$installLang?'selected':''?>><?=e($name)?></option><?php endforeach ?></select></label><label><?=e(installer_t('website_url',$installLang))?><input type="url" name="app_url" value="<?= e($submittedUrl) ?>" required autocomplete="url"></label><label><?=e(installer_t('business',$installLang))?><input name="business_name" value="<?= e($businessName) ?>" maxlength="120" required autocomplete="organization"></label><label><?=e(installer_t('your_name',$installLang))?><input name="admin_name" value="<?= e($adminName) ?>" maxlength="100" required autocomplete="name"></label><label><?=e(installer_t('admin_email',$installLang))?><input type="email" name="admin_email" value="<?= e($adminEmail) ?>" maxlength="254" required autocomplete="username"></label><label><?=e(installer_t('password',$installLang))?><input type="password" name="admin_password" minlength="12" maxlength="72" required autocomplete="new-password"></label><label><?=e(installer_t('confirm',$installLang))?><input type="password" name="admin_password_confirm" minlength="12" maxlength="72" required autocomplete="new-password"></label><button type="submit" <?= !$ready ? 'disabled' : '' ?>><?=e(installer_t('submit',$installLang))?> ↗</button></form></section></div><footer class="installer-footer"><?=e(installer_t('footer',$installLang))?></footer></main></body></html><?php
}
