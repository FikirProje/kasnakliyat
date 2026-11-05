<?php
declare(strict_types=1);

require_once __DIR__.'/../src/I18n.php';
require_once __DIR__.'/../src/InstallerI18n.php';

$locales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','fa','zh','ja','ko'];
$keys = ['title','label','eyebrow','heading','intro','server','first_admin','ready','missing','website_url','business','your_name','admin_email','password','confirm','token','token_hint','submit','language','storage_hint','url_notice','closed','footer'];
$profileKeys = ['country','timezone','currency','units','profile_note','metric','imperial'];
$errorKeys = ['name','business','country','timezone','currency','units','email','password','confirm','url','requirements','rate','database','success'];
$failures = [];
$english = [];
foreach ($keys as $key) $english[$key] = installer_t($key, 'en');
foreach ($locales as $locale) {
    foreach ($keys as $key) {
        $value = trim(installer_t($key, $locale));
        if ($value === '') $failures[] = $locale.'.'.$key.'.empty';
        // Italian uses the same internationally understood word for password.
        if ($locale !== 'en' && !($locale === 'it' && $key === 'password') && $value === ($english[$key] ?? '')) $failures[] = $locale.'.'.$key.'.english-fallback';
    }
    foreach ($profileKeys as $key) {
        $value = trim(installer_profile_t($key, $locale));
        if ($value === '') $failures[] = $locale.'.profile.'.$key.'.empty';
    }
    foreach ($errorKeys as $key) {
        $value = trim(installer_error_t($key, $locale));
        if ($value === '') $failures[] = $locale.'.error.'.$key.'.empty';
    }
    if (str_contains(installer_t('footer', $locale), 'Transport CMS')) $failures[] = $locale.'.footer.legacy-sector-copy';
}
if ($failures) {
    fwrite(STDERR, 'Installer locale failures: '.implode(', ', $failures).PHP_EOL);
    exit(1);
}
echo count($locales).' locales × '.(count($keys) + count($profileKeys) + count($errorKeys)).' installer strings passed.'.PHP_EOL;
