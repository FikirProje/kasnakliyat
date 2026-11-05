<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$items = theme_discover_manifests();
if (count($items) !== 2 || count(array_filter($items, static fn(array $item): bool => $item['errors'] === [])) !== 2) throw new RuntimeException('Bundled theme manifests failed.');
$manifest = $items[0]['manifest'];
if (count(theme_resolve_sections('nexa-business')) !== 7) throw new RuntimeException('Theme sections failed.');
if (theme_resolve_design_tokens('nexa-business') === []) throw new RuntimeException('Design token resolution failed.');
if (theme_validate_design_tokens((array)$manifest['tokens']) !== []) throw new RuntimeException('Design token validation failed.');
if (theme_validate_demo_payload((array)$manifest['demo']) !== []) throw new RuntimeException('Demo payload validation failed.');
$overridden = theme_resolve_sections('nexa-business', ['hero'=>['title'=>'Custom']]);
if (($overridden['hero']['title'] ?? '') !== 'Custom') throw new RuntimeException('Theme section override failed.');
$child = theme_resolve_sections('nexa-business-child');
if (($child['hero']['component'] ?? '') !== 'sections/hero-child' || ($child['hero']['title'] ?? '') !== 'A sector-ready digital presence' || ($child['header']['component'] ?? '') !== 'sections/header' || ($child['footer']['component'] ?? '') !== 'sections/footer-child') throw new RuntimeException('Child theme inheritance or override failed.');
if ((theme_resolve_design_tokens('nexa-business-child')['color_primary'] ?? '') !== '#0d2e42') throw new RuntimeException('Child token inheritance failed.');
$childItem = null;
foreach ($items as $item) {
    if (($item['manifest']['slug'] ?? '') === 'nexa-business-child') {
        $childItem = $item;
        break;
    }
}
if (!is_array($childItem)) throw new RuntimeException('Child theme manifest was not discovered.');
$projection = platform_api_theme_public($childItem, true);
if (($projection['slug'] ?? '') !== 'nexa-business-child' || count((array)($projection['sections'] ?? [])) !== 7 || ($projection['tokens']['color_primary'] ?? '') !== '#0d2e42') throw new RuntimeException('Public theme projection failed.');
$custom = theme_render_sections('nexa-business', ['tokens'=>['color_accent'=>'#126e82'], 'sections'=>['hero'=>['title'=>'Configured']]]);
if (!str_contains($custom, 'Configured')) throw new RuntimeException('Theme builder section override failed.');
$thrown = false;
try { theme_safe_asset_path('nexa-business', '../secret'); } catch (Throwable) { $thrown = true; }
if (!$thrown) throw new RuntimeException('Theme traversal guard failed.');
echo "Theme SDK smoke passed.\n";
