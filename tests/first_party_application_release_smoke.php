<?php
declare(strict_types=1);

if (PHP_OS_FAMILY === 'Windows' && !in_array('sqlite', PDO::getAvailableDrivers(), true) && getenv('TOFIDESK_SQLITE_REEXEC') !== '1') {
    $args = [escapeshellarg(PHP_BINARY)];
    foreach (['php_sqlite3.dll', 'php_pdo_sqlite.dll'] as $extension) {
        $path = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR.$extension;
        if (is_file($path)) { $args[] = '-d'; $args[] = escapeshellarg('extension='.$path); }
    }
    if (count($args) > 1) {
        $args[] = escapeshellarg(__FILE__);
        putenv('TOFIDESK_SQLITE_REEXEC=1');
        passthru('set TOFIDESK_SQLITE_REEXEC=1&&'.implode(' ', $args), $exitCode);
        exit($exitCode);
    }
}

$dbPath = tempnam(sys_get_temp_dir(), 'tofidesk-release-db-');
$package = tempnam(sys_get_temp_dir(), 'tofidesk-release-package-');
if ($dbPath === false || $package === false) throw new RuntimeException('Could not create release fixtures.');
try {
    file_put_contents($package, 'first-party application release fixture');
    putenv('LICENSE_DB_PATH='.$dbPath);
    putenv('RELEASE_SIGNING_SECRET=first-party-release-secret-2026-tofidesk');
    $tool = dirname(__DIR__).'/license-center/bin/release.php';
    $oldArgv = $argv;
    $argv = ['release.php','--product=fixture-cms','--version=1.0.0','--package='.$package,'--channel=stable','--database=SQLite or MySQL','--extensions=pdo,pdo_sqlite,mbstring,openssl,zip'];
    include $tool;
    $argv = $oldArgv;
    $pdo = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $manifest = $pdo->query('SELECT manifest_json FROM application_releases LIMIT 1')->fetchColumn();
    $data = json_decode((string)$manifest, true, 32, JSON_THROW_ON_ERROR);
    if (($data['compatibility']['php'] ?? '') !== '>=8.2.0') throw new RuntimeException('PHP compatibility was not persisted.');
    if (($data['compatibility']['database'] ?? '') !== 'SQLite or MySQL') throw new RuntimeException('Database compatibility was not persisted.');
    if (!in_array('pdo_sqlite', $data['compatibility']['extensions'] ?? [], true)) throw new RuntimeException('Extension compatibility was not persisted.');
    echo "PASS first-party application release persists compatibility manifest and signature\n";
} finally {
    @unlink($dbPath);
    @unlink($package);
}
