<?php
declare(strict_types=1);

$layout = file_get_contents(__DIR__.'/../templates/public/layout.php');
$js = file_get_contents(__DIR__.'/../public/assets/site.js');
$css = file_get_contents(__DIR__.'/../public/assets/platform-search.css');
$publicSite = file_get_contents(__DIR__.'/../src/PublicSite.php');

foreach ([
    'data-location-picker', 'data-location-query', 'data-location-id',
    '/api/platform/v1/locations/search', 'aria-autocomplete="list"',
] as $needle) {
    if (!str_contains($layout.$js, $needle)) throw new RuntimeException("Location UI contract missing: {$needle}");
}
foreach (['locationQuery', 'platform_location_search($locationQuery', 'Never guess a fuzzy'] as $needle) {
    if (!str_contains($publicSite, $needle)) throw new RuntimeException("No-JS location fallback missing: {$needle}");
}
foreach (['location-suggestions', 'location-suggestion', 'field-hint'] as $needle) {
    if (!str_contains($css, $needle)) throw new RuntimeException("Location UI style missing: {$needle}");
}
echo "PASS readable hierarchical location picker and API contract\n";
