#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);
$command = $argv[1] ?? 'help';
$options = [];
foreach (array_slice($argv, 2) as $argument) if (preg_match('/^--([a-z_-]+)=(.*)$/', $argument, $m)) $options[$m[1]] = $m[2];
function password_input(string $prompt = 'Password (12+ characters): '): string {
    fwrite(STDERR, $prompt);
    $tty = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($tty) system('stty -echo');
    try { return rtrim(fgets(STDIN) ?: '', "\r\n"); }
    finally { if ($tty) { system('stty echo'); fwrite(STDERR, "\n"); } }
}
try {
    if ($command === 'migrate') { migrate(); echo "Database migrations applied.\n"; }
    elseif ($command === 'user:create') {
        migrate(); $email = $options['email'] ?? ''; $name = $options['name'] ?? 'Yönetici'; $role = $options['role'] ?? 'admin';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin','editor','operator'], true)) throw new RuntimeException('Use --email=address --role=admin|editor|operator.');
        $password = password_input();
        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) throw new RuntimeException('Password must have 12+ characters and at most 72 bytes.');
        query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)', [$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
        audit('user.created.cli', $email . ' / ' . $role); echo "User created.\n";
    } elseif ($command === 'user:password') {
        $user = row('SELECT id FROM users WHERE email=?', [$options['email'] ?? '']);
        if (!$user) throw new RuntimeException('User not found.');
        $password = password_input();
        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) throw new RuntimeException('Password must have 12+ characters and at most 72 bytes.');
        query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password,PASSWORD_DEFAULT),$user['id']]); audit('user.password.cli', (string)$user['id']); echo "Password changed.\n";
    } elseif ($command === 'user:2fa') {
        $user = row('SELECT id,email FROM users WHERE email=?', [$options['email'] ?? '']);
        if (!$user) throw new RuntimeException('User not found.');
        $secret = totp_secret();
        fwrite(STDERR, "Add this secret to your authenticator (do not share): " . $secret . "\n");
        fwrite(STDERR, 'Enter a current six-digit code to enable: '); $code = trim(fgets(STDIN) ?: '');
        if (!totp_valid($secret, $code)) throw new RuntimeException('Verification failed. Existing 2FA unchanged.');
        query('UPDATE users SET totp_secret=? WHERE id=?', [$secret,$user['id']]); audit('user.2fa.enabled.cli',(string)$user['id']); echo "Two-factor authentication enabled.\n";
    } elseif ($command === 'worker') {
        echo json_encode(['ai'=>process_ai_jobs(3),'mail'=>send_mail_queue(10),'notifications'=>send_notification_queue(10)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } elseif ($command === 'backup') { echo create_backup() . "\n"; }
    elseif ($command === 'check') {
        foreach (['pdo_sqlite','mbstring','fileinfo','curl','openssl','gd','zlib'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException('Missing extension: '.$extension);
        $version = query('SELECT max(version) FROM migrations')->fetchColumn();
        echo 'PHP '.PHP_VERSION.'; database version '.$version.'; '.count(locales())." locales; storage writable: ".(is_writable(storage_path())?'yes':'no')."\n";
        echo 'AI credential: '.(envv('AI_API_KEY')!==''?'configured':'not configured')."; SMTP host: ".(envv('SMTP_HOST')!==''?'configured':'not configured')."\n";
    } else {
        echo "Kaş Nakliyat\n  migrate\n  check\n  user:create --email=you@example.com --name=Name --role=admin\n  user:password --email=you@example.com\n  user:2fa --email=you@example.com\n  worker\n  backup\n";
    }
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
