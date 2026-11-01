<?php
declare(strict_types=1);

// Focused regression checks. No provider calls, credentials or application data.
$temporary = sys_get_temp_dir() . '/kasnakliyat-integrations-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);
putenv('STORAGE_PATH=' . $temporary . '/storage');
putenv('DB_PATH=' . $temporary . '/app.sqlite');
foreach (['AI_API_KEY', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_FROM'] as $name) {
    putenv($name . '=');
}
require dirname(__DIR__) . '/src/bootstrap.php';
$passed = 0;
$failed = false;
function integration_check(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passed++;
    echo 'PASS ' . $message . PHP_EOL;
}
function integration_rejects(callable $action, string $message): void
{
    try {
        $action();
    } catch (RuntimeException $error) {
        integration_check(true, $message);
        return;
    }
    integration_check(false, $message);
}
function integration_archive_entries(string $filename): array
{
    $archive = gzdecode((string) file_get_contents($filename));
    if ($archive === false || substr($archive, -1024) !== str_repeat("\0", 1024)) {
        throw new RuntimeException('Archive gzip data or termination is invalid');
    }
    $entries = [];
    $offset = 0;
    while ($offset + 512 <= strlen($archive)) {
        $header = substr($archive, $offset, 512);
        if ($header === str_repeat("\0", 512)) {
            break;
        }
        $checksum = array_sum(unpack('C*', substr_replace($header, str_repeat(' ', 8), 148, 8)));
        if ($checksum !== octdec(trim(substr($header, 148, 8), "\0 ")) || substr($header, 257, 6) !== "ustar\0") {
            throw new RuntimeException('Archive USTAR header/checksum is invalid');
        }
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");
        $name = ($prefix !== '' ? $prefix . '/' : '') . $name;
        $size = (int) octdec(trim(substr($header, 124, 12), "\0 "));
        if (!in_array($header[156], ['0', '5'], true) || $offset + 512 + $size > strlen($archive)) {
            throw new RuntimeException('Archive entry type or length is invalid');
        }
        $entries[$name] = substr($archive, $offset + 512, $size);
        $offset += 512 + $size + (512 - $size % 512) % 512;
    }
    return $entries;
}
try {
    migrate();
    save_setting('ai_provider', '  ');
    save_setting('ai_model', '');
    integration_check(ai_provider_setting() === 'openai' && ai_model_setting() === 'gpt-4.1-mini', 'blank saved AI settings retain supported defaults');
    save_setting('ai_model', 'gpt-4.1');
    integration_check(ai_model_setting() === 'gpt-4.1', 'an explicit AI model is preserved');
    save_setting('ai_model', '');
    query("INSERT INTO articles(kind,source_lang) VALUES('article','tr')");
    $article = (int) db()->lastInsertId();
    query("INSERT INTO translations(article_id,lang,title,slug,body,status) VALUES(?,'tr','Kaynak','kaynak','Korunacak kaynak metin','published')", [$article]);
    $job = enqueue_ai_job($article, 'tr', 'image', 'Original editorial illustration');
    $result = process_ai_jobs(1);
    integration_check($result === ['completed' => 0, 'failed' => 1], 'image generation without a key is a recorded failure');
    integration_check(str_contains(row('SELECT error FROM jobs WHERE id=?', [$job])['error'], 'AI_API_KEY'), 'image job retains an actionable error');
    integration_check((int) row('SELECT count(*) AS n FROM media')['n'] === 0, 'failed image generation creates no media');
    $source = row('SELECT body,status,cover_media_id FROM translations WHERE article_id=?', [$article]);
    integration_check($source['body'] === 'Korunacak kaynak metin' && $source['status'] === 'published' && $source['cover_media_id'] === null, 'failed image generation preserves published text and cover');

    integration_rejects(fn() => enqueue_mail("customer@example.test\r\nBcc: other@example.test", 'Subject', 'Body'), 'recipient header injection is rejected');
    integration_rejects(fn() => enqueue_mail('customer@example.test', "Subject\r\nBcc: other@example.test", 'Body'), 'subject header injection is rejected');

    $active = enqueue_mail('customer@example.test', 'Active lease', 'Keep the active worker claim.');
    query("UPDATE mail_queue SET status='sending',attempts=1,claimed_at=datetime('now') WHERE id=?", [$active]);
    integration_check(send_mail_queue(1) === ['sent' => 0, 'failed' => 0], 'an active SMTP lease cannot be claimed twice');
    query("UPDATE mail_queue SET claimed_at=datetime('now','-11 minutes') WHERE id=?", [$active]);
    integration_check(send_mail_queue(1) === ['sent' => 0, 'failed' => 1], 'a stale SMTP lease is recovered and processed');
    $mail = row('SELECT status,attempts,claimed_at,body FROM mail_queue WHERE id=?', [$active]);
    integration_check($mail['status'] === 'failed' && (int) $mail['attempts'] === 2 && $mail['claimed_at'] === null && $mail['body'] === 'Keep the active worker claim.', 'recovered SMTP failures retain the body and clear the lease');

    $exhausted = enqueue_mail('customer@example.test', 'Exhausted lease', 'Retain this record.');
    query("UPDATE mail_queue SET status='sending',attempts=3,claimed_at=datetime('now','-11 minutes') WHERE id=?", [$exhausted]);
    send_mail_queue(1);
    $mail = row('SELECT status,attempts,claimed_at FROM mail_queue WHERE id=?', [$exhausted]);
    integration_check($mail['status'] === 'failed' && (int) $mail['attempts'] === 3 && $mail['claimed_at'] === null, 'an exhausted stale SMTP lease is failed without an extra attempt');

    $job = enqueue_ai_job($article, 'en', 'localize', 'Translate naturally.');
    query("UPDATE jobs SET status='running',attempts=1,updated_at=datetime('now') WHERE id=?", [$job]);
    integration_check(process_ai_jobs(1) === ['completed' => 0, 'failed' => 0], 'an active AI lease cannot be claimed twice');
    query("UPDATE jobs SET updated_at=datetime('now','-11 minutes') WHERE id=?", [$job]);
    integration_check(process_ai_jobs(1) === ['completed' => 0, 'failed' => 1], 'a stale AI lease is recovered without overwriting content');
    integration_check((int) row('SELECT attempts FROM jobs WHERE id=?', [$job])['attempts'] === 2, 'AI lease recovery increments the attempt count');

    $emptyBackup = integration_archive_entries(create_backup());
    integration_check(isset($emptyBackup['app.sqlite'], $emptyBackup['manifest.json'], $emptyBackup['media/'], $emptyBackup['media/originals/']), 'native backup retains empty media directories and database');
    $emptyManifest = json_decode($emptyBackup['manifest.json'], true, 32, JSON_THROW_ON_ERROR);
    integration_check($emptyManifest['environment_secret_files_included'] === false && $emptyManifest['application_auth_data_included'] === true, 'backup manifest accurately describes secret files and authentication data');

    $image = imagecreatetruecolor(2, 2);
    $customerFile = $temporary . '/customer.png';
    imagepng($image, $customerFile);
    $customerMedia = media_store_file($customerFile, 'customer.png', 'tr', false);
    integration_check(row('SELECT purpose FROM media WHERE id=?', [$customerMedia])['purpose'] === 'customer', 'media imports are private customer media by default');
    $libraryFile = $temporary . '/library.png';
    imagepng($image, $libraryFile);
    imagedestroy($image);
    $libraryMedia = media_store_file($libraryFile, 'library.png', 'tr', false, 'library');
    integration_check(row('SELECT purpose FROM media WHERE id=?', [$libraryMedia])['purpose'] === 'library', 'editorial library media requires an explicit purpose');
    integration_rejects(fn() => media_store_file(__FILE__, 'invalid.png', 'tr', false, 'public'), 'invalid media purposes are rejected');
    $mediaBackup = integration_archive_entries(create_backup());
    $manifest = json_decode($mediaBackup['manifest.json'], true, 32, JSON_THROW_ON_ERROR);
    $matching = true;
    foreach ($manifest['sha256'] as $name => $hash) {
        $matching = $matching && isset($mediaBackup[$name]) && hash('sha256', $mediaBackup[$name]) === $hash;
    }
    integration_check($matching && count($manifest['sha256']) >= 5, 'native backup round-trips database and nonempty media with matching hashes');

    $archiveInput = $temporary . '/archive-input';
    $longDirectory = str_repeat('a', 80);
    mkdir($archiveInput . '/' . $longDirectory, 0700, true);
    $payloads = [];
    foreach ([0, 1, 511, 512, 513] as $length) {
        $name = $longDirectory . '/' . str_repeat('b', 80) . '-' . $length;
        $payloads[$name] = $length ? random_bytes($length) : '';
        file_put_contents($archiveInput . '/' . $name, $payloads[$name]);
    }
    integration_write_archive($archiveInput, $temporary . '/boundaries.tar.gz');
    $roundtrip = integration_archive_entries($temporary . '/boundaries.tar.gz');
    $matching = true;
    foreach ($payloads as $name => $body) {
        $matching = $matching && isset($roundtrip[$name]) && $roundtrip[$name] === $body;
    }
    integration_check($matching, 'native USTAR handles prefixes and binary files at 512-byte boundaries');

    integration_rejects(fn() => media_path(['filename' => '../app.sqlite']), 'media path traversal is rejected');
    integration_rejects(fn() => media_upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'name' => 'fake.png']), 'filesystem paths cannot impersonate HTTP uploads');
    integration_rejects(fn() => backup_path('../app.sqlite'), 'backup path traversal is rejected');
    echo $passed . ' integration assertions passed.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    integration_remove_tree($temporary);
}
exit($failed ? 1 : 0);
