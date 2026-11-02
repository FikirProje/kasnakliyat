<?php
declare(strict_types=1);
// Direct cPanel/Plesk fallback when URL rewriting is disabled.
if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>TofiDesk sunucu gereksinimi</title><main><h1>PHP 8.2 veya üzeri gerekli</h1><p>Bu sunucuda PHP '.htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8').' çalışıyor. Plesk veya cPanel PHP ayarlarından PHP 8.2, 8.3 ya da 8.4 seçin.</p></main>';
    exit;
}
$_SERVER['REQUEST_URI'] = '/install';
require __DIR__ . '/public/index.php';
