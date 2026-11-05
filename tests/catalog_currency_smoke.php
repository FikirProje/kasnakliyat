<?php
declare(strict_types=1);

function checkout_currency_rate(string $base, string $quote): float
{
    return $base === 'EUR' && $quote === 'USD' ? 1.1 : 1.0;
}
function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

require dirname(__DIR__).'/license-center/public/catalog_public.php';

$_GET = [];
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
if (catalog_display_currency('es') !== 'USD') throw new RuntimeException('Country currency hint did not override language fallback.');
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'TR';
if (catalog_display_currency('en') !== 'TRY') throw new RuntimeException('Turkish country currency hint failed.');
$_GET['currency'] = 'GBP';
if (catalog_display_currency('tr') !== 'GBP') throw new RuntimeException('Explicit currency selection failed.');
$_GET = ['lang'=>'es', 'q'=>'theme', 'currency'=>'USD'];
$selector = catalog_currency_switcher('es');
if (strpos($selector, 'name="currency"') === false || strpos($selector, 'name="lang"') === false || strpos($selector, 'name="q"') === false) throw new RuntimeException('Currency selector did not preserve language and filters.');
unset($_GET['currency']);
if (catalog_display_amount(10000, 'EUR', 'USD') !== 11000) throw new RuntimeException('Display-only currency conversion failed.');
if (catalog_currency_key('TRY') !== 'TL' || catalog_currency_key('TL') !== 'TL' || catalog_currency_key('USD') !== 'USD') throw new RuntimeException('TRY/TL currency normalization failed.');
echo "PASS country-aware storefront currency and display conversion\n";
