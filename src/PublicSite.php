<?php
declare(strict_types=1);

function public_icon(string $name): string {
    $paths = [
        'arrow-up'=>'M5 19 19 5M5 5h14v14',
        'arrow-right'=>'M4 12h16m-6-6 6 6-6 6',
        'arrow-left'=>'M20 12H4m6-6-6 6 6 6',
        'arrow-down'=>'M12 4v16m-6-6 6 6 6-6',
        'check'=>'m5 12 4 4L19 6',
        'diamond'=>'m12 3 9 9-9 9-9-9Z',
        'exchange'=>'M4 8h16m-4-4 4 4-4 4M20 16H4m4-4-4 4 4 4',
        'book'=>'M4 5h6l2 2 2-2h6v14h-6l-2 2-2-2H4ZM12 7v14',
        'globe'=>'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c-5 5-5 13 0 18 5-5 5-13 0-18Z',
        'package'=>'m3 7 9-4 9 4v10l-9 4-9-4Zm0 0 9 4 9-4M12 11v10M7 5l10 4',
        'dot'=>'M14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
    ];
    return '<svg class="icon icon-'.e($name).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="'.($paths[$name] ?? $paths['arrow-up']).'"/></svg>';
}
function public_resolve_redirect(string $lang, string $slug, string $kind): ?array {
    $seen = [$slug=>true];
    for ($depth=0; $depth<12; $depth++) {
        $moved=row('SELECT new_slug FROM redirects WHERE lang=? AND old_slug=?',[$lang,$slug]);
        if (!$moved || !is_string($moved['new_slug']) || $moved['new_slug']==='') return null;
        $slug=$moved['new_slug'];
        if (isset($seen[$slug])) return null;
        $seen[$slug]=true;
        foreach(public_published($lang,$slug) as $candidate) {
            if($candidate['kind']===$kind) return $candidate;
        }
    }
    return null;
}
function public_visible_sql(): string {
    return "t.status = 'published' AND (t.scheduled_at IS NULL OR t.scheduled_at = '' OR t.scheduled_at <= datetime('now'))";
}
function public_published(string $lang, ?string $slug = null): array {
    $sql = 'SELECT t.*, a.kind, a.category, a.created_at FROM translations t JOIN articles a ON a.id=t.article_id WHERE t.lang=? AND ' . public_visible_sql();
    $args = [$lang];
    if ($slug !== null) { $sql .= ' AND t.slug=?'; $args[] = $slug; }
    return rows($sql . ' ORDER BY t.updated_at DESC', $args);
}
function gallery_labels(string $lang): array {
    $labels=['tr'=>['Galeri','Araç, ofis ve çalışma anları','Daha fazla görsel yükle'], 'en'=>['Gallery','Vehicles, office and work moments','Load more images'], 'de'=>['Galerie','Fahrzeuge, Büro und Arbeitsmomente','Mehr Bilder laden'], 'fr'=>['Galerie','Véhicules, bureau et moments de travail','Charger plus d’images'], 'es'=>['Galería','Vehículos, oficina y momentos de trabajo','Cargar más imágenes'], 'it'=>['Galleria','Veicoli, ufficio e momenti di lavoro','Carica altre immagini'], 'pt'=>['Galeria','Veículos, escritório e momentos de trabalho','Carregar mais imagens'], 'nl'=>['Galerij','Voertuigen, kantoor en werkmomenten','Meer afbeeldingen laden'], 'ru'=>['Галерея','Автомобили, офис и рабочие моменты','Загрузить ещё'], 'ar'=>['المعرض','المركبات والمكتب ولحظات العمل','تحميل المزيد'], 'zh'=>['图库','车辆、办公室与工作场景','加载更多图片'], 'ja'=>['ギャラリー','車両・オフィス・作業風景','さらに読み込む'], 'ko'=>['갤러리','차량, 사무실 및 작업 현장','더 보기'], 'fa'=>['گالری','خودروها، دفتر و لحظه‌های کار','بارگذاری تصاویر بیشتر']];
    return $labels[$lang] ?? $labels['tr'];
}
function public_gallery_items(string $lang, int $offset = 0, int $limit = 12): array {
    $limit=max(1,min($limit,24)); $offset=max(0,min($offset,10000));
    return rows("SELECT g.id,g.sort_order,m.id AS media_id,m.width,m.height,COALESCE(mt.alt,tr.alt,'') AS alt,COALESCE(mt.caption,tr.caption,'') AS caption FROM gallery_items g JOIN media m ON m.id=g.media_id LEFT JOIN media_translations mt ON mt.media_id=m.id AND mt.lang=? LEFT JOIN media_translations tr ON tr.media_id=m.id AND tr.lang='tr' WHERE g.status='published' AND m.purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=m.id) ORDER BY g.sort_order DESC,g.id DESC LIMIT ? OFFSET ?",[$lang,$limit,$offset]);
}
function public_gallery_json(string $lang): never {
    $offset=filter_input(INPUT_GET,'offset',FILTER_VALIDATE_INT,['options'=>['default'=>0,'min_range'=>0]]) ?? 0;
    $items=public_gallery_items($lang,$offset,12);
    $payload=['items'=>array_map(static fn($item)=>['id'=>(int)$item['id'],'src'=>'/media/'.(int)$item['media_id'].'?variant=webp','alt'=>(string)$item['alt'],'caption'=>(string)$item['caption'],'width'=>(int)$item['width'],'height'=>(int)$item['height']],$items),'next_offset'=>count($items)===12?$offset+12:null];
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: public, max-age=300');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function public_routes_for_sitemap(): array {
    $routes = [];
    foreach (array_keys(locales()) as $lang) {
        foreach ([''=>'home','hizmetler/yuk-tasimaciligi/'=>'service','blog/'=>'blog','galeri/'=>'gallery','teklif-al/'=>'quote','randevu/'=>'appointment','gizlilik/'=>'privacy'] as $tail=>$group) {
            if ($group==='service' && row('SELECT t.id FROM translations t JOIN articles a ON a.id=t.article_id WHERE t.lang=? AND t.slug=? AND a.kind=? AND '.public_visible_sql(),[$lang,'yuk-tasimaciligi','service'])) continue;
            $routes[] = ['path'=>"/$lang/$tail",'lang'=>$lang,'group'=>$group];
        }
    }
    foreach (rows('SELECT t.lang,t.slug,t.article_id,a.kind FROM translations t JOIN articles a ON a.id=t.article_id WHERE ' . public_visible_sql()) as $item) {
        $prefix = ['service'=>'hizmetler','page'=>'sayfa','article'=>'blog'][$item['kind']] ?? 'blog';
        $routes[] = ['path'=>'/'.$item['lang'].'/'.$prefix.'/'.rawurlencode($item['slug']).'/', 'lang'=>$item['lang'],'group'=>'content-'.$item['article_id']];
    }
    return $routes;
}
function public_content_path(array $item): string {
    return '/'.$item['lang'].'/'.(['service'=>'hizmetler','page'=>'sayfa','article'=>'blog'][$item['kind']] ?? 'blog').'/'.rawurlencode($item['slug']).'/';
}
function public_request(string $lang, string $kind): array {
    $errors = [];
    csrf_verify();
    if (!rate_limit('request-'.($_SERVER['REMOTE_ADDR'] ?? 'local'), 10, 3600)) $errors[] = t('error_rate',$lang);
    if (!empty($_POST['website'])) $errors[] = t('error_required',$lang);
    $fields = [];
    foreach (['name'=>120,'email'=>254,'phone'=>60,'origin'=>240,'destination'=>240,'cargo'=>240,'preferred_date'=>10,'slot'=>5,'notes'=>5000] as $field=>$limit) {
        $value = $_POST[$field] ?? '';
        $fields[$field] = is_string($value) ? trim($value) : '';
        if (mb_strlen($fields[$field]) > $limit) $errors[] = t('error_required',$lang);
    }
    foreach (['name','email','phone','origin','destination','cargo','preferred_date'] as $field) {
        if ($fields[$field] === '') { $errors[] = t('error_required',$lang); break; }
    }
    if (!filter_var($fields['email'],FILTER_VALIDATE_EMAIL)) $errors[] = t('error_email',$lang);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$fields['preferred_date']);
    if (!$date || $date->format('Y-m-d') !== $fields['preferred_date'] || $fields['preferred_date'] < date('Y-m-d')) $errors[] = t('error_date',$lang);
    if (($kind === 'appointment' && $fields['slot'] === '') || ($fields['slot'] !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$fields['slot']))) $errors[] = t('error_slot',$lang);
    if (($_POST['consent'] ?? '') !== '1') $errors[] = t('error_consent',$lang);
    if ($errors) return array_values(array_unique($errors));
    $mediaId = null;
    try {
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $mediaId = media_upload($_FILES['photo'],$lang,'customer');
        }
    } catch (Throwable $error) { return [t('error_upload',$lang)]; }
    $reference = 'KN-'.strtoupper(bin2hex(random_bytes(5)));
    $q = db()->prepare('INSERT INTO requests(reference,type,name,email,phone,origin,destination,cargo,preferred_date,slot,notes,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,\'new\',datetime(\'now\'),datetime(\'now\'))');
    $q->execute([$reference,$kind,$fields['name'],$fields['email'],$fields['phone'],$fields['origin'],$fields['destination'],$fields['cargo'],$fields['preferred_date'],$fields['slot'],$fields['notes']]);
    $requestId = (int)db()->lastInsertId();
    if ($mediaId) { $q=db()->prepare('INSERT INTO request_media(request_id,media_id) VALUES(?,?)'); $q->execute([$requestId,$mediaId]); }
    if (filter_var(setting('contact_email'),FILTER_VALIDATE_EMAIL)) {
        enqueue_mail(setting('contact_email'),'Kaş Nakliyat · '.$reference,'Yeni '.($kind==='quote'?'teklif':'randevu').' talebi. Ayrıntılar yönetim panelindedir. Referans: '.$reference,$requestId);
    }
    audit('request.created',$reference);
    flash('success',t('request_received',$lang).' '.t('request_reference',$lang).': '.$reference.' '.t('request_pending',$lang));
    redirect('/'.$lang.'/'.($kind==='quote'?'teklif-al/':'randevu/'));
}
function handle_public(string $path): void {
    if ($path === '/') redirect('/tr/');
    if (!preg_match('#^/([a-z]{2})(?:/(.*))?$#u',$path,$matches) || !array_key_exists($matches[1],locales())) {
        render_error(404,'Sayfa bulunamadı / Page not found');
    }
    $lang=$matches[1]; $tail=trim($matches[2] ?? '','/'); $page='home'; $article=null; $errors=[];
    $root='/'.$lang.'/';
    if ($tail==='blog') $page='blog';
    elseif ($tail==='galeri') $page='gallery';
    elseif ($tail==='teklif-al') $page='quote';
    elseif ($tail==='randevu') $page='appointment';
    elseif ($tail==='gizlilik') $page='privacy';
    elseif ($tail==='hizmetler/yuk-tasimaciligi') {
        $page='service';
        foreach(public_published($lang,'yuk-tasimaciligi') as $candidate) { if($candidate['kind']==='service') { $article=$candidate; $page='article'; break; } }
    }
    elseif ($tail!=='') {
        if (preg_match('#^(blog|hizmetler|sayfa)/([^/]+)$#u',$tail,$parts)) {
            $slug=$parts[2];
            foreach (public_published($lang,$slug) as $candidate) {
                if (($parts[1]==='hizmetler' && $candidate['kind']==='service') || ($parts[1]==='blog' && $candidate['kind']==='article') || ($parts[1]==='sayfa' && $candidate['kind']==='page')) { $article=$candidate; break; }
            }
            if (!$article) {
                $kind=['blog'=>'article','hizmetler'=>'service','sayfa'=>'page'][$parts[1]];
                $target=public_resolve_redirect($lang,$slug,$kind);
                if ($target) { header('Location: '.public_content_path($target),true,301); return; }
            }
        }
        if (!$article) { $page='404'; http_response_code(404); } else $page='article';
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
        if (!in_array($page,['quote','appointment'],true)) { http_response_code(405); header('Allow: GET'); echo 'Method not allowed'; return; }
        $errors=public_request($lang,$page==='quote'?'quote':'appointment');
        if ($errors) http_response_code(422);
    }
    $galleryLabels=gallery_labels($lang);
    $titleKeys=['home'=>'hero_title','service'=>'service_title','blog'=>'blog_title','quote'=>'quote_title','appointment'=>'appointment_title','privacy'=>'privacy_title','404'=>'not_found'];
    $title=$article ? ($article['seo_title'] ?: $article['title']) : ($page==='gallery' ? $galleryLabels[0] : ($page==='home' ? (setting('homepage_title_'.$lang) ?: t('hero_title',$lang)) : t($titleKeys[$page] ?? 'hero_title',$lang)));
    $descriptionKeys=['home'=>'hero_text','service'=>'service_intro','blog'=>'blog_intro','quote'=>'quote_intro','appointment'=>'appointment_intro','privacy'=>'privacy_text','404'=>'not_found_text'];
    $description=$article ? ($article['seo_description'] ?: $article['excerpt']) : ($page==='gallery' ? $galleryLabels[1] : ($page==='home' ? (setting('homepage_intro_'.$lang) ?: t('hero_text',$lang)) : t($descriptionKeys[$page] ?? 'hero_text',$lang)));
    $heroTitle=$page==='home' ? (setting('homepage_title_'.$lang) ?: t('hero_title',$lang)) : $title;
    if ($page==='home') {
        $title=setting('homepage_seo_title_'.$lang) ?: $title;
        $description=setting('homepage_seo_description_'.$lang) ?: $description;
    }
    $canonical=base_url($article ? public_content_path($article) : $root.($tail===''?'':$tail.'/'));
    $alternates=[];
    if ($article) {
        foreach (rows('SELECT t.*,a.kind FROM translations t JOIN articles a ON a.id=t.article_id WHERE t.article_id=? AND '.public_visible_sql(),[$article['article_id']]) as $alternative) $alternates[$alternative['lang']]=public_content_path($alternative);
    } elseif ($page!=='404') {
        foreach (array_keys(locales()) as $locale) $alternates[$locale]='/'.$locale.'/'.($tail===''?'':$tail.'/');
    }
    $articles=array_values(array_filter(public_published($lang),static fn($item)=>$item['kind']==='article'));
    $categories=array_values(array_unique(array_filter(array_column($articles,'category'))));
    $category=is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
    if ($page==='blog' && $category!=='') $articles=array_values(array_filter($articles,static fn($item)=>$item['category']===$category));
    $services=array_values(array_filter(public_published($lang),static fn($item)=>$item['kind']==='service'));
    $pages=array_values(array_filter(public_published($lang),static fn($item)=>$item['kind']==='page'));
    $galleryItems=$page==='gallery'||$page==='home'?public_gallery_items($lang,0,$page==='home'?6:12):[];
    $coverAlt=$article && !empty($article['cover_media_id']) ? (row('SELECT alt FROM media_translations WHERE media_id=? AND lang=?',[$article['cover_media_id'],$lang])['alt'] ?? '') : '';
    $ogImage=$article && !empty($article['cover_media_id']) ? base_url('/media/'.$article['cover_media_id'].'?variant=webp') : base_url('/assets/share-card.png');
    $flashes=take_flash();
    require __DIR__.'/../templates/public/layout.php';
}
