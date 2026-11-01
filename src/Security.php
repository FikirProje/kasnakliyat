<?php
declare(strict_types=1);
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $provided = $_POST['csrf'] ?? $_POST['_csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) render_error(419, t('error_session', request_lang()));
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && rtrim($origin, '/') !== base_url()) render_error(403, t('error_session', request_lang()));
}
function rate_limit(string $bucket, int $limit, int $seconds): bool {
    $bucket = hash('sha256', $bucket);
    $now = time();
    query('INSERT INTO rate_limits(bucket,count,started_at) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET count=CASE WHEN started_at<=? THEN 1 ELSE count+1 END,started_at=CASE WHEN started_at<=? THEN excluded.started_at ELSE started_at END', [$bucket, $now, $now - $seconds, $now - $seconds]);
    return (int)row('SELECT count FROM rate_limits WHERE bucket=?', [$bucket])['count'] <= $limit;
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    if (time() - (int)($_SESSION['last_seen'] ?? 0) > 7200) { unset($_SESSION['user_id']); return null; }
    $_SESSION['last_seen'] = time();
    return row('SELECT id,name,email,role,totp_secret FROM users WHERE id=?', [$_SESSION['user_id']]);
}
function require_login(): array { $user = current_user(); if (!$user) redirect('/admin/login'); return $user; }
function require_role(array $roles): array { $user = require_login(); if (!in_array($user['role'], $roles, true)) render_error(403, 'Bu işlem için yetkiniz yok.'); return $user; }
function login_user(string $email, string $password, string $totp = ''): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (!rate_limit('login:' . $ip, 8, 900) || !rate_limit('account:' . mb_strtolower(trim($email)), 12, 900)) return false;
    $user = row('SELECT * FROM users WHERE email=?', [trim($email)]);
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    $valid = password_verify($password, $user['password_hash'] ?? $dummy);
    if (!$user || !$valid || (!empty($user['totp_secret']) && !totp_valid($user['totp_secret'], $totp))) {
        query('INSERT INTO login_attempts(ip) VALUES(?)', [hash('sha256', $ip)]); return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id']; $_SESSION['last_seen'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    audit('auth.login', 'Successful login'); return true;
}
function logout_user(): void {
    if (!empty($_SESSION['user_id'])) audit('auth.logout');
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
}
function totp_secret(): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $result = '';
    for ($i = 0; $i < 32; $i++) $result .= $alphabet[random_int(0, 31)];
    return $result;
}
function totp_code(string $secret, ?int $time = null): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $decoded = '';
    foreach (str_split(strtoupper($secret)) as $c) { $n = strpos($alphabet, $c); if ($n === false) throw new RuntimeException('Invalid TOTP secret.'); $bits .= str_pad(decbin($n), 5, '0', STR_PAD_LEFT); }
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $decoded .= chr(bindec(substr($bits, $i, 8)));
    $step = intdiv($time ?? time(), 30); $hash = hash_hmac('sha1', pack('N2', 0, $step), $decoded, true); $offset = ord($hash[19]) & 15;
    $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_valid(string $secret, string $code): bool {
    if (!preg_match('/^\d{6}$/', $code)) return false;
    foreach ([-30, 0, 30] as $offset) if (hash_equals(totp_code($secret, time() + $offset), $code)) return true;
    return false;
}
