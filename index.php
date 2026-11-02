<?php
declare(strict_types=1);
if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TofiDesk sunucu gereksinimi</title><style>body{font:16px/1.6 system-ui;background:#092b3d;color:#153346;margin:0;padding:28px}.card{max-width:720px;margin:8vh auto;background:#f7faf8;border-radius:18px;padding:34px}h1{color:#08283a}strong{color:#ff7c2c}</style><main class="card"><strong>TOFIDESK</strong><h1>PHP 8.2 veya üzeri gerekli</h1><p>Bu sunucuda PHP '.htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8').' çalışıyor. Plesk veya cPanel PHP ayarlarından PHP 8.2, 8.3 ya da 8.4 seçin ve sayfayı yenileyin.</p></main>';
    exit;
}
require __DIR__ . '/public/index.php';
