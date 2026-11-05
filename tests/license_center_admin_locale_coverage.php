<?php
declare(strict_types=1);

require __DIR__.'/../license-center/public/admin_i18n.php';

$locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
$required = ['Dashboard','Themes','Licences','Orders','Requests','Support','Health','Settings','Sign out','Save','Publish','Draft','Published','Profile','Security','Notifications','Releases','Installations','Update events','Activation anomalies','Revocations','Review cases','Product QA','Product AI','Commerce controls','Manual payments','Pricing and licences','Privacy policy','Terms of service','Support SLA','Product quality review','Quality','Assets','Missing','Action','Review moderation','Approve and publish','Complete missing fields','No pending reviews.','Product catalogue','Create software, themes and add-ons as first-class products.','Type','Software','Theme','Add-on','Categories','Tags','14-language content and SEO','Translations','Updated','Theme package releases','Store a signed-ready theme release.','Sales','Release'];
$dictionary = lc_admin_dictionary();
$failures = [];
foreach ($locales as $locale) {
    foreach ($required as $key) {
        if (!isset($dictionary[$locale][$key]) || trim((string)$dictionary[$locale][$key]) === '') $failures[] = $locale.'.'.$key;
    }
    if ($locale !== 'en') {
        $sample = lc_admin_translate_html(implode(' · ', $required), $locale);
        if ($sample === implode(' · ', $required)) $failures[] = $locale.'.unchanged';
    }
}
if ($failures) { fwrite(STDERR, 'Missing license-center admin translations: '.implode(', ', $failures).PHP_EOL); exit(1); }
echo count($locales).' locales × '.count($required).' license-center admin labels passed.'.PHP_EOL;
