<?php
declare(strict_types=1);
$htaccess = file_get_contents(dirname(__DIR__).'/license-center/.htaccess');
$endpoint = file_get_contents(dirname(__DIR__).'/license-center/public/seo.php');
$layout = file_get_contents(dirname(__DIR__).'/templates/public/layout.php');
$productCenter = file_get_contents(dirname(__DIR__).'/license-center/public/index.php');
foreach (['sitemap\\.xml','robots\\.txt'] as $rule) if (!is_string($htaccess) || strpos($htaccess, $rule) === false) throw new RuntimeException('Missing SEO rewrite: '.$rule);
foreach (['application/xml','urlset','LICENSE_DB_PATH','localized_slug','Disallow: /admin'] as $needle) if (!is_string($endpoint) || strpos($endpoint, $needle) === false) throw new RuntimeException('Missing SEO endpoint contract: '.$needle);
foreach (['google_site_verification','bing_site_verification','yandex_site_verification','msvalidate.01','yandex-verification'] as $needle) if (!is_string($layout) || strpos($layout, $needle) === false) throw new RuntimeException('Missing webmaster verification contract: '.$needle);
foreach (['bing_verification','yandex_verification','msvalidate.01','yandex-verification'] as $needle) if (!is_string($productCenter) || strpos($productCenter, $needle) === false) throw new RuntimeException('Missing Product Center webmaster contract: '.$needle);
echo "PASS dynamic sitemap and robots endpoint contract\n";
