<?php
declare(strict_types=1);
require __DIR__.'/../src/UpdateClient.php';

$root = sys_get_temp_dir().'/tofidesk-update-'.bin2hex(random_bytes(4));
$source = $root.'/rollback'; $destination = $root.'/current';
mkdir($source.'/src', 0700, true); mkdir($destination.'/src', 0700, true); mkdir($destination.'/storage', 0700, true);
file_put_contents($source.'/src/old.php', '<?php return "old";');
file_put_contents($destination.'/src/old.php', '<?php return "broken";');
file_put_contents($destination.'/src/new.php', '<?php return "new";');
file_put_contents($destination.'/storage/runtime.db', 'keep');
file_put_contents($destination.'/.env', 'keep-env');

update_restore_copy($source, $destination);
if (file_get_contents($destination.'/src/old.php') !== '<?php return "old";') throw new RuntimeException('Rollback did not restore an existing file.');
if (is_file($destination.'/src/new.php')) throw new RuntimeException('Rollback left a file introduced by the failed release.');
if (file_get_contents($destination.'/storage/runtime.db') !== 'keep' || file_get_contents($destination.'/.env') !== 'keep-env') throw new RuntimeException('Rollback touched runtime data.');

function integration_remove_tree(string $path): void { if (!is_dir($path)) return; $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($path); }
integration_remove_tree($root);
echo "PASS rollback restores old files, removes new files and preserves runtime data\n";
