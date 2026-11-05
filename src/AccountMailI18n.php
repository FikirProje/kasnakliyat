<?php
declare(strict_types=1);

/** Shared localized transactional account messages. */
function account_mail_text(string $key, string $locale, string $link): array
{
    $locale = in_array($locale, ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'], true) ? $locale : 'en';
    $templates = [
        'en' => ['reset'=>['TofiDesk password reset','Use this link within one hour:\n\n'.$link], 'verify'=>['Verify your TofiDesk account','Verify your account within 24 hours:\n\n'.$link]],
        'tr' => ['reset'=>['TofiDesk parola sıfırlama','Parolanızı bir saat içinde yenilemek için bu bağlantıyı açın:\n\n'.$link], 'verify'=>['TofiDesk hesap doğrulama','Hesabınızı 24 saat içinde doğrulamak için bu bağlantıyı açın:\n\n'.$link]],
        'de' => ['reset'=>['TofiDesk-Passwort zurücksetzen','Öffnen Sie diesen Link innerhalb einer Stunde, um Ihr Passwort zurückzusetzen:\n\n'.$link], 'verify'=>['TofiDesk-Konto bestätigen','Bestätigen Sie Ihr Konto innerhalb von 24 Stunden:\n\n'.$link]],
        'fr' => ['reset'=>['Réinitialisation du mot de passe TofiDesk','Ouvrez ce lien dans l’heure pour réinitialiser votre mot de passe :\n\n'.$link], 'verify'=>['Vérifiez votre compte TofiDesk','Vérifiez votre compte dans les 24 heures :\n\n'.$link]],
        'es' => ['reset'=>['Restablecimiento de contraseña de TofiDesk','Abra este enlace durante la próxima hora para restablecer su contraseña:\n\n'.$link], 'verify'=>['Verifique su cuenta de TofiDesk','Verifique su cuenta en un plazo de 24 horas:\n\n'.$link]],
        'it' => ['reset'=>['Reimpostazione password TofiDesk','Apri questo link entro un’ora per reimpostare la password:\n\n'.$link], 'verify'=>['Verifica il tuo account TofiDesk','Verifica il tuo account entro 24 ore:\n\n'.$link]],
        'pt' => ['reset'=>['Reposição da palavra-passe TofiDesk','Abra esta ligação dentro de uma hora para repor a palavra-passe:\n\n'.$link], 'verify'=>['Verifique a sua conta TofiDesk','Verifique a sua conta no prazo de 24 horas:\n\n'.$link]],
        'ru' => ['reset'=>['Сброс пароля TofiDesk','Откройте эту ссылку в течение часа, чтобы сменить пароль:\n\n'.$link], 'verify'=>['Подтвердите аккаунт TofiDesk','Подтвердите аккаунт в течение 24 часов:\n\n'.$link]],
        'ar' => ['reset'=>['إعادة تعيين كلمة مرور TofiDesk','افتح هذا الرابط خلال ساعة لإعادة تعيين كلمة المرور:\n\n'.$link], 'verify'=>['تأكيد حساب TofiDesk','أكد حسابك خلال 24 ساعة:\n\n'.$link]],
        'fa' => ['reset'=>['بازنشانی گذرواژه TofiDesk','این پیوند را ظرف یک ساعت برای بازنشانی گذرواژه باز کنید:\n\n'.$link], 'verify'=>['تأیید حساب TofiDesk','حساب خود را ظرف ۲۴ ساعت تأیید کنید:\n\n'.$link]],
        'nl' => ['reset'=>['TofiDesk-wachtwoord resetten','Open deze link binnen één uur om uw wachtwoord opnieuw in te stellen:\n\n'.$link], 'verify'=>['Uw TofiDesk-account verifiëren','Verifieer uw account binnen 24 uur:\n\n'.$link]],
        'zh' => ['reset'=>['TofiDesk 密码重置','请在一小时内打开此链接重置密码：\n\n'.$link], 'verify'=>['验证您的 TofiDesk 账户','请在 24 小时内验证您的账户。\n\n'.$link]],
        'ja' => ['reset'=>['TofiDesk パスワードのリセット','1時間以内にこのリンクを開いてパスワードをリセットしてください。\n\n'.$link], 'verify'=>['TofiDesk アカウントの確認','24時間以内にアカウントを確認してください。\n\n'.$link]],
        'ko' => ['reset'=>['TofiDesk 비밀번호 재설정','한 시간 이내에 이 링크를 열어 비밀번호를 재설정하세요.\n\n'.$link], 'verify'=>['TofiDesk 계정 인증','24시간 이내에 계정을 인증하세요.\n\n'.$link]],
    ];
    $message = $templates[$locale][$key] ?? $templates['en'][$key];
    return [$message[0], str_replace('\\n', "\n", $message[1])];
}
