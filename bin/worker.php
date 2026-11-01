<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
try {
    $mode = $argv[1] ?? 'all';
    if (!in_array($mode, ['all', 'ai', 'mail'], true)) {
        throw new InvalidArgumentException('Usage: php bin/worker.php [all|ai|mail]');
    }
    $result = [];
    if ($mode !== 'mail') {
        $result['ai'] = process_ai_jobs(3);
    }
    if ($mode !== 'ai') {
        $result['mail'] = send_mail_queue(10);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    $failures = (int) ($result['ai']['failed'] ?? 0) + (int) ($result['mail']['failed'] ?? 0);
    exit($failures ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
