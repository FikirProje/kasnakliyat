<?php
declare(strict_types=1);

// Standalone SEO endpoint: it must work before the application router starts
// and must degrade to the public landing routes if the catalogue database is
// unavailable during a fresh install.
$root = dirname(__DIR__);
$envFiles = [$root.'/.env', dirname($root).'/.env'];
foreach ($envFiles as $envFile) {
    if (!is_file($envFile)) continue;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $match) && getenv($match[1]) === false) putenv($match[1].'='.trim(trim($match[2]), "\"'"));
    }
}
$base = rtrim((string)(getenv('APP_URL') ?: 'https://product.tofidesk.com'), '/');
$locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
$resource = (string)($_GET['resource'] ?? 'sitemap');

if ($resource === 'robots') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /shop\nAllow: /pricing\nAllow: /product\nAllow: /search\nAllow: /privacy\nAllow: /terms\nAllow: /support-policy\nDisallow: /account\nDisallow: /admin\nDisallow: /login\nDisallow: /install\nDisallow: /api/\nSitemap: ".$base."/sitemap.xml\n";
    exit;
}

$urls = [];
$add = static function (string $loc, string $changefreq = 'weekly', string $priority = '0.6', ?string $lastmod = null) use (&$urls): void {
    $urls[] = ['loc'=>$loc, 'changefreq'=>$changefreq, 'priority'=>$priority, 'lastmod'=>$lastmod];
};
foreach (['/shop'=>'weekly','/pricing'=>'monthly','/privacy'=>'yearly','/terms'=>'yearly','/support-policy'=>'yearly'] as $path => $freq) {
    foreach ($locales as $locale) $add($base.$path.'?lang='.$locale, $freq, $path === '/shop' ? '1.0' : '0.5');
}

$dbPath = (string)(getenv('LICENSE_DB_PATH') ?: $root.'/storage/licenses.sqlite');
if (is_file($dbPath) && extension_loaded('pdo_sqlite')) {
    try {
        $db = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $rows = $db->query("SELECT p.slug,p.updated_at,t.locale,t.slug localized_slug,t.canonical_url FROM products p JOIN product_translations t ON t.product_id=p.id AND t.status='published' WHERE p.status='published' ORDER BY p.updated_at DESC,p.id DESC")->fetchAll();
        foreach ($rows as $row) {
            $slug = trim((string)($row['localized_slug'] ?: $row['slug']));
            if ($slug === '') continue;
            $locale = (string)$row['locale'];
            $loc = filter_var((string)($row['canonical_url'] ?? ''), FILTER_VALIDATE_URL) ?: $base.'/product?slug='.rawurlencode($slug).'&lang='.rawurlencode($locale);
            $add($loc, 'weekly', '0.8', (string)($row['updated_at'] ?? ''));
        }
    } catch (Throwable $error) {
        // A fresh install must still expose a valid XML sitemap.
    }
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as $url) {
    echo '<url><loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
    if ($url['lastmod'] !== null && $url['lastmod'] !== '') echo '<lastmod>'.htmlspecialchars(substr($url['lastmod'], 0, 10), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>';
    echo '<changefreq>'.$url['changefreq'].'</changefreq><priority>'.$url['priority'].'</priority></url>';
}
echo '</urlset>';
