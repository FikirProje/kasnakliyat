<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/AccountMailI18n.php';
$locales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','zh','ja','ko','fa'];
$link = 'https://example.test/account/reset?token=fixture';
foreach ($locales as $locale) foreach (['reset','verify'] as $key) {
    [$subject, $body] = account_mail_text($key, $locale, $link);
    if ($subject === '' || !str_contains($body, $link) || !str_contains($body, "\n\n")) throw new RuntimeException("mail template failed: {$locale}/{$key}");
}
echo count($locales).' locales × 2 account email templates passed.'.PHP_EOL;
