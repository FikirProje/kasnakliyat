<?php
declare(strict_types=1);

function platform_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: '.(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET' ? 'public, max-age=60' : 'no-store'));
    header('X-TofiDesk-API-Version: 1');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function platform_api_require_write_token(): void
{
    $expected=trim(envv('PLATFORM_API_TOKEN','')); if(strlen($expected)<32) platform_api_json(['error'=>'write_api_not_configured'],503);
    $authorization=(string)($_SERVER['HTTP_AUTHORIZATION']??''); $provided=(string)($_SERVER['HTTP_X_TOFIDESK_API_KEY']??'');
    if($provided==='' && preg_match('/^Bearer\s+(.+)$/i',$authorization,$match)) $provided=trim($match[1]);
    if($provided==='' || !hash_equals($expected,$provided)) platform_api_json(['error'=>'invalid_api_token'],401);
}

function platform_api_json_body(): array
{
    $length=(int)($_SERVER['CONTENT_LENGTH']??0); if($length>1000000) platform_api_json(['error'=>'payload_too_large'],413);
    $raw=file_get_contents('php://input'); $payload=json_decode(is_string($raw)?$raw:'',true);
    if(!is_array($payload)) platform_api_json(['error'=>'invalid_json'],400); return $payload;
}

function platform_api_module_public(array $module): array
{
    $manifest = json_decode((string)($module['manifest_json'] ?? '{}'), true);
    if (!is_array($manifest)) $manifest = [];
    return [
        'slug'=>(string)$module['slug'],
        'name'=>(string)$module['name'],
        'version'=>(string)$module['version'],
        'kind'=>(string)$module['kind'],
        'requires'=>(array)($manifest['requires'] ?? []),
        'content_types'=>array_values((array)($manifest['content_types'] ?? [])),
        'taxonomies'=>array_values((array)($manifest['taxonomies'] ?? [])),
        'hooks'=>array_values((array)($manifest['hooks'] ?? [])),
        'permissions'=>array_values((array)($manifest['permissions'] ?? [])),
        'translations'=>array_values((array)($manifest['translations'] ?? [])),
        'settings'=>is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [],
    ];
}

function platform_api_theme_public(array $item, bool $resolved = false): array
{
    $manifest = is_array($item['manifest'] ?? null) ? $item['manifest'] : [];
    $slug = (string)($manifest['slug'] ?? '');
    $payload = [
        'slug'=>$slug,
        'name'=>(string)($manifest['name'] ?? ''),
        'version'=>(string)($manifest['version'] ?? ''),
        'kind'=>(string)($manifest['kind'] ?? 'theme'),
        'parent'=>$manifest['parent'] ?? null,
        'license'=>(string)($manifest['license'] ?? ''),
        'requirements'=>is_array($manifest['requirements'] ?? null) ? $manifest['requirements'] : [],
        'translations'=>array_values((array)($manifest['translations'] ?? [])),
        'demo'=>is_array($manifest['demo'] ?? null) ? $manifest['demo'] : [],
        'errors'=>array_values((array)($item['errors'] ?? [])),
    ];
    if ($resolved && $payload['errors'] === []) {
        $payload['tokens']=theme_resolve_design_tokens($slug);
        $payload['sections']=array_values(theme_resolve_sections($slug));
    } else {
        $payload['tokens']=is_array($manifest['tokens'] ?? null) ? $manifest['tokens'] : [];
        $payload['sections']=array_values((array)($manifest['sections'] ?? []));
    }
    return $payload;
}

