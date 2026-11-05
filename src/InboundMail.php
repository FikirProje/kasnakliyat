<?php
declare(strict_types=1);

/**
 * Conservative IMAP inbox collector. Credentials only come from process
 * environment. It does not mark mail read or delete mail. Attachments are
 * bounded, MIME-filtered and stored outside the public web root.
 */
function inbound_mail_config(): array
{
    $host = strtolower(trim(envv('IMAP_HOST')));
    $port = trim(envv('IMAP_PORT', '993'));
    $user = trim(envv('IMAP_USER'));
    $password = envv('IMAP_PASSWORD');
    $folder = trim(envv('IMAP_FOLDER', 'INBOX')) ?: 'INBOX';
    if ($host === '' && $user === '' && $password === '') return [];
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
        || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
        || !filter_var($user, FILTER_VALIDATE_EMAIL) || $password === ''
        || !preg_match('/^[A-Za-z0-9._ -]{1,80}$/D', $folder)) {
        throw new RuntimeException('IMAP ayarları eksik veya geçersiz. IMAP_HOST, IMAP_PORT, IMAP_USER, IMAP_PASSWORD ve IMAP_FOLDER değerlerini kontrol edin.');
    }
    return compact('host', 'port', 'user', 'password', 'folder');
}

function process_inbound_mail(int $limit = 20): array
{
    $config = inbound_mail_config();
    if (!$config) return ['received'=>0, 'skipped'=>0, 'configured'=>false];
    if (!extension_loaded('imap') || !function_exists('imap_open')) {
        throw new RuntimeException('IMAP PHP eklentisi kurulu değil. Gelen kutusu işlenmedi.');
    }
    // Do not use novalidate-cert: inbound customer mail must fail closed if the
    // IMAP server certificate cannot be verified by the host CA store.
    $mailbox = sprintf('{%s:%d/imap/ssl}%s', $config['host'], (int)$config['port'], $config['folder']);
    $connection = @imap_open($mailbox, $config['user'], $config['password'], OP_READONLY, 1, ['DISABLE_AUTHENTICATOR'=>'GSSAPI']);
    if ($connection === false) throw new RuntimeException('IMAP bağlantısı kurulamadı. Sunucu, TLS, hesap ve ağ erişimini kontrol edin.');
    try {
        $uidValidity = (string)(imap_status($connection, $mailbox, SA_UIDVALIDITY)->uidvalidity ?? '0');
        $uids = imap_search($connection, 'UNSEEN', SE_UID) ?: [];
        rsort($uids, SORT_NUMERIC);
        $counts=['received'=>0,'skipped'=>0,'attachments'=>0,'configured'=>true];
        foreach (array_slice($uids, 0, max(0, min($limit, 100))) as $uid) {
            if (row('SELECT id FROM inbound_messages WHERE uidvalidity=? AND uid=?', [$uidValidity, (string)$uid])) { $counts['skipped']++; continue; }
            $overview = imap_fetch_overview($connection, (string)$uid, FT_UID)[0] ?? null;
            $rawHeaders = imap_fetchheader($connection, (string)$uid, FT_UID);
            $body = imap_body($connection, (string)$uid, FT_UID | FT_PEEK);
            if (!is_object($overview) || !is_string($rawHeaders) || !is_string($body) || strlen($body) > 500000) { $counts['skipped']++; continue; }
            $from = inbound_mail_header($rawHeaders, 'From');
            $subject = inbound_mail_header($rawHeaders, 'Subject');
            $messageId = inbound_mail_header($rawHeaders, 'Message-ID');
            $inReplyTo = mb_substr(inbound_mail_header($rawHeaders, 'In-Reply-To'), 0, 500);
            $references = mb_substr(inbound_mail_header($rawHeaders, 'References'), 0, 2000);
            if (!filter_var($from, FILTER_VALIDATE_EMAIL) || $subject === '' || mb_strlen($subject)>500) { $counts['skipped']++; continue; }
            $text = inbound_mail_text($body);
            $requestId = null;
            if (preg_match('/(?:Talep|Request)\s*(?:#|no\.?|number)?\s*([A-Z0-9-]{6,40})/iu', $subject.' '.$text, $match)) {
                $requestId = row('SELECT id FROM requests WHERE reference=?', [$match[1]])['id'] ?? null;
            }
            $threadKey = inbound_mail_thread_key($inReplyTo, $references, $messageId, $subject, $requestId);
            if ($requestId === null && $threadKey !== '') {
                $requestId = row('SELECT request_id FROM inbound_messages WHERE thread_key=? AND request_id IS NOT NULL ORDER BY id DESC LIMIT 1', [$threadKey])['request_id'] ?? null;
            }
            $structure = @imap_fetchstructure($connection, (string)$uid, FT_UID);
            $attachments = is_object($structure) ? inbound_mail_attachments($connection, (string)$uid, $structure) : [];
            $savedPaths = array_column($attachments, 'storage_path');
            try {
                query('INSERT INTO inbound_messages(uidvalidity,uid,message_id,in_reply_to,references_header,thread_key,request_id,sender,subject,body,attachment_count,received_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,datetime(\'now\'))', [$uidValidity,(string)$uid,mb_substr($messageId,0,500),$inReplyTo,$references,$threadKey,$requestId,$from,mb_substr($subject,0,500),$text,count($attachments),gmdate('Y-m-d H:i:s', (int)($overview->udate ?? time()))]);
                $messageIdDb = (int)db()->lastInsertId();
                foreach ($attachments as $attachment) query('INSERT INTO inbound_attachments(message_id,filename,content_type,size,sha256,storage_path) VALUES(?,?,?,?,?,?)', [$messageIdDb,$attachment['filename'],$attachment['content_type'],$attachment['size'],$attachment['sha256'],$attachment['storage_path']]);
            } catch (Throwable $error) {
                foreach ($savedPaths as $savedPath) if (is_string($savedPath) && is_file($savedPath)) @unlink($savedPath);
                throw $error;
            }
            $counts['attachments'] += count($attachments);
            $counts['received']++;
        }
        return $counts;
    } finally { imap_close($connection); }
}

