<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/AdminI18n.php';

$locales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','zh','ja','ko','fa'];
$keys = ['management','welcome','email','password','login','logout','open_site','workspace','skip','language','save_language','language_saved'];
$dictionary = admin_dictionary();
$failures = [];
foreach ($locales as $locale) {
    foreach ($keys as $key) {
        if (!isset($dictionary[$locale][$key]) || trim((string)$dictionary[$locale][$key]) === '') $failures[] = $locale.'.'.$key;
    }
}
if ($failures) { fwrite(STDERR, "Missing admin translations: ".implode(', ', $failures).PHP_EOL); exit(1); }
echo count($locales).' locales × '.count($keys).' admin strings passed.'.PHP_EOL;
