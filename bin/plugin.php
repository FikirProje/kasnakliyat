<?php
declare(strict_types=1);

// Dedicated plugin SDK entrypoint; the shared module command remains the
// single validator and scaffold implementation.
$command = $argv[1] ?? 'validate';
if (in_array($command, ['--help', '-h', 'help'], true)) {
    echo "TofiDesk plugin SDK\n\n";
    echo "Usage:\n  php bin/plugin.php validate [plugins|directory]\n  php bin/plugin.php list [plugins|directory]\n  php bin/plugin.php scaffold <slug> <name>\n";
    exit(0);
}
if ($command === 'scaffold') {
    $argv = ['plugin.php', 'scaffold', (string)($argv[2] ?? ''), (string)($argv[3] ?? ''), 'plugin'];
} elseif (in_array($command, ['validate', 'list'], true)) {
    $argv = ['plugin.php', $command, dirname(__DIR__).'/plugins'];
} else {
    fwrite(STDERR, "Usage: php bin/plugin.php validate|list|scaffold <slug> <name>\n");
    exit(2);
}
require __DIR__.'/module.php';