function purge_inbound_mail(int $retentionDays = 0): int
{
    $days = $retentionDays > 0 ? $retentionDays : (int)envv('IMAP_RETENTION_DAYS', '0');
    if ($days < 1 || $days > 3650) return 0;
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-'.$days.' days')->format('Y-m-d H:i:s');
    $old = rows('SELECT id FROM inbound_messages WHERE received_at < ?', [$cutoff]);
    foreach ($old as $message) {
        foreach (rows('SELECT storage_path FROM inbound_attachments WHERE message_id=?', [(int)$message['id']]) as $attachment) {
            $path = (string)$attachment['storage_path'];
            if ($path !== '' && is_file($path)) @unlink($path);
        }
        query('DELETE FROM inbound_attachments WHERE message_id=?', [(int)$message['id']]);
    }
    return $old ? query('DELETE FROM inbound_messages WHERE received_at < ?', [$cutoff])->rowCount() : 0;
}

function inbound_mail_thread_key(string $inReplyTo, string $references, string $messageId, string $subject, ?int $requestId): string
{
    $ids = preg_split('/\s+/', trim($references), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $seed = $inReplyTo !== '' ? $inReplyTo : (string)($ids[0] ?? '');
    if ($seed === '' && $requestId !== null) $seed = 'request:' . $requestId;
    if ($seed === '') $seed = $messageId !== '' ? $messageId : 'subject:' . strtolower(preg_replace('/^\s*(re|fw|fwd)\s*:\s*/i', '', trim($subject)));
    return mb_substr(trim($seed), 0, 500);
}

function inbound_mail_attachments($connection, string $uid, object $structure): array
{
    $allowed = ['application/pdf','image/jpeg','image/png','image/webp','text/plain','application/zip'];
    $maxFiles = 5; $maxEach = 10 * 1024 * 1024; $maxTotal = 20 * 1024 * 1024; $total = 0; $files = [];
    $walk = function (object $part, string $partNumber = '') use (&$walk, &$files, &$total, $connection, $uid, $allowed, $maxFiles, $maxEach, $maxTotal): void {
        if (count($files) >= $maxFiles) return;
        $typeNames = [0=>'text',1=>'multipart',2=>'message',3=>'application',4=>'audio',5=>'image',6=>'video',7=>'other'];
        $type = strtolower(($typeNames[(int)($part->type ?? 7)] ?? 'other').'/'.strtolower((string)($part->subtype ?? 'octet-stream')));
        $params = [];
        foreach (['parameters','dparameters'] as $parameterGroup) foreach (($part->{$parameterGroup} ?? []) as $parameter) {
            $params[strtolower((string)($parameter->attribute ?? ''))] = (string)($parameter->value ?? '');
        }
        $filename = trim((string)($params['filename'] ?? $params['name'] ?? ''));
        $disposition = strtolower((string)($part->disposition ?? ''));
        if (($filename !== '' || $disposition === 'attachment') && in_array($type, $allowed, true) && $partNumber !== '') {
            $raw = @imap_fetchbody($connection, $uid, $partNumber, FT_UID | FT_PEEK);
            $encoding = (int)($part->encoding ?? 0);
            if ($encoding === 3) $raw = base64_decode(preg_replace('/\s+/', '', (string)$raw), true);
            elseif ($encoding === 4) $raw = quoted_printable_decode((string)$raw);
            if (is_string($raw) && $raw !== '' && strlen($raw) <= $maxEach && $total + strlen($raw) <= $maxTotal) {
                $directory = storage_path('mail/attachments');
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Gelen e-posta ek dizini oluşturulamadı.');
                $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename ?: 'attachment.bin')) ?: 'attachment.bin';
                $path = $directory.'/'.bin2hex(random_bytes(16)).'.bin';
                if (file_put_contents($path, $raw, LOCK_EX) !== strlen($raw)) throw new RuntimeException('Gelen e-posta eki kaydedilemedi.');
                chmod($path, 0600);
                $files[] = ['filename'=>mb_substr($safeName,0,180),'content_type'=>$type,'size'=>strlen($raw),'sha256'=>hash('sha256',$raw),'storage_path'=>$path];
                $total += strlen($raw);
            }
        }
        foreach (($part->parts ?? []) as $index => $child) $walk($child, $partNumber === '' ? (string)($index + 1) : $partNumber.'.'.($index + 1));
    };
    $walk($structure);
    return $files;
}

function inbound_mail_header(string $headers, string $name): string
{
    if (!preg_match('/^'.preg_quote($name, '/').':\s*(.+(?:\R[ \t].+)*)$/mi', $headers, $match)) return '';
    $value = trim(preg_replace('/\R[ \t]+/', ' ', $match[1]));
    $decoded = function_exists('iconv_mime_decode') ? @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : $value;
    if ($name === 'From' && preg_match('/<([^>]+)>/', $decoded ?: $value, $email)) return trim($email[1]);
    return trim((string)($decoded ?: $value));
}

function inbound_mail_text(string $body): string
{
    $body = preg_replace('/\r\n?/', "\n", $body) ?? '';
    $body = preg_replace('/\n--[\w-]+(?:--)?\s*$/s', '', $body) ?? $body;
    $body = strip_tags($body);
    return mb_substr(trim($body), 0, 100000);
}