function platform_search_labels(string $key, string $locale): string
{
    $labels = [
        'tr'=>['title'=>'Ara ve keşfet','intro'=>'İçerikleri, ürünleri ve sektör modüllerini konum ve kategoriye göre keşfedin.','query'=>'Ne arıyorsunuz?','type'=>'İçerik türü','all'=>'Tümü','location'=>'Konum','location_hint'=>'Ülke, il, ilçe veya mahalle ara','location_choose'=>'Listeden bir konum seçin','radius'=>'Yarıçap (km)','sort'=>'Sıralama','updated'=>'Yeni güncellenen','title_sort'=>'Başlığa göre','submit'=>'Ara','results'=>'Sonuç','empty'=>'Bu filtrelerle eşleşen sonuç bulunamadı.'],
        'en'=>['title'=>'Search and discover','intro'=>'Discover content, products and sector modules by type, category and location.','query'=>'What are you looking for?','type'=>'Content type','all'=>'All','location'=>'Location','location_hint'=>'Search country, region, city or neighborhood','location_choose'=>'Choose a location from the suggestions','radius'=>'Radius (km)','sort'=>'Sort','updated'=>'Recently updated','title_sort'=>'By title','submit'=>'Search','results'=>'Results','empty'=>'No results match these filters.'],
        'de'=>['title'=>'Suchen und entdecken','intro'=>'Entdecken Sie Inhalte, Produkte und Branchenmodule nach Typ, Kategorie und Standort.','query'=>'Wonach suchen Sie?','type'=>'Inhaltstyp','all'=>'Alle','location'=>'Standort','location_hint'=>'Land, Region, Stadt oder Stadtteil suchen','location_choose'=>'Standort aus den Vorschlägen wählen','radius'=>'Radius (km)','sort'=>'Sortierung','updated'=>'Zuletzt aktualisiert','title_sort'=>'Nach Titel','submit'=>'Suchen','results'=>'Ergebnisse','empty'=>'Keine Ergebnisse für diese Filter.'],
        'fr'=>['title'=>'Rechercher et découvrir','intro'=>'Découvrez du contenu, des produits et des modules par type, catégorie et lieu.','query'=>'Que recherchez-vous ?','type'=>'Type de contenu','all'=>'Tous','location'=>'Lieu','location_hint'=>'Rechercher un pays, une région ou une ville','location_choose'=>'Choisissez un lieu dans les suggestions','radius'=>'Rayon (km)','sort'=>'Tri','updated'=>'Récemment mis à jour','title_sort'=>'Par titre','submit'=>'Rechercher','results'=>'Résultats','empty'=>'Aucun résultat pour ces filtres.'],
        'es'=>['title'=>'Buscar y descubrir','intro'=>'Descubre contenidos, productos y módulos por tipo, categoría y ubicación.','query'=>'¿Qué buscas?','type'=>'Tipo de contenido','all'=>'Todos','location'=>'Ubicación','location_hint'=>'Busca país, región, ciudad o barrio','location_choose'=>'Elige una ubicación de las sugerencias','radius'=>'Radio (km)','sort'=>'Ordenar','updated'=>'Actualizados recientemente','title_sort'=>'Por título','submit'=>'Buscar','results'=>'Resultados','empty'=>'No hay resultados para estos filtros.'],
        'it'=>['title'=>'Cerca e scopri','intro'=>'Scopri contenuti, prodotti e moduli per tipo, categoria e posizione.','query'=>'Cosa stai cercando?','type'=>'Tipo di contenuto','all'=>'Tutti','location'=>'ID posizione','radius'=>'Raggio (km)','sort'=>'Ordina','updated'=>'Aggiornati di recente','title_sort'=>'Per titolo','submit'=>'Cerca','results'=>'Risultati','empty'=>'Nessun risultato per questi filtri.'],
        'pt'=>['title'=>'Pesquisar e descobrir','intro'=>'Descubra conteúdos, produtos e módulos por tipo, categoria e localização.','query'=>'O que você procura?','type'=>'Tipo de conteúdo','all'=>'Todos','location'=>'ID da localização','radius'=>'Raio (km)','sort'=>'Ordenar','updated'=>'Atualizados recentemente','title_sort'=>'Por título','submit'=>'Pesquisar','results'=>'Resultados','empty'=>'Nenhum resultado para estes filtros.'],
        'nl'=>['title'=>'Zoeken en ontdekken','intro'=>'Ontdek inhoud, producten en modules op type, categorie en locatie.','query'=>'Waarnaar zoekt u?','type'=>'Inhoudstype','all'=>'Alle','location'=>'Locatie-ID','radius'=>'Straal (km)','sort'=>'Sorteren','updated'=>'Recent bijgewerkt','title_sort'=>'Op titel','submit'=>'Zoeken','results'=>'Resultaten','empty'=>'Geen resultaten voor deze filters.'],
        'ru'=>['title'=>'Поиск и открытие','intro'=>'Находите контент, продукты и отраслевые модули по типу, категории и месту.','query'=>'Что вы ищете?','type'=>'Тип контента','all'=>'Все','location'=>'ID места','radius'=>'Радиус (км)','sort'=>'Сортировка','updated'=>'Недавно обновлённые','title_sort'=>'По названию','submit'=>'Искать','results'=>'Результаты','empty'=>'По этим фильтрам ничего не найдено.'],
        'ar'=>['title'=>'البحث والاكتشاف','intro'=>'اكتشف المحتوى والمنتجات والوحدات حسب النوع والفئة والموقع.','query'=>'ما الذي تبحث عنه؟','type'=>'نوع المحتوى','all'=>'الكل','location'=>'معرّف الموقع','radius'=>'النطاق (كم)','sort'=>'الترتيب','updated'=>'الأحدث تحديثاً','title_sort'=>'حسب العنوان','submit'=>'بحث','results'=>'النتائج','empty'=>'لا توجد نتائج لهذه الفلاتر.'],
        'fa'=>['title'=>'جستجو و کشف','intro'=>'محتوا، محصولات و ماژول‌ها را بر اساس نوع، دسته و مکان کشف کنید.','query'=>'دنبال چه هستید؟','type'=>'نوع محتوا','all'=>'همه','location'=>'شناسه مکان','radius'=>'شعاع (کیلومتر)','sort'=>'مرتب‌سازی','updated'=>'به‌تازگی به‌روزشده','title_sort'=>'بر اساس عنوان','submit'=>'جستجو','results'=>'نتایج','empty'=>'نتیجه‌ای با این فیلترها پیدا نشد.'],
        'zh'=>['title'=>'搜索与发现','intro'=>'按类型、类别和位置发现内容、产品和行业模块。','query'=>'您在寻找什么？','type'=>'内容类型','all'=>'全部','location'=>'位置 ID','radius'=>'半径（公里）','sort'=>'排序','updated'=>'最近更新','title_sort'=>'按标题','submit'=>'搜索','results'=>'结果','empty'=>'没有符合这些筛选条件的结果。'],
        'ja'=>['title'=>'検索と発見','intro'=>'種類、カテゴリ、場所からコンテンツ、製品、業界モジュールを探せます。','query'=>'何をお探しですか？','type'=>'コンテンツタイプ','all'=>'すべて','location'=>'場所 ID','radius'=>'半径（km）','sort'=>'並べ替え','updated'=>'最近更新','title_sort'=>'タイトル順','submit'=>'検索','results'=>'結果','empty'=>'この条件に一致する結果はありません。'],
        'ko'=>['title'=>'검색 및 탐색','intro'=>'유형, 카테고리, 위치별로 콘텐츠와 제품 및 업종 모듈을 탐색하세요.','query'=>'무엇을 찾고 있나요?','type'=>'콘텐츠 유형','all'=>'전체','location'=>'위치 ID','radius'=>'반경(km)','sort'=>'정렬','updated'=>'최근 업데이트','title_sort'=>'제목순','submit'=>'검색','results'=>'결과','empty'=>'필터와 일치하는 결과가 없습니다.'],
    ];
    return $labels[$locale][$key] ?? $labels['en'][$key] ?? $key;
}

