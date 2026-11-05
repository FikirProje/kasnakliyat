<?php
declare(strict_types=1);

/** Read-only source/package quality gate for a TofiDesk release. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }
$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$checks = [];
$check = static function (string $name, bool $ok, string $detail = '') use (&$checks): void { $checks[] = ['name'=>$name,'ok'=>$ok,'detail'=>$detail]; };
$expectedLocales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','fa','zh','ja','ko'];
$i18n = (string)@file_get_contents($root.'/src/I18n.php');
preg_match('/return \[(.*?)\];/s', $i18n, $localeMatch);
$localeCount = isset($localeMatch[1]) ? preg_match_all("/'([a-z]{2})'\s*=>/", $localeMatch[1]) : 0;
$check('14 core locales', $localeCount === 14, 'found='.$localeCount);
$check('RTL locales', str_contains($i18n, "['ar', 'fa']"), 'ar/fa direction contract');
$check('browser country and language preference', str_contains($i18n, 'HTTP_CF_IPCOUNTRY') && str_contains($i18n, 'HTTP_ACCEPT_LANGUAGE'), 'country and Accept-Language fallback');
$requiredFiles = ['src/Platform.php','src/PlatformApi.php','src/ModuleManager.php','src/ThemeManager.php','src/ThemeRenderer.php','src/Database.php','bin/console.php','bin/cron.php','bin/worker.php','bin/module.php','bin/theme.php','bin/location.php','public/index.php','install.php'];
foreach ($requiredFiles as $file) $check('file '.$file, is_file($root.'/'.$file));
$requiredFunctions = ['platform_create_entry','platform_register_content_field','platform_upsert_taxonomy','platform_upsert_term','platform_entry_terms','platform_set_entry_terms','platform_search_advanced','platform_location_search','platform_favorite','platform_compare_add','platform_review_submit','platform_popularity_touch','platform_popular','platform_requirement_matches','platform_sync_permission_catalog','platform_user_can','platform_cache_set','platform_maintenance','platform_queue_push','platform_register_webhook','platform_adapter_statuses'];
$platform = (string)@file_get_contents($root.'/src/Platform.php');
$moduleManager = (string)@file_get_contents($root.'/src/ModuleManager.php');
foreach ($requiredFunctions as $function) $check('function '.$function, str_contains($platform, 'function '.$function.'(') || str_contains($moduleManager, 'function '.$function.'('));
$check('module manifest hook and permission validation', str_contains($moduleManager, 'platform_allowed_events()') && str_contains($moduleManager, "'permissions'"));
$api = (string)@file_get_contents($root.'/src/PlatformApi.php');
foreach (['modules','themes','themes/detail','locations','locations/search','taxonomies','taxonomies/terms','entries','schema','menus','forms','forms/submit','search','related','reviews','popular'] as $resource) $check('API '.$resource, str_contains($api, $resource));
$admin = (string)@file_get_contents($root.'/src/Admin.php');
$check('admin sector content studio', str_contains($admin, 'function admin_content_models_page(') && str_contains($admin, "action === 'create_entry'") && str_contains($admin, "'content-models'"));
$db = (string)@file_get_contents($root.'/src/Database.php');
$check('migration v24 permissions', str_contains($db, 'version=24') && str_contains($db, 'platform_role_permissions'));
$modules = glob($root.'/modules/*/module.json') ?: [];
$plugins = glob($root.'/plugins/*/plugin.json') ?: [];
$check('sector/plugin manifests', count($modules)+count($plugins) >= 10, 'modules='.count($modules).'; plugins='.count($plugins));
$fieldManifests = 0;
foreach ($modules as $manifestFile) {
    $manifest = json_decode((string)@file_get_contents($manifestFile), true);
    if (is_array($manifest) && is_array($manifest['fields'] ?? null) && $manifest['fields'] !== []) $fieldManifests++;
}
$check('manifest-declared sector fields', $fieldManifests >= count($modules), 'field_manifests='.$fieldManifests.'/'.count($modules));
$theme = $root.'/themes/nexa-business/theme.json'; $check('SDK theme manifest', is_file($theme));
$themeManager=(string)@file_get_contents($root.'/src/ThemeManager.php'); $check('SDK resolved tokens', str_contains($themeManager, 'function theme_resolve_design_tokens('));
$moduleCli=(string)@file_get_contents($root.'/bin/module.php'); $themeCli=(string)@file_get_contents($root.'/bin/theme.php');
$check('SDK scaffold commands', str_contains($moduleCli,"\$command === 'scaffold'") && str_contains($themeCli,"\$command === 'scaffold'"));
foreach (['tests/smoke.py','tests/platform_core_smoke.php','tests/platform_api_contract.py','tests/license_center_runtime_locale_coverage.py','tests/deployment_archive_contract.py','tests/first_party_application_release_smoke.php','tests/first_party_theme_release_smoke.php','tests/theme_sdk_smoke.php','tests/admin_surface_contract.py','tests/product_center_surface_contract.py','tests/goal_acceptance_contract.py','tests/license_center_install_path_contract.py'] as $file) $check('test '.$file, is_file($root.'/'.$file));
foreach (['outputs/product-tofidesk-app-deployment.zip','outputs/product-tofidesk-marketplace.zip','outputs/product-tofidesk-license-center.zip'] as $file) $check('package '.$file, is_file($root.'/'.$file));
$failed = count(array_filter($checks, static fn(array $item): bool => !$item['ok']));
$report = ['tool'=>'tofidesk-platform-audit','status'=>$failed===0?'pass':'fail','failed'=>$failed,'checks'=>$checks];
if ($json) { echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL; }
else { echo "TofiDesk platform audit: ".strtoupper($report['status'])."\n"; foreach ($checks as $item) echo ($item['ok']?'PASS ':'FAIL ').$item['name'].($item['detail']!==''?' ('.$item['detail'].')':'')."\n"; }
exit($failed === 0 ? 0 : 3);
