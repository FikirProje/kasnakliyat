<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../license-center/public/account_routes.php');
$locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
foreach ($locales as $locale) {
    if (!str_contains($source, "'{$locale}'=>['lang'=>'{$locale}'")) {
        throw new RuntimeException('Missing localized preflight copy: '.$locale);
    }
}
if (str_contains($source, '<html lang="tr"><meta charset="utf-8"><meta name="viewport"')) {
    throw new RuntimeException('Preflight page still has a hard-coded Turkish document language.');
}
if (!str_contains($source, "in_array(\$locale, ['ar','fa'], true)")) {
    throw new RuntimeException('Preflight RTL direction is missing.');
}
echo count($locales).' locales × localized license-center preflight coverage passed.'.PHP_EOL;