function platform_api_handle(string $path): bool
{
    if (!preg_match('#^/api/platform/v1(?:/(.*))?$#', $path, $match)) return false;
    $resource = trim((string)($match[1] ?? ''), '/');
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if ($method !== 'GET') platform_api_require_write_token();
    if ($method==='POST' && $resource==='forms/submit') {
        $payload=platform_api_json_body(); $slug=is_string($payload['slug']??null)?trim((string)$payload['slug']):''; $lang=is_string($payload['lang']??null)?(string)$payload['lang']:'tr'; $values=is_array($payload['values']??null)?$payload['values']:null;
        if(!preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/',$slug)||!isset(core_locales()[$lang])||$values===null) platform_api_json(['error'=>'invalid_form_submission'],400);
        $form=row("SELECT id FROM platform_forms WHERE slug=? AND locale=? AND status='enabled'",[$slug,$lang]); if(!$form) platform_api_json(['error'=>'form_not_found'],404);
        $submissionId=platform_submit_form((int)$form['id'],$lang,$values,(string)($_SERVER['REMOTE_ADDR']??'')); platform_api_json(['api_version'=>'1','submission_id'=>$submissionId,'status'=>'received'],201);
    }
    if ($method !== 'GET') platform_api_json(['error'=>'method_not_allowed','message'=>'This write operation is not available in the public v1 API.'], 405);
    if ($resource === 'modules') {
        $items = [];
        foreach (rows("SELECT * FROM platform_modules WHERE status='enabled' ORDER BY kind,slug") as $module) $items[] = platform_api_module_public($module);
        platform_api_json(['api_version'=>'1','items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'themes') {
        $items=[];
        foreach (theme_discover_manifests() as $theme) {
            if (($theme['manifest'] ?? null) === null || ($theme['errors'] ?? []) !== []) continue;
            $items[]=platform_api_theme_public($theme, false);
        }
        platform_api_json(['api_version'=>'1','items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'themes/detail') {
        $slug=is_string($_GET['slug']??null)?trim((string)$_GET['slug']):'';
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug)) platform_api_json(['error'=>'invalid_theme_slug'],400);
        $found=null;
        foreach (theme_discover_manifests() as $theme) if (($theme['manifest']['slug'] ?? '') === $slug) { $found=$theme; break; }
        if ($found === null || ($found['errors'] ?? []) !== []) platform_api_json(['error'=>'theme_not_found'],404);
        platform_api_json(['api_version'=>'1','theme'=>platform_api_theme_public($found, true)]);
    }
    if ($resource === 'locations') {
        $parent = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
        $level = is_string($_GET['level'] ?? null) ? (string)$_GET['level'] : null;
        $country = is_string($_GET['country'] ?? null) ? strtoupper((string)$_GET['country']) : '';
        if ($parent !== null && $parent > 0) $items = platform_location_children($parent, $level !== '' ? $level : null);
        else {
            $sql='SELECT * FROM platform_locations WHERE parent_id IS NULL'; $args=[];
            if ($country !== '') { if (!preg_match('/^[A-Z]{2}$/', $country)) platform_api_json(['error'=>'invalid_country'],400); $sql.=' AND country_code=?'; $args[]=$country; }
            if ($level !== null && $level !== '') { $sql.=' AND level=?'; $args[]=$level; }
            $sql.=' ORDER BY name LIMIT 500'; $items=rows($sql,$args);
        }
        $items=array_map(static fn(array $item): array => ['id'=>(int)$item['id'],'parent_id'=>$item['parent_id']===null?null:(int)$item['parent_id'],'country_code'=>$item['country_code'],'level'=>$item['level'],'name'=>$item['name'],'slug'=>$item['slug'],'postal_code'=>$item['postal_code'],'latitude'=>$item['latitude'],'longitude'=>$item['longitude']], $items);
        platform_api_json(['api_version'=>'1','items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'locations/search') {
        $q=is_string($_GET['q']??null)?trim((string)$_GET['q']):''; $country=is_string($_GET['country']??null)?strtoupper((string)$_GET['country']):'';
        if($q===''||mb_strlen($q)>120) platform_api_json(['error'=>'invalid_location_query'],400);
        $items=array_map(static fn(array $item): array => ['id'=>(int)$item['id'],'parent_id'=>$item['parent_id']===null?null:(int)$item['parent_id'],'country_code'=>$item['country_code'],'level'=>$item['level'],'name'=>$item['name'],'slug'=>$item['slug'],'postal_code'=>$item['postal_code'],'latitude'=>$item['latitude'],'longitude'=>$item['longitude']], platform_location_search($q,$country,(int)($_GET['limit']??20)));
        platform_api_json(['api_version'=>'1','items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'taxonomies') {
        $items=[];
        foreach(rows("SELECT tx.*, (SELECT count(*) FROM taxonomy_terms tt WHERE tt.taxonomy_id=tx.id) AS term_count FROM taxonomies tx WHERE tx.status='enabled' ORDER BY tx.name") as $taxonomy) $items[]=['id'=>(int)$taxonomy['id'],'slug'=>$taxonomy['slug'],'name'=>$taxonomy['name'],'hierarchical'=>(bool)$taxonomy['hierarchical'],'term_count'=>(int)$taxonomy['term_count']];
        platform_api_json(['api_version'=>'1','items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'taxonomies/terms') {
        $taxonomy=is_string($_GET['taxonomy']??null)?trim((string)$_GET['taxonomy']):''; $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr'; $parent=isset($_GET['parent_id'])&&$_GET['parent_id']!==''?(int)$_GET['parent_id']:null;
        if($taxonomy===''||!isset(core_locales()[$lang])||($parent!==null&&$parent<1)) platform_api_json(['error'=>'invalid_taxonomy_request'],400);
        if(!platform_taxonomy($taxonomy)) platform_api_json(['error'=>'taxonomy_not_found'],404);
        $items=array_map(static fn(array $term): array => ['id'=>(int)$term['id'],'parent_id'=>$term['parent_id']===null?null:(int)$term['parent_id'],'slug'=>$term['slug'],'name'=>$term['name'],'locale'=>$term['locale'],'metadata'=>json_decode((string)$term['metadata_json'],true)?:[]],platform_taxonomy_terms($taxonomy,$lang,$parent));
        platform_api_json(['api_version'=>'1','taxonomy'=>$taxonomy,'locale'=>$lang,'items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'entries') {
        $type=is_string($_GET['type']??null)?trim((string)$_GET['type']):''; $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr'; $slug=is_string($_GET['slug']??null)?trim((string)$_GET['slug']):''; $limit=max(1,min((int)($_GET['limit']??30),100));
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,100}$/',$type)||!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_entry_request'],400);
        if(!platform_content_type($type)) platform_api_json(['error'=>'type_not_found'],404);
        $sql="SELECT ce.id,ce.slug,ce.location_id,ce.data_json,ce.updated_at,cet.locale,cet.title,cet.summary,cet.body,cet.seo_json FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id AND ct.slug=? JOIN content_entry_translations cet ON cet.entry_id=ce.id AND cet.locale=? AND cet.status='published'"; $args=[$type,$lang];
        if($slug!==''){if(!preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/',$slug)) platform_api_json(['error'=>'invalid_entry_slug'],400);$sql.=' WHERE ce.slug=?';$args[]=$slug;} else {$sql.=' ORDER BY ce.updated_at DESC,ce.id DESC LIMIT ?';$args[]=$limit;}
        $rows=rows($sql,$args); $items=[]; foreach($rows as $entry){$data=json_decode((string)$entry['data_json'],true);$seo=json_decode((string)$entry['seo_json'],true);$items[]=['id'=>(int)$entry['id'],'type'=>$type,'slug'=>$entry['slug'],'locale'=>$entry['locale'],'title'=>$entry['title'],'summary'=>$entry['summary'],'body'=>$entry['body'],'data'=>is_array($data)?$data:[],'seo'=>is_array($seo)?$seo:[],'location_id'=>$entry['location_id']===null?null:(int)$entry['location_id'],'terms'=>array_map(static fn(array $term):array=>['id'=>(int)$term['id'],'taxonomy'=>$term['taxonomy_slug'],'slug'=>$term['slug'],'name'=>$term['name'],'locale'=>$term['locale']],platform_entry_terms((int)$entry['id'],null,$lang)),'updated_at'=>$entry['updated_at']];}
        if($slug!=='') platform_api_json(['api_version'=>'1','item'=>$items[0]??null,'total'=>count($items)]);
        platform_api_json(['api_version'=>'1','type'=>$type,'locale'=>$lang,'items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'schema') {
        $type=is_string($_GET['type']??null)?trim((string)$_GET['type']):''; if(!preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/',$type)) platform_api_json(['error'=>'invalid_type'],400);
        $contentType=platform_content_type($type); if(!$contentType) platform_api_json(['error'=>'type_not_found'],404);
        $fields=array_map(static function(array $field): array { return ['slug'=>$field['slug'],'label'=>$field['label'],'field_type'=>$field['field_type'],'config'=>json_decode((string)$field['config_json'],true)?:[],'sort_order'=>(int)$field['sort_order']]; }, platform_content_fields($type));
        platform_api_json(['api_version'=>'1','type'=>$type,'fields'=>$fields,'total'=>count($fields)]);
    }
    if ($resource === 'menus') {
        $slug=is_string($_GET['slug']??null)?trim((string)$_GET['slug']):''; $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr'; if(!preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/',$slug)||!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_menu_request'],400);
        $menu=row("SELECT * FROM platform_menus WHERE slug=? AND locale=? AND status='enabled'",[$slug,$lang]); if(!$menu) platform_api_json(['api_version'=>'1','items'=>[],'total'=>0]);
        $items=array_map(static fn(array $item): array => ['id'=>(int)$item['id'],'parent_id'=>$item['parent_id']===null?null:(int)$item['parent_id'],'label'=>$item['label'],'target_url'=>$item['target_url'],'target'=>$item['target'],'sort_order'=>(int)$item['sort_order'],'visible'=>(bool)$item['visible']], rows('SELECT * FROM platform_menu_items WHERE menu_id=? AND visible=1 ORDER BY sort_order,id',[(int)$menu['id']]));
        platform_api_json(['api_version'=>'1','slug'=>$slug,'locale'=>$lang,'items'=>$items,'total'=>count($items)]);
    }
    if ($resource === 'forms') {
        $slug=is_string($_GET['slug']??null)?trim((string)$_GET['slug']):''; $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr'; if(!preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/',$slug)||!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_form_request'],400);
        $form=row("SELECT * FROM platform_forms WHERE slug=? AND locale=? AND status='enabled'",[$slug,$lang]); if(!$form) platform_api_json(['error'=>'form_not_found'],404);
        $fields=array_map(static fn(array $field): array => ['slug'=>$field['slug'],'label'=>$field['label'],'field_type'=>$field['field_type'],'required'=>(bool)$field['required'],'config'=>json_decode((string)$field['config_json'],true)?:[],'sort_order'=>(int)$field['sort_order']], rows('SELECT * FROM platform_form_fields WHERE form_id=? ORDER BY sort_order,id',[(int)$form['id']]));
        platform_api_json(['api_version'=>'1','slug'=>$slug,'locale'=>$lang,'name'=>$form['name'],'success_message'=>$form['success_message'],'fields'=>$fields,'total'=>count($fields)]);
    }
    if (preg_match('#^(?:discovery/)?related$#', $resource)) {
        $type=is_string($_GET['type']??null)?(string)$_GET['type']:''; $id=(int)($_GET['id']??0); $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr';
        if($type===''||$id<=0||!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_related_request'],400);
        platform_api_json(['api_version'=>'1']+platform_related($lang,$type,$id,(int)($_GET['limit']??6)));
    }
    if (preg_match('#^(?:discovery/)?reviews$#', $resource)) {
        $type=is_string($_GET['type']??null)?(string)$_GET['type']:''; $id=(int)($_GET['id']??0);
        if($type===''||$id<=0) platform_api_json(['error'=>'invalid_review_request'],400);
        platform_api_json(['api_version'=>'1']+platform_review_summary($type,$id));
    }
    if (preg_match('#^(?:discovery/)?popular$#', $resource)) {
        $lang=is_string($_GET['lang']??null)?(string)$_GET['lang']:'tr'; $type=is_string($_GET['type']??null)?trim((string)$_GET['type']):'';
        if(!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_locale'],400);
        platform_api_json(['api_version'=>'1']+platform_popular($lang,$type,(int)($_GET['limit']??12)));
    }
    if ($resource === 'search') {
        $lang = is_string($_GET['lang'] ?? null) ? (string)$_GET['lang'] : request_lang();
        $types = is_string($_GET['types'] ?? null) ? array_values(array_filter(explode(',', (string)$_GET['types']), static fn(string $v): bool => preg_match('/^[a-z0-9_-]{1,80}$/', $v))) : [];
        $termIds = is_string($_GET['term_ids'] ?? null) ? array_values(array_filter(array_map('intval', explode(',', (string)$_GET['term_ids'])), static fn(int $v): bool => $v > 0)) : [];
        if (!isset(core_locales()[$lang])) platform_api_json(['error'=>'invalid_locale'],400);
        $result=platform_search_advanced(['locale'=>$lang,'term'=>is_string($_GET['q']??null)?(string)$_GET['q']:'','types'=>$types,'term_ids'=>$termIds,'location_id'=>isset($_GET['location_id'])?(int)$_GET['location_id']:null,'include_descendants'=>($_GET['include_descendants']??'')==='1','latitude'=>is_numeric($_GET['lat']??null)?(float)$_GET['lat']:null,'longitude'=>is_numeric($_GET['lon']??null)?(float)$_GET['lon']:null,'radius_km'=>is_numeric($_GET['radius_km']??null)?min(5000,max(0,(float)$_GET['radius_km'])):0,'sort'=>is_string($_GET['sort']??null)?(string)$_GET['sort']:'updated','limit'=>isset($_GET['limit'])?(int)$_GET['limit']:30]);
        platform_api_json(['api_version'=>'1']+$result);
    }
    platform_api_json(['error'=>'not_found','message'=>'API resource not found.'],404);
}
