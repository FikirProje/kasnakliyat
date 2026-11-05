<?php
declare(strict_types=1);
require_once __DIR__.'/account_mail_i18n.php';
if (!defined('LC_ROOT')) define('LC_ROOT', __DIR__ . '/..');
foreach ([LC_ROOT.'/.env', dirname(LC_ROOT).'/.env'] as $env) if (is_file($env)) foreach (file($env, FILE_IGNORE_NEW_LINES) as $line) if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1])===false) putenv($m[1].'='.trim(trim($m[2]), "\"'"));
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/index.php')) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
}
function lc_preflight(): void {
    $missing = [];
    if (version_compare(PHP_VERSION, '8.2.0', '<')) $missing[] = 'PHP 8.2+ (mevcut: '.PHP_VERSION.')';
    foreach (['pdo_sqlite'=>'PDO SQLite','sqlite3'=>'SQLite3','curl'=>'cURL','openssl'=>'OpenSSL','mbstring'=>'mbstring'] as $extension=>$label) if (!extension_loaded($extension)) $missing[] = $label;
    if (!$missing) return;
    $supported = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
    $requested = strtolower((string)($_GET['lang'] ?? ''));
    if (!in_array($requested, $supported, true)) {
        foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $guess = strtolower(substr(trim($part), 0, 2));
            if (in_array($guess, $supported, true)) { $requested = $guess; break; }
        }
    }
    $locale = in_array($requested, $supported, true) ? $requested : 'en';
    $copy = [
        'tr'=>['lang'=>'tr','title'=>'TofiDesk gereksinimleri','heading'=>'Sunucu gereksinimleri eksik','help'=>'Plesk/cPanel PHP ayarlarından aşağıdaki gereksinimleri etkinleştirin ve sayfayı yenileyin.'],
        'en'=>['lang'=>'en','title'=>'TofiDesk requirements','heading'=>'Server requirements are missing','help'=>'Enable the requirements below in your Plesk/cPanel PHP settings, then refresh this page.'],
        'de'=>['lang'=>'de','title'=>'TofiDesk-Anforderungen','heading'=>'Serveranforderungen fehlen','help'=>'Aktivieren Sie die folgenden Anforderungen in den PHP-Einstellungen von Plesk/cPanel und laden Sie die Seite neu.'],
        'fr'=>['lang'=>'fr','title'=>'Prérequis TofiDesk','heading'=>'Prérequis serveur manquants','help'=>'Activez les prérequis ci-dessous dans les réglages PHP de Plesk/cPanel, puis actualisez la page.'],
        'es'=>['lang'=>'es','title'=>'Requisitos de TofiDesk','heading'=>'Faltan requisitos del servidor','help'=>'Active los requisitos siguientes en la configuración PHP de Plesk/cPanel y vuelva a cargar la página.'],
        'it'=>['lang'=>'it','title'=>'Requisiti TofiDesk','heading'=>'Requisiti del server mancanti','help'=>'Abilita i requisiti seguenti nelle impostazioni PHP di Plesk/cPanel, quindi aggiorna la pagina.'],
        'pt'=>['lang'=>'pt','title'=>'Requisitos TofiDesk','heading'=>'Faltam requisitos do servidor','help'=>'Ative os requisitos abaixo nas definições PHP do Plesk/cPanel e atualize a página.'],
        'nl'=>['lang'=>'nl','title'=>'TofiDesk-vereisten','heading'=>'Serververeisten ontbreken','help'=>'Schakel de onderstaande vereisten in bij de PHP-instellingen van Plesk/cPanel en vernieuw deze pagina.'],
        'ru'=>['lang'=>'ru','title'=>'Требования TofiDesk','heading'=>'Не выполнены требования сервера','help'=>'Включите перечисленные требования в настройках PHP Plesk/cPanel и обновите страницу.'],
        'ar'=>['lang'=>'ar','title'=>'متطلبات TofiDesk','heading'=>'متطلبات الخادم غير مكتملة','help'=>'فعّل المتطلبات التالية في إعدادات PHP في Plesk/cPanel ثم حدّث الصفحة.'],
        'fa'=>['lang'=>'fa','title'=>'نیازمندی‌های TofiDesk','heading'=>'نیازمندی‌های سرور ناقص است','help'=>'نیازمندی‌های زیر را در تنظیمات PHP پلسک/cPanel فعال کنید و صفحه را تازه‌سازی کنید.'],
        'zh'=>['lang'=>'zh','title'=>'TofiDesk 服务器要求','heading'=>'服务器要求未满足','help'=>'请在 Plesk/cPanel 的 PHP 设置中启用以下要求，然后刷新页面。'],
        'ja'=>['lang'=>'ja','title'=>'TofiDesk の要件','heading'=>'サーバー要件が不足しています','help'=>'Plesk/cPanel の PHP 設定で以下の要件を有効にしてから、このページを更新してください。'],
        'ko'=>['lang'=>'ko','title'=>'TofiDesk 요구 사항','heading'=>'서버 요구 사항이 충족되지 않았습니다','help'=>'Plesk/cPanel PHP 설정에서 다음 요구 사항을 활성화한 후 페이지를 새로 고치세요.'],
    ][$locale];
    $dir = in_array($locale, ['ar','fa'], true) ? 'rtl' : 'ltr';
    http_response_code(503);
    $items = implode('', array_map(static fn(string $item): string => '<li>✗ '.htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>', $missing));
    echo '<!doctype html><html lang="'.htmlspecialchars($copy['lang'], ENT_QUOTES, 'UTF-8').'" dir="'.$dir.'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($copy['title'], ENT_QUOTES, 'UTF-8').'</title><style>body{font:16px/1.6 system-ui;background:#092b3d;color:#153346;margin:0;padding:28px}.card{max-width:720px;margin:8vh auto;background:#f7faf8;border-radius:18px;padding:34px}h1{color:#08283a}li{padding:10px;margin:7px 0;background:#fff;border:1px solid #dce8e3;border-radius:8px;color:#a33}</style><main class="card"><strong>TOFIDESK</strong><h1>'.htmlspecialchars($copy['heading'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h1><p>'.htmlspecialchars($copy['help'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p><ul>'.$items.'</ul><p>PHP 8.2+ · PDO SQLite · SQLite3 · cURL · OpenSSL · mbstring</p></main></html>';
    exit;
}
lc_preflight();
function release_schema(): void {
    lc_db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS products(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'application' CHECK(kind IN ('application','theme','addon')),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','archived')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS product_bundles(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL UNIQUE REFERENCES products(id) ON DELETE CASCADE,
    code TEXT NOT NULL UNIQUE,
    discount_percent INTEGER NOT NULL DEFAULT 0 CHECK(discount_percent BETWEEN 0 AND 100),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','archived')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS product_bundle_items(
    bundle_id INTEGER NOT NULL REFERENCES product_bundles(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    plan_id INTEGER REFERENCES license_plans(id) ON DELETE SET NULL,
    quantity INTEGER NOT NULL DEFAULT 1 CHECK(quantity BETWEEN 1 AND 100),
    sort_order INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY(bundle_id,product_id)
);
CREATE INDEX IF NOT EXISTS product_bundle_items_lookup ON product_bundle_items(bundle_id,sort_order,product_id);
CREATE TABLE IF NOT EXISTS license_plans(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    license_type TEXT NOT NULL DEFAULT 'single_site' CHECK(license_type IN ('single_site','multi_site','unlimited','subscription','lifetime')),
    billing_period TEXT NOT NULL DEFAULT 'lifetime' CHECK(billing_period IN ('lifetime','monthly','yearly')),
    duration_days INTEGER NOT NULL DEFAULT 0 CHECK(duration_days >= 0),
    domain_limit INTEGER NOT NULL DEFAULT 1 CHECK(domain_limit BETWEEN 1 AND 500),
    support_days INTEGER NOT NULL DEFAULT 365 CHECK(support_days >= 0),
    update_days INTEGER NOT NULL DEFAULT 365 CHECK(update_days >= 0),
    currency TEXT NOT NULL DEFAULT 'EUR',
    amount_minor INTEGER NOT NULL DEFAULT 0 CHECK(amount_minor >= 0),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','archived')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id,code)
);
CREATE TABLE IF NOT EXISTS license_plan_translations(
    id INTEGER PRIMARY KEY,
    plan_id INTEGER NOT NULL REFERENCES license_plans(id) ON DELETE CASCADE,
    locale TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('draft','published')),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(plan_id,locale)
);
CREATE TABLE IF NOT EXISTS license_entitlements(
    id INTEGER PRIMARY KEY,
    license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    entitlement TEXT NOT NULL,
    value TEXT NOT NULL DEFAULT '',
    expires_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(license_id,entitlement)
);
CREATE TABLE IF NOT EXISTS application_releases(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    version TEXT NOT NULL,
    channel TEXT NOT NULL DEFAULT 'stable' CHECK(channel IN ('preview','beta','stable')),
    package_name TEXT NOT NULL,
    package_path TEXT NOT NULL,
    manifest_json TEXT NOT NULL,
    sha256 TEXT NOT NULL,
    signature TEXT NOT NULL,
    min_php TEXT NOT NULL DEFAULT '8.2.0',
    migration_version INTEGER NOT NULL DEFAULT 0,
    changelog TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','revoked')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TEXT,
    UNIQUE(product_id,version,channel)
);
CREATE TABLE IF NOT EXISTS installations(
    id INTEGER PRIMARY KEY,
    license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    installation_hash TEXT NOT NULL UNIQUE,
    product TEXT NOT NULL,
    domain TEXT NOT NULL,
    installed_version TEXT NOT NULL,
    migration_version INTEGER NOT NULL DEFAULT 0,
    php_version TEXT NOT NULL DEFAULT '',
    last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    grace_until TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','review','revoked')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS update_events(
    id INTEGER PRIMARY KEY,
    installation_id INTEGER REFERENCES installations(id) ON DELETE SET NULL,
    license_id INTEGER REFERENCES licenses(id) ON DELETE SET NULL,
    from_version TEXT NOT NULL DEFAULT '',
    to_version TEXT NOT NULL DEFAULT '',
    action TEXT NOT NULL CHECK(action IN ('check','approved','downloaded','applied','failed','rolled_back')),
    detail TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS download_events(
    id INTEGER PRIMARY KEY,
    buyer_id INTEGER NOT NULL REFERENCES buyers(id) ON DELETE CASCADE,
    license_id INTEGER REFERENCES licenses(id) ON DELETE SET NULL,
    product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
    release_id INTEGER,
    release_kind TEXT NOT NULL CHECK(release_kind IN ('application','theme','addon','download')),
    version TEXT NOT NULL DEFAULT '',
    filename TEXT NOT NULL DEFAULT '',
    sha256 TEXT NOT NULL DEFAULT '',
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS product_translations(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    locale TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    slug TEXT NOT NULL DEFAULT '',
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    keywords TEXT NOT NULL DEFAULT '',
    canonical_url TEXT NOT NULL DEFAULT '',
    og_image TEXT NOT NULL DEFAULT '',
    schema_json TEXT NOT NULL DEFAULT '{}',
    faq_schema_json TEXT NOT NULL DEFAULT '{}',
    breadcrumb_schema_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published')),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id,locale)
);
CREATE TABLE IF NOT EXISTS product_assets(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK(kind IN ('screenshot','video','demo','document','download','og_image','icon')),
    label TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    storage_path TEXT NOT NULL DEFAULT '',
    sha256 TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','revoked')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS product_prices(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    currency TEXT NOT NULL,
    amount_minor INTEGER NOT NULL CHECK(amount_minor >= 0),
    compare_minor INTEGER NOT NULL DEFAULT 0 CHECK(compare_minor >= 0),
    tax_mode TEXT NOT NULL DEFAULT 'exclusive' CHECK(tax_mode IN ('exclusive','inclusive','not_applicable')),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id,currency)
);
CREATE TABLE IF NOT EXISTS product_categories(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('draft','published'))
);
CREATE TABLE IF NOT EXISTS product_category_links(
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES product_categories(id) ON DELETE CASCADE,
    PRIMARY KEY(product_id,category_id)
);
CREATE TABLE IF NOT EXISTS product_tags(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('draft','published'))
);
CREATE TABLE IF NOT EXISTS product_tag_links(
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    tag_id INTEGER NOT NULL REFERENCES product_tags(id) ON DELETE CASCADE,
    PRIMARY KEY(product_id,tag_id)
);
CREATE TABLE IF NOT EXISTS product_reviews(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    buyer_id INTEGER REFERENCES buyers(id) ON DELETE SET NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id,buyer_id)
);
CREATE TABLE IF NOT EXISTS product_favorites(
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    buyer_id INTEGER NOT NULL REFERENCES buyers(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(product_id,buyer_id)
);
CREATE TABLE IF NOT EXISTS product_translation_jobs(
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    source_locale TEXT NOT NULL,
    target_locales_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','running','completed','failed')),
    provider TEXT NOT NULL DEFAULT 'openai',
    model TEXT NOT NULL DEFAULT '',
    error TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TEXT
);
CREATE TABLE IF NOT EXISTS product_translation_revisions(
    id INTEGER PRIMARY KEY,
    job_id INTEGER NOT NULL REFERENCES product_translation_jobs(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    locale TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','approved','rejected')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TEXT
);
CREATE TABLE IF NOT EXISTS theme_packages(
    id INTEGER PRIMARY KEY,
    theme_id INTEGER NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    version TEXT NOT NULL,
    package_name TEXT NOT NULL,
    storage_path TEXT NOT NULL,
    sha256 TEXT NOT NULL,
    signature TEXT NOT NULL DEFAULT '',
    requirements_json TEXT NOT NULL DEFAULT '{}',
    changelog TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published','revoked')),
    rollback_of INTEGER REFERENCES theme_packages(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TEXT,
    UNIQUE(theme_id,version)
);
CREATE INDEX IF NOT EXISTS theme_packages_lookup ON theme_packages(theme_id,status,version);
CREATE TABLE IF NOT EXISTS billing_addresses(
    id INTEGER PRIMARY KEY,
    buyer_id INTEGER NOT NULL REFERENCES buyers(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    company TEXT NOT NULL DEFAULT '',
    country_code TEXT NOT NULL,
    region TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    address_line TEXT NOT NULL,
    tax_number TEXT NOT NULL DEFAULT '',
    is_default INTEGER NOT NULL DEFAULT 0 CHECK(is_default IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tax_profiles(
    id INTEGER PRIMARY KEY,
    country_code TEXT NOT NULL,
    region TEXT NOT NULL DEFAULT '',
    tax_name TEXT NOT NULL,
    rate_basis_points INTEGER NOT NULL DEFAULT 0 CHECK(rate_basis_points BETWEEN 0 AND 10000),
    inclusive INTEGER NOT NULL DEFAULT 0 CHECK(inclusive IN (0,1)),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(country_code,region,tax_name)
);
CREATE TABLE IF NOT EXISTS coupons(
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    discount_type TEXT NOT NULL CHECK(discount_type IN ('percent','fixed')),
    discount_value INTEGER NOT NULL CHECK(discount_value >= 0),
    currency TEXT NOT NULL DEFAULT '',
    usage_limit INTEGER NOT NULL DEFAULT 0 CHECK(usage_limit >= 0),
    used_count INTEGER NOT NULL DEFAULT 0 CHECK(used_count >= 0),
    starts_at TEXT,
    ends_at TEXT,
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1))
);
CREATE TABLE IF NOT EXISTS coupon_redemptions(
    coupon_id INTEGER NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    buyer_id INTEGER REFERENCES buyers(id) ON DELETE SET NULL,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    redeemed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(coupon_id,order_id)
);
CREATE TABLE IF NOT EXISTS order_adjustments(
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK(kind IN ('tax','discount','fee','refund')),
    code TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL DEFAULT '',
    amount_minor INTEGER NOT NULL,
    currency TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS currency_rates(
    id INTEGER PRIMARY KEY,
    base_currency TEXT NOT NULL,
    quote_currency TEXT NOT NULL,
    rate TEXT NOT NULL,
    provider TEXT NOT NULL DEFAULT 'manual',
    observed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(base_currency,quote_currency,observed_at)
);
CREATE TABLE IF NOT EXISTS manual_payments(
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    reference TEXT NOT NULL UNIQUE,
    proof_path TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
    reviewed_by INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    reviewed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS license_revocations(
    id INTEGER PRIMARY KEY,
    license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    effective_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS releases_lookup ON application_releases(product_id,channel,status,version);
CREATE INDEX IF NOT EXISTS product_translations_lookup ON product_translations(product_id,locale,status);
CREATE INDEX IF NOT EXISTS product_assets_lookup ON product_assets(product_id,kind,status,sort_order);
CREATE INDEX IF NOT EXISTS product_prices_lookup ON product_prices(product_id,currency,active);
CREATE INDEX IF NOT EXISTS product_reviews_lookup ON product_reviews(product_id,status,created_at);
CREATE INDEX IF NOT EXISTS license_plans_lookup ON license_plans(product_id,status,code);
CREATE INDEX IF NOT EXISTS license_entitlements_lookup ON license_entitlements(license_id,entitlement);
CREATE INDEX IF NOT EXISTS installations_license ON installations(license_id,status,last_seen_at);
CREATE INDEX IF NOT EXISTS update_events_installation ON update_events(installation_id,created_at);
CREATE INDEX IF NOT EXISTS download_events_buyer ON download_events(buyer_id,created_at);
SQL);
    try { lc_db()->exec("ALTER TABLE license_revocations ADD COLUMN expires_at TEXT"); } catch (Throwable $e) { }
    try { lc_db()->exec("ALTER TABLE license_revocations ADD COLUMN created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) { }
}
setup();
release_schema();
function lc_plan_name(array $plan, string $locale): string {
    $fallback = (string)($plan['name'] ?? '');
    $planId = (int)($plan['id'] ?? 0);
    if ($planId <= 0 || $fallback === '') return $fallback;
    $translation = one('SELECT name FROM license_plan_translations WHERE plan_id=? AND locale=? AND status=? LIMIT 1', [$planId, $locale, 'published']);
    return $translation && trim((string)$translation['name']) !== '' ? (string)$translation['name'] : $fallback;
}
function lc_plan_description(array $plan, string $locale): string {
    $fallback = (string)($plan['description'] ?? '');
    $planId = (int)($plan['id'] ?? 0);
    if ($planId <= 0) return $fallback;
    $translation = one('SELECT description FROM license_plan_translations WHERE plan_id=? AND locale=? AND status=? LIMIT 1', [$planId, $locale, 'published']);
    return $translation && trim((string)$translation['description']) !== '' ? (string)$translation['description'] : $fallback;
}
lc_db()->exec("CREATE TABLE IF NOT EXISTS product_metrics(product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,views INTEGER NOT NULL DEFAULT 0,downloads INTEGER NOT NULL DEFAULT 0,sales INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
lc_db()->exec("CREATE TABLE IF NOT EXISTS release_subscriptions(id INTEGER PRIMARY KEY,buyer_id INTEGER NOT NULL REFERENCES buyers(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,locale TEXT NOT NULL DEFAULT 'en',enabled INTEGER NOT NULL DEFAULT 1 CHECK(enabled IN(0,1)),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(buyer_id,product_id))");
lc_db()->exec("CREATE INDEX IF NOT EXISTS release_subscriptions_product ON release_subscriptions(product_id,enabled)");
foreach ([
    "ALTER TABLE product_translations ADD COLUMN faq_schema_json TEXT NOT NULL DEFAULT '{}'",
    "ALTER TABLE product_translations ADD COLUMN breadcrumb_schema_json TEXT NOT NULL DEFAULT '{}'",
] as $catalogMigration) { try { lc_db()->exec($catalogMigration); } catch (Throwable $e) {} }
foreach ([
    "ALTER TABLE theme_packages ADD COLUMN product_id INTEGER REFERENCES products(id) ON DELETE SET NULL",
    "ALTER TABLE theme_packages ADD COLUMN parent_package_id INTEGER REFERENCES theme_packages(id) ON DELETE SET NULL",
    "ALTER TABLE theme_packages ADD COLUMN preview_video_url TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE theme_packages ADD COLUMN theme_config_json TEXT NOT NULL DEFAULT '{}'",
] as $themePackageMigration) { try { lc_db()->exec($themePackageMigration); } catch (Throwable $e) {} }
foreach ([
    "ALTER TABLE licenses ADD COLUMN product_id INTEGER REFERENCES products(id) ON DELETE SET NULL",
    "ALTER TABLE licenses ADD COLUMN plan_id INTEGER REFERENCES license_plans(id) ON DELETE SET NULL",
    "ALTER TABLE licenses ADD COLUMN support_until TEXT",
    "ALTER TABLE licenses ADD COLUMN updates_until TEXT"
] as $licenseMigration) { try { lc_db()->exec($licenseMigration); } catch (Throwable $e) { } }
try { lc_db()->exec("ALTER TABLE license_plans ADD COLUMN license_type TEXT NOT NULL DEFAULT 'single_site'"); } catch (Throwable $e) { }
// Pricing cards may link to /contact?plan=... for backwards compatibility;
// route a selected plan into the authenticated checkout flow instead.
if ($path === '/contact' && isset($_GET['plan'])) {
    $plan = (string) $_GET['plan'];
    if (in_array($plan, ['Starter', 'Studio', 'Agency'], true)) {
        header('Location: ./checkout?plan=' . rawurlencode($plan), true, 302);
        exit;
    }
}
// Keep legacy manual plan defaults, while explicit product plans remain authoritative.
lc_db()->exec("DROP TRIGGER IF EXISTS license_plan_domain_limit; CREATE TRIGGER license_plan_domain_limit AFTER INSERT ON licenses WHEN NEW.plan_id IS NULL BEGIN UPDATE licenses SET domain_limit=CASE WHEN product LIKE '%Agency%' THEN 10 WHEN product LIKE '%Studio%' THEN 3 ELSE 1 END WHERE id=NEW.id; END");
$sessionPath = dirname(lc_env('LICENSE_DB_PATH', LC_ROOT.'/storage/licenses.sqlite')).'/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path($sessionPath);
    session_start();
}
require __DIR__.'/theme_checkout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($path, ['/account/login', '/account/register', '/account/forgot-password'], true)) {
    rate_limit('buyer-auth:'.($path).':'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 600);
}

// The sales centre must not load analytics before an explicit visitor choice.
ob_start(static function (string $html): string {
    $lang = function_exists('lc_public_locale') ? lc_public_locale() : 'tr';
    $localeNames = ['tr'=>'Türkçe','en'=>'English','de'=>'Deutsch','fr'=>'Français','es'=>'Español','it'=>'Italiano','pt'=>'Português','ru'=>'Русский','ar'=>'العربية','fa'=>'فارسی','nl'=>'Nederlands','zh'=>'中文','ja'=>'日本語','ko'=>'한국어'];
    $options = '';
    foreach ($localeNames as $code => $name) {
        $options .= '<option value="?lang='.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'"'.($lang === $code ? ' selected' : '').'>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</option>';
    }
    $html = preg_replace('#<span class="locale-switch">.*?</span>#s', '<label class="locale-select"><span class="sr-only">Language</span><select onchange="if(this.value)location.href=this.value">'.str_replace('<option', '<option', $options).'</select></label>', $html) ?? $html;
    $buyerNav = [
        'tr'=>['Güvenlik','Ürünler','Güncellemeler','Sürüm bildirimleri','İndirmeler','Favoriler'],
        'en'=>['Security','Products','Updates','Release alerts','Downloads','Favorites'],
        'de'=>['Sicherheit','Produkte','Updates','Versionsbenachrichtigungen','Downloads','Favoriten'],
        'fr'=>['Sécurité','Produits','Mises à jour','Alertes de version','Téléchargements','Favoris'],
        'es'=>['Seguridad','Productos','Actualizaciones','Alertas de versiones','Descargas','Favoritos'],
        'it'=>['Sicurezza','Prodotti','Aggiornamenti','Avvisi sulle versioni','Download','Preferiti'],
        'pt'=>['Segurança','Produtos','Atualizações','Alertas de versões','Downloads','Favoritos'],
        'ru'=>['Безопасность','Продукты','Обновления','Уведомления о версиях','Загрузки','Избранное'],
        'ar'=>['الأمان','المنتجات','التحديثات','تنبيهات الإصدارات','التنزيلات','المفضلة'],
        'fa'=>['امنیت','محصولات','به‌روزرسانی‌ها','هشدار نسخه‌ها','دانلودها','موردعلاقه‌ها'],
        'nl'=>['Beveiliging','Producten','Updates','Releasemeldingen','Downloads','Favorieten'],
        'zh'=>['安全','产品','更新','版本提醒','下载','收藏'],
        'ja'=>['セキュリティ','製品','更新','リリース通知','ダウンロード','お気に入り'],
        'ko'=>['보안','제품','업데이트','릴리스 알림','다운로드','즐겨찾기'],
    ][$lang] ?? ['Security','Products','Updates','Release alerts','Downloads','Favorites'];
    $html = str_replace('<a href="./account/security">Security</a>', '<a href="./account/security">'.$buyerNav[0].'</a><a href="./account/entitlements">'.$buyerNav[1].'</a><a href="./account/updates">'.$buyerNav[2].'</a><a href="./account/release-alerts">'.$buyerNav[3].'</a><a href="./account/download-history">'.$buyerNav[4].'</a><a href="./account/favorites">'.$buyerNav[5].'</a>', $html);
    $translations = [
        'tr'=>['Product'=>'Ürün','Pricing'=>'Fiyatlandırma','Themes'=>'Temalar','Contact'=>'İletişim','Buy now'=>'Satın al','Sell transport websites with confidence.'=>'Her sektöre uygun tema ve yazılımı güvenle keşfedin.','Transport business platform'=>'Global tema ve yazılım marketplace','One polished CMS for local movers, logistics teams and international transport brands. Built-in multilingual pages, quotation workflows, theme delivery and domain licensing.'=>'Kurumsal web siteleri, dijital ürünler ve farklı sektörler için profesyonel yazılım. Çok dilli içerik, tema teslimi, güvenli lisans ve güncelleme yönetimi dahildir.','Buy Transport CMS →'=>'TofiDesk yazılımını satın al →','Explore themes'=>'Temaları keşfet','14 languages'=>'14 dil','Public site, content and RTL support for global transport businesses.'=>'Global işletmeler için web sitesi, içerik ve RTL desteği.','Bind each purchase to approved customer domains from one centre.'=>'Her satın alımı tek merkezden onaylı müşteri alan adlarına bağlayın.','Publish safe branded themes to eligible installations.'=>'Güvenli markalı temaları uygun kurulumlara yayınlayın.','Themes made for transport brands'=>'Global markalar için profesyonel temalar','All languages'=>'Tüm diller','Coastal transport and local moving'=>'Lojistik, hizmet ve kurumsal web siteleri','City logistics and scheduled freight'=>'Modern işletmeler ve dijital ürünler','Minimal premium home-moving brand'=>'Premium, sade ve dönüşüm odaklı tasarım','Included software features'=>'Dahil edilen yazılım özellikleri','Quote and appointment requests, service pages, gallery, multilingual blog, social/SEO metadata, sitemap, Open Graph, SMTP queue, IMAP collection worker, protected media, backups and role-based administration.'=>'Teklif ve randevu talepleri, hizmet sayfaları, galeri, çok dilli blog, sosyal medya ve SEO meta alanları, sitemap, Open Graph, SMTP kuyruğu, korumalı medya, yedekleme ve rol tabanlı yönetim.','Single Site'=>'Tek alan adı','Licensed software for transport businesses'=>'Global yazılım ve tema satış platformu','We use optional analytics cookies to improve the sales experience.'=>'Satış deneyimini geliştirmek için isteğe bağlı analitik çerezler kullanıyoruz.','Only necessary'=>'Yalnızca gerekli','Accept analytics'=>'Analitiği kabul et','Licence control'=>'Lisans yönetimi','Theme delivery'=>'Tema teslimi','Themes made for transport brands'=>'Her sektör için profesyonel temalar','Included software features'=>'Dahil edilen yazılım özellikleri','Only necessary'=>'Yalnızca gerekli','Accept analytics'=>'Analitiği kabul et','We use optional analytics cookies to improve the sales experience.'=>'Satış deneyimini geliştirmek için isteğe bağlı analitik çerezler kullanıyoruz.'],
        'de'=>['Product'=>'Produkt','Pricing'=>'Preise','Themes'=>'Themes','Contact'=>'Kontakt','Buy now'=>'Jetzt kaufen','Explore themes'=>'Themes entdecken','Build your digital presence with confidence.'=>'Bauen Sie Ihre digitale Präsenz mit Vertrauen auf.','Explore software →'=>'Software entdecken →','Professional themes for every industry'=>'Professionelle Themes für jede Branche','Global software & theme marketplace'=>'Globaler Marktplatz für Software und Themes','Public site, content and RTL support for global businesses.'=>'Website-, Inhalts- und RTL-Unterstützung für globale Unternehmen.'],
        'fr'=>['Product'=>'Produit','Pricing'=>'Tarifs','Themes'=>'Thèmes','Contact'=>'Contact','Buy now'=>'Acheter','Explore themes'=>'Découvrir les thèmes','Build your digital presence with confidence.'=>'Développez votre présence numérique en toute confiance.','Explore software →'=>'Découvrir les logiciels →','Professional themes for every industry'=>'Des thèmes professionnels pour chaque secteur','Global software & theme marketplace'=>'Marketplace mondiale de logiciels et de thèmes','Public site, content and RTL support for global businesses.'=>'Site public, contenu et prise en charge RTL pour les entreprises internationales.'],
        'es'=>['Product'=>'Producto','Pricing'=>'Precios','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Explore themes'=>'Ver temas','Build your digital presence with confidence.'=>'Construye tu presencia digital con confianza.','Explore software →'=>'Explorar software →','Professional themes for every industry'=>'Temas profesionales para cada sector','Global software & theme marketplace'=>'Marketplace global de software y temas','Public site, content and RTL support for global businesses.'=>'Sitio público, contenido y soporte RTL para empresas globales.'],
        'it'=>['Product'=>'Prodotto','Pricing'=>'Prezzi','Themes'=>'Temi','Contact'=>'Contatti','Buy now'=>'Acquista','Explore themes'=>'Scopri i temi','Build your digital presence with confidence.'=>'Costruisci la tua presenza digitale con sicurezza.','Explore software →'=>'Scopri il software →','Professional themes for every industry'=>'Temi professionali per ogni settore','Global software & theme marketplace'=>'Marketplace globale di software e temi','Public site, content and RTL support for global businesses.'=>'Sito pubblico, contenuti e supporto RTL per aziende globali.'],
        'pt'=>['Product'=>'Produto','Pricing'=>'Preços','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Explore themes'=>'Explorar temas','Build your digital presence with confidence.'=>'Construa a sua presença digital com confiança.','Explore software →'=>'Explorar software →','Professional themes for every industry'=>'Temas profissionais para todos os setores','Global software & theme marketplace'=>'Marketplace global de software e temas','Public site, content and RTL support for global businesses.'=>'Site público, conteúdo e suporte RTL para empresas globais.'],
        'nl'=>['Product'=>'Product','Pricing'=>'Prijzen','Themes'=>'Thema’s','Contact'=>'Contact','Buy now'=>'Kopen','Explore themes'=>'Thema’s bekijken','Build your digital presence with confidence.'=>'Bouw vol vertrouwen aan uw digitale aanwezigheid.','Explore software →'=>'Software ontdekken →','Professional themes for every industry'=>'Professionele thema’s voor elke sector','Global software & theme marketplace'=>'Wereldwijde marktplaats voor software en thema’s','Public site, content and RTL support for global businesses.'=>'Publieke website, content en RTL-ondersteuning voor wereldwijde bedrijven.'],
        'ru'=>['Product'=>'Продукт','Pricing'=>'Цены','Themes'=>'Темы','Contact'=>'Контакты','Buy now'=>'Купить','Explore themes'=>'Все темы','Build your digital presence with confidence.'=>'Создавайте цифровое присутствие с уверенностью.','Explore software →'=>'Открыть программное обеспечение →','Professional themes for every industry'=>'Профессиональные темы для любой отрасли','Global software & theme marketplace'=>'Глобальный маркетплейс программного обеспечения и тем','Public site, content and RTL support for global businesses.'=>'Публичный сайт, контент и поддержка RTL для глобального бизнеса.'],
        'ar'=>['Product'=>'المنتج','Pricing'=>'الأسعار','Themes'=>'القوالب','Contact'=>'اتصل بنا','Buy now'=>'اشتر الآن','Explore themes'=>'استكشف القوالب','Build your digital presence with confidence.'=>'ابنِ حضورك الرقمي بثقة.','Explore software →'=>'استكشف البرامج →','Professional themes for every industry'=>'قوالب احترافية لكل قطاع','Global software & theme marketplace'=>'سوق عالمي للبرامج والقوالب','Public site, content and RTL support for global businesses.'=>'موقع ومحتوى ودعم RTL للشركات العالمية.'],
        'fa'=>['Product'=>'محصول','Pricing'=>'قیمت‌ها','Themes'=>'قالب‌ها','Contact'=>'تماس','Buy now'=>'خرید','Explore themes'=>'مشاهده قالب‌ها','Build your digital presence with confidence.'=>'حضور دیجیتال خود را با اطمینان بسازید.','Explore software →'=>'مشاهده نرم‌افزارها →','Professional themes for every industry'=>'قالب‌های حرفه‌ای برای هر صنعت','Global software & theme marketplace'=>'بازار جهانی نرم‌افزار و قالب','Public site, content and RTL support for global businesses.'=>'وب‌سایت، محتوا و پشتیبانی RTL برای کسب‌وکارهای جهانی.'],
        'zh'=>['Product'=>'产品','Pricing'=>'价格','Themes'=>'主题','Contact'=>'联系','Buy now'=>'立即购买','Explore themes'=>'浏览主题','Build your digital presence with confidence.'=>'自信地打造您的数字品牌。','Explore software →'=>'探索软件 →','Professional themes for every industry'=>'适用于各行业的专业主题','Global software & theme marketplace'=>'全球软件与网站主题市场','Public site, content and RTL support for global businesses.'=>'面向全球企业的网站、内容和 RTL 支持。'],
        'ja'=>['Product'=>'製品','Pricing'=>'料金','Themes'=>'テーマ','Contact'=>'お問い合わせ','Buy now'=>'購入する','Explore themes'=>'テーマを見る','Build your digital presence with confidence.'=>'自信を持ってデジタルプレゼンスを構築できます。','Explore software →'=>'ソフトウェアを見る →','Professional themes for every industry'=>'あらゆる業界向けのプロフェッショナルテーマ','Global software & theme marketplace'=>'グローバルなソフトウェア・テーママーケットプレイス','Public site, content and RTL support for global businesses.'=>'グローバル企業向けのサイト、コンテンツ、RTL 対応。'],
        'ko'=>['Product'=>'제품','Pricing'=>'가격','Themes'=>'테마','Contact'=>'문의','Buy now'=>'구매하기','Explore themes'=>'테마 보기','Build your digital presence with confidence.'=>'자신 있게 디지털 비즈니스를 구축하세요.','Explore software →'=>'소프트웨어 둘러보기 →','Professional themes for every industry'=>'모든 산업을 위한 전문 테마','Global software & theme marketplace'=>'글로벌 소프트웨어 및 테마 마켓플레이스','Public site, content and RTL support for global businesses.'=>'글로벌 비즈니스를 위한 사이트, 콘텐츠 및 RTL 지원.'],
    ];
    $globalLanding = [
        'tr'=>['Transport business platform'=>'Global tema ve yazılım marketplace','Sell transport websites with confidence.'=>'Her sektör için tema ve yazılımı güvenle keşfedin.','One polished CMS for local movers, logistics teams and international transport brands. Built-in multilingual pages, quotation workflows, theme delivery and domain licensing.'=>'Her sektöre uygun web sitesi, tema ve yazılım ürünleri için çok dilli satış, lisans ve güncelleme platformu.','Themes made for transport brands'=>'Her sektör için profesyonel temalar','Licensed software for transport businesses'=>'Global yazılım ve tema platformu'],
        'en'=>['Transport business platform'=>'Global software & theme marketplace','Sell transport websites with confidence.'=>'Discover professional software and themes for every industry.','One polished CMS for local movers, logistics teams and international transport brands. Built-in multilingual pages, quotation workflows, theme delivery and domain licensing.'=>'A global marketplace for professional websites, software and themes with multilingual sales, licensing and updates.','Themes made for transport brands'=>'Professional themes for every industry','Licensed software for transport businesses'=>'Global software and theme platform'],
        'de'=>['Transport business platform'=>'Globaler Software- und Theme-Marktplatz','Sell transport websites with confidence.'=>'Entdecken Sie professionelle Software und Themes für jede Branche.','Themes made for transport brands'=>'Professionelle Themes für jede Branche','Licensed software for transport businesses'=>'Globale Software- und Theme-Plattform'],
        'fr'=>['Transport business platform'=>'Marketplace mondial de logiciels et thèmes','Sell transport websites with confidence.'=>'Découvrez des logiciels et thèmes professionnels pour chaque secteur.','Themes made for transport brands'=>'Thèmes professionnels pour chaque secteur','Licensed software for transport businesses'=>'Plateforme mondiale de logiciels et thèmes'],
        'es'=>['Transport business platform'=>'Marketplace global de software y temas','Sell transport websites with confidence.'=>'Descubre software y temas profesionales para cada sector.','Themes made for transport brands'=>'Temas profesionales para cada sector','Licensed software for transport businesses'=>'Plataforma global de software y temas'],
        'it'=>['Transport business platform'=>'Marketplace globale di software e temi','Sell transport websites with confidence.'=>'Scopri software e temi professionali per ogni settore.','Themes made for transport brands'=>'Temi professionali per ogni settore','Licensed software for transport businesses'=>'Piattaforma globale di software e temi'],
        'pt'=>['Transport business platform'=>'Marketplace global de software e temas','Sell transport websites with confidence.'=>'Descubra software e temas profissionais para todos os setores.','Themes made for transport brands'=>'Temas profissionais para todos os setores','Licensed software for transport businesses'=>'Plataforma global de software e temas'],
        'nl'=>['Transport business platform'=>'Wereldwijde software- en themamarktplaats','Sell transport websites with confidence.'=>'Ontdek professionele software en thema’s voor elke sector.','Themes made for transport brands'=>'Professionele thema’s voor elke sector','Licensed software for transport businesses'=>'Wereldwijd software- en themaplatform'],
        'ru'=>['Transport business platform'=>'Глобальный маркетплейс ПО и тем','Sell transport websites with confidence.'=>'Открывайте профессиональные решения и темы для любой отрасли.','Themes made for transport brands'=>'Профессиональные темы для любой отрасли','Licensed software for transport businesses'=>'Глобальная платформа ПО и тем'],
        'ar'=>['Transport business platform'=>'سوق عالمي للبرامج والقوالب','Sell transport websites with confidence.'=>'اكتشف البرامج والقوالب الاحترافية لكل قطاع.','Themes made for transport brands'=>'قوالب احترافية لكل قطاع','Licensed software for transport businesses'=>'منصة عالمية للبرامج والقوالب'],
        'fa'=>['Transport business platform'=>'بازار جهانی نرم‌افزار و قالب','Sell transport websites with confidence.'=>'نرم‌افزار و قالب‌های حرفه‌ای برای هر صنعت را کشف کنید.','Themes made for transport brands'=>'قالب‌های حرفه‌ای برای هر صنعت','Licensed software for transport businesses'=>'پلتفرم جهانی نرم‌افزار و قالب'],
        'zh'=>['Transport business platform'=>'全球软件与主题市场','Sell transport websites with confidence.'=>'探索适用于各行业的专业软件和主题。','Themes made for transport brands'=>'适用于各行业的专业主题','Licensed software for transport businesses'=>'全球软件与主题平台'],
        'ja'=>['Transport business platform'=>'グローバルなソフトウェア・テーママーケットプレイス','Sell transport websites with confidence.'=>'あらゆる業界向けのプロフェッショナルなソフトウェアとテーマをご覧ください。','Themes made for transport brands'=>'あらゆる業界向けのプロフェッショナルテーマ','Licensed software for transport businesses'=>'グローバルなソフトウェア・テーマプラットフォーム'],
        'ko'=>['Transport business platform'=>'글로벌 소프트웨어 및 테마 마켓플레이스','Sell transport websites with confidence.'=>'모든 산업을 위한 전문 소프트웨어와 테마를 만나보세요.','Themes made for transport brands'=>'모든 산업을 위한 전문 테마','Licensed software for transport businesses'=>'글로벌 소프트웨어 및 테마 플랫폼'],
    ];
    foreach ($globalLanding as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    $adminTerms = [
        'tr'=>['Dashboard'=>'Panel','Products'=>'Ürünler','Product catalogue'=>'Ürün kataloğu','Themes'=>'Temalar','Theme packages'=>'Tema paketleri','Licences'=>'Lisanslar','Orders'=>'Siparişler','Requests'=>'Talepler','Support'=>'Destek','Health'=>'Sistem sağlığı','Settings'=>'Ayarlar','Sign out'=>'Çıkış yap','Product name'=>'Ürün adı','Type'=>'Tür','Status'=>'Durum','Draft'=>'Taslak','Published'=>'Yayınlandı','Software'=>'Yazılım','Theme'=>'Tema','Add-on'=>'Eklenti','Create product'=>'Ürün oluştur','Save product'=>'Ürünü kaydet','Marketplace search'=>'Marketplace araması','Search marketplace'=>'Marketplace’te ara'],
        'en'=>['Dashboard'=>'Dashboard','Products'=>'Products','Product catalogue'=>'Product catalogue','Themes'=>'Themes','Theme packages'=>'Theme packages','Licences'=>'Licences','Orders'=>'Orders','Requests'=>'Requests','Support'=>'Support','Health'=>'Health','Settings'=>'Settings','Sign out'=>'Sign out','Product name'=>'Product name','Type'=>'Type','Status'=>'Status','Draft'=>'Draft','Published'=>'Published','Software'=>'Software','Theme'=>'Theme','Add-on'=>'Add-on','Create product'=>'Create product','Save product'=>'Save product','Marketplace search'=>'Marketplace search','Search marketplace'=>'Search marketplace'],
        'de'=>['Dashboard'=>'Dashboard','Products'=>'Produkte','Product catalogue'=>'Produktkatalog','Themes'=>'Themes','Theme packages'=>'Theme-Pakete','Licences'=>'Lizenzen','Orders'=>'Bestellungen','Requests'=>'Anfragen','Support'=>'Support','Health'=>'Systemstatus','Settings'=>'Einstellungen','Sign out'=>'Abmelden','Product name'=>'Produktname','Type'=>'Typ','Status'=>'Status','Draft'=>'Entwurf','Published'=>'Veröffentlicht','Software'=>'Software','Theme'=>'Theme','Add-on'=>'Add-on','Create product'=>'Produkt erstellen','Save product'=>'Produkt speichern','Marketplace search'=>'Marketplace-Suche','Search marketplace'=>'Marketplace durchsuchen'],
        'fr'=>['Dashboard'=>'Tableau de bord','Products'=>'Produits','Product catalogue'=>'Catalogue de produits','Themes'=>'Thèmes','Theme packages'=>'Packs de thèmes','Licences'=>'Licences','Orders'=>'Commandes','Requests'=>'Demandes','Support'=>'Assistance','Health'=>'État du système','Settings'=>'Paramètres','Sign out'=>'Déconnexion','Product name'=>'Nom du produit','Type'=>'Type','Status'=>'Statut','Draft'=>'Brouillon','Published'=>'Publié','Software'=>'Logiciel','Theme'=>'Thème','Add-on'=>'Extension','Create product'=>'Créer le produit','Save product'=>'Enregistrer le produit','Marketplace search'=>'Recherche marketplace','Search marketplace'=>'Rechercher'],
        'es'=>['Dashboard'=>'Panel','Products'=>'Productos','Product catalogue'=>'Catálogo de productos','Themes'=>'Temas','Theme packages'=>'Paquetes de temas','Licences'=>'Licencias','Orders'=>'Pedidos','Requests'=>'Solicitudes','Support'=>'Soporte','Health'=>'Estado del sistema','Settings'=>'Ajustes','Sign out'=>'Cerrar sesión','Product name'=>'Nombre del producto','Type'=>'Tipo','Status'=>'Estado','Draft'=>'Borrador','Published'=>'Publicado','Software'=>'Software','Theme'=>'Tema','Add-on'=>'Complemento','Create product'=>'Crear producto','Save product'=>'Guardar producto','Marketplace search'=>'Búsqueda del marketplace','Search marketplace'=>'Buscar en el marketplace'],
        'it'=>['Dashboard'=>'Dashboard','Products'=>'Prodotti','Product catalogue'=>'Catalogo prodotti','Themes'=>'Temi','Theme packages'=>'Pacchetti tema','Licences'=>'Licenze','Orders'=>'Ordini','Requests'=>'Richieste','Support'=>'Supporto','Health'=>'Stato sistema','Settings'=>'Impostazioni','Sign out'=>'Esci','Product name'=>'Nome prodotto','Type'=>'Tipo','Status'=>'Stato','Draft'=>'Bozza','Published'=>'Pubblicato','Software'=>'Software','Theme'=>'Tema','Add-on'=>'Componente aggiuntivo','Create product'=>'Crea prodotto','Save product'=>'Salva prodotto','Marketplace search'=>'Ricerca marketplace','Search marketplace'=>'Cerca nel marketplace'],
        'pt'=>['Dashboard'=>'Painel','Products'=>'Produtos','Product catalogue'=>'Catálogo de produtos','Themes'=>'Temas','Theme packages'=>'Pacotes de temas','Licences'=>'Licenças','Orders'=>'Pedidos','Requests'=>'Solicitações','Support'=>'Suporte','Health'=>'Estado do sistema','Settings'=>'Definições','Sign out'=>'Sair','Product name'=>'Nome do produto','Type'=>'Tipo','Status'=>'Estado','Draft'=>'Rascunho','Published'=>'Publicado','Software'=>'Software','Theme'=>'Tema','Add-on'=>'Extensão','Create product'=>'Criar produto','Save product'=>'Guardar produto','Marketplace search'=>'Pesquisa no marketplace','Search marketplace'=>'Pesquisar no marketplace'],
        'nl'=>['Dashboard'=>'Dashboard','Products'=>'Producten','Product catalogue'=>'Productcatalogus','Themes'=>'Thema’s','Theme packages'=>'Themapakketten','Licences'=>'Licenties','Orders'=>'Bestellingen','Requests'=>'Aanvragen','Support'=>'Ondersteuning','Health'=>'Systeemstatus','Settings'=>'Instellingen','Sign out'=>'Uitloggen','Product name'=>'Productnaam','Type'=>'Type','Status'=>'Status','Draft'=>'Concept','Published'=>'Gepubliceerd','Software'=>'Software','Theme'=>'Thema','Add-on'=>'Add-on','Create product'=>'Product maken','Save product'=>'Product opslaan','Marketplace search'=>'Marketplace zoeken','Search marketplace'=>'Marketplace doorzoeken'],
        'ru'=>['Dashboard'=>'Панель','Products'=>'Продукты','Product catalogue'=>'Каталог продуктов','Themes'=>'Темы','Theme packages'=>'Пакеты тем','Licences'=>'Лицензии','Orders'=>'Заказы','Requests'=>'Запросы','Support'=>'Поддержка','Health'=>'Состояние системы','Settings'=>'Настройки','Sign out'=>'Выйти','Product name'=>'Название продукта','Type'=>'Тип','Status'=>'Статус','Draft'=>'Черновик','Published'=>'Опубликовано','Software'=>'ПО','Theme'=>'Тема','Add-on'=>'Дополнение','Create product'=>'Создать продукт','Save product'=>'Сохранить продукт','Marketplace search'=>'Поиск на маркетплейсе','Search marketplace'=>'Искать на маркетплейсе'],
        'ar'=>['Dashboard'=>'لوحة التحكم','Products'=>'المنتجات','Product catalogue'=>'كتالوج المنتجات','Themes'=>'القوالب','Theme packages'=>'حزم القوالب','Licences'=>'التراخيص','Orders'=>'الطلبات','Requests'=>'الطلبات','Support'=>'الدعم','Health'=>'حالة النظام','Settings'=>'الإعدادات','Sign out'=>'تسجيل الخروج','Product name'=>'اسم المنتج','Type'=>'النوع','Status'=>'الحالة','Draft'=>'مسودة','Published'=>'منشور','Software'=>'برنامج','Theme'=>'قالب','Add-on'=>'إضافة','Create product'=>'إنشاء منتج','Save product'=>'حفظ المنتج','Marketplace search'=>'بحث السوق','Search marketplace'=>'البحث في السوق'],
        'fa'=>['Dashboard'=>'داشبورد','Products'=>'محصولات','Product catalogue'=>'کاتالوگ محصولات','Themes'=>'قالب‌ها','Theme packages'=>'بسته‌های قالب','Licences'=>'مجوزها','Orders'=>'سفارش‌ها','Requests'=>'درخواست‌ها','Support'=>'پشتیبانی','Health'=>'وضعیت سیستم','Settings'=>'تنظیمات','Sign out'=>'خروج','Product name'=>'نام محصول','Type'=>'نوع','Status'=>'وضعیت','Draft'=>'پیش‌نویس','Published'=>'منتشرشده','Software'=>'نرم‌افزار','Theme'=>'قالب','Add-on'=>'افزونه','Create product'=>'ایجاد محصول','Save product'=>'ذخیره محصول','Marketplace search'=>'جستجوی بازار','Search marketplace'=>'جستجو در بازار'],
        'zh'=>['Dashboard'=>'控制面板','Products'=>'产品','Product catalogue'=>'产品目录','Themes'=>'主题','Theme packages'=>'主题包','Licences'=>'许可证','Orders'=>'订单','Requests'=>'请求','Support'=>'支持','Health'=>'系统状态','Settings'=>'设置','Sign out'=>'退出','Product name'=>'产品名称','Type'=>'类型','Status'=>'状态','Draft'=>'草稿','Published'=>'已发布','Software'=>'软件','Theme'=>'主题','Add-on'=>'插件','Create product'=>'创建产品','Save product'=>'保存产品','Marketplace search'=>'市场搜索','Search marketplace'=>'搜索市场'],
        'ja'=>['Dashboard'=>'ダッシュボード','Products'=>'製品','Product catalogue'=>'製品カタログ','Themes'=>'テーマ','Theme packages'=>'テーマパッケージ','Licences'=>'ライセンス','Orders'=>'注文','Requests'=>'リクエスト','Support'=>'サポート','Health'=>'システム状態','Settings'=>'設定','Sign out'=>'ログアウト','Product name'=>'製品名','Type'=>'種類','Status'=>'状態','Draft'=>'下書き','Published'=>'公開済み','Software'=>'ソフトウェア','Theme'=>'テーマ','Add-on'=>'アドオン','Create product'=>'製品を作成','Save product'=>'製品を保存','Marketplace search'=>'マーケット検索','Search marketplace'=>'マーケットを検索'],
        'ko'=>['Dashboard'=>'대시보드','Products'=>'제품','Product catalogue'=>'제품 카탈로그','Themes'=>'테마','Theme packages'=>'테마 패키지','Licences'=>'라이선스','Orders'=>'주문','Requests'=>'요청','Support'=>'지원','Health'=>'시스템 상태','Settings'=>'설정','Sign out'=>'로그아웃','Product name'=>'제품명','Type'=>'유형','Status'=>'상태','Draft'=>'초안','Published'=>'게시됨','Software'=>'소프트웨어','Theme'=>'테마','Add-on'=>'애드온','Create product'=>'제품 만들기','Save product'=>'제품 저장','Marketplace search'=>'마켓 검색','Search marketplace'=>'마켓 검색'],
    ];
    $catalogTerms = [
        'tr'=>['Product quality review'=>'Ürün yayın kalite kontrolü','Product QA'=>'Ürün kalite kontrolü','Approve and publish'=>'Onayla ve yayınla','Complete missing fields'=>'Eksik alanları tamamlayın','Create licence plan'=>'Lisans planı oluştur','Issue licence'=>'Lisans oluştur','Licence plan'=>'Lisans planı','Billing period'=>'Faturalandırma dönemi','Domain limit'=>'Alan adı limiti','Support days'=>'Destek günü','Update days'=>'Güncelleme günü','Amount (minor units)'=>'Tutar (alt birim)','Choose product'=>'Ürün seçin','Product is not ready for publication.'=>'Ürün yayın için hazır değil.'],
        'de'=>['Product quality review'=>'Produkt-Qualitätsprüfung','Product QA'=>'Produktprüfung','Approve and publish'=>'Genehmigen und veröffentlichen','Complete missing fields'=>'Fehlende Felder vervollständigen','Create licence plan'=>'Lizenzplan erstellen','Issue licence'=>'Lizenz ausstellen','Licence plan'=>'Lizenzplan','Billing period'=>'Abrechnungszeitraum','Domain limit'=>'Domain-Limit','Support days'=>'Supporttage','Update days'=>'Updatetage','Amount (minor units)'=>'Betrag (Minor Units)','Choose product'=>'Produkt auswählen'],
        'fr'=>['Product quality review'=>'Contrôle qualité du produit','Product QA'=>'Contrôle produit','Approve and publish'=>'Approuver et publier','Complete missing fields'=>'Compléter les champs manquants','Create licence plan'=>'Créer un plan de licence','Issue licence'=>'Émettre une licence','Licence plan'=>'Plan de licence','Billing period'=>'Période de facturation','Domain limit'=>'Limite de domaines','Support days'=>'Jours de support','Update days'=>'Jours de mises à jour','Amount (minor units)'=>'Montant (unités mineures)','Choose product'=>'Choisir un produit'],
        'es'=>['Product quality review'=>'Revisión de calidad del producto','Product QA'=>'Control del producto','Approve and publish'=>'Aprobar y publicar','Complete missing fields'=>'Completar campos faltantes','Create licence plan'=>'Crear plan de licencia','Issue licence'=>'Emitir licencia','Licence plan'=>'Plan de licencia','Billing period'=>'Periodo de facturación','Domain limit'=>'Límite de dominios','Support days'=>'Días de soporte','Update days'=>'Días de actualizaciones','Amount (minor units)'=>'Importe (unidades menores)','Choose product'=>'Elegir producto'],
        'it'=>['Product quality review'=>'Controllo qualità prodotto','Product QA'=>'Controllo prodotto','Approve and publish'=>'Approva e pubblica','Complete missing fields'=>'Completa i campi mancanti','Create licence plan'=>'Crea piano licenza','Issue licence'=>'Emetti licenza','Licence plan'=>'Piano licenza','Billing period'=>'Periodo di fatturazione','Domain limit'=>'Limite domini','Support days'=>'Giorni di supporto','Update days'=>'Giorni di aggiornamento','Amount (minor units)'=>'Importo (unità minori)','Choose product'=>'Scegli prodotto'],
        'pt'=>['Product quality review'=>'Revisão de qualidade do produto','Product QA'=>'Controlo do produto','Approve and publish'=>'Aprovar e publicar','Complete missing fields'=>'Preencher campos em falta','Create licence plan'=>'Criar plano de licença','Issue licence'=>'Emitir licença','Licence plan'=>'Plano de licença','Billing period'=>'Período de faturação','Domain limit'=>'Limite de domínios','Support days'=>'Dias de suporte','Update days'=>'Dias de atualizações','Amount (minor units)'=>'Montante (unidades menores)','Choose product'=>'Escolher produto'],
        'nl'=>['Product quality review'=>'Productkwaliteitscontrole','Product QA'=>'Productcontrole','Approve and publish'=>'Goedkeuren en publiceren','Complete missing fields'=>'Ontbrekende velden invullen','Create licence plan'=>'Licentieplan maken','Issue licence'=>'Licentie uitgeven','Licence plan'=>'Licentieplan','Billing period'=>'Factureringsperiode','Domain limit'=>'Domeinlimiet','Support days'=>'Supportdagen','Update days'=>'Updatedagen','Amount (minor units)'=>'Bedrag (kleine eenheden)','Choose product'=>'Product kiezen'],
        'ru'=>['Product quality review'=>'Проверка качества продукта','Product QA'=>'Проверка продукта','Approve and publish'=>'Одобрить и опубликовать','Complete missing fields'=>'Заполните недостающие поля','Create licence plan'=>'Создать план лицензии','Issue licence'=>'Выдать лицензию','Licence plan'=>'План лицензии','Billing period'=>'Период оплаты','Domain limit'=>'Лимит доменов','Support days'=>'Дни поддержки','Update days'=>'Дни обновлений','Amount (minor units)'=>'Сумма в минимальных единицах','Choose product'=>'Выберите продукт'],
        'ar'=>['Product quality review'=>'مراجعة جودة المنتج','Product QA'=>'فحص المنتج','Approve and publish'=>'اعتماد ونشر','Complete missing fields'=>'أكمل الحقول الناقصة','Create licence plan'=>'إنشاء خطة ترخيص','Issue licence'=>'إصدار ترخيص','Licence plan'=>'خطة الترخيص','Billing period'=>'فترة الفوترة','Domain limit'=>'حد النطاقات','Support days'=>'أيام الدعم','Update days'=>'أيام التحديث','Amount (minor units)'=>'المبلغ بالوحدات الصغرى','Choose product'=>'اختر منتجاً'],
        'fa'=>['Product quality review'=>'بررسی کیفیت محصول','Product QA'=>'کنترل محصول','Approve and publish'=>'تأیید و انتشار','Complete missing fields'=>'فیلدهای ناقص را تکمیل کنید','Create licence plan'=>'ایجاد طرح مجوز','Issue licence'=>'صدور مجوز','Licence plan'=>'طرح مجوز','Billing period'=>'دوره صورتحساب','Domain limit'=>'حد دامنه','Support days'=>'روزهای پشتیبانی','Update days'=>'روزهای به‌روزرسانی','Amount (minor units)'=>'مبلغ (واحد کوچک)','Choose product'=>'انتخاب محصول'],
        'zh'=>['Product quality review'=>'产品质量审核','Product QA'=>'产品审核','Approve and publish'=>'批准并发布','Complete missing fields'=>'补全缺失字段','Create licence plan'=>'创建许可证方案','Issue licence'=>'签发许可证','Licence plan'=>'许可证方案','Billing period'=>'计费周期','Domain limit'=>'域名限制','Support days'=>'支持天数','Update days'=>'更新天数','Amount (minor units)'=>'金额（最小单位）','Choose product'=>'选择产品'],
        'ja'=>['Product quality review'=>'製品品質レビュー','Product QA'=>'製品審査','Approve and publish'=>'承認して公開','Complete missing fields'=>'不足項目を入力してください','Create licence plan'=>'ライセンスプランを作成','Issue licence'=>'ライセンスを発行','Licence plan'=>'ライセンスプラン','Billing period'=>'請求期間','Domain limit'=>'ドメイン上限','Support days'=>'サポート日数','Update days'=>'更新日数','Amount (minor units)'=>'金額（最小単位）','Choose product'=>'製品を選択'],
        'ko'=>['Product quality review'=>'제품 품질 검토','Product QA'=>'제품 심사','Approve and publish'=>'승인 및 게시','Complete missing fields'=>'누락된 필드를 입력하세요','Create licence plan'=>'라이선스 플랜 만들기','Issue licence'=>'라이선스 발급','Licence plan'=>'라이선스 플랜','Billing period'=>'청구 기간','Domain limit'=>'도메인 한도','Support days'=>'지원 일수','Update days'=>'업데이트 일수','Amount (minor units)'=>'금액(최소 단위)','Choose product'=>'제품 선택'],
        'en'=>['Product quality review'=>'Product quality review','Product QA'=>'Product QA','Approve and publish'=>'Approve and publish','Complete missing fields'=>'Complete missing fields','Create licence plan'=>'Create licence plan','Issue licence'=>'Issue licence','Licence plan'=>'Licence plan','Billing period'=>'Billing period','Domain limit'=>'Domain limit','Support days'=>'Support days','Update days'=>'Update days','Amount (minor units)'=>'Amount (minor units)','Choose product'=>'Choose product'],
    ];
    foreach ($catalogTerms as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    foreach ($adminTerms as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    $discoveryTerms = [
        'tr'=>['Marketplace'=>'Marketplace','Search'=>'Ara','Compare'=>'Karşılaştır','Popular'=>'Popüler','Newest'=>'Yeni','View product →'=>'Ürünü görüntüle →','Compare products'=>'Ürünleri karşılaştır','Product comparison'=>'Ürün karşılaştırma','Remove'=>'Kaldır','No products selected. Add products from the marketplace.'=>'Ürün seçilmedi. Marketplace üzerinden ürün ekleyin.'],
        'en'=>['Marketplace'=>'Marketplace','Search'=>'Search','Compare'=>'Compare','Popular'=>'Popular','Newest'=>'Newest','View product →'=>'View product →','Compare products'=>'Compare products','Product comparison'=>'Product comparison','Remove'=>'Remove','No products selected. Add products from the marketplace.'=>'No products selected. Add products from the marketplace.'],
        'de'=>['Marketplace'=>'Marktplatz','Search'=>'Suchen','Compare'=>'Vergleichen','Popular'=>'Beliebt','Newest'=>'Neueste','View product →'=>'Produkt ansehen →','Compare products'=>'Produkte vergleichen','Product comparison'=>'Produktvergleich','Remove'=>'Entfernen','No products selected. Add products from the marketplace.'=>'Keine Produkte ausgewählt. Fügen Sie Produkte aus dem Marktplatz hinzu.'],
        'fr'=>['Marketplace'=>'Marketplace','Search'=>'Rechercher','Compare'=>'Comparer','Popular'=>'Populaires','Newest'=>'Nouveautés','View product →'=>'Voir le produit →','Compare products'=>'Comparer les produits','Product comparison'=>'Comparaison de produits','Remove'=>'Supprimer','No products selected. Add products from the marketplace.'=>'Aucun produit sélectionné. Ajoutez des produits depuis la marketplace.'],
        'es'=>['Marketplace'=>'Marketplace','Search'=>'Buscar','Compare'=>'Comparar','Popular'=>'Populares','Newest'=>'Novedades','View product →'=>'Ver producto →','Compare products'=>'Comparar productos','Product comparison'=>'Comparación de productos','Remove'=>'Eliminar','No products selected. Add products from the marketplace.'=>'No hay productos seleccionados. Añade productos desde el marketplace.'],
        'it'=>['Marketplace'=>'Marketplace','Search'=>'Cerca','Compare'=>'Confronta','Popular'=>'Popolari','Newest'=>'Più recenti','View product →'=>'Vedi prodotto →','Compare products'=>'Confronta prodotti','Product comparison'=>'Confronto prodotti','Remove'=>'Rimuovi','No products selected. Add products from the marketplace.'=>'Nessun prodotto selezionato. Aggiungi prodotti dal marketplace.'],
        'pt'=>['Marketplace'=>'Marketplace','Search'=>'Pesquisar','Compare'=>'Comparar','Popular'=>'Populares','Newest'=>'Mais recentes','View product →'=>'Ver produto →','Compare products'=>'Comparar produtos','Product comparison'=>'Comparação de produtos','Remove'=>'Remover','No products selected. Add products from the marketplace.'=>'Nenhum produto selecionado. Adicione produtos do marketplace.'],
        'nl'=>['Marketplace'=>'Marktplaats','Search'=>'Zoeken','Compare'=>'Vergelijken','Popular'=>'Populair','Newest'=>'Nieuwste','View product →'=>'Product bekijken →','Compare products'=>'Producten vergelijken','Product comparison'=>'Productvergelijking','Remove'=>'Verwijderen','No products selected. Add products from the marketplace.'=>'Geen producten geselecteerd. Voeg producten toe vanuit de marktplaats.'],
        'ru'=>['Marketplace'=>'Маркетплейс','Search'=>'Поиск','Compare'=>'Сравнить','Popular'=>'Популярные','Newest'=>'Новые','View product →'=>'Открыть продукт →','Compare products'=>'Сравнить продукты','Product comparison'=>'Сравнение продуктов','Remove'=>'Удалить','No products selected. Add products from the marketplace.'=>'Продукты не выбраны. Добавьте их из маркетплейса.'],
        'ar'=>['Marketplace'=>'السوق','Search'=>'بحث','Compare'=>'مقارنة','Popular'=>'الأكثر شيوعاً','Newest'=>'الأحدث','View product →'=>'عرض المنتج →','Compare products'=>'مقارنة المنتجات','Product comparison'=>'مقارنة المنتجات','Remove'=>'إزالة','No products selected. Add products from the marketplace.'=>'لم يتم اختيار منتجات. أضف منتجات من السوق.'],
        'fa'=>['Marketplace'=>'بازار','Search'=>'جستجو','Compare'=>'مقایسه','Popular'=>'محبوب','Newest'=>'جدیدترین','View product →'=>'مشاهده محصول →','Compare products'=>'مقایسه محصولات','Product comparison'=>'مقایسه محصولات','Remove'=>'حذف','No products selected. Add products from the marketplace.'=>'محصولی انتخاب نشده است. محصولات را از بازار اضافه کنید.'],
        'zh'=>['Marketplace'=>'市场','Search'=>'搜索','Compare'=>'比较','Popular'=>'热门','Newest'=>'最新','View product →'=>'查看产品 →','Compare products'=>'比较产品','Product comparison'=>'产品比较','Remove'=>'移除','No products selected. Add products from the marketplace.'=>'未选择产品。请从市场添加产品。'],
        'ja'=>['Marketplace'=>'マーケットプレイス','Search'=>'検索','Compare'=>'比較','Popular'=>'人気','Newest'=>'新着','View product →'=>'製品を見る →','Compare products'=>'製品を比較','Product comparison'=>'製品比較','Remove'=>'削除','No products selected. Add products from the marketplace.'=>'製品が選択されていません。マーケットプレイスから追加してください。'],
        'ko'=>['Marketplace'=>'마켓플레이스','Search'=>'검색','Compare'=>'비교','Popular'=>'인기','Newest'=>'최신','View product →'=>'제품 보기 →','Compare products'=>'제품 비교','Product comparison'=>'제품 비교','Remove'=>'삭제','No products selected. Add products from the marketplace.'=>'선택한 제품이 없습니다. 마켓플레이스에서 제품을 추가하세요.'],
    ];
    foreach ($discoveryTerms as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    $salesTerms = [
        'tr'=>['Commerce controls'=>'Ticaret kontrolleri','Tax profile'=>'Vergi profili','Coupon'=>'Kupon','Currency rate'=>'Döviz kuru','Manual payments'=>'Manuel ödemeler','Orders and invoices'=>'Siparişler ve faturalar','Refund'=>'İade','Customer support queue'=>'Müşteri destek kuyruğu','System health'=>'Sistem sağlığı','Product quality review'=>'Ürün yayın kalite kontrolü'],
        'en'=>['Commerce controls'=>'Commerce controls','Tax profile'=>'Tax profile','Coupon'=>'Coupon','Currency rate'=>'Currency rate','Manual payments'=>'Manual payments','Orders and invoices'=>'Orders and invoices','Refund'=>'Refund','Customer support queue'=>'Customer support queue','System health'=>'System health','Product quality review'=>'Product quality review'],
        'de'=>['Commerce controls'=>'Handelsverwaltung','Tax profile'=>'Steuerprofil','Coupon'=>'Gutschein','Currency rate'=>'Wechselkurs','Manual payments'=>'Manuelle Zahlungen','Orders and invoices'=>'Bestellungen und Rechnungen','Refund'=>'Erstattung','Customer support queue'=>'Kundensupport-Warteschlange','System health'=>'Systemstatus','Product quality review'=>'Produktqualitätsprüfung'],
        'fr'=>['Commerce controls'=>'Gestion commerciale','Tax profile'=>'Profil fiscal','Coupon'=>'Coupon','Currency rate'=>'Taux de change','Manual payments'=>'Paiements manuels','Orders and invoices'=>'Commandes et factures','Refund'=>'Remboursement','Customer support queue'=>'File du support client','System health'=>'État du système','Product quality review'=>'Contrôle qualité du produit'],
        'es'=>['Commerce controls'=>'Controles comerciales','Tax profile'=>'Perfil fiscal','Coupon'=>'Cupón','Currency rate'=>'Tipo de cambio','Manual payments'=>'Pagos manuales','Orders and invoices'=>'Pedidos y facturas','Refund'=>'Reembolso','Customer support queue'=>'Cola de soporte al cliente','System health'=>'Estado del sistema','Product quality review'=>'Revisión de calidad del producto'],
        'it'=>['Commerce controls'=>'Controlli commerciali','Tax profile'=>'Profilo fiscale','Coupon'=>'Coupon','Currency rate'=>'Tasso di cambio','Manual payments'=>'Pagamenti manuali','Orders and invoices'=>'Ordini e fatture','Refund'=>'Rimborso','Customer support queue'=>'Coda supporto clienti','System health'=>'Stato del sistema','Product quality review'=>'Controllo qualità prodotto'],
        'pt'=>['Commerce controls'=>'Controlos comerciais','Tax profile'=>'Perfil fiscal','Coupon'=>'Cupão','Currency rate'=>'Taxa de câmbio','Manual payments'=>'Pagamentos manuais','Orders and invoices'=>'Pedidos e faturas','Refund'=>'Reembolso','Customer support queue'=>'Fila de suporte ao cliente','System health'=>'Estado do sistema','Product quality review'=>'Revisão de qualidade do produto'],
        'nl'=>['Commerce controls'=>'Handelsbeheer','Tax profile'=>'Belastingprofiel','Coupon'=>'Coupon','Currency rate'=>'Wisselkoers','Manual payments'=>'Handmatige betalingen','Orders and invoices'=>'Bestellingen en facturen','Refund'=>'Terugbetaling','Customer support queue'=>'Klantsupportwachtrij','System health'=>'Systeemstatus','Product quality review'=>'Productkwaliteitscontrole'],
        'ru'=>['Commerce controls'=>'Управление продажами','Tax profile'=>'Налоговый профиль','Coupon'=>'Купон','Currency rate'=>'Курс валюты','Manual payments'=>'Ручные платежи','Orders and invoices'=>'Заказы и счета','Refund'=>'Возврат','Customer support queue'=>'Очередь поддержки клиентов','System health'=>'Состояние системы','Product quality review'=>'Проверка качества продукта'],
        'ar'=>['Commerce controls'=>'إدارة التجارة','Tax profile'=>'الملف الضريبي','Coupon'=>'قسيمة','Currency rate'=>'سعر الصرف','Manual payments'=>'المدفوعات اليدوية','Orders and invoices'=>'الطلبات والفواتير','Refund'=>'استرداد','Customer support queue'=>'قائمة دعم العملاء','System health'=>'حالة النظام','Product quality review'=>'مراجعة جودة المنتج'],
        'fa'=>['Commerce controls'=>'کنترل‌های تجاری','Tax profile'=>'پروفایل مالیاتی','Coupon'=>'کوپن','Currency rate'=>'نرخ ارز','Manual payments'=>'پرداخت‌های دستی','Orders and invoices'=>'سفارش‌ها و فاکتورها','Refund'=>'بازپرداخت','Customer support queue'=>'صف پشتیبانی مشتری','System health'=>'وضعیت سیستم','Product quality review'=>'بررسی کیفیت محصول'],
        'zh'=>['Commerce controls'=>'商业控制','Tax profile'=>'税务配置','Coupon'=>'优惠券','Currency rate'=>'汇率','Manual payments'=>'手动付款','Orders and invoices'=>'订单与发票','Refund'=>'退款','Customer support queue'=>'客户支持队列','System health'=>'系统状态','Product quality review'=>'产品质量审核'],
        'ja'=>['Commerce controls'=>'販売管理','Tax profile'=>'税務プロファイル','Coupon'=>'クーポン','Currency rate'=>'為替レート','Manual payments'=>'手動決済','Orders and invoices'=>'注文と請求書','Refund'=>'返金','Customer support queue'=>'顧客サポートキュー','System health'=>'システム状態','Product quality review'=>'製品品質レビュー'],
        'ko'=>['Commerce controls'=>'상거래 관리','Tax profile'=>'세금 프로필','Coupon'=>'쿠폰','Currency rate'=>'환율','Manual payments'=>'수동 결제','Orders and invoices'=>'주문 및 청구서','Refund'=>'환불','Customer support queue'=>'고객 지원 대기열','System health'=>'시스템 상태','Product quality review'=>'제품 품질 검토'],
    ];
    foreach ($salesTerms as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    // Buyer account pages are rendered by a small legacy route layer. Keep its
    // high-frequency labels localized too, so switching language never leaves
    // the customer dashboard half translated.
    $buyerCommon = [
        'tr'=>['Buyer sign in'=>'Müşteri girişi','Forgot password?'=>'Şifrenizi mi unuttunuz?','Create buyer account'=>'Müşteri hesabı oluştur','Name'=>'Ad','Email'=>'E-posta','Password'=>'Parola','Sign in'=>'Giriş yap','Create account'=>'Hesap oluştur','Send reset link'=>'Sıfırlama bağlantısı gönder','Back to sign in'=>'Girişe dön','Create a new password'=>'Yeni parola oluştur','New password'=>'Yeni parola','Confirm password'=>'Parolayı doğrula','Save new password'=>'Yeni parolayı kaydet','Email verified'=>'E-posta doğrulandı','Verification link expired'=>'Doğrulama bağlantısının süresi doldu','Product entitlements'=>'Ürün hakları','Product updates'=>'Ürün güncellemeleri','No published updates are available yet.'=>'Henüz yayınlanmış güncelleme yok.','Download unavailable'=>'İndirme kullanılamıyor'],
        'en'=>['Buyer sign in'=>'Buyer sign in','Forgot password?'=>'Forgot password?','Create buyer account'=>'Create buyer account','Name'=>'Name','Email'=>'Email','Password'=>'Password','Sign in'=>'Sign in','Create account'=>'Create account','Send reset link'=>'Send reset link','Back to sign in'=>'Back to sign in','Create a new password'=>'Create a new password','New password'=>'New password','Confirm password'=>'Confirm password','Save new password'=>'Save new password','Email verified'=>'Email verified','Verification link expired'=>'Verification link expired','Product entitlements'=>'Product entitlements','Product updates'=>'Product updates','No published updates are available yet.'=>'No published updates are available yet.','Download unavailable'=>'Download unavailable'],
        'de'=>['Buyer sign in'=>'Kundenanmeldung','Forgot password?'=>'Passwort vergessen?','Create buyer account'=>'Kundenkonto erstellen','Name'=>'Name','Email'=>'E-Mail','Password'=>'Passwort','Sign in'=>'Anmelden','Create account'=>'Konto erstellen','Send reset link'=>'Link zum Zurücksetzen senden','Back to sign in'=>'Zur Anmeldung','Create a new password'=>'Neues Passwort erstellen','New password'=>'Neues Passwort','Confirm password'=>'Passwort bestätigen','Save new password'=>'Neues Passwort speichern','Email verified'=>'E-Mail bestätigt','Verification link expired'=>'Bestätigungslink abgelaufen','Product entitlements'=>'Produktberechtigungen','Product updates'=>'Produktupdates','No published updates are available yet.'=>'Noch keine veröffentlichten Updates verfügbar.','Download unavailable'=>'Download nicht verfügbar'],
        'fr'=>['Buyer sign in'=>'Connexion client','Forgot password?'=>'Mot de passe oublié ?','Create buyer account'=>'Créer un compte client','Name'=>'Nom','Email'=>'E-mail','Password'=>'Mot de passe','Sign in'=>'Se connecter','Create account'=>'Créer le compte','Send reset link'=>'Envoyer le lien de réinitialisation','Back to sign in'=>'Retour à la connexion','Create a new password'=>'Créer un nouveau mot de passe','New password'=>'Nouveau mot de passe','Confirm password'=>'Confirmer le mot de passe','Save new password'=>'Enregistrer le mot de passe','Email verified'=>'E-mail confirmé','Verification link expired'=>'Lien de confirmation expiré','Product entitlements'=>'Droits produit','Product updates'=>'Mises à jour du produit','No published updates are available yet.'=>'Aucune mise à jour publiée pour le moment.','Download unavailable'=>'Téléchargement indisponible'],
        'es'=>['Buyer sign in'=>'Acceso de cliente','Forgot password?'=>'¿Has olvidado la contraseña?','Create buyer account'=>'Crear cuenta de cliente','Name'=>'Nombre','Email'=>'Correo electrónico','Password'=>'Contraseña','Sign in'=>'Iniciar sesión','Create account'=>'Crear cuenta','Send reset link'=>'Enviar enlace de restablecimiento','Back to sign in'=>'Volver al acceso','Create a new password'=>'Crear una nueva contraseña','New password'=>'Nueva contraseña','Confirm password'=>'Confirmar contraseña','Save new password'=>'Guardar nueva contraseña','Email verified'=>'Correo verificado','Verification link expired'=>'Enlace de verificación caducado','Product entitlements'=>'Derechos del producto','Product updates'=>'Actualizaciones del producto','No published updates are available yet.'=>'Todavía no hay actualizaciones publicadas.','Download unavailable'=>'Descarga no disponible'],
        'it'=>['Buyer sign in'=>'Accesso cliente','Forgot password?'=>'Password dimenticata?','Create buyer account'=>'Crea account cliente','Name'=>'Nome','Email'=>'E-mail','Password'=>'Password','Sign in'=>'Accedi','Create account'=>'Crea account','Send reset link'=>'Invia link di reimpostazione','Back to sign in'=>'Torna all’accesso','Create a new password'=>'Crea una nuova password','New password'=>'Nuova password','Confirm password'=>'Conferma password','Save new password'=>'Salva nuova password','Email verified'=>'E-mail verificata','Verification link expired'=>'Link di verifica scaduto','Product entitlements'=>'Diritti del prodotto','Product updates'=>'Aggiornamenti del prodotto','No published updates are available yet.'=>'Non ci sono ancora aggiornamenti pubblicati.','Download unavailable'=>'Download non disponibile'],
        'pt'=>['Buyer sign in'=>'Entrada do cliente','Forgot password?'=>'Esqueceu-se da palavra-passe?','Create buyer account'=>'Criar conta de cliente','Name'=>'Nome','Email'=>'E-mail','Password'=>'Palavra-passe','Sign in'=>'Entrar','Create account'=>'Criar conta','Send reset link'=>'Enviar ligação de recuperação','Back to sign in'=>'Voltar ao acesso','Create a new password'=>'Criar nova palavra-passe','New password'=>'Nova palavra-passe','Confirm password'=>'Confirmar palavra-passe','Save new password'=>'Guardar nova palavra-passe','Email verified'=>'E-mail verificado','Verification link expired'=>'Ligação de verificação expirada','Product entitlements'=>'Direitos do produto','Product updates'=>'Atualizações do produto','No published updates are available yet.'=>'Ainda não existem atualizações publicadas.','Download unavailable'=>'Download indisponível'],
        'nl'=>['Buyer sign in'=>'Klant aanmelden','Forgot password?'=>'Wachtwoord vergeten?','Create buyer account'=>'Klantaccount maken','Name'=>'Naam','Email'=>'E-mail','Password'=>'Wachtwoord','Sign in'=>'Aanmelden','Create account'=>'Account maken','Send reset link'=>'Resetlink verzenden','Back to sign in'=>'Terug naar aanmelden','Create a new password'=>'Nieuw wachtwoord maken','New password'=>'Nieuw wachtwoord','Confirm password'=>'Wachtwoord bevestigen','Save new password'=>'Nieuw wachtwoord opslaan','Email verified'=>'E-mail bevestigd','Verification link expired'=>'Verificatielink verlopen','Product entitlements'=>'Productrechten','Product updates'=>'Productupdates','No published updates are available yet.'=>'Er zijn nog geen gepubliceerde updates.','Download unavailable'=>'Download niet beschikbaar'],
        'ru'=>['Buyer sign in'=>'Вход клиента','Forgot password?'=>'Забыли пароль?','Create buyer account'=>'Создать аккаунт клиента','Name'=>'Имя','Email'=>'Эл. почта','Password'=>'Пароль','Sign in'=>'Войти','Create account'=>'Создать аккаунт','Send reset link'=>'Отправить ссылку для сброса','Back to sign in'=>'Вернуться ко входу','Create a new password'=>'Создать новый пароль','New password'=>'Новый пароль','Confirm password'=>'Подтвердите пароль','Save new password'=>'Сохранить новый пароль','Email verified'=>'Почта подтверждена','Verification link expired'=>'Ссылка подтверждения истекла','Product entitlements'=>'Права на продукт','Product updates'=>'Обновления продукта','No published updates are available yet.'=>'Опубликованных обновлений пока нет.','Download unavailable'=>'Скачивание недоступно'],
        'ar'=>['Buyer sign in'=>'دخول العميل','Forgot password?'=>'هل نسيت كلمة المرور؟','Create buyer account'=>'إنشاء حساب عميل','Name'=>'الاسم','Email'=>'البريد الإلكتروني','Password'=>'كلمة المرور','Sign in'=>'تسجيل الدخول','Create account'=>'إنشاء الحساب','Send reset link'=>'إرسال رابط إعادة التعيين','Back to sign in'=>'العودة إلى الدخول','Create a new password'=>'إنشاء كلمة مرور جديدة','New password'=>'كلمة المرور الجديدة','Confirm password'=>'تأكيد كلمة المرور','Save new password'=>'حفظ كلمة المرور الجديدة','Email verified'=>'تم التحقق من البريد','Verification link expired'=>'انتهت صلاحية رابط التحقق','Product entitlements'=>'حقوق المنتج','Product updates'=>'تحديثات المنتج','No published updates are available yet.'=>'لا توجد تحديثات منشورة بعد.','Download unavailable'=>'التنزيل غير متاح'],
        'fa'=>['Buyer sign in'=>'ورود مشتری','Forgot password?'=>'گذرواژه را فراموش کرده‌اید؟','Create buyer account'=>'ایجاد حساب مشتری','Name'=>'نام','Email'=>'ایمیل','Password'=>'گذرواژه','Sign in'=>'ورود','Create account'=>'ایجاد حساب','Send reset link'=>'ارسال پیوند بازنشانی','Back to sign in'=>'بازگشت به ورود','Create a new password'=>'ایجاد گذرواژه جدید','New password'=>'گذرواژه جدید','Confirm password'=>'تأیید گذرواژه','Save new password'=>'ذخیره گذرواژه جدید','Email verified'=>'ایمیل تأیید شد','Verification link expired'=>'پیوند تأیید منقضی شده است','Product entitlements'=>'حقوق محصول','Product updates'=>'به‌روزرسانی‌های محصول','No published updates are available yet.'=>'هنوز به‌روزرسانی منتشرشده‌ای وجود ندارد.','Download unavailable'=>'دانلود در دسترس نیست'],
        'zh'=>['Buyer sign in'=>'客户登录','Forgot password?'=>'忘记密码？','Create buyer account'=>'创建客户账户','Name'=>'姓名','Email'=>'电子邮箱','Password'=>'密码','Sign in'=>'登录','Create account'=>'创建账户','Send reset link'=>'发送重置链接','Back to sign in'=>'返回登录','Create a new password'=>'创建新密码','New password'=>'新密码','Confirm password'=>'确认密码','Save new password'=>'保存新密码','Email verified'=>'邮箱已验证','Verification link expired'=>'验证链接已过期','Product entitlements'=>'产品权益','Product updates'=>'产品更新','No published updates are available yet.'=>'暂无已发布的更新。','Download unavailable'=>'下载不可用'],
        'ja'=>['Buyer sign in'=>'顧客ログイン','Forgot password?'=>'パスワードを忘れましたか？','Create buyer account'=>'顧客アカウントを作成','Name'=>'名前','Email'=>'メールアドレス','Password'=>'パスワード','Sign in'=>'ログイン','Create account'=>'アカウントを作成','Send reset link'=>'リセットリンクを送信','Back to sign in'=>'ログインに戻る','Create a new password'=>'新しいパスワードを作成','New password'=>'新しいパスワード','Confirm password'=>'パスワードを確認','Save new password'=>'新しいパスワードを保存','Email verified'=>'メール確認済み','Verification link expired'=>'確認リンクの期限切れ','Product entitlements'=>'製品の権利','Product updates'=>'製品の更新','No published updates are available yet.'=>'公開済みの更新はまだありません。','Download unavailable'=>'ダウンロードできません'],
        'ko'=>['Buyer sign in'=>'고객 로그인','Forgot password?'=>'비밀번호를 잊으셨나요?','Create buyer account'=>'고객 계정 만들기','Name'=>'이름','Email'=>'이메일','Password'=>'비밀번호','Sign in'=>'로그인','Create account'=>'계정 만들기','Send reset link'=>'재설정 링크 보내기','Back to sign in'=>'로그인으로 돌아가기','Create a new password'=>'새 비밀번호 만들기','New password'=>'새 비밀번호','Confirm password'=>'비밀번호 확인','Save new password'=>'새 비밀번호 저장','Email verified'=>'이메일 인증 완료','Verification link expired'=>'인증 링크가 만료되었습니다','Product entitlements'=>'제품 권한','Product updates'=>'제품 업데이트','No published updates are available yet.'=>'게시된 업데이트가 아직 없습니다.','Download unavailable'=>'다운로드할 수 없습니다'],
    ];
    $downloadTerms = [
        'tr'=>['Download history'=>'İndirme geçmişi','Downloaded'=>'İndirildi','Release'=>'Sürüm','File'=>'Dosya','Checksum'=>'Kontrol özeti','No downloads yet.'=>'Henüz indirme yok.'],
        'en'=>['Download history'=>'Download history','Downloaded'=>'Downloaded','Release'=>'Release','File'=>'File','Checksum'=>'Checksum','No downloads yet.'=>'No downloads yet.'],
        'de'=>['Download history'=>'Downloadverlauf','Downloaded'=>'Heruntergeladen','Release'=>'Version','File'=>'Datei','Checksum'=>'Prüfsumme','No downloads yet.'=>'Noch keine Downloads.'],
        'fr'=>['Download history'=>'Historique des téléchargements','Downloaded'=>'Téléchargé','Release'=>'Version','File'=>'Fichier','Checksum'=>'Somme de contrôle','No downloads yet.'=>'Aucun téléchargement.'],
        'es'=>['Download history'=>'Historial de descargas','Downloaded'=>'Descargado','Release'=>'Versión','File'=>'Archivo','Checksum'=>'Suma de comprobación','No downloads yet.'=>'Aún no hay descargas.'],
        'it'=>['Download history'=>'Cronologia download','Downloaded'=>'Scaricato','Release'=>'Versione','File'=>'File','Checksum'=>'Checksum','No downloads yet.'=>'Nessun download.'],
        'pt'=>['Download history'=>'Histórico de downloads','Downloaded'=>'Descarregado','Release'=>'Versão','File'=>'Ficheiro','Checksum'=>'Checksum','No downloads yet.'=>'Ainda não existem downloads.'],
        'nl'=>['Download history'=>'Downloadgeschiedenis','Downloaded'=>'Gedownload','Release'=>'Release','File'=>'Bestand','Checksum'=>'Controlesom','No downloads yet.'=>'Nog geen downloads.'],
        'ru'=>['Download history'=>'История загрузок','Downloaded'=>'Загружено','Release'=>'Версия','File'=>'Файл','Checksum'=>'Контрольная сумма','No downloads yet.'=>'Загрузок пока нет.'],
        'ar'=>['Download history'=>'سجل التنزيلات','Downloaded'=>'تم التنزيل','Release'=>'الإصدار','File'=>'الملف','Checksum'=>'قيمة التحقق','No downloads yet.'=>'لا توجد تنزيلات بعد.'],
        'fa'=>['Download history'=>'تاریخچه دانلود','Downloaded'=>'دانلود شد','Release'=>'نسخه','File'=>'فایل','Checksum'=>'چک‌سام','No downloads yet.'=>'هنوز دانلودی وجود ندارد.'],
        'zh'=>['Download history'=>'下载历史','Downloaded'=>'已下载','Release'=>'版本','File'=>'文件','Checksum'=>'校验和','No downloads yet.'=>'暂无下载。'],
        'ja'=>['Download history'=>'ダウンロード履歴','Downloaded'=>'ダウンロード済み','Release'=>'リリース','File'=>'ファイル','Checksum'=>'チェックサム','No downloads yet.'=>'ダウンロード履歴はありません。'],
        'ko'=>['Download history'=>'다운로드 기록','Downloaded'=>'다운로드됨','Release'=>'릴리스','File'=>'파일','Checksum'=>'체크섬','No downloads yet.'=>'아직 다운로드가 없습니다.'],
    ];
    $licenseChangeTerms = [
        'tr'=>['Licence change requests'=>'Lisans değişikliği talepleri','Licence requests'=>'Lisans talepleri','Request type'=>'Talep türü','Replace domain'=>'Alan adını değiştir','Transfer licence'=>'Lisansı devret','Current domain'=>'Mevcut alan adı','New domain'=>'Yeni alan adı','Target customer email'=>'Hedef müşteri e-postası','Reason'=>'Gerekçe','Evidence / support note'=>'Kanıt / destek notu','Submit request'=>'Talebi gönder','Request history'=>'Talep geçmişi','Approve'=>'Onayla','Reject'=>'Reddet','Review note'=>'İnceleme notu','No requests.'=>'Talep yok.'],
        'en'=>['Licence change requests'=>'Licence change requests','Licence requests'=>'Licence requests','Request type'=>'Request type','Replace domain'=>'Replace domain','Transfer licence'=>'Transfer licence','Current domain'=>'Current domain','New domain'=>'New domain','Target customer email'=>'Target customer email','Reason'=>'Reason','Evidence / support note'=>'Evidence / support note','Submit request'=>'Submit request','Request history'=>'Request history','Approve'=>'Approve','Reject'=>'Reject','Review note'=>'Review note','No requests.'=>'No requests.'],
        'de'=>['Licence change requests'=>'Anträge zur Lizenzänderung','Licence requests'=>'Lizenzanträge','Request type'=>'Antragstyp','Replace domain'=>'Domain ersetzen','Transfer licence'=>'Lizenz übertragen','Current domain'=>'Aktuelle Domain','New domain'=>'Neue Domain','Target customer email'=>'E-Mail des Zielkunden','Reason'=>'Begründung','Evidence / support note'=>'Nachweis / Supportnotiz','Submit request'=>'Antrag senden','Request history'=>'Antragsverlauf','Approve'=>'Genehmigen','Reject'=>'Ablehnen','Review note'=>'Prüfnotiz','No requests.'=>'Keine Anträge.'],
        'fr'=>['Licence change requests'=>'Demandes de modification de licence','Licence requests'=>'Demandes de licence','Request type'=>'Type de demande','Replace domain'=>'Remplacer le domaine','Transfer licence'=>'Transférer la licence','Current domain'=>'Domaine actuel','New domain'=>'Nouveau domaine','Target customer email'=>'E-mail du client cible','Reason'=>'Motif','Evidence / support note'=>'Preuve / note de support','Submit request'=>'Envoyer la demande','Request history'=>'Historique des demandes','Approve'=>'Approuver','Reject'=>'Refuser','Review note'=>'Note de contrôle','No requests.'=>'Aucune demande.'],
        'es'=>['Licence change requests'=>'Solicitudes de cambio de licencia','Licence requests'=>'Solicitudes de licencia','Request type'=>'Tipo de solicitud','Replace domain'=>'Cambiar dominio','Transfer licence'=>'Transferir licencia','Current domain'=>'Dominio actual','New domain'=>'Nuevo dominio','Target customer email'=>'Correo del cliente destino','Reason'=>'Motivo','Evidence / support note'=>'Prueba / nota de soporte','Submit request'=>'Enviar solicitud','Request history'=>'Historial de solicitudes','Approve'=>'Aprobar','Reject'=>'Rechazar','Review note'=>'Nota de revisión','No requests.'=>'No hay solicitudes.'],
        'it'=>['Licence change requests'=>'Richieste di modifica licenza','Licence requests'=>'Richieste di licenza','Request type'=>'Tipo di richiesta','Replace domain'=>'Sostituisci dominio','Transfer licence'=>'Trasferisci licenza','Current domain'=>'Dominio attuale','New domain'=>'Nuovo dominio','Target customer email'=>'E-mail cliente destinatario','Reason'=>'Motivo','Evidence / support note'=>'Prova / nota di supporto','Submit request'=>'Invia richiesta','Request history'=>'Cronologia richieste','Approve'=>'Approva','Reject'=>'Rifiuta','Review note'=>'Nota di revisione','No requests.'=>'Nessuna richiesta.'],
        'pt'=>['Licence change requests'=>'Pedidos de alteração da licença','Licence requests'=>'Pedidos de licença','Request type'=>'Tipo de pedido','Replace domain'=>'Substituir domínio','Transfer licence'=>'Transferir licença','Current domain'=>'Domínio atual','New domain'=>'Novo domínio','Target customer email'=>'E-mail do cliente destino','Reason'=>'Motivo','Evidence / support note'=>'Prova / nota de suporte','Submit request'=>'Enviar pedido','Request history'=>'Histórico de pedidos','Approve'=>'Aprovar','Reject'=>'Rejeitar','Review note'=>'Nota de revisão','No requests.'=>'Sem pedidos.'],
        'nl'=>['Licence change requests'=>'Aanvragen voor licentiewijziging','Licence requests'=>'Licentieaanvragen','Request type'=>'Type aanvraag','Replace domain'=>'Domein vervangen','Transfer licence'=>'Licentie overdragen','Current domain'=>'Huidig domein','New domain'=>'Nieuw domein','Target customer email'=>'E-mail doelklant','Reason'=>'Reden','Evidence / support note'=>'Bewijs / supportnotitie','Submit request'=>'Aanvraag indienen','Request history'=>'Aanvraaggeschiedenis','Approve'=>'Goedkeuren','Reject'=>'Afwijzen','Review note'=>'Beoordelingsnotitie','No requests.'=>'Geen aanvragen.'],
        'ru'=>['Licence change requests'=>'Запросы на изменение лицензии','Licence requests'=>'Запросы лицензий','Request type'=>'Тип запроса','Replace domain'=>'Заменить домен','Transfer licence'=>'Передать лицензию','Current domain'=>'Текущий домен','New domain'=>'Новый домен','Target customer email'=>'Почта целевого клиента','Reason'=>'Причина','Evidence / support note'=>'Доказательства / заметка поддержки','Submit request'=>'Отправить запрос','Request history'=>'История запросов','Approve'=>'Одобрить','Reject'=>'Отклонить','Review note'=>'Примечание проверки','No requests.'=>'Запросов нет.'],
        'ar'=>['Licence change requests'=>'طلبات تغيير الترخيص','Licence requests'=>'طلبات الترخيص','Request type'=>'نوع الطلب','Replace domain'=>'استبدال النطاق','Transfer licence'=>'نقل الترخيص','Current domain'=>'النطاق الحالي','New domain'=>'النطاق الجديد','Target customer email'=>'بريد العميل المستهدف','Reason'=>'السبب','Evidence / support note'=>'الدليل / ملاحظة الدعم','Submit request'=>'إرسال الطلب','Request history'=>'سجل الطلبات','Approve'=>'اعتماد','Reject'=>'رفض','Review note'=>'ملاحظة المراجعة','No requests.'=>'لا توجد طلبات.'],
        'fa'=>['Licence change requests'=>'درخواست‌های تغییر مجوز','Licence requests'=>'درخواست‌های مجوز','Request type'=>'نوع درخواست','Replace domain'=>'تعویض دامنه','Transfer licence'=>'انتقال مجوز','Current domain'=>'دامنه فعلی','New domain'=>'دامنه جدید','Target customer email'=>'ایمیل مشتری مقصد','Reason'=>'دلیل','Evidence / support note'=>'مدرک / یادداشت پشتیبانی','Submit request'=>'ارسال درخواست','Request history'=>'تاریخچه درخواست‌ها','Approve'=>'تأیید','Reject'=>'رد','Review note'=>'یادداشت بررسی','No requests.'=>'درخواستی وجود ندارد.'],
        'zh'=>['Licence change requests'=>'许可证变更请求','Licence requests'=>'许可证请求','Request type'=>'请求类型','Replace domain'=>'更换域名','Transfer licence'=>'转移许可证','Current domain'=>'当前域名','New domain'=>'新域名','Target customer email'=>'目标客户邮箱','Reason'=>'原因','Evidence / support note'=>'证据 / 支持备注','Submit request'=>'提交请求','Request history'=>'请求历史','Approve'=>'批准','Reject'=>'拒绝','Review note'=>'审核备注','No requests.'=>'暂无请求。'],
        'ja'=>['Licence change requests'=>'ライセンス変更申請','Licence requests'=>'ライセンス申請','Request type'=>'申請種別','Replace domain'=>'ドメインを変更','Transfer licence'=>'ライセンスを移管','Current domain'=>'現在のドメイン','New domain'=>'新しいドメイン','Target customer email'=>'移管先顧客のメール','Reason'=>'理由','Evidence / support note'=>'証拠 / サポートメモ','Submit request'=>'申請を送信','Request history'=>'申請履歴','Approve'=>'承認','Reject'=>'却下','Review note'=>'審査メモ','No requests.'=>'申請はありません。'],
        'ko'=>['Licence change requests'=>'라이선스 변경 요청','Licence requests'=>'라이선스 요청','Request type'=>'요청 유형','Replace domain'=>'도메인 변경','Transfer licence'=>'라이선스 이전','Current domain'=>'현재 도메인','New domain'=>'새 도메인','Target customer email'=>'대상 고객 이메일','Reason'=>'사유','Evidence / support note'=>'증빙 / 지원 메모','Submit request'=>'요청 제출','Request history'=>'요청 기록','Approve'=>'승인','Reject'=>'거부','Review note'=>'검토 메모','No requests.'=>'요청이 없습니다.'],
    ];
    foreach ($licenseChangeTerms as $locale => $terms) $buyerCommon[$locale] = array_merge($buyerCommon[$locale] ?? [], $terms);
    foreach ($downloadTerms as $locale => $terms) $buyerCommon[$locale] = array_merge($buyerCommon[$locale] ?? [], $terms);
    foreach ($buyerCommon as $locale => $terms) $translations[$locale] = array_merge($translations[$locale] ?? [], $terms);
    $protected = [];
    $html = preg_replace_callback('#<(script|style)\b[^>]*>.*?</\1>#is', static function (array $match) use (&$protected): string {
        $key = '__LC_PROTECTED_'.count($protected).'__';
        $protected[$key] = $match[0];
        return $key;
    }, $html) ?? $html;
    foreach ($translations[$lang] ?? [] as $from => $to) {
        $html = str_replace($from, $to, $html);
    }
    if ($lang === 'tr') {
        $buyerTranslations = [
            'Dashboard'=>'Panel','Support'=>'Destek','Profile'=>'Profil','Security'=>'Güvenlik','Sign out'=>'Çıkış yap',
            'Licences'=>'Lisanslar','Orders'=>'Siparişler','Requests'=>'Talepler','Health'=>'Sistem sağlığı','Settings'=>'Ayarlar',
            'Licence service'=>'Lisans merkezi','Active licences'=>'Aktif lisanslar','Bound domains'=>'Bağlı alan adları',
            'Published themes'=>'Yayınlanan temalar','Orders and invoices'=>'Siparişler ve faturalar','Downloads'=>'İndirmeler',
            'Products & licences'=>'Ürünler ve lisanslar','Licences and domains'=>'Lisanslar ve alan adları',
            'Customer support queue'=>'Müşteri destek kuyruğu','Purchase request queue'=>'Satın alma talepleri',
            'System health'=>'Sistem sağlığı','Sales centre health'=>'Satış merkezi sağlığı',
            'Create buyer account'=>'Müşteri hesabı oluştur','Forgot your password?'=>'Şifrenizi mi unuttunuz?',
            'Create a new password'=>'Yeni parola oluştur','Email'=>'E-posta','Password'=>'Parola',
            'New password'=>'Yeni parola','Confirm password'=>'Parolayı doğrula','Save new password'=>'Yeni parolayı kaydet',
            'Send reset link'=>'Sıfırlama bağlantısı gönder','Back to sign in'=>'Girişe dön',
            'Resend verification link'=>'Doğrulama bağlantısını yeniden gönder','Email not verified.'=>'E-posta doğrulanmadı.',
            'Welcome, '=>'Hoş geldiniz, ','Invoice not found'=>'Fatura bulunamadı','Download unavailable'=>'İndirme kullanılamıyor',
            'Theme version history'=>'Tema sürüm geçmişi','Publishable themes'=>'Yayınlanabilir temalar',
            'Save settings'=>'Ayarları kaydet','Sales settings'=>'Satış ayarları','Save theme'=>'Temayı kaydet',
            'Create licence'=>'Lisans oluştur','Unbind a domain'=>'Alan adı bağlantısını kaldır','Refund'=>'İade',
            'Only necessary'=>'Yalnızca gerekli','Accept analytics'=>'Analitiği kabul et'
        ];
        foreach ($buyerTranslations as $from => $to) {
            $html = str_replace($from, $to, $html);
        }
    }
    $html = strtr($html, $protected);
    $html = str_replace('</style>', '.locale-select select{font:600 12px Poppins,Arial;background:#ffffff18;color:#fff;border:1px solid #ffffff44;border-radius:8px;padding:8px 28px 8px 10px;max-width:150px}.locale-select option{color:#08283a;background:#fff}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>', $html);
    $accepted = ($_COOKIE['lc_cookie_consent'] ?? '') === 'accepted';
    if (!$accepted) {
        $html = preg_replace('#<script async src="https://www\.googletagmanager\.com/gtag/js\?id=[^"]+"></script><script>.*?</script>#s', '', $html) ?? $html;
        if (strpos($html, 'data-lc-cookie-banner') === false && strpos($html, '<meta name="robots" content="noindex,nofollow">') === false) {
            $cookieCopy = [
                'tr'=>['İsteğe bağlı analitik çerezler satış deneyimini geliştirmemize yardımcı olur.','Yalnızca gerekli','Analitiği kabul et'],
                'en'=>['We use optional analytics cookies to improve the sales experience.','Only necessary','Accept analytics'],
                'de'=>['Optionale Analyse-Cookies verbessern das Verkaufserlebnis.','Nur notwendige','Analyse akzeptieren'],
                'fr'=>['Les cookies analytiques facultatifs améliorent l’expérience de vente.','Nécessaires uniquement','Accepter les analyses'],
                'es'=>['Las cookies analíticas opcionales mejoran la experiencia de compra.','Solo necesarias','Aceptar analítica'],
                'it'=>['I cookie analitici facoltativi migliorano l’esperienza di acquisto.','Solo necessari','Accetta analisi'],
                'pt'=>['Os cookies analíticos opcionais melhoram a experiência de compra.','Apenas necessários','Aceitar análises'],
                'nl'=>['Optionele analytische cookies verbeteren de verkoopervaring.','Alleen noodzakelijk','Analyse accepteren'],
                'ru'=>['Необязательные аналитические файлы cookie улучшают процесс покупки.','Только необходимые','Принять аналитику'],
                'ar'=>['تساعد ملفات تعريف الارتباط التحليلية الاختيارية في تحسين تجربة البيع.','الضرورية فقط','قبول التحليلات'],
                'fa'=>['کوکی‌های تحلیلی اختیاری تجربه خرید را بهتر می‌کنند.','فقط ضروری','پذیرش تحلیل‌ها'],
                'zh'=>['可选分析 Cookie 有助于改善购买体验。','仅必要 Cookie','接受分析'],
                'ja'=>['任意の分析 Cookie は購入体験の改善に役立ちます。','必要なもののみ','分析を許可'],
                'ko'=>['선택적 분석 쿠키는 구매 경험을 개선하는 데 도움이 됩니다.','필수 항목만','분석 허용'],
            ][$lang] ?? ['We use optional analytics cookies to improve the sales experience.','Only necessary','Accept analytics'];
            $banner = '<div data-lc-cookie-banner style="position:fixed;left:16px;right:16px;bottom:16px;z-index:20;background:#06283a;color:#fff;padding:16px 18px;border-radius:10px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span>'.htmlspecialchars($cookieCopy[0], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</span><span><button type="button" data-lc-cookie="essential">'.htmlspecialchars($cookieCopy[1], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</button> <button type="button" data-lc-cookie="accepted">'.htmlspecialchars($cookieCopy[2], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</button></span></div><script>document.querySelectorAll("[data-lc-cookie]").forEach(function(b){b.addEventListener("click",function(){document.cookie="lc_cookie_consent="+b.dataset.lcCookie+";path=/;max-age=31536000;SameSite=Lax";location.reload()})});</script>';
            $html = str_replace('</html>', $banner.'</html>', $html);
        }
    }
    return $html;
});

function lc_smtp_send(string $to, string $subject, string $body): bool
{
    $host = lc_env('LC_SMTP_HOST');
    $port = (int)lc_env('LC_SMTP_PORT', '587');
    $user = lc_env('LC_SMTP_USER');
    $pass = lc_env('LC_SMTP_PASSWORD');
    $from = lc_env('LC_MAIL_FROM');
    if ($host === '' || $from === '') return false;
    $secure = strtolower(lc_env('LC_SMTP_SECURE', 'tls'));
    $transport = $secure === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($transport.$host.':'.$port, $errno, $error, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) return false;
    stream_set_timeout($socket, 10);
    $read = static function () use ($socket): string { $line=''; do { $line.=fgets($socket, 512) ?: ''; } while (isset($line[3]) && $line[3] === '-'); return $line; };
    $write = static function (string $command) use ($socket, $read): void { fwrite($socket, $command."\r\n"); $reply=$read(); if ($reply === '' || (int)substr($reply, 0, 3) >= 400) throw new RuntimeException('SMTP command failed.'); };
    try {
        $read();
        $write('EHLO tofidesk');
        if ($secure === 'tls') { $write('STARTTLS'); if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed.'); $write('EHLO tofidesk'); }
        if ($user !== '') { $write('AUTH LOGIN'); $write(base64_encode($user)); $write(base64_encode($pass)); }
        $write('MAIL FROM:<'.$from.'>'); $write('RCPT TO:<'.$to.'>'); $write('DATA');
        fwrite($socket, 'From: '.$from."\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body."\r\n.\r\n");
        $reply=$read(); $write('QUIT'); fclose($socket);
        return (int)substr($reply, 0, 3) < 400;
    } catch (Throwable) { fclose($socket); return false; }
}

function lc_legacy_account_mail_text(string $key, string $locale, string $link): array
{
    $locale = in_array($locale, ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'], true) ? $locale : 'en';
    $templates = [
        'en' => ['reset'=>['TofiDesk password reset','Use this link within one hour:\n\n'.$link], 'verify'=>['Verify your TofiDesk account','Verify your account within 24 hours:\n\n'.$link]],
        'tr' => ['reset'=>['TofiDesk parola sıfırlama','Parolanızı bir saat içinde yenilemek için bu bağlantıyı açın:\n\n'.$link], 'verify'=>['TofiDesk hesap doğrulama','Hesabınızı 24 saat içinde doğrulamak için bu bağlantıyı açın:\n\n'.$link]],
        'de' => ['reset'=>['TofiDesk-Passwort zurücksetzen','Öffnen Sie diesen Link innerhalb einer Stunde, um Ihr Passwort zurückzusetzen:\n\n'.$link], 'verify'=>['TofiDesk-Konto bestätigen','Bestätigen Sie Ihr Konto innerhalb von 24 Stunden:\n\n'.$link]],
        'fr' => ['reset'=>['Réinitialisation du mot de passe TofiDesk','Ouvrez ce lien dans l’heure pour réinitialiser votre mot de passe :\n\n'.$link], 'verify'=>['Vérifiez votre compte TofiDesk','Vérifiez votre compte dans les 24 heures :\n\n'.$link]],
        'es' => ['reset'=>['Restablecimiento de contraseña de TofiDesk','Abra este enlace durante la próxima hora para restablecer su contraseña:\n\n'.$link], 'verify'=>['Verifique su cuenta de TofiDesk','Verifique su cuenta en un plazo de 24 horas:\n\n'.$link]],
        'it' => ['reset'=>['Reimpostazione password TofiDesk','Apri questo link entro un’ora per reimpostare la password:\n\n'.$link], 'verify'=>['Verifica il tuo account TofiDesk','Verifica il tuo account entro 24 ore:\n\n'.$link]],
        'pt' => ['reset'=>['Reposição da palavra-passe TofiDesk','Abra esta ligação dentro de uma hora para repor a palavra-passe:\n\n'.$link], 'verify'=>['Verifique a sua conta TofiDesk','Verifique a sua conta no prazo de 24 horas:\n\n'.$link]],
        'ru' => ['reset'=>['Сброс пароля TofiDesk','Откройте эту ссылку в течение часа, чтобы сменить пароль:\n\n'.$link], 'verify'=>['Подтвердите аккаунт TofiDesk','Подтвердите аккаунт в течение 24 часов:\n\n'.$link]],
        'ar' => ['reset'=>['إعادة تعيين كلمة مرور TofiDesk','افتح هذا الرابط خلال ساعة لإعادة تعيين كلمة المرور:\n\n'.$link], 'verify'=>['تأكيد حساب TofiDesk','أكد حسابك خلال 24 ساعة:\n\n'.$link]],
        'fa' => ['reset'=>['بازنشانی گذرواژه TofiDesk','این پیوند را ظرف یک ساعت برای بازنشانی گذرواژه باز کنید:\n\n'.$link], 'verify'=>['تأیید حساب TofiDesk','حساب خود را ظرف ۲۴ ساعت تأیید کنید:\n\n'.$link]],
        'nl' => ['reset'=>['TofiDesk-wachtwoord resetten','Open deze link binnen één uur om uw wachtwoord opnieuw in te stellen:\n\n'.$link], 'verify'=>['Uw TofiDesk-account verifiëren','Verifieer uw account binnen 24 uur:\n\n'.$link]],
        'zh' => ['reset'=>['TofiDesk 密码重置','请在一小时内打开此链接重置密码：\n\n'.$link], 'verify'=>['验证您的 TofiDesk 账户','请在 24 小时内验证您的账户：\n\n'.$link]],
        'ja' => ['reset'=>['TofiDesk パスワードのリセット','1時間以内にこのリンクを開いてパスワードをリセットしてください。\n\n'.$link], 'verify'=>['TofiDesk アカウントの確認','24時間以内にアカウントを確認してください。\n\n'.$link]],
        'ko' => ['reset'=>['TofiDesk 비밀번호 재설정','한 시간 이내에 이 링크를 열어 비밀번호를 재설정하세요.\n\n'.$link], 'verify'=>['TofiDesk 계정 인증','24시간 이내에 계정을 인증하세요.\n\n'.$link]],
    ];
    $message = $templates[$locale][$key] ?? $templates['en'][$key];
    return [$message[0], str_replace('\\n', "\n", $message[1])];
}

function lc_account_mail_text(string $key, string $locale, string $link): array
{
    return account_mail_text($key, $locale, $link);
}

function lc_send_account_email(string $to, string $subject, string $body): void
{
    if (lc_smtp_send($to, $subject, $body)) return;
    $from = lc_env('LC_MAIL_FROM', '');
    if ($from !== '' && function_exists('mail')) {
        $headers = 'From: '.$from."\r\n".'Content-Type: text/plain; charset=UTF-8';
        if (@mail($to, $subject, $body, $headers)) return;
    }
    $dir = LC_ROOT.'/storage/outbox';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $name = $dir.'/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(8)).'.json';
    file_put_contents($name, json_encode(['to'=>$to,'subject'=>$subject,'body'=>$body,'created_at'=>gmdate('c')], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
}

function buyer_entitlements_route(): void
{
    global $path;
    if ($path !== '/account/entitlements') return;
    $buyer = buyer_required();
    $licenses = q('SELECT l.*,p.name product_name,lp.name plan_name,lp.billing_period,lp.support_days,lp.update_days FROM licenses l LEFT JOIN products p ON p.id=l.product_id LEFT JOIN license_plans lp ON lp.id=l.plan_id WHERE l.buyer_id=? ORDER BY l.id DESC', [$buyer['id']])->fetchAll();
    $cards = '';
    foreach ($licenses as $license) {
        $entitlements = q('SELECT entitlement,value FROM license_entitlements WHERE license_id=? ORDER BY entitlement', [$license['id']])->fetchAll();
        $items = '';
        foreach ($entitlements as $entitlement) $items .= '<li>'.esc(ucwords(str_replace('_',' ',(string)$entitlement['entitlement']))).': '.esc($entitlement['value']).'</li>';
        $cards .= '<article class="card"><h2>'.esc($license['product_name'] ?: $license['product']).'</h2><p class="muted">'.esc($license['plan_name'] ?: 'Manual licence').' · '.esc($license['status']).'</p><p>Domains: '.(int)$license['domain_limit'].' · Support until: '.esc($license['support_until'] ?: '—').' · Updates until: '.esc($license['updates_until'] ?: '—').'</p>'.($items !== '' ? '<ul>'.$items.'</ul>' : '').'</article>';
    }
    buyer_layout('Product entitlements','<h1>Product entitlements</h1><p class="muted">Your purchased product, licence plan and service rights.</p>'.($cards !== '' ? $cards : '<div class="card"><p>No product entitlements are assigned to this account yet.</p></div>'));
}

function buyer_updates_route(): void
{
    global $path;
    if ($path !== '/account/updates') return;
    $buyer = buyer_required();
    $licenses = q("SELECT l.*,p.id product_id,p.name product_name,p.slug FROM licenses l JOIN products p ON p.id=l.product_id WHERE l.buyer_id=? AND l.status='active'", [$buyer['id']])->fetchAll();
    $rows = '';
    foreach ($licenses as $license) {
        $releases = q("SELECT r.id,CASE WHEN p.kind='addon' THEN 'addon' ELSE 'application' END AS release_kind,r.version,r.package_name,r.changelog,r.created_at FROM application_releases r JOIN products p ON p.id=r.product_id WHERE r.product_id=? AND r.channel='stable' AND r.status='published' UNION ALL SELECT id,'theme' AS release_kind,version,package_name,changelog,created_at FROM theme_packages WHERE product_id=? AND status='published' ORDER BY created_at DESC LIMIT 10", [$license['product_id'],$license['product_id']])->fetchAll();
        foreach ($releases as $release) {
            $expired = !empty($license['updates_until']) && strtotime((string)$license['updates_until']) < time();
            $rows .= '<tr><td>'.esc($license['product_name']).'</td><td><strong>v'.esc($release['version']).'</strong> <small>'.esc(ucfirst((string)$release['release_kind'])).'</small></td><td>'.nl2br(esc($release['changelog'])).'</td><td>'.esc($release['created_at']).'</td><td>'.($expired ? '<span class="muted">Update entitlement expired</span>' : '<a class="button" href="./account/download?license_id='.(int)$license['id'].'&release_id='.(int)$release['id'].'&release_kind='.rawurlencode((string)$release['release_kind']).'">Download</a>').'</td></tr>';
        }
    }
    buyer_layout('Product updates','<h1>Product updates</h1><p class="muted">Published versions for your licensed products. Downloads are delivered through the signed updater after domain verification.</p><div class="card"><table><tr><th>Product</th><th>Version</th><th>Changelog</th><th>Published</th><th>Access</th></tr>'.($rows ?: '<tr><td colspan="5">No published updates are available yet.</td></tr>').'</table></div>');
}

function buyer_release_alerts_route(): void
{
    global $path;
    if ($path !== '/account/release-alerts') return;
    $buyer = buyer_required();
    $lang = lc_public_locale();
    $terms = [
        'tr'=>['title'=>'Sürüm bildirimleri','intro'=>'Lisanslı ürünlerinizin yeni sürümlerinden ve değişiklik günlüklerinden haberdar olun.','save'=>'Tercihleri kaydet','saved'=>'Bildirim tercihleri kaydedildi.','empty'=>'Henüz lisanslı ürün yok.'],
        'en'=>['title'=>'Release alerts','intro'=>'Stay informed about new versions and changelogs for your licensed products.','save'=>'Save preferences','saved'=>'Release preferences saved.','empty'=>'You have no licensed products yet.'],
        'de'=>['title'=>'Versionsbenachrichtigungen','intro'=>'Erhalten Sie Hinweise zu neuen Versionen und Änderungsprotokollen Ihrer lizenzierten Produkte.','save'=>'Einstellungen speichern','saved'=>'Einstellungen gespeichert.','empty'=>'Noch keine lizenzierten Produkte.'],
        'fr'=>['title'=>'Alertes de version','intro'=>'Recevez les nouvelles versions et les journaux de modifications de vos produits sous licence.','save'=>'Enregistrer','saved'=>'Préférences enregistrées.','empty'=>'Aucun produit sous licence pour le moment.'],
        'es'=>['title'=>'Alertas de versiones','intro'=>'Reciba avisos de nuevas versiones y cambios de sus productos con licencia.','save'=>'Guardar preferencias','saved'=>'Preferencias guardadas.','empty'=>'Aún no tiene productos con licencia.'],
        'it'=>['title'=>'Avvisi sulle versioni','intro'=>'Ricevi aggiornamenti sulle nuove versioni e sui changelog dei prodotti con licenza.','save'=>'Salva preferenze','saved'=>'Preferenze salvate.','empty'=>'Non hai ancora prodotti con licenza.'],
        'pt'=>['title'=>'Alertas de versões','intro'=>'Receba avisos sobre novas versões e alterações dos seus produtos licenciados.','save'=>'Guardar preferências','saved'=>'Preferências guardadas.','empty'=>'Ainda não tem produtos licenciados.'],
        'ru'=>['title'=>'Уведомления о версиях','intro'=>'Получайте уведомления о новых версиях и изменениях лицензированных продуктов.','save'=>'Сохранить настройки','saved'=>'Настройки сохранены.','empty'=>'Лицензированных продуктов пока нет.'],
        'ar'=>['title'=>'تنبيهات الإصدارات','intro'=>'تلقَّ إشعارات بالإصدارات الجديدة وسجل التغييرات لمنتجاتك المرخصة.','save'=>'حفظ التفضيلات','saved'=>'تم حفظ التفضيلات.','empty'=>'لا توجد منتجات مرخصة بعد.'],
        'fa'=>['title'=>'هشدار نسخه‌ها','intro'=>'از نسخه‌های جدید و تغییرات محصولات دارای مجوز خود مطلع شوید.','save'=>'ذخیره تنظیمات','saved'=>'تنظیمات ذخیره شد.','empty'=>'هنوز محصول دارای مجوزی ندارید.'],
        'nl'=>['title'=>'Releasemeldingen','intro'=>'Blijf op de hoogte van nieuwe versies en changelogs van uw gelicentieerde producten.','save'=>'Voorkeuren opslaan','saved'=>'Voorkeuren opgeslagen.','empty'=>'U heeft nog geen gelicentieerde producten.'],
        'zh'=>['title'=>'版本提醒','intro'=>'获取已授权产品的新版本和更新日志提醒。','save'=>'保存偏好','saved'=>'偏好已保存。','empty'=>'您还没有已授权产品。'],
        'ja'=>['title'=>'リリース通知','intro'=>'ライセンス済み製品の新バージョンと変更履歴を通知します。','save'=>'設定を保存','saved'=>'設定を保存しました。','empty'=>'ライセンス済み製品はまだありません。'],
        'ko'=>['title'=>'릴리스 알림','intro'=>'라이선스가 있는 제품의 새 버전과 변경 로그를 알려드립니다.','save'=>'환경설정 저장','saved'=>'환경설정을 저장했습니다.','empty'=>'아직 라이선스 제품이 없습니다.'],
    ][$lang] ?? ['title'=>'Release alerts','intro'=>'Stay informed about new versions and changelogs for your licensed products.','save'=>'Save preferences','saved'=>'Release preferences saved.','empty'=>'You have no licensed products yet.'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        foreach ((array)($_POST['product'] ?? []) as $productId => $value) {
            $productId = (int)$productId;
            if ($productId <= 0) continue;
            if (!one("SELECT 1 FROM licenses WHERE buyer_id=? AND product_id=? AND status='active' LIMIT 1", [$buyer['id'],$productId])) continue;
            $enabled = $value === '1' ? 1 : 0;
            q('INSERT INTO release_subscriptions(buyer_id,product_id,locale,enabled,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(buyer_id,product_id) DO UPDATE SET locale=excluded.locale,enabled=excluded.enabled,updated_at=CURRENT_TIMESTAMP', [$buyer['id'],$productId,$lang,$enabled]);
        }
        header('Location: /account/release-alerts?saved=1'); exit;
    }
    $products = q("SELECT DISTINCT p.id,p.name,p.slug,COALESCE(rs.enabled,1) enabled FROM licenses l JOIN products p ON p.id=l.product_id LEFT JOIN release_subscriptions rs ON rs.buyer_id=l.buyer_id AND rs.product_id=p.id WHERE l.buyer_id=? AND l.status='active' AND p.status='published' ORDER BY p.name", [$buyer['id']])->fetchAll();
    $rows = '';
    foreach ($products as $product) $rows .= '<label style="display:flex;align-items:center;gap:12px"><input type="hidden" name="product['.(int)$product['id'].']" value="0"><input type="checkbox" name="product['.(int)$product['id'].']" value="1"'.((int)$product['enabled']===1?' checked':'').'> <span>'.esc((string)$product['name']).'</span></label>';
    $notice = isset($_GET['saved']) ? '<p class="notice success">'.esc($terms['saved']).'</p>' : '';
    $body = '<h1>'.esc($terms['title']).'</h1><p class="muted">'.esc($terms['intro']).'</p>'.$notice.($rows !== '' ? '<form class="card" method="post"><input type="hidden" name="csrf" value="'.esc(csrf()).'"><fieldset><legend>'.esc($terms['title']).'</legend>'.$rows.'</fieldset><button>'.esc($terms['save']).'</button></form>' : '<div class="card"><p>'.esc($terms['empty']).'</p></div>');
    buyer_layout($terms['title'], $body);
}

function buyer_download_history_route(): void
{
    global $path;
    if ($path !== '/account/download-history') return;
    $buyer = buyer_required();
    $events = q("SELECT d.*,p.name AS product_name FROM download_events d LEFT JOIN products p ON p.id=d.product_id WHERE d.buyer_id=? ORDER BY d.created_at DESC,d.id DESC LIMIT 100", [$buyer['id']])->fetchAll();
    $rows = '';
    foreach ($events as $event) {
        $rows .= '<tr><td>'.esc((string)($event['product_name'] ?: 'Product')).'</td><td>'.esc((string)$event['version']).'</td><td>'.esc((string)$event['filename']).'</td><td>'.esc((string)$event['created_at']).'</td><td><code>'.esc(substr((string)$event['sha256'], 0, 16)).'…</code></td></tr>';
    }
    $body = '<h1>Download history</h1><p class="muted">Downloaded releases remain visible in your account for audit and support.</p><div class="card"><table><tr><th>Product</th><th>Release</th><th>File</th><th>Downloaded</th><th>Checksum</th></tr>'.($rows !== '' ? $rows : '<tr><td colspan="5">No downloads yet.</td></tr>').'</table></div>';
    buyer_layout('Download history', $body);
}

function buyer_download_route(): void
{
    global $path;
    if ($path !== '/account/download') return;
    $buyer = buyer_required();
    $licenseId = (int)($_GET['license_id'] ?? 0);
    $releaseId = (int)($_GET['release_id'] ?? 0);
    $license = one("SELECT l.*,p.slug product_slug FROM licenses l JOIN products p ON p.id=l.product_id WHERE l.id=? AND l.buyer_id=? AND l.status='active'", [$licenseId,$buyer['id']]);
    $releaseKind = (string)($_GET['release_kind'] ?? 'application');
    $release = $releaseKind === 'theme'
        ? one("SELECT r.*,p.slug product_slug FROM theme_packages r JOIN products p ON p.id=r.product_id WHERE r.id=? AND r.status='published'", [$releaseId])
        : one("SELECT r.*,p.slug product_slug FROM application_releases r JOIN products p ON p.id=r.product_id WHERE r.id=? AND r.status='published' AND r.channel='stable'", [$releaseId]);
    if (!$license || !$release || (int)$license['product_id'] !== (int)$release['product_id']) throw new RuntimeException('This release is not assigned to the licence.');
    if (!empty($license['updates_until']) && strtotime((string)$license['updates_until']) < time()) throw new RuntimeException('The update entitlement has expired.');
    $packagePath = $releaseKind === 'theme' ? (string)$release['storage_path'] : (string)$release['package_path'];
    if (!is_file($packagePath) || !is_readable($packagePath)) throw new RuntimeException('The release package is unavailable.');
    if (!preg_match('/^[a-f0-9]{64}$/i', (string)$release['sha256']) || !hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', $packagePath)))) throw new RuntimeException('The release integrity check failed.');
    if ($releaseKind === 'theme') {
        $signingKey = trim((string)lc_env('LICENSE_PACKAGE_SIGNING_KEY'));
        $expectedSignature = $signingKey !== '' ? hash_hmac('sha256', (string)$release['sha256'].'|'.(int)($release['theme_id'] ?? 0).'|'.(string)$release['version'], $signingKey) : '';
        if ($expectedSignature === '' || !hash_equals($expectedSignature, (string)$release['signature'])) throw new RuntimeException('The theme release signature could not be verified.');
    }
    q('INSERT INTO update_events(installation_id,license_id,from_version,to_version,action,detail) VALUES(NULL,?,?,?,?,?)', [$licenseId,'',''.$release['version'],'downloaded','buyer account download']);
    q('INSERT INTO download_events(buyer_id,license_id,product_id,release_id,release_kind,version,filename,sha256,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?,?)', [$buyer['id'],$licenseId,$release['product_id'],(int)$releaseId,$releaseKind,(string)$release['version'],basename((string)$release['package_name']),(string)$release['sha256'],substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64),substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500)]);
    q('INSERT INTO product_metrics(product_id,downloads,updated_at) VALUES(?,1,CURRENT_TIMESTAMP) ON CONFLICT(product_id) DO UPDATE SET downloads=downloads+1,updated_at=CURRENT_TIMESTAMP', [$release['product_id']]);
    header('Content-Type: application/zip');
    header('Content-Length: '.filesize($packagePath));
    header('Content-Disposition: attachment; filename="'.basename((string)$release['package_name']).'"');
    header('X-TofiDesk-SHA256: '.(string)$release['sha256']);
    readfile($packagePath); exit;
}

if ($path === '/account/forgot-password') {
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $buyer = filter_var($email, FILTER_VALIDATE_EMAIL) ? one('SELECT * FROM buyers WHERE email=?', [$email]) : null;
        if ($buyer) {
            $raw = bin2hex(random_bytes(32));
            q('UPDATE password_resets SET used_at=? WHERE buyer_id=? AND used_at IS NULL', [time(), $buyer['id']]);
            q('INSERT INTO password_resets(buyer_id,token_hash,expires_at) VALUES(?,?,?)', [$buyer['id'], hash('sha256', $raw), time()+3600]);
            $link = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/account/reset-password?token='.rawurlencode($raw);
            [$subject, $body] = lc_account_mail_text('reset', (string)($buyer['locale'] ?? lc_public_locale()), $link);
            lc_send_account_email($email, $subject, $body);
        }
        $message = 'If the address exists, reset instructions have been sent.';
    }
    buyer_layout('Forgot password', '<div class="card"><h1>Forgot your password?</h1><p class="muted">'.esc($message).'</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>Email<input type="email" name="email" required></label><button>Send reset link</button></form><p><a href="./login">Back to sign in</a></p></div>');
}

if ($path === '/account/reset-password') {
    $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    $reset = $token !== '' ? one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $token), time()]) : null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
        require_csrf();
        $pass = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($pass) < 12 || $pass !== $confirm) throw new RuntimeException('Use matching passwords of at least 12 characters.');
        q('UPDATE buyers SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $reset['buyer_id']]);
        q('INSERT INTO sales_settings(key,value,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=CURRENT_TIMESTAMP', ['buyer_session_epoch', (string)time()]);
        q('UPDATE password_resets SET used_at=? WHERE id=?', [time(), $reset['id']]);
        header('Location: ./login?reset=1'); exit;
    }
    if (!$reset) buyer_layout('Reset link expired', '<div class="card"><h1>Reset link expired</h1><p>Request a new link to continue.</p><a class="button" href="./forgot-password">Request new link</a></div>');
    buyer_layout('Create new password', '<div class="card"><h1>Create a new password</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="token" value="'.esc($token).'"><label>New password<input type="password" name="password" minlength="12" required></label><label>Confirm password<input type="password" name="password_confirm" minlength="12" required></label><button>Save new password</button></form></div>');
}

if ($path === '/account/register') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!$email || $name === '' || strlen($password) < 12) throw new RuntimeException('Name, valid email and a 12+ character password are required.');
        q('INSERT INTO buyers(email,name,password_hash) VALUES(?,?,?)', [$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        $buyerId = (int)lc_db()->lastInsertId();
        $raw = bin2hex(random_bytes(32));
        q('INSERT INTO email_verifications(buyer_id,token_hash,expires_at) VALUES(?,?,?)', [$buyerId, hash('sha256', $raw), time()+86400]);
        $link = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/account/verify-email?token='.rawurlencode($raw);
        [$subject, $body] = lc_account_mail_text('verify', (string)($_POST['locale'] ?? lc_public_locale()), $link);
        lc_send_account_email((string)$email, $subject, $body);
        $_SESSION['buyer'] = $buyerId;
        $_SESSION['buyer_epoch'] = sales_setting('buyer_session_epoch', '1');
        header('Location: ./account'); exit;
    }
    buyer_layout('Create buyer account', '<div class="card"><h1>Create buyer account</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="12" required></label><button>Create account</button></form><p class="muted">Already registered? <a href="./login">Sign in</a></p></div>');
}

if ($path === '/account/verify-email') {
    $token = (string)($_GET['token'] ?? '');
    $item = $token !== '' ? one('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $token), time()]) : null;
    if ($item) {
        q('UPDATE buyers SET email_verified_at=datetime(\'now\') WHERE id=?', [$item['buyer_id']]);
        q('UPDATE email_verifications SET used_at=? WHERE id=?', [time(), $item['id']]);
        buyer_layout('Email verified', '<div class="card"><h1>Email verified</h1><p>Your account is ready. <a href="./login">Sign in</a></p></div>');
    }
    buyer_layout('Verification link expired', '<div class="card"><h1>Verification link expired</h1><p>Request a new account verification link from support.</p></div>');
}

