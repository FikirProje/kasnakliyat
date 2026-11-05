<?php
declare(strict_types=1);
function lc_root_preflight(): void {
    $missing = [];
    if (PHP_VERSION_ID < 80200) $missing[] = 'PHP 8.2 veya üzeri';
    foreach (['pdo_sqlite'=>'PDO SQLite','sqlite3'=>'SQLite3','curl'=>'cURL','openssl'=>'OpenSSL','mbstring'=>'mbstring'] as $extension=>$label) {
        if (!extension_loaded($extension)) $missing[] = $label;
    }
    if ($missing === []) return;
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $items = implode('', array_map(static fn(string $item): string => '<li>'.htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>', $missing));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TofiDesk gereksinimleri</title><style>body{font:16px/1.6 system-ui;background:#092b3d;color:#153346;margin:0;padding:28px}.card{max-width:720px;margin:8vh auto;background:#f7faf8;border-radius:18px;padding:34px}h1{color:#08283a}li{padding:10px;margin:7px 0;background:#fff;border:1px solid #dce8e3;border-radius:8px;color:#a33}</style><main class="card"><strong>TOFIDESK LICENSE CENTRE</strong><h1>Sunucu gereksinimleri eksik</h1><p>Plesk/cPanel PHP ayarlarında aşağıdaki bileşenleri etkinleştirin, ardından sayfayı yenileyin.</p><ul>'.$items.'</ul><p>Gerekli: PHP 8.2+ · PDO SQLite · SQLite3 · cURL · OpenSSL · mbstring</p></main>';
    exit;
}
lc_root_preflight();
require __DIR__ . '/public/index.php';
