<?php
declare(strict_types=1);
$command = $argv[1] ?? 'validate';

if (in_array($command, ['--help', '-h', 'help'], true)) {
    echo "TofiDesk module/plugin SDK\n\n";
    echo "Usage:\n  php bin/module.php validate [modules|plugins|directory]\n  php bin/module.php list [modules|plugins|directory]\n  php bin/module.php scaffold <slug> <name> [sector|plugin]\n";
    exit(0);
}

// Manifest operations are intentionally database-free so package authors can
// validate/scaffold on any PHP 8.2+ cPanel/Plesk host before installation.
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
} else {
    require dirname(__DIR__).'/src/bootstrap.php';
}
$items = platform_discover_module_manifests($argv[2] ?? null);
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
if ($command === 'scaffold') {
    $slug = strtolower(trim((string)($argv[2] ?? ''))); $name = trim((string)($argv[3] ?? ''));
    $kind = strtolower(trim((string)($argv[4] ?? 'sector')));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug) || $name === '' || !in_array($kind, ['sector','plugin'], true)) { fwrite(STDERR, "Usage: php bin/module.php scaffold <slug> <name> [sector|plugin]\n"); exit(2); }
    $base = $kind === 'plugin' ? 'plugins' : 'modules';
    $manifestFile = $kind === 'plugin' ? 'plugin.json' : 'module.json';
    $directory = ROOT.'/'.$base.'/'.$slug; if (is_dir($directory)) { fwrite(STDERR, "Module directory already exists.\n"); exit(1); }
    if (!mkdir($directory, 0755, true)) { fwrite(STDERR, "Module directory could not be created.\n"); exit(1); }
    $manifest = ['slug'=>$slug,'name'=>$name,'version'=>'1.0.0','kind'=>$kind,'requires'=>['tofidesk'=>'>=1.0.0','php'=>'>=8.2'],'content_types'=>[],'taxonomies'=>[],'hooks'=>[],'permissions'=>[],'translations'=>array_keys(core_locales()),'license'=>'commercial','settings'=>[]];
    file_put_contents($directory.'/'.$manifestFile, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    echo "Created $base/$slug/$manifestFile\n"; exit(0);
}
fwrite(STDERR, "Usage: php bin/module.php validate|list|scaffold [directory]\n"); exit(2);
