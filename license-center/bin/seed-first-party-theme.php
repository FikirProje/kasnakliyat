<?php
declare(strict_types=1);

/**
 * Publish a first-party, data-only theme release from a manifest.
 *
 * Usage:
 *   php bin/seed-first-party-theme.php --db=/private/licenses.sqlite \
 *     --key-env=LICENSE_PACKAGE_SIGNING_KEY \
 *     --preview-video=https://cdn.example.test/nexa-preview.mp4
 *
 * The command never puts PHP or executable files in the theme ZIP. It is
 * intentionally explicit about the signing key and preview video so a
 * release cannot silently bypass the Product Quality requirements.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }

$root = dirname(__DIR__);
foreach ([$root.'/.env', dirname($root).'/.env'] as $env) {
    if (!is_file($env)) continue;
    foreach (file($env, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1]) === false) putenv($m[1].'='.trim(trim($m[2]), "\"'"));
    }
}

function arg_value(string $name, string $default = ''): string
{
    global $argv;
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name) + 3);
    return $default;
}

$dbPath = arg_value('db', getenv('LICENSE_DB_PATH') ?: $root.'/storage/licenses.sqlite');
$manifestPath = arg_value('manifest', $root.'/seed/nexa-business/theme.json');
$productSlug = arg_value('product', 'nexa-business-theme');
$version = arg_value('version', '1.0.0');
$previewVideo = trim(arg_value('preview-video'));
$keyEnv = arg_value('key-env', 'LICENSE_PACKAGE_SIGNING_KEY');
$signingKey = trim((string)(getenv($keyEnv) ?: ''));

if (!is_file($dbPath)) throw new RuntimeException('Database not found: '.$dbPath);
if (!is_file($manifestPath)) throw new RuntimeException('Theme manifest not found: '.$manifestPath);
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) throw new RuntimeException('Version must use semantic versioning.');
if ($signingKey === '') throw new RuntimeException('A private signing key is required in '.$keyEnv.'; nothing was published.');
if ($previewVideo !== '' && filter_var($previewVideo, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Preview video must be an absolute URL.');
if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive is required.');

$manifestRaw = file_get_contents($manifestPath);
$manifest = json_decode((string)$manifestRaw, true, 32, JSON_THROW_ON_ERROR);
if (!is_array($manifest) || ($manifest['kind'] ?? 'theme') !== 'theme') throw new RuntimeException('Manifest must describe a theme.');
$manifest['version'] = $version;
$manifest['release'] = ['version'=>$version,'generated_at'=>gmdate('c'),'source'=>'first-party'];
$manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";

$db = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$productStmt = $db->prepare("SELECT id,kind,status FROM products WHERE slug=? LIMIT 1");
$productStmt->execute([$productSlug]);
$product = $productStmt->fetch();
if (!$product || $product['kind'] !== 'theme') throw new RuntimeException('Published theme product not found: '.$productSlug);

$themeStmt = $db->prepare('SELECT id,name FROM themes WHERE name=? ORDER BY id LIMIT 1');
$themeStmt->execute([(string)($manifest['name'] ?? 'Nexa Business')]);
$theme = $themeStmt->fetch();
if (!$theme) {
    $insertTheme = $db->prepare("INSERT INTO themes(name,locale,status,version) VALUES(?,?, 'published',?)");
    $insertTheme->execute([(string)($manifest['name'] ?? 'Nexa Business'), 'global', $version]);
    $theme = ['id'=>(int)$db->lastInsertId(),'name'=>(string)($manifest['name'] ?? 'Nexa Business')];
}

$packageDir = rtrim((string)(getenv('LICENSE_PACKAGE_PATH') ?: $root.'/storage/theme-packages'), '/').'/'.(int)$theme['id'];
if (!is_dir($packageDir) && !mkdir($packageDir, 0700, true) && !is_dir($packageDir)) throw new RuntimeException('Theme package directory is not writable.');
$packagePath = $packageDir.'/theme-'.(int)$theme['id'].'-'.str_replace(['.','+'],['_','_'],$version).'-first-party.zip';
$zip = new ZipArchive();
if ($zip->open($packagePath, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create theme package.');
$zip->addFromString('theme.json', $manifestJson);
$zip->addFromString('README.md', "# ".((string)($manifest['name'] ?? 'Theme'))."\n\nData-only TofiDesk theme release ".$version.".\n");
$zip->close();
@chmod($packagePath, 0600);
$sha = hash_file('sha256', $packagePath);
$signature = hash_hmac('sha256', $sha.'|'.(int)$theme['id'].'|'.$version, $signingKey);
$requirements = json_encode(['min_php'=>'8.2.0','browser'=>'modern evergreen browser','notes'=>'Data-only theme; no executable files.'], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$changelog = 'First-party Nexa Business Theme release '.$version.'.';
$stmt = $db->prepare('INSERT INTO theme_packages(theme_id,product_id,version,package_name,storage_path,sha256,signature,requirements_json,changelog,status,preview_video_url,theme_config_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([(int)$theme['id'],(int)$product['id'],$version,basename($packagePath),$packagePath,$sha,$signature,$requirements,$changelog,'published',$previewVideo,json_encode($manifest['tokens'] ?? [], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
if ($previewVideo !== '') {
    $asset = $db->prepare("INSERT OR IGNORE INTO product_assets(product_id,kind,label,url,status) VALUES(?,?,?,?, 'published')");
    $asset->execute([(int)$product['id'],'video','Theme preview video',$previewVideo]);
}
fwrite(STDOUT, "Published {$productSlug} v{$version}\nPackage: {$packagePath}\nSHA-256: {$sha}\nSignature: {$signature}\n");
