<?php
declare(strict_types=1);

// This contract only inspects JSON manifests; it must not require the
// application bootstrap or a database extension.
$root = dirname(__DIR__);
$items = [];
foreach (glob($root.'/modules/*/module.json') ?: [] as $path) {
    $manifest = json_decode((string)file_get_contents($path), true);
    $items[] = ['manifest'=>is_array($manifest) ? $manifest : [], 'errors'=>is_array($manifest) ? [] : ['json']];
}
$bySlug = [];
foreach ($items as $item) {
    $manifest = is_array($item['manifest'] ?? null) ? $item['manifest'] : [];
    if (($item['errors'] ?? []) === [] && isset($manifest['slug'])) {
        $bySlug[(string)$manifest['slug']] = $manifest;
    }
}

$expected = [
    'transport-pack' => ['shipment', 'quote_request'],
    'real-estate-pack' => ['listing', 'agent'],
    'restaurant-pack' => ['restaurant', 'reservation'],
];
foreach ($expected as $slug => $types) {
    $manifest = $bySlug[$slug] ?? null;
    if (!is_array($manifest)) throw new RuntimeException('Missing reference sector module: '.$slug);
    if (($manifest['kind'] ?? '') !== 'sector') throw new RuntimeException('Reference module is not sector-scoped: '.$slug);
    foreach ($types as $type) {
        if (!in_array($type, (array)($manifest['content_types'] ?? []), true)) {
            throw new RuntimeException('Reference module lacks content type '.$type.': '.$slug);
        }
        if (!is_array($manifest['fields'][$type] ?? null) || $manifest['fields'][$type] === []) {
            throw new RuntimeException('Reference module lacks fields for '.$type.': '.$slug);
        }
    }
    if (count((array)($manifest['translations'] ?? [])) !== 14) {
        throw new RuntimeException('Reference module is not 14-locale ready: '.$slug);
    }
    if (!is_array($manifest['settings'] ?? null) || $manifest['settings'] === []) {
        throw new RuntimeException('Reference module lacks configurable settings: '.$slug);
    }
}

echo 'PASS transport, real-estate and restaurant reference sector packs'.PHP_EOL;
