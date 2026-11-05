<?php
declare(strict_types=1);

const LC_INSTALL_ROOT = __DIR__ . '/..';

foreach ([LC_INSTALL_ROOT . '/.env', dirname(LC_INSTALL_ROOT) . '/.env'] as $envFile) {
    if (!is_file($envFile)) continue;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $match) && getenv($match[1]) === false) {
            putenv($match[1] . '=' . trim(trim($match[2]), "\"'"));
        }
    }
}

function li_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function li_locale(): string
{
    $supported = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
    $requested = (string)($_POST['lang'] ?? $_GET['lang'] ?? '');
    if ($requested === '') {
        foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (in_array($code, $supported, true)) { $requested = $code; break; }
        }
    }
    return in_array($requested, $supported, true) ? $requested : 'en';
}

function li_dictionary(): array
{
    return [
        'tr'=>['title'=>'TofiDesk Lisans Merkezi kurulumu','intro'=>'Sunucu kontrolü tamamlandı. İlk yöneticiyi oluşturun.','requirements'=>'Sunucu gereksinimleri','resolve'=>'Devam etmeden önce aşağıdaki gereksinimleri karşılayın.','email'=>'E-posta','password'=>'Şifre','create'=>'Yönetici oluştur','already'=>'Zaten kurulu','already_help'=>'Bu lisans merkezinde bir yönetici hesabı zaten bulunuyor.','login'=>'Girişe git','invalid'=>'Geçersiz kurulum','invalid_help'=>'Geçerli bir e-posta ve en az 12 karakterlik bir şifre kullanın.','dbfail'=>'Veritabanı kontrolü başarısız','token'=>'İlk kurulum anahtarsızdır','token_help'=>'İlk yönetici oluşturulduktan sonra kurulum otomatik olarak kapanır.','storage'=>'Yazılabilir depolama'],
        'en'=>['title'=>'Install TofiDesk License Centre','intro'=>'Server checks passed. Create the first administrator.','requirements'=>'Server requirements','resolve'=>'Resolve the requirements below before continuing.','email'=>'Email','password'=>'Password','create'=>'Create administrator','already'=>'Already installed','already_help'=>'This license centre already has an administrator account.','login'=>'Go to sign in','invalid'=>'Invalid setup','invalid_help'=>'Use a valid email and a password of at least 12 characters.','dbfail'=>'Database check failed','token'=>'Tokenless first setup','token_help'=>'The installer closes automatically after the first administrator is created.','storage'=>'Writable storage'],
        'de'=>['title'=>'TofiDesk-Lizenzzentrum installieren','intro'=>'Die Serverprüfung ist erfolgreich. Erstellen Sie den ersten Administrator.','requirements'=>'Serveranforderungen','resolve'=>'Erfüllen Sie die folgenden Anforderungen, bevor Sie fortfahren.','email'=>'E-Mail','password'=>'Passwort','create'=>'Administrator erstellen','already'=>'Bereits installiert','already_help'=>'Dieses Lizenzzentrum besitzt bereits ein Administratorkonto.','login'=>'Zur Anmeldung','invalid'=>'Ungültige Einrichtung','invalid_help'=>'Verwenden Sie eine gültige E-Mail und ein Passwort mit mindestens 12 Zeichen.','dbfail'=>'Datenbankprüfung fehlgeschlagen','token'=>'Ersteinrichtung ohne Token','token_help'=>'Nach dem Erstellen des ersten Administrators wird die Installation automatisch geschlossen.','storage'=>'Beschreibbarer Speicher'],
        'fr'=>['title'=>'Installer le centre de licences TofiDesk','intro'=>'La vérification du serveur est terminée. Créez le premier administrateur.','requirements'=>'Exigences du serveur','resolve'=>'Résolvez les exigences ci-dessous avant de continuer.','email'=>'E-mail','password'=>'Mot de passe','create'=>'Créer l’administrateur','already'=>'Déjà installé','already_help'=>'Ce centre de licences possède déjà un compte administrateur.','login'=>'Aller à la connexion','invalid'=>'Configuration invalide','invalid_help'=>'Utilisez un e-mail valide et un mot de passe d’au moins 12 caractères.','dbfail'=>'Échec de la vérification de la base de données','token'=>'Installation initiale sans jeton','token_help'=>'L’installation se ferme automatiquement après la création du premier administrateur.','storage'=>'Stockage accessible en écriture'],
        'es'=>['title'=>'Instalar el centro de licencias TofiDesk','intro'=>'La comprobación del servidor fue correcta. Cree el primer administrador.','requirements'=>'Requisitos del servidor','resolve'=>'Resuelva los requisitos siguientes antes de continuar.','email'=>'Correo electrónico','password'=>'Contraseña','create'=>'Crear administrador','already'=>'Ya está instalado','already_help'=>'Este centro de licencias ya tiene una cuenta de administrador.','login'=>'Ir al inicio de sesión','invalid'=>'Configuración no válida','invalid_help'=>'Use un correo válido y una contraseña de al menos 12 caracteres.','dbfail'=>'Falló la comprobación de la base de datos','token'=>'Configuración inicial sin token','token_help'=>'El instalador se cierra automáticamente después de crear el primer administrador.','storage'=>'Almacenamiento escribible'],
        'it'=>['title'=>'Installa il centro licenze TofiDesk','intro'=>'Controllo del server completato. Crea il primo amministratore.','requirements'=>'Requisiti del server','resolve'=>'Risolvi i requisiti seguenti prima di continuare.','email'=>'E-mail','password'=>'Password','create'=>'Crea amministratore','already'=>'Già installato','already_help'=>'Questo centro licenze ha già un account amministratore.','login'=>'Vai al login','invalid'=>'Configurazione non valida','invalid_help'=>'Usa un’e-mail valida e una password di almeno 12 caratteri.','dbfail'=>'Controllo del database non riuscito','token'=>'Prima configurazione senza token','token_help'=>'Il programma di installazione si chiude automaticamente dopo la creazione del primo amministratore.','storage'=>'Archiviazione scrivibile'],
        'pt'=>['title'=>'Instalar o centro de licenças TofiDesk','intro'=>'A verificação do servidor foi concluída. Crie o primeiro administrador.','requirements'=>'Requisitos do servidor','resolve'=>'Resolva os requisitos abaixo antes de continuar.','email'=>'E-mail','password'=>'Palavra-passe','create'=>'Criar administrador','already'=>'Já instalado','already_help'=>'Este centro de licenças já possui uma conta de administrador.','login'=>'Ir para o início de sessão','invalid'=>'Configuração inválida','invalid_help'=>'Use um e-mail válido e uma palavra-passe com pelo menos 12 caracteres.','dbfail'=>'Falha na verificação da base de dados','token'=>'Configuração inicial sem token','token_help'=>'O instalador fecha automaticamente após a criação do primeiro administrador.','storage'=>'Armazenamento gravável'],
        'nl'=>['title'=>'TofiDesk License Centre installeren','intro'=>'De servercontrole is geslaagd. Maak de eerste beheerder aan.','requirements'=>'Serververeisten','resolve'=>'Los de onderstaande vereisten op voordat u doorgaat.','email'=>'E-mail','password'=>'Wachtwoord','create'=>'Beheerder maken','already'=>'Al geïnstalleerd','already_help'=>'Dit licentiecentrum heeft al een beheerdersaccount.','login'=>'Naar aanmelden','invalid'=>'Ongeldige installatie','invalid_help'=>'Gebruik een geldig e-mailadres en een wachtwoord van minimaal 12 tekens.','dbfail'=>'Databasecontrole mislukt','token'=>'Eerste installatie zonder token','token_help'=>'De installer sluit automatisch nadat de eerste beheerder is aangemaakt.','storage'=>'Schrijfbare opslag'],
        'ru'=>['title'=>'Установка центра лицензий TofiDesk','intro'=>'Проверка сервера пройдена. Создайте первого администратора.','requirements'=>'Требования сервера','resolve'=>'Устраните указанные требования перед продолжением.','email'=>'Электронная почта','password'=>'Пароль','create'=>'Создать администратора','already'=>'Уже установлено','already_help'=>'В этом центре лицензий уже есть учётная запись администратора.','login'=>'Перейти ко входу','invalid'=>'Неверная настройка','invalid_help'=>'Используйте действующую почту и пароль длиной не менее 12 символов.','dbfail'=>'Ошибка проверки базы данных','token'=>'Первоначальная настройка без токена','token_help'=>'После создания первого администратора установщик закроется автоматически.','storage'=>'Записываемое хранилище'],
        'ar'=>['title'=>'تثبيت مركز تراخيص TofiDesk','intro'=>'اكتمل فحص الخادم. أنشئ المدير الأول.','requirements'=>'متطلبات الخادم','resolve'=>'عالج المتطلبات التالية قبل المتابعة.','email'=>'البريد الإلكتروني','password'=>'كلمة المرور','create'=>'إنشاء المدير','already'=>'مثبّت مسبقاً','already_help'=>'يحتوي مركز التراخيص هذا على حساب مدير بالفعل.','login'=>'الانتقال إلى تسجيل الدخول','invalid'=>'إعداد غير صالح','invalid_help'=>'استخدم بريداً صالحاً وكلمة مرور من 12 حرفاً على الأقل.','dbfail'=>'فشل فحص قاعدة البيانات','token'=>'إعداد أولي بلا رمز','token_help'=>'سيُغلق المثبّت تلقائياً بعد إنشاء المدير الأول.','storage'=>'تخزين قابل للكتابة'],
        'fa'=>['title'=>'نصب مرکز مجوز TofiDesk','intro'=>'بررسی سرور با موفقیت انجام شد. نخستین مدیر را ایجاد کنید.','requirements'=>'نیازمندی‌های سرور','resolve'=>'پیش از ادامه، نیازمندی‌های زیر را برطرف کنید.','email'=>'ایمیل','password'=>'گذرواژه','create'=>'ایجاد مدیر','already'=>'از قبل نصب شده','already_help'=>'این مرکز مجوز از قبل حساب مدیر دارد.','login'=>'رفتن به ورود','invalid'=>'تنظیم نامعتبر','invalid_help'=>'ایمیل معتبر و گذرواژه حداقل ۱۲ کاراکتری وارد کنید.','dbfail'=>'بررسی پایگاه داده ناموفق بود','token'=>'راه‌اندازی اولیه بدون توکن','token_help'=>'پس از ایجاد نخستین مدیر، نصب‌کننده به‌صورت خودکار بسته می‌شود.','storage'=>'ذخیره‌سازی قابل نوشتن'],
        'zh'=>['title'=>'安装 TofiDesk 许可证中心','intro'=>'服务器检查通过。请创建首位管理员。','requirements'=>'服务器要求','resolve'=>'继续前请解决以下要求。','email'=>'电子邮箱','password'=>'密码','create'=>'创建管理员','already'=>'已安装','already_help'=>'此许可证中心已有管理员账户。','login'=>'前往登录','invalid'=>'设置无效','invalid_help'=>'请输入有效邮箱和至少 12 个字符的密码。','dbfail'=>'数据库检查失败','token'=>'无需令牌的首次设置','token_help'=>'创建首位管理员后，安装程序会自动关闭。','storage'=>'可写存储'],
        'ja'=>['title'=>'TofiDesk ライセンスセンターをインストール','intro'=>'サーバー確認が完了しました。最初の管理者を作成してください。','requirements'=>'サーバー要件','resolve'=>'続行する前に次の要件を解決してください。','email'=>'メールアドレス','password'=>'パスワード','create'=>'管理者を作成','already'=>'インストール済み','already_help'=>'このライセンスセンターには既に管理者アカウントがあります。','login'=>'ログインへ','invalid'=>'設定が無効です','invalid_help'=>'有効なメールアドレスと12文字以上のパスワードを使用してください。','dbfail'=>'データベース確認に失敗しました','token'=>'トークン不要の初期設定','token_help'=>'最初の管理者を作成するとインストーラーは自動的に閉じます。','storage'=>'書き込み可能なストレージ'],
        'ko'=>['title'=>'TofiDesk 라이선스 센터 설치','intro'=>'서버 확인이 완료되었습니다. 첫 관리자를 만드세요.','requirements'=>'서버 요구 사항','resolve'=>'계속하기 전에 다음 요구 사항을 해결하세요.','email'=>'이메일','password'=>'비밀번호','create'=>'관리자 만들기','already'=>'이미 설치됨','already_help'=>'이 라이선스 센터에는 이미 관리자 계정이 있습니다.','login'=>'로그인으로 이동','invalid'=>'잘못된 설정','invalid_help'=>'유효한 이메일과 12자 이상의 비밀번호를 사용하세요.','dbfail'=>'데이터베이스 확인 실패','token'=>'토큰 없는 초기 설정','token_help'=>'첫 관리자를 만들면 설치 프로그램이 자동으로 닫힙니다.','storage'=>'쓰기 가능한 저장소'],
    ];
}

