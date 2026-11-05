<?php
declare(strict_types=1);

$db = tempnam(sys_get_temp_dir(), 'platform-core-');
$storage = sys_get_temp_dir().'/platform-core-'.bin2hex(random_bytes(4));
putenv('DB_PATH='.$db); putenv('STORAGE_PATH='.$storage); putenv('APP_SECRET='.str_repeat('s',48)); putenv('PRODUCT_BRAND='); putenv('PRODUCT_LOCATION=');
require __DIR__.'/../src/bootstrap.php';
migrate();

$manifests = platform_discover_module_manifests();
if (count($manifests) !== 12 || count(array_filter($manifests, static fn(array $item): bool => $item['errors'] === [])) !== 12) throw new RuntimeException('Bundled sector/plugin manifests are invalid.');
if (!platform_requirement_matches(PHP_VERSION, '>=8.2') || platform_requirement_matches(PHP_VERSION, '>=99.0')) throw new RuntimeException('Manifest dependency comparator failed.');
$synced = platform_sync_bundled_modules();
if (count($synced) !== 12 || !in_array('content-discovery', $synced, true) || !in_array('ecommerce-pack', $synced, true) || !in_array('restaurant-pack', $synced, true) || !in_array('agency-pack', $synced, true)) throw new RuntimeException('Bundled sector/plugin modules were not synced.');
foreach ($manifests as $item) {
    $manifest = (array)($item['manifest'] ?? []);
    foreach ((array)($manifest['fields'] ?? []) as $type => $fields) {
        $expected = count((array)$fields);
        $actual = (int)(query('SELECT count(*) FROM content_fields f JOIN content_types t ON t.id=f.content_type_id WHERE t.slug=?', [(string)$type])->fetchColumn() ?: 0);
        if ($actual < $expected) throw new RuntimeException('Manifest fields were not fully synced for '.$type.'.');
    }
}
platform_sync_permission_catalog();
if (!platform_user_can(['role'=>'admin'],'admin.section.settings') || !platform_user_can(['role'=>'editor'],'admin.section.content') || !platform_user_can(['role'=>'editor'],'admin.section.content-models') || platform_user_can(['role'=>'operator'],'admin.section.settings') || !platform_user_can(['role'=>'admin'],'module.product.manage')) throw new RuntimeException('Data-driven permission registry failed.');

