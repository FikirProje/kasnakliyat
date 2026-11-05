<?php
declare(strict_types=1);

// Pure locale preference contract: no application bootstrap or database is needed.
require dirname(__DIR__).'/src/I18n.php';

$_COOKIE = [];
$_SERVER = ['HTTP_CF_IPCOUNTRY'=>'DE','HTTP_ACCEPT_LANGUAGE'=>'en-US,en;q=0.9'];
if (preferred_browser_locale() !== 'de') throw new RuntimeException('Country locale hint did not win over browser language.');

$_SERVER = ['HTTP_CF_IPCOUNTRY'=>'ZZ','HTTP_ACCEPT_LANGUAGE'=>'ja-JP,ko;q=0.8'];
if (preferred_browser_locale() !== 'ja') throw new RuntimeException('Accept-Language fallback failed.');

$_COOKIE = ['tofidesk_locale'=>'en'];
$_SERVER = ['HTTP_CF_IPCOUNTRY'=>'TR','HTTP_ACCEPT_LANGUAGE'=>'tr-TR'];
if (preferred_browser_locale() !== 'en') throw new RuntimeException('Explicit visitor locale override failed.');

echo "PASS country, browser and explicit locale preference precedence\n";
