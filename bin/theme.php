<?php
declare(strict_types=1);
$command = $argv[1] ?? 'validate';

if (in_array($command, ['--help', '-h', 'help'], true)) {
    echo "TofiDesk theme SDK\n\n";
    echo "Usage:\n  php bin/theme.php validate [themes|directory]\n  php bin/theme.php list [themes|directory]\n  php bin/theme.php demo <theme-slug> <demo.json>\n  php bin/theme.php scaffold <slug> <name>\n";
    exit(0);
}

// Theme manifest operations must work before a database exists. The demo
// importer remains a full application operation and therefore uses bootstrap.
if (in_array($command, ['validate', 'list', 'scaffold'], true)) {
    define('ROOT', dirname(__DIR__));
    require ROOT.'/src/I18n.php';
    if (!function_exists('envv')) {
        function envv(string $key, string $default = ''): string { $value = getenv($key); return $value === false || $value === '' ? $default : $value; }
    }
    if (!function_exists('platform_allowed_events')) {
        function platform_allowed_events(): array { return ['content.created','content.saved','content.published','search.reindex','order.paid','license.issued','update.applied','booking.created','enrollment.created','application.created']; }
    }
    require ROOT.'/src/ModuleManager.php';
    require ROOT.'/src/ThemeManager.php';
} else {
    require dirname(__DIR__).'/src/bootstrap.php';
}
$items = theme_discover_manifests($argv[2] ?? null);
if ($command === 'validate') {
    $invalid = 0;
    foreach ($items as $item) {
        $ok = $item['errors'] === [];
        echo ($ok ? 'PASS ' : 'FAIL ').($item['manifest']['slug'] ?? basename((string)$item['path'])).($ok ? "\n" : ' '.implode(',', $item['errors'])."\n");
        if (!$ok) $invalid++;
    }
    exit($invalid === 0 ? 0 : 1);
}
if ($command === 'list') { echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL; exit(0); }
if ($command === 'demo') {
    $slug = (string)($argv[2] ?? ''); $file = (string)($argv[3] ?? '');
    if ($slug === '' || $file === '' || !is_file($file)) { fwrite(STDERR, "Usage: php bin/theme.php demo <theme-slug> <demo.json>\n"); exit(2); }
    $payload = json_decode((string)file_get_contents($file), true);
    if (!is_array($payload)) { fwrite(STDERR, "Demo JSON is invalid.\n"); exit(1); }
    echo json_encode(theme_import_demo($slug, $payload), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL; exit(0);
}
if ($command === 'scaffold') {
    $slug = strtolower(trim((string)($argv[2] ?? ''))); $name = trim((string)($argv[3] ?? ''));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug) || $name === '') { fwrite(STDERR, "Usage: php bin/theme.php scaffold <slug> <name>\n"); exit(2); }
    $directory = ROOT.'/themes/'.$slug; if (is_dir($directory)) { fwrite(STDERR, "Theme directory already exists.\n"); exit(1); }
    if (!mkdir($directory, 0755, true)) { fwrite(STDERR, "Theme directory could not be created.\n"); exit(1); }
    $manifest = ['slug'=>$slug,'name'=>$name,'version'=>'1.0.0','parent'=>null,'kind'=>'theme','license'=>'commercial','translations'=>array_keys(core_locales()),'requirements'=>['php'=>'>=8.2','platform'=>'>=1.0.0'],'tokens'=>['color_primary'=>'#0D2E42','color_accent'=>'#FF9149','radius'=>'8px'],'sections'=>[],'demo'=>['pages'=>[],'content_types'=>['page','post','product']]];
    file_put_contents($directory.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    echo "Created themes/$slug/theme.json\n"; exit(0);
}
fwrite(STDERR, "Usage: php bin/theme.php validate|list|demo|scaffold [directory]\n"); exit(2);
