<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../license-center/public/order_fulfillment.php');
foreach (['function fulfillment_release(', "SELECT id,kind,status FROM products", 'FROM theme_packages WHERE product_id=?', 'FROM application_releases WHERE product_id=?', "str_replace('{LICENSE_ID}'", 'release_kind'] as $needle) {
    if (!str_contains($source, $needle)) throw new RuntimeException('Product-specific fulfillment contract missing: '.$needle);
}
if (substr_count($source, 'PRODUCT_DOWNLOAD_URL') > 4) throw new RuntimeException('Legacy global download URL leaked into product fulfillment.');

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP paid-order fulfillment fixture: PDO SQLite is not enabled in this CLI".PHP_EOL;
    exit(0);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function lc_db(): PDO { global $db; return $db; }
function q(string $sql, array $args=[]): PDOStatement { $stmt=lc_db()->prepare($sql); $stmt->execute($args); return $stmt; }
function one(string $sql, array $args=[]): ?array { return q($sql,$args)->fetch(PDO::FETCH_ASSOC) ?: null; }
function lc_env(string $key, string $default=''): string { return ['PRODUCT_DOWNLOAD_URL'=>'https://downloads.example.test/package.zip','PRODUCT_VERSION'=>'2.1.0','PRODUCT_FILENAME'=>'product.zip'][$key] ?? $default; }
function issue_key(): array { $id=(int)lc_db()->lastInsertId(); $secret='TESTSECRET123456789012'; return [$id,'TDL-'.$id.'-'.$secret,$secret]; }

lc_db()->exec("CREATE TABLE buyers(id INTEGER PRIMARY KEY,name TEXT,email TEXT);CREATE TABLE invoices(id INTEGER PRIMARY KEY,order_id INTEGER UNIQUE,invoice_number TEXT,customer_name TEXT,customer_email TEXT,currency TEXT,subtotal_minor INTEGER,tax_amount_minor INTEGER,total_minor INTEGER);CREATE TABLE licenses(id INTEGER PRIMARY KEY,product TEXT,key_hash TEXT,key_hint TEXT,status TEXT,domain_limit INTEGER,source TEXT,purchase_code_hash TEXT UNIQUE,buyer_id INTEGER,order_id INTEGER UNIQUE,product_id INTEGER,plan_id INTEGER,support_until TEXT,updates_until TEXT);CREATE TABLE license_plans(id INTEGER PRIMARY KEY,domain_limit INTEGER,support_days INTEGER,update_days INTEGER,billing_period TEXT,status TEXT);CREATE TABLE license_entitlements(id INTEGER PRIMARY KEY,license_id INTEGER,entitlement TEXT,value TEXT,expires_at TEXT,UNIQUE(license_id,entitlement));CREATE TABLE downloads(id INTEGER PRIMARY KEY,buyer_id INTEGER,product TEXT,product_id INTEGER,version TEXT,filename TEXT,url TEXT,order_id INTEGER UNIQUE);INSERT INTO buyers VALUES(1,'Buyer','buyer@example.test');INSERT INTO license_plans VALUES(7,3,365,365,'lifetime','published');");
$order = ['id'=>11,'buyer_id'=>1,'product'=>'Demo application','product_id'=>9,'plan_id'=>7,'amount_minor'=>14900,'tax_amount_minor'=>0,'currency'=>'EUR'];
require __DIR__.'/../license-center/public/order_fulfillment.php';
$first = fulfill_paid_order($order,'manual','MANUAL-11');
$second = fulfill_paid_order($order,'manual','MANUAL-11');
if (!is_string($first) || $second !== null) throw new RuntimeException('Paid order fulfillment is not idempotent.');
foreach ([['invoices',1],['licenses',1],['license_entitlements',4],['downloads',1]] as [$table,$expected]) {
    $actual=(int)lc_db()->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    if ($actual !== $expected) throw new RuntimeException("Unexpected {$table} count: {$actual}");
}

$source = file_get_contents(__DIR__.'/../license-center/public/commerce_admin.php');
foreach (['fulfill_paid_order(', "provider='manual'", 'beginTransaction()', 'commit()'] as $needle) if (!str_contains($source, $needle)) throw new RuntimeException('Manual review contract missing: '.$needle);
echo "PASS paid-order fulfillment: manual approval creates invoice, licence, entitlements and download idempotently".PHP_EOL;
