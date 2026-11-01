<?php
declare(strict_types=1);

/**
 * Conservative IMAP inbox collector. Credentials only come from process
 * environment. It does not mark mail read, delete mail, or download files.
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
        $counts=['received'=>0,'skipped'=>0,'configured'=>true];
        foreach (array_slice($uids, 0, max(0, min($limit, 100))) as $uid) {
            if (row('SELECT id FROM inbound_messages WHERE uidvalidity=? AND uid=?', [$uidValidity, (string)$uid])) { $counts['skipped']++; continue; }
            $overview = imap_fetch_overview($connection, (string)$uid, FT_UID)[0] ?? null;
            $rawHeaders = imap_fetchheader($connection, (string)$uid, FT_UID);
            $body = imap_body($connection, (string)$uid, FT_UID | FT_PEEK);
            if (!is_object($overview) || !is_string($rawHeaders) || !is_string($body) || strlen($body) > 500000) { $counts['skipped']++; continue; }
            $from = inbound_mail_header($rawHeaders, 'From');
            $subject = inbound_mail_header($rawHeaders, 'Subject');
            $messageId = inbound_mail_header($rawHeaders, 'Message-ID');
            if (!filter_var($from, FILTER_VALIDATE_EMAIL) || $subject === '' || mb_strlen($subject)>500) { $counts['skipped']++; continue; }
            $text = inbound_mail_text($body);
            $requestId = null;
            if (preg_match('/(?:Talep|Request)\s*(?:#|no\.?|number)?\s*([A-Z0-9-]{6,40})/iu', $subject.' '.$text, $match)) {
                $requestId = row('SELECT id FROM requests WHERE reference=?', [$match[1]])['id'] ?? null;
            }
            query('INSERT INTO inbound_messages(uidvalidity,uid,message_id,request_id,sender,subject,body,received_at,created_at) VALUES(?,?,?,?,?,?,?,?,datetime(\'now\'))', [$uidValidity,(string)$uid,mb_substr($messageId,0,500),$requestId,$from,mb_substr($subject,0,500),$text,gmdate('Y-m-d H:i:s', (int)($overview->udate ?? time()))]);
            $counts['received']++;
        }
        return $counts;
    } finally { imap_close($connection); }
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
