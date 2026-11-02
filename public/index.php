<?php
declare(strict_types=1);
function preflight_requirements_page(): void {
    $required = ['pdo_sqlite' => 'PDO SQLite', 'sqlite3' => 'SQLite3', 'curl' => 'cURL', 'openssl' => 'OpenSSL', 'mbstring' => 'mbstring', 'fileinfo' => 'fileinfo', 'gd' => 'GD/WebP'];
    $missing = [];
    foreach ($required as $extension => $label) if (!extension_loaded($extension)) $missing[] = $label;
    if (!$missing) {
        $storage = dirname(__DIR__) . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0700, true) && !is_dir($storage)) $missing[] = 'Yazılabilir storage dizini';
        elseif (!is_writable($storage)) $missing[] = 'Yazılabilir storage dizini';
    }
    if (!$missing) return;
    http_response_code(503);
    $lang = strtolower(substr((string)($_GET['lang'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'tr'), 0, 2));
    $title = $lang === 'tr' ? 'Kurulum için sunucu gereksinimleri eksik' : 'Server requirements are missing';
    $intro = $lang === 'tr' ? 'Plesk/cPanel PHP ayarlarında aşağıdaki gereksinimleri etkinleştirin ve sayfayı yenileyin.' : 'Enable the following requirements in the Plesk/cPanel PHP settings and reload the page.';
    echo '<!doctype html><html lang="'.htmlspecialchars($lang, ENT_QUOTES, 'UTF-8').'" dir="'.(in_array($lang, ['ar','fa'], true) ? 'rtl' : 'ltr').'" ><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').' · TofiDesk</title><style>body{font:16px/1.6 system-ui,sans-serif;background:#092b3d;color:#153346;margin:0;padding:28px}.card{max-width:760px;margin:8vh auto;background:#f7faf8;border-radius:18px;padding:34px;box-shadow:0 20px 60px #00141f66}h1{color:#08283a}li{padding:10px;margin:7px 0;background:#fff;border:1px solid #dce8e3;border-radius:8px;color:#a33}code{background:#edf3f0;padding:3px 6px;border-radius:4px}</style><main class="card"><p><strong>TOFIDESK</strong></p><h1>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h1><p>'.htmlspecialchars($intro, ENT_QUOTES, 'UTF-8').'</p><ul>'.implode('', array_map(static fn(string $item): string => '<li>✗ '.htmlspecialchars($item, ENT_QUOTES, 'UTF-8').'</li>', $missing)).'</ul><p>PHP 8.2+ · PDO SQLite · SQLite3 · cURL · OpenSSL · mbstring · fileinfo · GD/WebP</p><p><code>/install</code> adresini gereksinimler tamamlandıktan sonra tekrar açın.</p></main></html>';
    exit;
}
$preflightPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (PHP_SAPI !== 'cli' || $preflightPath !== '/health') preflight_requirements_page();
// For production point the document root to public/, never to the repository.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (PHP_SAPI === 'cli-server' && str_starts_with($path, '/assets/') && !str_contains($path, '..')) {
    $file = realpath(__DIR__ . $path);
    if ($file && str_starts_with($file, __DIR__ . '/assets/') && is_file($file)) return false;
}
require dirname(__DIR__) . '/src/bootstrap.php';
try {
    if (rtrim($path, '/') === '/install' && function_exists('handle_install')) { handle_install(); exit; }
    if (!row("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")) render_error(503, 'Uygulama kurulumu tamamlanmadı.');
    if (preg_match('/[\x00-\x1f\\\\]/', $path) || str_contains($path, '..')) render_error(400, 'Geçersiz adres.');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) { header('Allow: GET, HEAD, POST'); render_error(405, t('error_method', request_lang())); }
    if ($path === '/health') {
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        echo json_encode(['status' => 'ok', 'database' => query('SELECT 1')->fetchColumn() == 1]); exit;
    }
    if (preg_match('#^/api/gallery/?$#',$path)) {
        $lang=is_string($_GET['lang'] ?? null) ? $_GET['lang'] : 'tr';
        if (!isset(locales()[$lang])) render_error(404, 'Sayfa bulunamadı.');
        public_gallery_json($lang);
    }
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        if (envv('APP_ENV', 'development') !== 'production') echo "User-agent: *\nDisallow: /\n";
        else echo "User-agent: *\nDisallow: /admin/\nDisallow: /media/\nDisallow: /health\nSitemap: " . base_url('/sitemap.xml') . "\n";
        exit;
    }
    if (envv('APP_ENV', 'development') !== 'production' || str_starts_with($path, '/admin') || str_starts_with($path, '/media')) header('X-Robots-Tag: noindex, nofollow');
    if ($path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=utf-8');
        $routes = public_routes_for_sitemap(); $groups = [];
        foreach ($routes as $r) $groups[$r['group']][$r['lang']] = $r['path'];
        $xml = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        foreach ($routes as $r) {
            echo '<url><loc>' . $xml(base_url($r['path'])) . '</loc>';
            foreach ($groups[$r['group']] as $lang => $url) echo '<xhtml:link rel="alternate" hreflang="' . $xml($lang) . '" href="' . $xml(base_url($url)) . '"/>';
            $fallback = $groups[$r['group']]['tr'] ?? reset($groups[$r['group']]);
            echo '<xhtml:link rel="alternate" hreflang="x-default" href="' . $xml(base_url($fallback)) . '"/></url>';
        }
        echo '</urlset>'; exit;
    }
    if (preg_match('#^/media/(\d+)/?$#', $path, $matches)) {
        $media = row('SELECT * FROM media WHERE id=?', [(int)$matches[1]]);
        if (!$media) render_error(404, 'Görsel bulunamadı.');
        $private = $media['purpose'] === 'customer' || (bool)row('SELECT media_id FROM request_media WHERE media_id=?', [$media['id']]);
        if ($private) require_role(['admin','operator']);
        elseif (!row("SELECT id FROM translations WHERE cover_media_id=? AND status='published' AND (scheduled_at IS NULL OR scheduled_at='' OR scheduled_at<=datetime('now'))", [$media['id']]) && !row("SELECT id FROM gallery_items WHERE media_id=? AND status='published'",[$media['id']])) require_role(['admin','editor']);
        $variant = $_GET['variant'] ?? 'webp';
        if (!is_string($variant) || !in_array($variant, ['original', 'webp', 'avif'], true)) render_error(400, 'Geçersiz görsel biçimi.');
        try { $file = media_path($media, $variant); } catch (RuntimeException $error) { render_error(404, 'Görsel bulunamadı.'); }
        if (!is_file($file)) render_error(404, 'Görsel bulunamadı.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/avif'], true)) render_error(404, 'Görsel bulunamadı.');
        header('Content-Type: ' . $mime); header('Cache-Control: ' . ($private ? 'no-store' : 'private, max-age=3600')); header('Content-Length: ' . filesize($file)); readfile($file); exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    if (str_starts_with($path, '/admin')) { header('Cache-Control: no-store'); handle_admin(rtrim($path, '/') ?: '/admin'); }
    else handle_public($path);
} catch (Throwable $error) {
    error_log(get_class($error) . ': ' . $error->getMessage());
    render_error(500, t('error_server', request_lang()));
}
