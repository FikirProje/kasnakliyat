<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$name = trim((string)setting('pwa_name', setting('business_name', 'Kaş Nakliyat')));
$description = trim((string)setting('pwa_description', setting('site_description', 'Nakliyat ve taşıma hizmetleri için hızlı teklif ve randevu platformu.')));
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo json_encode([
    'name'=>$name, 'short_name'=>mb_substr($name,0,24), 'description'=>$description,
    'lang'=>'tr', 'dir'=>'ltr', 'start_url'=>'/tr/', 'scope'=>'/', 'display'=>'standalone',
    'background_color'=>'#f7faf8', 'theme_color'=>setting('theme_primary','#0D2E42'),
    'icons'=>[['src'=>setting('favicon_url','/assets/icon-192.png'),'sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable']],
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
