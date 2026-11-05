<?php
declare(strict_types=1);

/** Integration smoke for the read-only final marketplace gate. */
if (PHP_OS_FAMILY === 'Windows' && !in_array('sqlite', PDO::getAvailableDrivers(), true) && getenv('TOFIDESK_SQLITE_REEXEC') !== '1') {
    $arguments = [escapeshellarg(PHP_BINARY)];
    foreach (['php_pdo_sqlite.dll', 'php_sqlite3.dll'] as $extension) {
        $extensionPath = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR.$extension;
        if (is_file($extensionPath)) $arguments[] = '-d '.escapeshellarg('extension='.$extensionPath);
    }
    if (count($arguments) > 1) {
        $arguments[] = escapeshellarg(__FILE__);
        $env = 'set TOFIDESK_SQLITE_REEXEC=1&&';
        passthru($env.implode(' ', $arguments), $exitCode);
        exit($exitCode);
    }
}
$tmp = tempnam(sys_get_temp_dir(), 'tofidesk-audit-');
$package = tempnam(sys_get_temp_dir(), 'tofidesk-package-');
if ($tmp === false || $package === false) throw new RuntimeException('Could not create audit fixtures.');
try {
    file_put_contents($package, "signed fixture package\n");
    $db = new PDO('sqlite:'.$tmp, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE products(id INTEGER PRIMARY KEY,slug TEXT UNIQUE,name TEXT,kind TEXT,status TEXT);
        CREATE TABLE product_translations(product_id INTEGER,locale TEXT,title TEXT,description TEXT,slug TEXT,seo_title TEXT,seo_description TEXT,keywords TEXT,canonical_url TEXT,og_image TEXT,schema_json TEXT,faq_schema_json TEXT,breadcrumb_schema_json TEXT,status TEXT DEFAULT 'published');
        CREATE TABLE product_prices(product_id INTEGER,currency TEXT,amount_minor INTEGER,active INTEGER DEFAULT 1);
        CREATE TABLE product_assets(product_id INTEGER,kind TEXT,status TEXT);
        CREATE TABLE application_releases(product_id INTEGER,channel TEXT,status TEXT,package_path TEXT,manifest_json TEXT,sha256 TEXT,signature TEXT);
        CREATE TABLE license_plans(product_id INTEGER,status TEXT);
        CREATE TABLE product_category_links(product_id INTEGER,category_id INTEGER);
        CREATE TABLE product_tag_links(product_id INTEGER,tag_id INTEGER);");
    $db->exec("INSERT INTO products VALUES(1,'fixture-cms','Fixture CMS','application','published')");
    $locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
    $insert = $db->prepare('INSERT INTO product_translations VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $schema = json_encode(['@context'=>'https://schema.org','@type'=>'SoftwareApplication','name'=>'Fixture CMS','offers'=>['@type'=>'Offer','price'=>'149.00','priceCurrency'=>'EUR']]);
    $faq = json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>[]]);
    $breadcrumb = json_encode(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[]]);
    foreach ($locales as $locale) $insert->execute([1,$locale,'Fixture CMS','A complete product description.','fixture-cms','Fixture CMS | TofiDesk','A complete SEO description.','software, cms','https://example.test/'.$locale.'/fixture-cms','https://example.test/og.png',$schema,$faq,$breadcrumb,'published']);
    $db->exec("INSERT INTO product_prices VALUES(1,'EUR',14900,1);INSERT INTO product_assets VALUES(1,'demo','published');INSERT INTO product_assets VALUES(1,'document','published');INSERT INTO product_assets VALUES(1,'screenshot','published');INSERT INTO license_plans VALUES(1,'published');INSERT INTO product_category_links VALUES(1,1);INSERT INTO product_tag_links VALUES(1,1)");
    $sha = hash_file('sha256', $package);
    $secret = 'fixture-release-secret-32-characters-minimum';
    putenv('RELEASE_SIGNING_SECRET='.$secret);
    $manifest = json_encode(['product'=>'fixture-cms','version'=>'1.0.0','compatibility'=>['php'=>'>=8.2','database'=>'sqlite or mysql','extensions'=>['pdo','json','zip']]], JSON_UNESCAPED_SLASHES);
    $release = $db->prepare('INSERT INTO application_releases VALUES(1,?,?,?,?,?,?)');
    $release->execute(['stable','published',$package,$manifest,$sha,'HMAC-SHA256.'.hash_hmac('sha256',$manifest,$secret)]);
    $audit = dirname(__DIR__).'/license-center/bin/marketplace-audit.php';
    $phpArgs = [PHP_BINARY];
    if (PHP_OS_FAMILY === 'Windows') {
        foreach (['php_sqlite3.dll', 'php_pdo_sqlite.dll'] as $extension) {
            $extensionPath = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR.$extension;
            if (is_file($extensionPath)) {
                $phpArgs[] = '-d';
                $phpArgs[] = 'extension='.$extensionPath;
            }
        }
    }
    $command = implode(' ', array_map('escapeshellarg', $phpArgs)).' '.escapeshellarg($audit).' --db='.escapeshellarg($tmp).' --json';
    $output = [];
    $code = 0;
    exec($command.' 2>&1', $output, $code);
    if ($code !== 0) throw new RuntimeException('A complete fixture was blocked: '.implode("\n", $output));
    $db->exec("UPDATE application_releases SET signature=''");
    $output = [];
    $code = 0;
    exec($command.' 2>&1', $output, $code);
    if ($code !== 3) throw new RuntimeException('An unsigned fixture was not blocked.');
    echo "PASS marketplace audit blocks incomplete releases and accepts a complete product\n";
} finally {
    @unlink($tmp);
    @unlink($package);
}
