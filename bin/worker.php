<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
try {
    $mode = $argv[1] ?? 'all';
    if (!in_array($mode, ['all', 'ai', 'mail', 'notifications', 'inbox'], true)) {
        throw new InvalidArgumentException('Usage: php bin/worker.php [all|ai|mail|notifications|inbox]');
    }
    $result = [];
    if (in_array($mode, ['all','ai'], true)) {
        $result['ai'] = process_ai_jobs(3);
    }
    if (in_array($mode, ['all','mail'], true)) {
        $result['mail'] = send_mail_queue(10);
    }
    if (in_array($mode, ['all','notifications'], true)) {
        $result['notifications'] = send_notification_queue(10);
    }
    if (in_array($mode, ['all','inbox'], true)) {
        $result['inbox'] = process_inbound_mail(20);
        $result['inbox']['purged'] = purge_inbound_mail();
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    $failures = (int) ($result['ai']['failed'] ?? 0) + (int) ($result['mail']['failed'] ?? 0) + (int) ($result['notifications']['failed'] ?? 0);
    exit($failures ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
