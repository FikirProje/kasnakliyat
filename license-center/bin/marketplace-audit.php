<?php
declare(strict_types=1);

/**
 * Read-only pre-release audit for the TofiDesk marketplace.
 *
 * Usage:
 *   php bin/marketplace-audit.php --db=/private/licenses.sqlite
 *   php bin/marketplace-audit.php --db=/private/licenses.sqlite --json
 *
 * This command intentionally never changes the database and never prints
 * secrets. A product may be visible as a preview while it is not sale-ready.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }

$root = dirname(__DIR__);
foreach ([$root.'/.env', dirname($root).'/.env'] as $env) {
    if (!is_file($env)) continue;
    foreach (file($env, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1]) === false) {
            putenv($m[1].'='.trim(trim($m[2]), "\"'"));
        }
    }
}

function audit_arg(string $name, string $default = ''): string
{
    global $argv;
    foreach ($argv as $value) if (str_starts_with($value, '--'.$name.'=')) return substr($value, strlen($name) + 3);
    return $default;
}

$dbPath = audit_arg('db', getenv('LICENSE_DB_PATH') ?: $root.'/storage/licenses.sqlite');
$json = in_array('--json', $argv, true);
$includeDrafts = in_array('--all', $argv, true);
if (!is_file($dbPath)) { fwrite(STDERR, "Database not found: {$dbPath}\n"); exit(2); }

$db = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
$products = $db->query($includeDrafts ? "SELECT * FROM products WHERE status<>'archived' ORDER BY id" : "SELECT * FROM products WHERE status='published' ORDER BY id")->fetchAll();
$report = ['database'=>$dbPath, 'products'=>[], 'blocking'=>0, 'advisories'=>0];

$hasTable = static function (PDO $db, string $table): bool {
    $query = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$table]);
    return (bool)$query->fetchColumn();
};

$releaseSignatureValid = static function (array $release): bool {
    $signature = (string)($release['signature'] ?? '');
    $manifest = (string)($release['manifest_json'] ?? '');
    if ($signature === '' || $manifest === '') return false;
    if (str_starts_with($signature, 'HMAC-SHA256.')) {
        $secret = trim((string)(getenv('RELEASE_SIGNING_SECRET') ?: ''));
        return $secret !== '' && hash_equals('HMAC-SHA256.'.hash_hmac('sha256', $manifest, $secret), $signature);
    }
    if (str_starts_with($signature, 'RSA-SHA256.')) {
        $public = trim((string)(getenv('RELEASE_SIGNING_PUBLIC_KEY') ?: ''));
        $raw = base64_decode(substr($signature, strlen('RSA-SHA256.')), true);
        return $public !== '' && $raw !== false && openssl_verify($manifest, $raw, $public, OPENSSL_ALGO_SHA256) === 1;
    }
    return false;
};

$releaseCompatibilityValid = static function (string $kind, array $release): bool {
    $raw = (string)($kind === 'theme' ? ($release['requirements_json'] ?? '') : ($release['manifest_json'] ?? ''));
    $data = json_decode($raw, true);
    if (!is_array($data)) return false;
    $compatibility = is_array($data['compatibility'] ?? null) ? $data['compatibility'] : $data;
    $php = trim((string)($compatibility['php'] ?? $compatibility['min_php'] ?? ''));
    if ($php === '') return false;
    if ($kind === 'theme') return trim((string)($compatibility['browser'] ?? '')) !== '';
    $database = trim((string)($compatibility['database'] ?? ''));
    $extensions = $compatibility['extensions'] ?? [];
    return $database !== '' && is_array($extensions) && count(array_filter($extensions, static fn($extension): bool => trim((string)$extension) !== '')) > 0;
};

$jsonObject = static function (string $value): ?array {
    $decoded = json_decode($value, true);
    return is_array($decoded) && $decoded !== [] ? $decoded : null;
};
$schemaTypeValid = static function (string $value, array $allowed) use ($jsonObject): bool {
    $schema = $jsonObject($value);
    if ($schema === null) return false;
    $type = $schema['@type'] ?? null;
    return is_string($type) ? in_array($type, $allowed, true) : (is_array($type) && (bool)array_intersect($allowed, $type));
};
$schemaOfferValid = static function (string $value) use ($jsonObject): bool {
    $schema = $jsonObject($value);
    if ($schema === null || !array_key_exists('offers', $schema)) return false;
    $offers = is_array($schema['offers']) && array_is_list($schema['offers']) ? $schema['offers'] : [$schema['offers']];
    foreach ($offers as $offer) if (!is_array($offer) || !in_array($offer['@type'] ?? null, ['Offer','AggregateOffer'], true) || !isset($offer['priceCurrency'])) return false;
    return $offers !== [];
};
$httpUrlValid = static function (string $value): bool {
    return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false && (bool)preg_match('/^https?:\\/\\//i', $value);
};

foreach ($products as $product) {
    $id = (int)$product['id'];
    $blocking = [];
    $advisories = [];
    $translations = [];
    $query = $db->prepare('SELECT * FROM product_translations WHERE product_id=?');
    $query->execute([$id]);
    foreach ($query->fetchAll() as $translation) $translations[(string)$translation['locale']] = $translation;
    foreach ($locales as $locale) {
        $translation = $translations[$locale] ?? null;
        if (!$translation) { $blocking[] = strtoupper($locale).' translation'; continue; }
        foreach (['title','description','slug','seo_title','seo_description','keywords'] as $field) {
            if ((string)($translation['status'] ?? '') !== 'published' || trim((string)($translation[$field] ?? '')) === '') {
                $blocking[] = strtoupper($locale).' '.$field;
            }
        }
        if (!$httpUrlValid((string)($translation['canonical_url'] ?? ''))) $blocking[] = strtoupper($locale).' invalid canonical_url';
        if (!$httpUrlValid((string)($translation['og_image'] ?? ''))) $blocking[] = strtoupper($locale).' invalid og_image';
        $productTypes = (string)$product['kind'] === 'application' ? ['SoftwareApplication','Product'] : ['Product','SoftwareApplication'];
        if (!$schemaTypeValid((string)($translation['schema_json'] ?? ''), $productTypes)) $blocking[] = strtoupper($locale).' invalid product schema';
        if (!$schemaOfferValid((string)($translation['schema_json'] ?? ''))) $blocking[] = strtoupper($locale).' invalid offer schema';
        if (!$schemaTypeValid((string)($translation['faq_schema_json'] ?? ''), ['FAQPage'])) $blocking[] = strtoupper($locale).' invalid faq schema';
        if (!$schemaTypeValid((string)($translation['breadcrumb_schema_json'] ?? ''), ['BreadcrumbList'])) $blocking[] = strtoupper($locale).' invalid breadcrumb schema';
    }

    $query = $db->prepare('SELECT currency,amount_minor FROM product_prices WHERE product_id=? AND active=1 AND amount_minor>0');
    $query->execute([$id]);
    if (!$query->fetch()) $blocking[] = 'active price';

    $assetKinds = [];
    $query = $db->prepare("SELECT kind FROM product_assets WHERE product_id=? AND status='published'");
    $query->execute([$id]);
    foreach ($query->fetchAll() as $asset) $assetKinds[(string)$asset['kind']] = true;
    foreach (['demo','document','screenshot'] as $kind) if (!isset($assetKinds[$kind])) $blocking[] = $kind.' asset';
    if ((string)$product['kind'] === 'theme' && !isset($assetKinds['video'])) $blocking[] = 'video asset';

    $releaseReady = false;
    if (in_array((string)$product['kind'], ['application','addon'], true) && $hasTable($db, 'application_releases')) {
        $query = $db->prepare("SELECT package_path,manifest_json,sha256,signature FROM application_releases WHERE product_id=? AND channel='stable' AND status='published'");
        $query->execute([$id]);
        foreach ($query->fetchAll() as $release) {
            $path = (string)$release['package_path'];
            $releaseReady = is_file($path) && preg_match('/^[a-f0-9]{64}$/i', (string)$release['sha256']) && $releaseCompatibilityValid('application', $release) && $releaseSignatureValid($release) && hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', $path)));
            if ($releaseReady) break;
        }
    } elseif ((string)$product['kind'] === 'theme' && $hasTable($db, 'theme_packages')) {
        $query = $db->prepare("SELECT theme_id,version,storage_path,requirements_json,sha256,signature FROM theme_packages WHERE product_id=? AND status='published'");
        $query->execute([$id]);
        foreach ($query->fetchAll() as $release) {
            $path = (string)$release['storage_path'];
            $key = trim((string)(getenv('LICENSE_PACKAGE_SIGNING_KEY') ?: ''));
            $expected = $key !== '' ? hash_hmac('sha256', (string)$release['sha256'].'|'.(int)$release['theme_id'].'|'.(string)$release['version'], $key) : '';
            $releaseReady = is_file($path) && preg_match('/^[a-f0-9]{64}$/i', (string)$release['sha256']) && $releaseCompatibilityValid('theme', $release) && $expected !== '' && hash_equals($expected, (string)$release['signature']) && hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', $path)));
            if ($releaseReady) break;
        }
    } else {
        $releaseReady = isset($assetKinds['download']);
    }
    if (!$releaseReady) $blocking[] = 'verified signed release package';

    if ($hasTable($db, 'license_plans')) {
        $query = $db->prepare('SELECT 1 FROM license_plans WHERE product_id=? AND status=\'published\' LIMIT 1');
        $query->execute([$id]);
        if (!$query->fetchColumn()) $blocking[] = 'published licence plan';
    } else {
        $blocking[] = 'license_plans table';
    }
    if ($hasTable($db, 'product_category_links') && !$db->query("SELECT 1 FROM product_category_links WHERE product_id={$id} LIMIT 1")->fetchColumn()) $advisories[] = 'no category';
    if ($hasTable($db, 'product_tag_links') && !$db->query("SELECT 1 FROM product_tag_links WHERE product_id={$id} LIMIT 1")->fetchColumn()) $advisories[] = 'no tags';

    $report['products'][] = ['id'=>$id,'slug'=>(string)$product['slug'],'kind'=>(string)$product['kind'],'status'=>(string)$product['status'],'sale_ready'=>count($blocking)===0,'blocking'=>$blocking,'advisories'=>$advisories];
    $report['blocking'] += count($blocking);
    $report['advisories'] += count($advisories);
}

if ($json) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
} else {
    echo "TofiDesk marketplace audit\n";
    echo "Products: ".count($report['products'])." | Blocking: {$report['blocking']} | Advisories: {$report['advisories']}\n\n";
    foreach ($report['products'] as $item) {
        echo '['.($item['sale_ready'] ? 'READY' : 'BLOCKED').'] '.$item['kind'].' '.$item['slug'].'\n';
        foreach ($item['blocking'] as $issue) echo "  - BLOCKING: {$issue}\n";
        foreach ($item['advisories'] as $issue) echo "  - advisory: {$issue}\n";
    }
}
exit($report['blocking'] > 0 ? 3 : 0);
