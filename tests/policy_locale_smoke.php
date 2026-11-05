<?php
declare(strict_types=1);
require dirname(__DIR__).'/license-center/public/policy_i18n.php';
$locales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','fa','zh','ja','ko'];
$keys = ['privacy_title','privacy','terms_title','terms','terms_updates','terms_updates_text','sla_title','sla'];
foreach ($locales as $locale) foreach ($keys as $key) {
    $value = lc_public_policy($locale, $key);
    if ($value === '' || $value === $key) throw new RuntimeException("Missing policy translation: {$locale}/{$key}");
}
echo "PASS 14-locale storefront privacy, terms and support policy copy\n";
