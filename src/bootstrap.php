<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
umask(0077);
if (is_file(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1]) === false) {
            $value = trim($m[2]);
            if (strlen($value) >= 2 && in_array($value[0], ['"', "'"]) && substr($value, -1) === $value[0]) $value = substr($value, 1, -1);
            putenv($m[1] . '=' . $value);
        }
    }
}
function envv(string $key, string $default = ''): string { $v = getenv($key); return $v === false || $v === '' ? $default : $v; }
date_default_timezone_set(envv('APP_TIMEZONE', 'Europe/Istanbul'));
function storage_path(string $sub = ''): string {
    $base = envv('STORAGE_PATH', ROOT . '/storage');
    return $base . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}
foreach (['', 'sessions', 'logs', 'media/originals', 'media/variants', 'backups'] as $directory) {
    if (!is_dir(storage_path($directory))) mkdir(storage_path($directory), 0700, true);
}
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('error_log', storage_path('logs/php.log'));
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
function csp_nonce(): string {
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_save_path(storage_path('sessions'));
    session_name('kas_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => str_starts_with(app_origin(), 'https://'), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'nonce-".csp_nonce()."'; script-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    if (str_starts_with(app_origin(), 'https://')) header('Strict-Transport-Security: max-age=31536000');
}
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_origin(): string {
    $configured = rtrim(envv('APP_URL', ''), '/');
    $placeholder = $configured === '' || preg_match('#^https?://(?:127\.0\.0\.1|localhost)(?::\d+)?$#i', $configured);
    if ($placeholder && PHP_SAPI !== 'cli') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (is_string($host) && preg_match('/^[a-z0-9.\-\[\]:]+$/i', $host)) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            return ($https ? 'https://' : 'http://') . $host;
        }
    }
    return $configured !== '' ? $configured : 'http://127.0.0.1:8080';
}
function base_url(string $path = ''): string {
    $base = app_origin();
    if (!preg_match('#^https?://[a-z0-9.\-\[\]:]+(?::\d+)?$#i', $base)) throw new RuntimeException('APP_URL must contain an HTTP(S) origin without a path.');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}
function redirect(string $url): never {
    if (!str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\r\n]/', $url)) $url = '/';
    header('Location: ' . $url, true, 303); exit;
}
function flash(string $kind, string $message): void { $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message]; }
function take_flash(): array { $messages = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $messages; }
function audit(string $action, string $detail = ''): void {
    query('INSERT INTO audit_logs(user_id,action,detail,created_at) VALUES(?,?,?,datetime(\'now\'))', [$_SESSION['user_id'] ?? null, $action, mb_substr($detail, 0, 2000)]);
}
function request_lang(): string {
    preg_match('#^/([a-z]{2,3}(?:-[a-z0-9]{2,8})?)(?:/|$)#', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', $m);
    return isset($m[1]) && function_exists('locales') && isset(locales()[$m[1]]) ? $m[1] : 'tr';
}
function render_error(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $lang = request_lang(); $direction = function_exists('locale_direction') ? locale_direction($lang) : 'ltr';
    echo '<!doctype html><html lang="' . $lang . '" dir="' . $direction . '"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Kaş Nakliyat</title><link rel="stylesheet" href="/assets/site.css"><body><main class="narrow section"><h1>' . $status . '</h1><p>' . e($message) . '</p><a href="/' . $lang . '/">Kaş Nakliyat</a></main></body></html>'; exit;
}
foreach (['I18n', 'InstallerI18n', 'AdminI18n', 'CountryProfiles', 'Integrations', 'InboundMail', 'License', 'PaymentSignatures', 'PublicSite', 'Admin', 'Installer'] as $module) {
    $file = __DIR__ . '/' . $module . '.php';
    if (is_file($file)) require_once $file;
}
