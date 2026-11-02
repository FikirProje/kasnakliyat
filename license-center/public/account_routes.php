<?php
declare(strict_types=1);
if (!defined('LC_ROOT')) define('LC_ROOT', __DIR__ . '/..');
foreach ([LC_ROOT.'/.env', dirname(LC_ROOT).'/.env'] as $env) if (is_file($env)) foreach (file($env, FILE_IGNORE_NEW_LINES) as $line) if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1])===false) putenv($m[1].'='.trim(trim($m[2]), "\"'"));
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/index.php')) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
}
setup();
// Keep plan entitlements enforced server-side even when a payment callback
// creates the licence directly.
lc_db()->exec("CREATE TRIGGER IF NOT EXISTS license_plan_domain_limit AFTER INSERT ON licenses BEGIN UPDATE licenses SET domain_limit=CASE WHEN product LIKE '%Agency%' THEN 10 WHEN product LIKE '%Studio%' THEN 3 ELSE 1 END WHERE id=NEW.id; END");
$sessionPath = dirname(lc_env('LICENSE_DB_PATH', LC_ROOT.'/storage/licenses.sqlite')).'/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
session_save_path($sessionPath);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($path, ['/account/login', '/account/register', '/account/forgot-password'], true)) {
    rate_limit('buyer-auth:'.($path).':'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 600);
}

// The sales centre must not load analytics before an explicit visitor choice.
ob_start(static function (string $html): string {
    $lang = function_exists('lc_public_locale') ? lc_public_locale() : 'tr';
    $localeNames = ['tr'=>'Türkçe','en'=>'English','de'=>'Deutsch','fr'=>'Français','es'=>'Español','it'=>'Italiano','pt'=>'Português','ru'=>'Русский','ar'=>'العربية','fa'=>'فارسی','nl'=>'Nederlands','zh'=>'中文','ja'=>'日本語','ko'=>'한국어'];
    $options = '';
    foreach ($localeNames as $code => $name) {
        $options .= '<option value="?lang='.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'"'.($lang === $code ? ' selected' : '').'>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</option>';
    }
    $html = preg_replace('#<span class="locale-switch">.*?</span>#s', '<label class="locale-select"><span class="sr-only">Language</span><select onchange="if(this.value)location.href=this.value">'.str_replace('<option', '<option', $options).'</select></label>', $html) ?? $html;
    $translations = [
        'tr'=>['Product'=>'Ürün','Pricing'=>'Fiyatlandırma','Themes'=>'Temalar','Contact'=>'İletişim','Buy now'=>'Satın al','Sell transport websites with confidence.'=>'Taşımacılık sitelerini güvenle satın.', 'Explore themes'=>'Temaları keşfet'],
        'de'=>['Product'=>'Produkt','Pricing'=>'Preise','Themes'=>'Themes','Contact'=>'Kontakt','Buy now'=>'Jetzt kaufen','Explore themes'=>'Themes entdecken'],
        'fr'=>['Product'=>'Produit','Pricing'=>'Tarifs','Themes'=>'Thèmes','Contact'=>'Contact','Buy now'=>'Acheter','Explore themes'=>'Découvrir les thèmes'],
        'es'=>['Product'=>'Producto','Pricing'=>'Precios','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Explore themes'=>'Ver temas'],
        'it'=>['Product'=>'Prodotto','Pricing'=>'Prezzi','Themes'=>'Temi','Contact'=>'Contatti','Buy now'=>'Acquista','Explore themes'=>'Scopri i temi'],
        'pt'=>['Product'=>'Produto','Pricing'=>'Preços','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Explore themes'=>'Explorar temas'],
        'nl'=>['Product'=>'Product','Pricing'=>'Prijzen','Themes'=>'Thema’s','Contact'=>'Contact','Buy now'=>'Kopen','Explore themes'=>'Thema’s bekijken'],
        'ru'=>['Product'=>'Продукт','Pricing'=>'Цены','Themes'=>'Темы','Contact'=>'Контакты','Buy now'=>'Купить','Explore themes'=>'Все темы'],
        'ar'=>['Product'=>'المنتج','Pricing'=>'الأسعار','Themes'=>'القوالب','Contact'=>'اتصل بنا','Buy now'=>'اشتر الآن','Explore themes'=>'استكشف القوالب'],
        'fa'=>['Product'=>'محصول','Pricing'=>'قیمت‌ها','Themes'=>'قالب‌ها','Contact'=>'تماس','Buy now'=>'خرید','Explore themes'=>'مشاهده قالب‌ها'],
        'zh'=>['Product'=>'产品','Pricing'=>'价格','Themes'=>'主题','Contact'=>'联系','Buy now'=>'立即购买','Explore themes'=>'浏览主题'],
        'ja'=>['Product'=>'製品','Pricing'=>'料金','Themes'=>'テーマ','Contact'=>'お問い合わせ','Buy now'=>'購入する','Explore themes'=>'テーマを見る'],
        'ko'=>['Product'=>'제품','Pricing'=>'가격','Themes'=>'테마','Contact'=>'문의','Buy now'=>'구매하기','Explore themes'=>'테마 보기'],
    ];
    foreach ($translations[$lang] ?? [] as $from => $to) {
        $html = str_replace($from, $to, $html);
    }
    $html = str_replace('</style>', '.locale-select select{font:600 12px Poppins,Arial;background:#ffffff18;color:#fff;border:1px solid #ffffff44;border-radius:8px;padding:8px 28px 8px 10px;max-width:150px}.locale-select option{color:#08283a;background:#fff}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}', $html);
    $accepted = ($_COOKIE['lc_cookie_consent'] ?? '') === 'accepted';
    if (!$accepted) {
        $html = preg_replace('#<script async src="https://www\.googletagmanager\.com/gtag/js\?id=[^"]+"></script><script>.*?</script>#s', '', $html) ?? $html;
        if (strpos($html, 'data-lc-cookie-banner') === false && strpos($html, '<meta name="robots" content="noindex,nofollow">') === false) {
            $html = str_replace('</html>', '<div data-lc-cookie-banner style="position:fixed;left:16px;right:16px;bottom:16px;z-index:20;background:#06283a;color:#fff;padding:16px 18px;border-radius:10px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span>We use optional analytics cookies to improve the sales experience.</span><span><button type="button" data-lc-cookie="essential">Only necessary</button> <button type="button" data-lc-cookie="accepted">Accept analytics</button></span></div><script>document.querySelectorAll("[data-lc-cookie]").forEach(function(b){b.addEventListener("click",function(){document.cookie="lc_cookie_consent="+b.dataset.lcCookie+";path=/;max-age=31536000;SameSite=Lax";location.reload()})});</script></html>', $html);
        }
    }
    return $html;
});

