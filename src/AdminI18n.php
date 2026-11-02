<?php
declare(strict_types=1);

function admin_dictionary(): array
{
    static $dictionary;
    if ($dictionary !== null) return $dictionary;
    $dictionary = [
        'tr'=>['management'=>'YÖNETİM MERKEZİ','welcome'=>'Yeniden merhaba.','email'=>'E-posta','password'=>'Parola','login'=>'Güvenli giriş yap','logout'=>'Oturumu kapat','open_site'=>'Web sitesini aç','workspace'=>'ÇALIŞMA ALANINIZ','skip'=>'İçeriğe geç','language'=>'Dil','save_language'=>'Dili kaydet','language_saved'=>'Yönetim dili kaydedildi.'],
        'en'=>['management'=>'ADMIN CENTER','welcome'=>'Welcome back.','email'=>'Email','password'=>'Password','login'=>'Sign in securely','logout'=>'Sign out','open_site'=>'Open website','workspace'=>'YOUR WORKSPACE','skip'=>'Skip to content','language'=>'Language','save_language'=>'Save language','language_saved'=>'Admin language saved.'],
        'de'=>['management'=>'ADMIN-BEREICH','welcome'=>'Willkommen zurück.','email'=>'E-Mail','password'=>'Passwort','login'=>'Sicher anmelden','logout'=>'Abmelden','open_site'=>'Website öffnen','workspace'=>'IHR ARBEITSBEREICH','skip'=>'Zum Inhalt springen','language'=>'Sprache','save_language'=>'Sprache speichern','language_saved'=>'Admin-Sprache gespeichert.'],
        'fr'=>['management'=>'CENTRE ADMIN','welcome'=>'Bon retour.','email'=>'E-mail','password'=>'Mot de passe','login'=>'Connexion sécurisée','logout'=>'Se déconnecter','open_site'=>'Ouvrir le site','workspace'=>'VOTRE ESPACE DE TRAVAIL','skip'=>'Aller au contenu','language'=>'Langue','save_language'=>'Enregistrer la langue','language_saved'=>'Langue admin enregistrée.'],
        'es'=>['management'=>'CENTRO DE ADMINISTRACIÓN','welcome'=>'Bienvenido de nuevo.','email'=>'Correo electrónico','password'=>'Contraseña','login'=>'Acceder de forma segura','logout'=>'Cerrar sesión','open_site'=>'Abrir sitio web','workspace'=>'SU ESPACIO DE TRABAJO','skip'=>'Ir al contenido','language'=>'Idioma','save_language'=>'Guardar idioma','language_saved'=>'Idioma del admin guardado.'],
        'it'=>['management'=>'CENTRO AMMINISTRAZIONE','welcome'=>'Bentornato.','email'=>'E-mail','password'=>'Password','login'=>'Accedi in sicurezza','logout'=>'Esci','open_site'=>'Apri sito','workspace'=>'IL TUO SPAZIO DI LAVORO','skip'=>'Vai al contenuto','language'=>'Lingua','save_language'=>'Salva lingua','language_saved'=>'Lingua admin salvata.'],
        'pt'=>['management'=>'CENTRO DE ADMINISTRAÇÃO','welcome'=>'Bem-vindo de volta.','email'=>'E-mail','password'=>'Palavra-passe','login'=>'Entrar com segurança','logout'=>'Terminar sessão','open_site'=>'Abrir site','workspace'=>'O SEU ESPAÇO DE TRABALHO','skip'=>'Avançar para o conteúdo','language'=>'Idioma','save_language'=>'Guardar idioma','language_saved'=>'Idioma do admin guardado.'],
        'nl'=>['management'=>'BEHEERCENTRUM','welcome'=>'Welkom terug.','email'=>'E-mail','password'=>'Wachtwoord','login'=>'Veilig aanmelden','logout'=>'Afmelden','open_site'=>'Website openen','workspace'=>'UW WERKRUIMTE','skip'=>'Naar inhoud','language'=>'Taal','save_language'=>'Taal opslaan','language_saved'=>'Beheertaal opgeslagen.'],
        'ru'=>['management'=>'ЦЕНТР УПРАВЛЕНИЯ','welcome'=>'С возвращением.','email'=>'Эл. почта','password'=>'Пароль','login'=>'Безопасный вход','logout'=>'Выйти','open_site'=>'Открыть сайт','workspace'=>'ВАШЕ РАБОЧЕЕ ПРОСТРАНСТВО','skip'=>'Перейти к содержимому','language'=>'Язык','save_language'=>'Сохранить язык','language_saved'=>'Язык панели сохранён.'],
        'ar'=>['management'=>'مركز الإدارة','welcome'=>'مرحباً بعودتك.','email'=>'البريد الإلكتروني','password'=>'كلمة المرور','login'=>'تسجيل دخول آمن','logout'=>'تسجيل الخروج','open_site'=>'فتح الموقع','workspace'=>'مساحة عملك','skip'=>'الانتقال إلى المحتوى','language'=>'اللغة','save_language'=>'حفظ اللغة','language_saved'=>'تم حفظ لغة الإدارة.'],
        'zh'=>['management'=>'管理中心','welcome'=>'欢迎回来。','email'=>'电子邮件','password'=>'密码','login'=>'安全登录','logout'=>'退出登录','open_site'=>'打开网站','workspace'=>'您的工作区','skip'=>'跳到内容','language'=>'语言','save_language'=>'保存语言','language_saved'=>'管理语言已保存。'],
        'ja'=>['management'=>'管理センター','welcome'=>'おかえりなさい。','email'=>'メールアドレス','password'=>'パスワード','login'=>'安全にログイン','logout'=>'ログアウト','open_site'=>'サイトを開く','workspace'=>'あなたのワークスペース','skip'=>'コンテンツへ移動','language'=>'言語','save_language'=>'言語を保存','language_saved'=>'管理画面の言語を保存しました。'],
        'ko'=>['management'=>'관리 센터','welcome'=>'다시 오신 것을 환영합니다.','email'=>'이메일','password'=>'비밀번호','login'=>'안전하게 로그인','logout'=>'로그아웃','open_site'=>'웹사이트 열기','workspace'=>'내 작업 공간','skip'=>'콘텐츠로 이동','language'=>'언어','save_language'=>'언어 저장','language_saved'=>'관리 언어가 저장되었습니다.'],
        'fa'=>['management'=>'مرکز مدیریت','welcome'=>'خوش آمدید.','email'=>'ایمیل','password'=>'گذرواژه','login'=>'ورود امن','logout'=>'خروج','open_site'=>'باز کردن وب‌سایت','workspace'=>'فضای کاری شما','skip'=>'رفتن به محتوا','language'=>'زبان','save_language'=>'ذخیره زبان','language_saved'=>'زبان مدیریت ذخیره شد.'],
    ];
    $fallback = $dictionary['en'];
    foreach (core_locales() as $code => $label) $dictionary[$code] = $dictionary[$code] ?? $fallback;
    return $dictionary;
}

function admin_t(string $key, string $lang = 'en'): string
{
    $dictionary = admin_dictionary();
    return $dictionary[$lang][$key] ?? $dictionary['en'][$key] ?? $key;
}
