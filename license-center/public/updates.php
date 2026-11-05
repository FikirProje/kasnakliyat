<?php
declare(strict_types=1);

// Read-only release check endpoint. It deliberately does not download files
// or execute customer/application code.
if (!defined('LC_ROOT')) define('LC_ROOT', __DIR__ . '/..');
foreach ([LC_ROOT.'/.env', dirname(LC_ROOT).'/.env'] as $env) {
    if (!is_file($env)) continue;
    foreach (file($env, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1]) === false) putenv($m[1].'='.trim(trim($m[2]), "\"'") );
    }
}
function update_env(string $key, string $default = ''): string { $value=getenv($key); return $value===false||$value===''?$default:$value; }
function update_db(): PDO {
    static $db;
    if (!$db) {
        $path=update_env('LICENSE_DB_PATH',LC_ROOT.'/storage/licenses.sqlite');
        if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
        $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000');
    }
    return $db;
}
update_db()->exec("CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY,slug TEXT NOT NULL UNIQUE,name TEXT NOT NULL,kind TEXT NOT NULL DEFAULT 'application',status TEXT NOT NULL DEFAULT 'draft',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);CREATE TABLE IF NOT EXISTS application_releases(id INTEGER PRIMARY KEY,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,version TEXT NOT NULL,channel TEXT NOT NULL DEFAULT 'stable',package_name TEXT NOT NULL,package_path TEXT NOT NULL,manifest_json TEXT NOT NULL,sha256 TEXT NOT NULL,signature TEXT NOT NULL,min_php TEXT NOT NULL DEFAULT '8.2.0',migration_version INTEGER NOT NULL DEFAULT 0,changelog TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,published_at TEXT,UNIQUE(product_id,version,channel));CREATE TABLE IF NOT EXISTS theme_packages(id INTEGER PRIMARY KEY,theme_id INTEGER NOT NULL DEFAULT 0,product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,version TEXT NOT NULL,package_name TEXT NOT NULL,storage_path TEXT NOT NULL,sha256 TEXT NOT NULL,signature TEXT NOT NULL,requirements_json TEXT NOT NULL DEFAULT '{}',changelog TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);CREATE TABLE IF NOT EXISTS installations(id INTEGER PRIMARY KEY,license_id INTEGER NOT NULL,installation_hash TEXT NOT NULL UNIQUE,product TEXT NOT NULL,domain TEXT NOT NULL,installed_version TEXT NOT NULL,migration_version INTEGER NOT NULL DEFAULT 0,php_version TEXT NOT NULL DEFAULT '',last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,grace_until TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);CREATE TABLE IF NOT EXISTS update_events(id INTEGER PRIMARY KEY,installation_id INTEGER,license_id INTEGER,from_version TEXT NOT NULL DEFAULT '',to_version TEXT NOT NULL DEFAULT '',action TEXT NOT NULL,detail TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);CREATE TABLE IF NOT EXISTS license_revocations(id INTEGER PRIMARY KEY,license_id INTEGER NOT NULL,reason TEXT NOT NULL DEFAULT '',effective_at TEXT,expires_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);");
update_db()->exec("CREATE TABLE IF NOT EXISTS validation_cache(installation_hash TEXT NOT NULL,product TEXT NOT NULL,domain TEXT NOT NULL,response_json TEXT NOT NULL,verified_at INTEGER NOT NULL,expires_at INTEGER NOT NULL,PRIMARY KEY(installation_hash,product));");
function update_one(string $sql,array $params=[]): ?array { $s=update_db()->prepare($sql);$s->execute($params);return $s->fetch()?:null; }
function update_query(string $sql,array $params=[]): void { $s=update_db()->prepare($sql);$s->execute($params); }
function update_json(array $data,int $status=200): never { http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit; }
function update_host(string $value): string {
    $value=strtolower(trim($value));$value=preg_replace('#^https?://#','',$value);$value=explode('/',$value,2)[0];$value=preg_replace('/:\d+$/','',$value);
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',$value)) throw new RuntimeException('A valid public domain is required.');
    return $value;
}
function update_key_parts(string $key): array { if(!preg_match('/^TDL-([0-9]+)-([A-Z0-9]{24})$/',strtoupper(trim($key)),$m)) throw new RuntimeException('Invalid licence key format.'); return [(int)$m[1],strtoupper($m[2])]; }
function update_rate_limit(string $bucket,int $limit=30,int $window=60): void {
    $now=time();$row=update_one('SELECT * FROM rate_limits WHERE bucket=?',[$bucket]);
    if (!$row || $now-(int)$row['window_started_at'] >= $window) update_query('INSERT INTO rate_limits(bucket,attempts,window_started_at) VALUES(?,?,?) ON CONFLICT(bucket) DO UPDATE SET attempts=excluded.attempts,window_started_at=excluded.window_started_at',[$bucket,1,$now]);
    else { if((int)$row['attempts'] >= $limit) throw new RuntimeException('Rate limit exceeded.'); update_query('UPDATE rate_limits SET attempts=attempts+1 WHERE bucket=?',[$bucket]); }
}
try {
    if (($_SERVER['REQUEST_METHOD']??'GET') !== 'POST') throw new RuntimeException('POST required.');
    $ip=$_SERVER['REMOTE_ADDR']??'unknown';update_rate_limit('release-check:'.$ip);
    $input=json_decode(file_get_contents('php://input')?:'',true);if(!is_array($input))$input=$_POST;
    [$licenseId,$secret]=update_key_parts((string)($input['license_key']??''));
    $license=update_one('SELECT * FROM licenses WHERE id=?',[$licenseId]);
    if(!$license||!password_verify($secret,$license['key_hash'])||$license['status']!=='active')throw new RuntimeException('Licence is not active.');
    $revoked=update_one("SELECT * FROM license_revocations WHERE license_id=? AND (effective_at IS NULL OR effective_at<=CURRENT_TIMESTAMP) AND (expires_at IS NULL OR expires_at>CURRENT_TIMESTAMP) ORDER BY id DESC",[$licenseId]);
    if($revoked)throw new RuntimeException('Licence access has been revoked.');
    $product=preg_replace('/[^a-z0-9._-]/','',strtolower((string)($input['product']??$license['product'])));
    if($product===''||$product!==strtolower((string)$license['product']))throw new RuntimeException('Product is not allowed for this licence.');
    $productRecord=update_one("SELECT id,kind FROM products WHERE slug=? AND status='published'",[$product]);
    if(!$productRecord) throw new RuntimeException('Product is not published.');
    $domain=update_host((string)($input['domain']??''));
    $bound=update_one('SELECT id FROM domains WHERE license_id=? AND domain=?',[$licenseId,$domain]);
    if(!$bound)throw new RuntimeException('This domain is not activated for the licence.');
    $installation=preg_replace('/[^a-f0-9-]/','',strtolower((string)($input['installation_id']??'')));
    if(strlen($installation)<16||strlen($installation)>128)throw new RuntimeException('A valid installation id is required.');
    $installed=(string)($input['installed_version']??'0.0.0');$migration=(int)($input['migration_version']??0);$php=(string)($input['php_version']??'');
    $existing=update_one('SELECT * FROM installations WHERE installation_hash=?',[$installation]);
    if(!$existing){$installationCount=(int)(update_one('SELECT count(*) AS n FROM installations WHERE license_id=? AND status=\'active\'',[$licenseId])['n']??0);if($installationCount >= (int)$license['domain_limit'])throw new RuntimeException('Installation seat limit reached for this licence.');}
    if($existing && (int)$existing['license_id']!==$licenseId)throw new RuntimeException('Installation is bound to another licence.');
    if($existing) update_query('UPDATE installations SET domain=?,installed_version=?,migration_version=?,php_version=?,last_seen_at=CURRENT_TIMESTAMP,status=\'active\' WHERE id=?',[$domain,$installed,$migration,$php,$existing['id']]);
    else { update_query('INSERT INTO installations(license_id,installation_hash,product,domain,installed_version,migration_version,php_version,grace_until) VALUES(?,?,?,?,?,?,?,datetime(\'now\',\'+14 days\'))',[$licenseId,$installation,$product,$domain,$installed,$migration,$php]);$existing=update_one('SELECT * FROM installations WHERE installation_hash=?',[$installation]); }
    $requestedAction=(string)($input['action']??'check');
    if ($requestedAction === 'download' && !empty($license['updates_until']) && strtotime((string)$license['updates_until']) < time()) throw new RuntimeException('The update entitlement for this licence has expired.');
    if($requestedAction==='download') {
        $requestedVersion=(string)($input['version']??'');
        $releaseKind = ($productRecord['kind'] ?? 'application') === 'theme' ? 'theme' : (($productRecord['kind'] ?? 'application') === 'addon' ? 'addon' : 'application');
        $release = $releaseKind === 'theme'
            ? update_one("SELECT r.*, 'theme' AS release_kind FROM theme_packages r JOIN products p ON p.id=r.product_id WHERE p.slug=? AND p.status='published' AND r.status='published' AND r.version=?",[$product,$requestedVersion])
            : update_one("SELECT r.*, CASE WHEN p.kind='addon' THEN 'addon' ELSE 'application' END AS release_kind FROM application_releases r JOIN products p ON p.id=r.product_id WHERE p.slug=? AND p.status='published' AND r.channel='stable' AND r.status='published' AND r.version=?",[$product,$requestedVersion]);
        if (!$release) {
            throw new RuntimeException('Requested release is unavailable.');
        }
        $packagePath = $releaseKind === 'theme' ? (string)($release['storage_path'] ?? '') : (string)($release['package_path'] ?? '');
        if(!$release||$packagePath===''||!is_file($packagePath)) throw new RuntimeException('Requested release is unavailable.');
        if (!hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', $packagePath)))) throw new RuntimeException('Release integrity check failed.');
        if ($releaseKind === 'theme') {
            $signingKey = trim(update_env('LICENSE_PACKAGE_SIGNING_KEY'));
            $expectedSignature = $signingKey !== '' ? hash_hmac('sha256', (string)$release['sha256'].'|'.(int)($release['theme_id'] ?? 0).'|'.(string)$release['version'], $signingKey) : '';
            if ($expectedSignature === '' || !hash_equals($expectedSignature, (string)$release['signature'])) throw new RuntimeException('Theme release signature verification failed.');
        }
        update_query('INSERT INTO update_events(installation_id,license_id,from_version,to_version,action,detail) VALUES(?,?,?,?,?,?)',[$existing['id'],$licenseId,$installed,$release['version'],'downloaded',$domain]);
        header('Content-Type: application/zip'); header('Content-Length: '.filesize($packagePath)); header('Content-Disposition: attachment; filename="'.basename($release['package_name']).'"'); header('X-TofiDesk-SHA256: '.$release['sha256']); readfile($packagePath); exit;
    }
    update_query('INSERT INTO update_events(installation_id,license_id,from_version,action,detail) VALUES(?,?,?,?,?)',[$existing['id'],$licenseId,$installed,'check',$domain]);
    $releases = ($productRecord['kind'] ?? 'application') === 'theme'
        ? update_db()->prepare("SELECT r.*,p.slug AS product_slug,'theme' AS release_kind FROM theme_packages r JOIN products p ON p.id=r.product_id WHERE p.slug=? AND p.status='published' AND r.status='published' ORDER BY r.created_at DESC")
        : update_db()->prepare("SELECT r.*,p.slug AS product_slug,CASE WHEN p.kind='addon' THEN 'addon' ELSE 'application' END AS release_kind FROM application_releases r JOIN products p ON p.id=r.product_id WHERE p.slug=? AND p.status='published' AND r.channel='stable' AND r.status='published' ORDER BY r.created_at DESC");
    $releases->execute([$product]);$release=null;
    foreach($releases->fetchAll() as $candidate)if(version_compare((string)$candidate['version'],$installed,'>')){$release=$candidate;break;}
    $plan = !empty($license['plan_id']) ? update_one('SELECT name,billing_period,domain_limit,support_days,update_days,currency,amount_minor FROM license_plans WHERE id=?',[(int)$license['plan_id']]) : null;
    $manifest = $release && isset($release['manifest_json']) ? json_decode((string)$release['manifest_json'], true) : null;
    if (!$manifest && $release && isset($release['requirements_json'])) $manifest = json_decode((string)$release['requirements_json'], true);
    $result=['active'=>true,'product'=>$product,'product_kind'=>$productRecord['kind'],'installed_version'=>$installed,'checked_at'=>gmdate('c'),'cache_until'=>gmdate('c',time()+600),'grace_until'=>$existing['grace_until'],'support_until'=>$license['support_until']??null,'updates_until'=>$license['updates_until']??null,'plan'=>$plan,'update'=>$release?['kind'=>$release['release_kind'],'version'=>$release['version'],'changelog'=>$release['changelog'],'sha256'=>$release['sha256'],'signature'=>$release['signature'],'min_php'=>$release['min_php']??($manifest['min_php']??'8.2.0'),'migration_version'=>(int)($release['migration_version']??0),'manifest'=>$manifest]:null];
    update_query('INSERT INTO validation_cache(installation_hash,product,domain,response_json,verified_at,expires_at) VALUES(?,?,?,?,?,?) ON CONFLICT(installation_hash,product) DO UPDATE SET domain=excluded.domain,response_json=excluded.response_json,verified_at=excluded.verified_at,expires_at=excluded.expires_at',[$installation,$product,$domain,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),time(),time()+600]);
    update_json($result);
} catch(Throwable $error) { update_json(['active'=>false,'error'=>$error->getMessage()],422); }