function lc_smtp_send(string $to, string $subject, string $body): bool
{
    $host = lc_env('LC_SMTP_HOST');
    $port = (int)lc_env('LC_SMTP_PORT', '587');
    $user = lc_env('LC_SMTP_USER');
    $pass = lc_env('LC_SMTP_PASSWORD');
    $from = lc_env('LC_MAIL_FROM');
    if ($host === '' || $from === '') return false;
    $secure = strtolower(lc_env('LC_SMTP_SECURE', 'tls'));
    $transport = $secure === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($transport.$host.':'.$port, $errno, $error, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) return false;
    stream_set_timeout($socket, 10);
    $read = static function () use ($socket): string { $line=''; do { $line.=fgets($socket, 512) ?: ''; } while (isset($line[3]) && $line[3] === '-'); return $line; };
    $write = static function (string $command) use ($socket, $read): void { fwrite($socket, $command."\r\n"); $reply=$read(); if ($reply === '' || (int)substr($reply, 0, 3) >= 400) throw new RuntimeException('SMTP command failed.'); };
    try {
        $read();
        $write('EHLO tofidesk');
        if ($secure === 'tls') { $write('STARTTLS'); if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed.'); $write('EHLO tofidesk'); }
        if ($user !== '') { $write('AUTH LOGIN'); $write(base64_encode($user)); $write(base64_encode($pass)); }
        $write('MAIL FROM:<'.$from.'>'); $write('RCPT TO:<'.$to.'>'); $write('DATA');
        fwrite($socket, 'From: '.$from."\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body."\r\n.\r\n");
        $reply=$read(); $write('QUIT'); fclose($socket);
        return (int)substr($reply, 0, 3) < 400;
    } catch (Throwable) { fclose($socket); return false; }
}

function lc_send_account_email(string $to, string $subject, string $body): void
{
    if (lc_smtp_send($to, $subject, $body)) return;
    $from = lc_env('LC_MAIL_FROM', '');
    if ($from !== '' && function_exists('mail')) {
        $headers = 'From: '.$from."\r\n".'Content-Type: text/plain; charset=UTF-8';
        if (@mail($to, $subject, $body, $headers)) return;
    }
    $dir = LC_ROOT.'/storage/outbox';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $name = $dir.'/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(8)).'.json';
    file_put_contents($name, json_encode(['to'=>$to,'subject'=>$subject,'body'=>$body,'created_at'=>gmdate('c')], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
}

if ($path === '/account/forgot-password') {
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $buyer = filter_var($email, FILTER_VALIDATE_EMAIL) ? one('SELECT * FROM buyers WHERE email=?', [$email]) : null;
        if ($buyer) {
            $raw = bin2hex(random_bytes(32));
            q('UPDATE password_resets SET used_at=? WHERE buyer_id=? AND used_at IS NULL', [time(), $buyer['id']]);
            q('INSERT INTO password_resets(buyer_id,token_hash,expires_at) VALUES(?,?,?)', [$buyer['id'], hash('sha256', $raw), time()+3600]);
            $link = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/account/reset-password?token='.rawurlencode($raw);
            lc_send_account_email($email, 'TofiDesk password reset', "Use this link within one hour:\n\n".$link);
        }
        $message = 'If the address exists, reset instructions have been sent.';
    }
    buyer_layout('Forgot password', '<div class="card"><h1>Forgot your password?</h1><p class="muted">'.esc($message).'</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>Email<input type="email" name="email" required></label><button>Send reset link</button></form><p><a href="./login">Back to sign in</a></p></div>');
}

if ($path === '/account/reset-password') {
    $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    $reset = $token !== '' ? one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $token), time()]) : null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
        require_csrf();
        $pass = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($pass) < 12 || $pass !== $confirm) throw new RuntimeException('Use matching passwords of at least 12 characters.');
        q('UPDATE buyers SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $reset['buyer_id']]);
        q('INSERT INTO sales_settings(key,value,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=CURRENT_TIMESTAMP', ['buyer_session_epoch', (string)time()]);
        q('UPDATE password_resets SET used_at=? WHERE id=?', [time(), $reset['id']]);
        header('Location: ./login?reset=1'); exit;
    }
    if (!$reset) buyer_layout('Reset link expired', '<div class="card"><h1>Reset link expired</h1><p>Request a new link to continue.</p><a class="button" href="./forgot-password">Request new link</a></div>');
    buyer_layout('Create new password', '<div class="card"><h1>Create a new password</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="token" value="'.esc($token).'"><label>New password<input type="password" name="password" minlength="12" required></label><label>Confirm password<input type="password" name="password_confirm" minlength="12" required></label><button>Save new password</button></form></div>');
}

