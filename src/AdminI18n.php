<?php
declare(strict_types=1);

function admin_dictionary(): array
{
    static $dictionary;
    if ($dictionary !== null) return $dictionary;
    $dictionary = [
        'tr'=>['management'=>'YÖNETİM MERKEZİ','welcome'=>'Yeniden merhaba.','email'=>'E-posta','password'=>'Parola','login'=>'Güvenli giriş yap','logout'=>'Oturumu kapat','open_site'=>'Web sitesini aç','workspace'=>'ÇALIŞMA ALANINIZ','skip'=>'İçeriğe geç','language'=>'Dil','save_language'=>'Dili kaydet','language_saved'=>'Yönetim dili kaydedildi.'],
        'en'=>['management'=>'ADMIN CENTER','welcome'=>'Welcome back.','email'=>'Email','password'=>'Password','login'=>'Sign in securely','logout'=>'Sign out','open_site'=>'Open website','workspace'=>'YOUR WORKSPACE','skip'=>'Skip to content','language'=>'Language','save_language'=>'Save language','language_saved'=>'Admin language saved.'],
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