$moduleId = platform_register_module(['slug'=>'real-estate-pack','name'=>'Real estate pack','version'=>'1.0.0','kind'=>'sector','hooks'=>['content.saved'],'source'=>'first-party']);
if ($moduleId <= 0 || !platform_module('real-estate-pack')) throw new RuntimeException('Module registry failed.');
query("UPDATE platform_modules SET status='disabled' WHERE id=?", [$moduleId]);
platform_sync_bundled_modules();
if ((string)(row('SELECT status FROM platform_modules WHERE id=?', [$moduleId])['status'] ?? '') !== 'disabled') throw new RuntimeException('Module sync re-enabled an administrator-disabled module.');
if (platform_content_type('listing') !== null) throw new RuntimeException('Disabled module content type remained publicly active.');
if (platform_taxonomy('property_type') !== null) throw new RuntimeException('Disabled module taxonomy remained publicly active.');
query("UPDATE platform_modules SET status='enabled' WHERE id=?", [$moduleId]);
if (!platform_module('real-estate-pack')) throw new RuntimeException('Module could not be re-enabled after sync.');
if (platform_content_type('listing') === null) throw new RuntimeException('Re-enabled module content type was not restored.');
if (platform_taxonomy('property_type') === null) throw new RuntimeException('Re-enabled module taxonomy was not restored.');
function platform_test_hook(array $payload): void { $GLOBALS['platform_test_hook_payload'] = $payload; }
platform_register_hook($moduleId, 'content.saved', 'platform_test_hook', 10);
platform_dispatch('content.saved', ['entry_id'=>42]);
if (($GLOBALS['platform_test_hook_payload']['entry_id'] ?? 0) !== 42) throw new RuntimeException('Safe hook dispatch failed.');
$hookRejected=false; try { platform_register_hook($moduleId, 'order.paid', 'platform_test_hook'); } catch (InvalidArgumentException) { $hookRejected=true; }
if (!$hookRejected) throw new RuntimeException('Undeclared hook was accepted.');
$badManifestRejected=false; try { platform_register_module(['slug'=>'bad-pack','name'=>'Bad pack','version'=>'1.0.0','kind'=>'plugin','hooks'=>['unknown.event'],'permissions'=>['bad permission']]); } catch (InvalidArgumentException) { $badManifestRejected=true; }
if (!$badManifestRejected) throw new RuntimeException('Invalid module manifest was accepted.');
$plugin=(int)(row("SELECT id FROM platform_modules WHERE slug='content-discovery'")['id']??0); $webhookId=platform_register_webhook($plugin,'search.reindex','https://hooks.example.test/tofidesk',str_repeat('w',32));
if($webhookId<=0 || platform_dispatch_webhooks('search.reindex',['entity_id'=>42])!==1 || platform_dispatch_webhooks('search.reindex',['entity_id'=>42])!==0) throw new RuntimeException('Signed webhook queue or idempotency failed.');
if (count(platform_adapter_definitions()) !== 8 || count(platform_adapter_statuses()) !== 8 || !in_array('payment', array_column(platform_adapter_statuses(), 'slug'), true)) throw new RuntimeException('Provider-neutral adapter registry failed.');
$smsStatus = platform_adapter_status('sms');
if (!array_key_exists('missing_env', $smsStatus) || !array_key_exists('configured', $smsStatus)) throw new RuntimeException('Provider-aware notification adapter health contract failed.');
if (!in_array('openstreetmap', platform_map_providers(), true) || !str_starts_with(platform_map_url(['latitude'=>41.0082,'longitude'=>28.9784], 'openstreetmap'), 'https://www.openstreetmap.org/')) throw new RuntimeException('Provider-neutral map URL contract failed.');
platform_cache_set('smoke:cache',['value'=>42],60); if((platform_cache_get('smoke:cache')['value']??0)!==42) throw new RuntimeException('Platform cache failed.');
platform_cache_set('smoke:expired',['value'=>1],60); query('UPDATE platform_cache SET expires_at=? WHERE cache_key=?',[time()-1,'smoke:expired']); $maintenance=platform_maintenance(2); if(platform_cache_get('smoke:expired')!==null || !array_key_exists('expired_cache',$maintenance) || !array_key_exists('recovered_queue',$maintenance)) throw new RuntimeException('Cron maintenance pass failed.');
platform_register_queue_handler('smoke.handler', static function(array $payload): void { $GLOBALS['platform_queue_payload']=$payload; }); $queueId=platform_queue_push('smoke','smoke.handler',['value'=>42],'smoke-idempotent'); if($queueId<=0 || platform_queue_push('smoke','smoke.handler',['value'=>42],'smoke-idempotent')!==$queueId || platform_process_queue(2)['completed']!==1 || ($GLOBALS['platform_queue_payload']['value']??0)!==42) throw new RuntimeException('Platform queue failed.');
$visitor='smoke-visitor'; platform_favorite($visitor,'listing',42,'tr'); if(count(platform_favorites($visitor,'listing'))!==1) throw new RuntimeException('Favorites registry failed.'); platform_compare_add($visitor,'listing',42); platform_compare_add($visitor,'listing',43); if(count(platform_comparisons($visitor,'listing'))!==2) throw new RuntimeException('Comparison registry failed.'); platform_review_submit($visitor,'listing',42,'tr',5,'Great','A useful listing review.'); if((int)platform_review_summary('listing',42)['total']!==0) { throw new RuntimeException('Unmoderated review leaked publicly.'); } platform_popularity_touch('listing',42,'tr','views',3); platform_popularity_touch('listing',42,'tr','downloads',1); if((int)(platform_popular('tr','listing',5)['items'][0]['downloads']??0)!==1) throw new RuntimeException('Popularity registry failed.');
platform_save_module_setting($moduleId, 'property_search', ['facets'=>['property_type','location']], null);
if ((platform_module_settings('real-estate-pack')['property_search']['facets'][0] ?? '') !== 'property_type') throw new RuntimeException('Module settings registry failed.');
$type = platform_content_type('listing');
if (!$type) throw new RuntimeException('Generic listing content type missing.');
$manifestFields = platform_content_fields('listing');
if (!in_array('bedrooms', array_column($manifestFields, 'slug'), true)) throw new RuntimeException('Manifest-declared sector fields were not synced.');
$fieldId=platform_register_content_field('listing',['slug'=>'bedrooms','label'=>'Bedrooms','field_type'=>'number','config'=>['min'=>0]]);
if($fieldId<=0 || !in_array('bedrooms', array_column(platform_content_fields('listing'), 'slug'), true)) throw new RuntimeException('Custom content field registry failed.');
$invalidFieldRejected=false; try { platform_create_entry('listing','invalid-listing',['bedrooms'=>-1]); } catch (InvalidArgumentException) { $invalidFieldRejected=true; }
if (!$invalidFieldRejected) throw new RuntimeException('Content field validation accepted an invalid minimum.');
$unknownFieldRejected=false; try { platform_create_entry('listing','unknown-field',['not_declared'=>true]); } catch (InvalidArgumentException) { $unknownFieldRejected=true; }
if (!$unknownFieldRejected) throw new RuntimeException('Content field validation accepted an unknown field.');
$updateEntry=platform_create_entry('listing','editable-listing',['bedrooms'=>2]); platform_update_entry($updateEntry,'editable-listing-updated',['bedrooms'=>4],null);
if ((string)row('SELECT slug FROM content_entries WHERE id=?',[$updateEntry])['slug'] !== 'editable-listing-updated') throw new RuntimeException('Generic content entry update failed.');
$taxonomyId=platform_upsert_taxonomy('property_type','Property type',true); $parentTerm=platform_upsert_term('property_type','residential','Residential','en'); $childTerm=platform_upsert_term('property_type','apartment','Apartment','en',$parentTerm,['icon'=>'home']);
if($taxonomyId<=0||$parentTerm<=0||$childTerm<=0||count(platform_taxonomy_terms('property_type','en',$parentTerm))!==1) throw new RuntimeException('Taxonomy term registry failed.');
platform_set_entry_terms($updateEntry,[$childTerm]); if(count(platform_entry_terms($updateEntry,'property_type','en'))!==1) throw new RuntimeException('Content taxonomy linking failed.');
$termSearch=platform_search_advanced(['locale'=>'tr','term_ids'=>[$childTerm],'types'=>['listing']]);
if(($termSearch['total']??0)!==0) throw new RuntimeException('Taxonomy search filter leaked an unindexed entry.');
$menuId=platform_upsert_menu('primary','tr','Primary navigation'); platform_add_menu_item($menuId,['label'=>'Home','target_url'=>'/tr/','sort_order'=>1]);
if((int)query('SELECT count(*) FROM platform_menu_items WHERE menu_id=?',[$menuId])->fetchColumn()!==1) throw new RuntimeException('Menu registry failed.');
$formId=platform_upsert_form('contact','tr','Contact form','Thanks'); platform_add_form_field($formId,['slug'=>'email','label'=>'Email','field_type'=>'email','required'=>true]); $submissionId=platform_submit_form($formId,'tr',['email'=>'buyer@example.test'],'127.0.0.1');
if($submissionId<=0 || (int)query('SELECT count(*) FROM platform_form_submissions WHERE form_id=?',[$formId])->fetchColumn()!==1) throw new RuntimeException('Form submission registry failed.');
$entryId = platform_create_entry('listing', 'central-apartment', ['bedrooms'=>3,'bathrooms'=>2], null, null);
foreach (array_keys(core_locales()) as $locale) platform_save_translation($entryId, $locale, ['title'=>'Central apartment','summary'=>'A flexible listing','body'=>'Published listing content','seo'=>['title'=>'Central apartment','canonical'=>'https://example.test/'.$locale.'/central-apartment']], 'published');
$translationCount = (int)query('SELECT COUNT(*) FROM content_entry_translations WHERE entry_id=?', [$entryId])->fetchColumn();
if ($translationCount !== 14) throw new RuntimeException('Expected 14 localized entry records, got '.$translationCount);
query('INSERT INTO search_documents(entity_type,entity_id,locale,title,body,keywords) VALUES(?,?,?,?,?,?)', ['listing',$entryId,'tr','Merkez daire','İlan açıklaması','emlak, daire']);
platform_set_entry_terms($entryId,[$childTerm]); $termSearch=platform_search_advanced(['locale'=>'tr','term_ids'=>[$childTerm],'types'=>['listing']]);
if(($termSearch['total']??0)!==1) throw new RuntimeException('Taxonomy search filter failed.');
if (count(platform_search('tr','daire','listing')) !== 1) throw new RuntimeException('Generic search registry failed.');
$advanced = platform_search_advanced(['locale'=>'tr','term'=>'daire','types'=>['listing'],'sort'=>'title','limit'=>10]);
if ($advanced['total'] !== 1 || count($advanced['items']) !== 1 || ($advanced['facets']['types']['listing'] ?? 0) !== 1 || ($advanced['facets']['terms']['property_type']['apartment'] ?? 0) !== 1) throw new RuntimeException('Advanced search facets failed.');
$demo = theme_import_demo('nexa-business', ['pages'=>[['type'=>'page','slug'=>'demo-home','data'=>['layout'=>'hero'],'translations'=>['tr'=>['title'=>'Demo ana sayfa','body'=>'Taslak demo içerik']]]]], null);
if (($demo['status'] ?? '') !== 'draft' || ($demo['count'] ?? 0) !== 1 || !row("SELECT id FROM content_entries WHERE slug='demo-home' AND status='draft'")) throw new RuntimeException('Theme demo import failed.');
$countries = (int)query("SELECT COUNT(*) FROM platform_locations WHERE level='country'")->fetchColumn();
if ($countries < 190) throw new RuntimeException('Country location baseline is incomplete: '.$countries);
$countryId = (int)(row("SELECT id FROM platform_locations WHERE country_code='TR' AND level='country'")['id'] ?? 0);
$cityId = platform_upsert_location(['parent_id'=>$countryId,'country_code'=>'TR','level'=>'city','name'=>'Istanbul','slug'=>'istanbul']);
if ($cityId <= 0 || count(platform_location_children($countryId, 'city')) !== 1) throw new RuntimeException('Hierarchical location registry failed.');
if (count(platform_location_search('Istanbul','TR',5)) !== 1) throw new RuntimeException('Provider-neutral location search failed.');
$schema = (array)query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('platform_modules','platform_module_settings','content_types','content_fields','content_entries','content_entry_translations','taxonomies','taxonomy_terms','platform_locations','search_documents','platform_webhooks','platform_menus','platform_menu_items','platform_forms','platform_form_fields','platform_form_submissions','platform_webhook_deliveries','platform_adapters','platform_cache','platform_queue','platform_favorites','platform_comparisons','platform_reviews','platform_popularity','platform_roles','platform_permissions','platform_role_permissions')")->fetchAll(PDO::FETCH_COLUMN);
if (count($schema) !== 27) throw new RuntimeException('Platform schema is incomplete.');
echo "PASS sector-neutral module, content, 14-locale, taxonomy/search schema and registry\n";