if ($path === '/account/register') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!$email || $name === '' || strlen($password) < 12) throw new RuntimeException('Name, valid email and a 12+ character password are required.');
        q('INSERT INTO buyers(email,name,password_hash) VALUES(?,?,?)', [$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        $buyerId = (int)lc_db()->lastInsertId();
        $raw = bin2hex(random_bytes(32));
        q('INSERT INTO email_verifications(buyer_id,token_hash,expires_at) VALUES(?,?,?)', [$buyerId, hash('sha256', $raw), time()+86400]);
        $link = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/account/verify-email?token='.rawurlencode($raw);
        lc_send_account_email((string)$email, 'Verify your TofiDesk account', "Verify your account within 24 hours:\n\n".$link);
        $_SESSION['buyer'] = $buyerId;
        $_SESSION['buyer_epoch'] = sales_setting('buyer_session_epoch', '1');
        header('Location: ./account'); exit;
    }
    buyer_layout('Create buyer account', '<div class="card"><h1>Create buyer account</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="12" required></label><button>Create account</button></form><p class="muted">Already registered? <a href="./login">Sign in</a></p></div>');
}

if ($path === '/account/verify-email') {
    $token = (string)($_GET['token'] ?? '');
    $item = $token !== '' ? one('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $token), time()]) : null;
    if ($item) {
        q('UPDATE buyers SET email_verified_at=datetime(\'now\') WHERE id=?', [$item['buyer_id']]);
        q('UPDATE email_verifications SET used_at=? WHERE id=?', [time(), $item['id']]);
        buyer_layout('Email verified', '<div class="card"><h1>Email verified</h1><p>Your account is ready. <a href="./login">Sign in</a></p></div>');
    }
    buyer_layout('Verification link expired', '<div class="card"><h1>Verification link expired</h1><p>Request a new account verification link from support.</p></div>');
}