function li_t(string $key, string $lang): string
{
    $dictionary = li_dictionary();
    return $dictionary[$lang][$key] ?? $dictionary['en'][$key] ?? $key;
}

function li_db(): PDO
{
    static $db;
    if (!$db) {
        $path = li_env('LICENSE_DB_PATH', LC_INSTALL_ROOT . '/storage/licenses.sqlite');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

function li_requirements(): array
{
    $storage = li_env('LICENSE_STORAGE_PATH', LC_INSTALL_ROOT . '/storage');
    return [
        'PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'PDO SQLite' => extension_loaded('pdo_sqlite'),
        'SQLite3' => extension_loaded('sqlite3'),
        'cURL' => extension_loaded('curl'),
        'OpenSSL' => extension_loaded('openssl'),
        'JSON' => extension_loaded('json'),
        'mbstring' => extension_loaded('mbstring'),
        li_dictionary()[li_locale()]['storage'] => is_dir($storage) ? is_writable($storage) : is_writable(dirname($storage)),
        'HTTPS or local server' => !empty($_SERVER['HTTPS']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost','127.0.0.1'], true),
    ];
}

function li_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function li_html(string $title, string $body): never
{
    $lang = li_locale();
    $dir = in_array($lang, ['ar','fa'], true) ? 'rtl' : 'ltr';
    $names = ['tr'=>'Türkçe','en'=>'English','de'=>'Deutsch','fr'=>'Français','es'=>'Español','it'=>'Italiano','pt'=>'Português','ru'=>'Русский','ar'=>'العربية','fa'=>'فارسی','nl'=>'Nederlands','zh'=>'中文','ja'=>'日本語','ko'=>'한국어'];
    $options = '';
    foreach ($names as $code => $name) $options .= '<option value="?lang='.$code.'"'.($code === $lang ? ' selected' : '').'>'.li_e($name).'</option>';
    echo '<!doctype html><html lang="'.li_e($lang).'" dir="'.$dir.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.li_e($title).' · TofiDesk</title><style>body{font:15px/1.6 Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:linear-gradient(135deg,#061f2d,#0d5264);color:#153346;margin:0;min-height:100vh}.box{max-width:980px;margin:48px auto;padding:42px;background:#f7faf8;border-radius:24px;box-shadow:0 25px 80px #00141f66}.brand{color:#ff7c2c;font-size:13px;font-weight:800;letter-spacing:3px}.box h1{font-size:clamp(30px,4vw,48px);line-height:1.08;color:#08283a;margin:28px 0 12px}.box h2{font-size:18px;color:#08283a;margin-top:30px}.box p{color:#61757c}.box ul{list-style:none;padding:0}.box li{padding:12px 15px;margin:7px 0;border:1px solid #dce8e3;border-radius:10px;background:#fff}.ok{color:#16834b}.bad{color:#b42318}.langs{display:flex;justify-content:flex-end}.langs select{font:600 13px Inter,system-ui,sans-serif;color:#08283a;background:#fff;border:1px solid #cbdad8;border-radius:8px;padding:9px 28px 9px 10px;min-width:140px}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}label{display:block;margin:16px 0;font-weight:700;color:#365665}input{display:block;width:100%;box-sizing:border-box;padding:13px 14px;margin-top:7px;border:1px solid #cbdad8;border-radius:9px;background:#fff}button{background:#ff7c2c;color:#08283a;border:0;border-radius:9px;padding:14px 22px;font-weight:800;cursor:pointer;box-shadow:0 8px 18px #ff7c2c44}@media(max-width:760px){.box{margin:0;padding:26px 20px;border-radius:0;min-height:100vh}}</style></head><body><main class="box"><div class="brand">TOFIDESK</div><nav class="langs"><label class="sr-only">Language</label><select onchange="if(this.value)location.href=this.value">'.$options.'</select></nav><h1>'.li_e($title).'</h1>'.$body.'</main></body></html>';
    exit;
}

$lang = li_locale();
$t = static fn(string $key): string => li_t($key, $lang);
$checks = li_requirements();
$errors = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($errors) li_html($t('requirements'), '<p class="bad">'.li_e($t('resolve')).'</p><ul>'.implode('', array_map(static fn(string $name): string => '<li class="bad">✗ '.li_e($name).'</li>', $errors)).'</ul>');

try {
    $db = li_db();
    $db->exec('CREATE TABLE IF NOT EXISTS admins(id INTEGER PRIMARY KEY,email TEXT UNIQUE NOT NULL,password_hash TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    if ($db->query('SELECT id FROM admins LIMIT 1')->fetch()) {
        // Keep the installer file in the release for safe updates, but make
        // the endpoint inert once an administrator exists. This is safer than relying on
        // a customer to delete a file through Plesk/cPanel.
        http_response_code(403);
        li_html($t('already'), '<p>'.li_e($t('already_help')).' <a href="./login">'.li_e($t('login')).'</a></p>');
    }
} catch (Throwable $error) {
    li_html($t('dbfail'), '<p class="bad">'.li_e($error->getMessage()).'</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    if (!$email || strlen($password) < 12) li_html($t('invalid'), '<p class="bad">'.li_e($t('invalid_help')).'</p>');
    try {
        $stmt = li_db()->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)');
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
        header('Location: ./login');
        exit;
    } catch (Throwable $error) {
        li_html($t('dbfail'), '<p class="bad">'.li_e($error->getMessage()).'</p>');
    }
}

$items = '';
foreach ($checks as $name => $ok) $items .= '<li class="'.($ok ? 'ok' : 'bad').'">'.($ok ? '✓' : '✗').' '.li_e($name).'</li>';
li_html($t('title'), '<p>'.li_e($t('intro')).'</p><p class="muted">'.li_e($t('token')).' — '.li_e($t('token_help')).'</p><h2>'.li_e($t('requirements')).'</h2><ul>'.$items.'</ul><form method="post"><input type="hidden" name="lang" value="'.li_e($lang).'"><label>'.li_e($t('email')).'<input type="email" name="email" required autocomplete="username"></label><label>'.li_e($t('password')).'<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><button>'.li_e($t('create')).'</button></form>');
