<?php
declare(strict_types=1);

function product_ai_seo_fields(array $product, string $locale, array $item): array
{
    $slug = strtolower(trim((string)$item['slug']));
    $base = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/');
    $canonical = $base.'/product?slug='.rawurlencode($slug).'&lang='.rawurlencode($locale);
    $asset = one("SELECT url,storage_path FROM product_assets WHERE product_id=? AND kind IN ('og_image','screenshot','icon') AND status='published' ORDER BY CASE kind WHEN 'og_image' THEN 0 WHEN 'screenshot' THEN 1 ELSE 2 END,id LIMIT 1", [(int)$product['id']]);
    $ogImage = $asset ? ((string)$asset['url'] !== '' ? (string)$asset['url'] : (string)$asset['storage_path']) : '';
    $schemaType = (string)$product['kind'] === 'application' ? 'SoftwareApplication' : 'Product';
    $schema = ['@context'=>'https://schema.org','@type'=>$schemaType,'name'=>trim((string)$item['title']),'description'=>trim((string)($item['summary'] ?: $item['description'])),'url'=>$canonical,'inLanguage'=>$locale];
    $prices = q('SELECT currency,amount_minor FROM product_prices WHERE product_id=? AND active=1 ORDER BY currency LIMIT 1', [(int)$product['id']])->fetch();
    if ($prices) $schema['offers'] = ['@type'=>'Offer','price'=>(string)(((int)$prices['amount_minor'])/100),'priceCurrency'=>(string)$prices['currency'],'availability'=>'https://schema.org/InStock','url'=>$canonical];
    $faq = ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>[['@type'=>'Question','name'=>trim((string)$item['title']),'acceptedAnswer'=>['@type'=>'Answer','text'=>trim((string)($item['summary'] ?: $item['description']))]]]];
    $breadcrumb = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Marketplace','item'=>$base.'/shop?lang='.rawurlencode($locale)],['@type'=>'ListItem','position'=>2,'name'=>trim((string)$item['title']),'item'=>$canonical]]];
    return ['canonical_url'=>$canonical,'og_image'=>$ogImage,'schema_json'=>json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'faq_schema_json'=>json_encode($faq,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'breadcrumb_schema_json'=>json_encode($breadcrumb,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)];
}

function product_ai_admin_route(): void
{
    global $path;
    if ($path !== '/product-ai') return;
    login_required();
    $locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (($_POST['action'] ?? '') === 'approve_revision') {
            $revisionId = (int)($_POST['revision_id'] ?? 0);
            $revision = one('SELECT * FROM product_translation_revisions WHERE id=? AND status=?', [$revisionId, 'draft']);
            if (!$revision) throw new RuntimeException('Draft translation revision not found.');
            $payload = json_decode((string)$revision['payload_json'], true, 64, JSON_THROW_ON_ERROR);
            q("UPDATE product_translation_revisions SET status='approved',approved_at=CURRENT_TIMESTAMP WHERE id=?", [$revisionId]);
            q("UPDATE product_translations SET status='review',updated_at=CURRENT_TIMESTAMP WHERE product_id=? AND locale=?", [$revision['product_id'], $revision['locale']]);
            header('Location: ./product-ai?reviewed=1'); exit;
        }
        $productId = (int)($_POST['product_id'] ?? 0);
        $sourceLocale = strtolower(trim((string)($_POST['source_locale'] ?? 'tr')));
        $targets = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), (array)($_POST['target_locales'] ?? [])), static fn(string $locale): bool => in_array($locale, $locales, true) && $locale !== $sourceLocale)));
        $product = one('SELECT * FROM products WHERE id=?', [$productId]);
        $source = one('SELECT * FROM product_translations WHERE product_id=? AND locale=?', [$productId, $sourceLocale]);
        if (!$product || !$source || !$targets) throw new RuntimeException('Choose a product, source translation and at least one target language.');
        $categories = array_column(q('SELECT pc.name FROM product_category_links pcl JOIN product_categories pc ON pc.id=pcl.category_id WHERE pcl.product_id=? ORDER BY pc.name', [$productId])->fetchAll(), 'name');
        $tags = array_column(q('SELECT pt.name FROM product_tag_links ptl JOIN product_tags pt ON pt.id=ptl.tag_id WHERE ptl.product_id=? ORDER BY pt.name', [$productId])->fetchAll(), 'name');
        $sectorContext = implode(', ', array_unique(array_merge($categories, $tags))) ?: 'general business software and digital products';
        q('INSERT INTO product_translation_jobs(product_id,source_locale,target_locales_json,status,provider,model) VALUES(?,?,?,?,?,?)', [$productId,$sourceLocale,json_encode($targets,JSON_UNESCAPED_UNICODE),'running','openai',lc_env('AI_MODEL','')]);
        $jobId = (int)lc_db()->lastInsertId();
        try {
            $key = trim(lc_env('AI_API_KEY'));
            if ($key === '') throw new RuntimeException('AI_API_KEY is not configured. The job remains recorded for a later retry.');
            $base = rtrim(lc_env('AI_BASE_URL','https://api.openai.com'),'/');
            if (str_ends_with($base,'/v1')) $endpoint = $base.'/chat/completions'; else $endpoint = $base.'/v1/chat/completions';
            $system = 'You are a professional product marketplace localization editor. The product taxonomy context is: '.$sectorContext.'. Return only one JSON object keyed by the requested locale codes. Each value must contain title, summary, description, seo_title, seo_description, keywords and slug. Preserve product facts, do not invent prices or guarantees, do not inject transport, logistics or any other sector claims unless the source or taxonomy explicitly supports them, use natural local SEO, and keep slugs lowercase with hyphens.';
            $payload = json_encode(['model'=>lc_env('AI_MODEL','gpt-4o-mini'),'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>json_encode(['product'=>$product['name'],'kind'=>$product['kind'],'sector_context'=>$sectorContext,'categories'=>$categories,'tags'=>$tags,'source_locale'=>$sourceLocale,'targets'=>$targets,'source'=>['title'=>$source['title'],'summary'=>$source['summary'],'description'=>$source['description'],'seo_title'=>$source['seo_title'],'seo_description'=>$source['seo_description'],'keywords'=>$source['keywords'],'slug'=>$source['slug']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]],'response_format'=>['type'=>'json_object'],'temperature'=>0.2],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $ch = curl_init($endpoint); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json']]); $raw=curl_exec($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            if ($raw===false || $error!=='' || $status<200 || $status>=300) throw new RuntimeException('AI translation request failed.');
            $envelope=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR); $text=$envelope['choices'][0]['message']['content']??null; if(!is_string($text)) throw new RuntimeException('AI returned no translation result.');
            $result=json_decode($text,true,64,JSON_THROW_ON_ERROR); if(!is_array($result)) throw new RuntimeException('AI translation result is invalid.');
            foreach($targets as $locale){$item=$result[$locale]??null;if(!is_array($item))throw new RuntimeException('AI result is missing locale '.$locale.'.');foreach(['title'=>220,'summary'=>1000,'description'=>30000,'seo_title'=>100,'seo_description'=>220,'keywords'=>1000,'slug'=>120] as $field=>$limit){if(!isset($item[$field])||!is_string($item[$field])||trim($item[$field])===''||mb_strlen($item[$field])>$limit)throw new RuntimeException('AI result is invalid for '.$locale.'.');}$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)$item['slug']),'-'));if($slug==='')throw new RuntimeException('AI returned an invalid slug for '.$locale.'.');$item['slug']=$slug;$item=array_merge($item,product_ai_seo_fields($product,$locale,$item));$encoded=json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);q('INSERT INTO product_translation_revisions(job_id,product_id,locale,payload_json,status) VALUES(?,?,?,?,?)',[$jobId,$productId,$locale,$encoded,'draft']);q('INSERT INTO product_translations(product_id,locale,title,summary,description,slug,seo_title,seo_description,keywords,canonical_url,og_image,schema_json,faq_schema_json,breadcrumb_schema_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(product_id,locale) DO UPDATE SET title=excluded.title,summary=excluded.summary,description=excluded.description,slug=excluded.slug,seo_title=excluded.seo_title,seo_description=excluded.seo_description,keywords=excluded.keywords,canonical_url=excluded.canonical_url,og_image=excluded.og_image,schema_json=excluded.schema_json,faq_schema_json=excluded.faq_schema_json,breadcrumb_schema_json=excluded.breadcrumb_schema_json,status=\'draft\',updated_at=CURRENT_TIMESTAMP',[$productId,$locale,trim($item['title']),trim($item['summary']),trim($item['description']),$slug,trim($item['seo_title']),trim($item['seo_description']),trim($item['keywords']),$item['canonical_url'],$item['og_image'],$item['schema_json'],$item['faq_schema_json'],$item['breadcrumb_schema_json'],'draft']);}
            q("UPDATE product_translation_jobs SET status='completed',completed_at=CURRENT_TIMESTAMP WHERE id=?",[$jobId]);
        } catch (Throwable $e) { q("UPDATE product_translation_jobs SET status='failed',error=?,completed_at=CURRENT_TIMESTAMP WHERE id=?",[mb_substr($e->getMessage(),0,1000),$jobId]); throw $e; }
        header('Location: ./product-ai?job='.$jobId); exit;
    }
    $products=q("SELECT id,name,kind FROM products WHERE status<>'archived' ORDER BY name")->fetchAll(); $options='';foreach($products as $product)$options.='<option value="'.(int)$product['id'].'">'.esc($product['name']).' · '.esc($product['kind']).'</option>';
    $checks=q('SELECT j.*,p.name FROM product_translation_jobs j JOIN products p ON p.id=j.product_id ORDER BY j.id DESC LIMIT 50')->fetchAll();$rows='';foreach($checks as $job)$rows.='<tr><td>'.esc($job['name']).'</td><td>'.esc($job['source_locale']).' → '.esc(implode(', ',json_decode($job['target_locales_json'],true)?:[])).'</td><td>'.esc($job['status']).'</td><td>'.esc($job['error']).'</td><td>'.esc($job['created_at']).'</td></tr>';
    $revisions=q("SELECT r.*,p.name FROM product_translation_revisions r JOIN products p ON p.id=r.product_id WHERE r.status='draft' ORDER BY r.id DESC LIMIT 100")->fetchAll();$revisionRows='';foreach($revisions as $revision)$revisionRows.='<tr><td>'.esc($revision['name']).'</td><td>'.esc(strtoupper($revision['locale'])).'</td><td>'.esc($revision['created_at']).'</td><td><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="approve_revision"/><input type="hidden" name="revision_id" value="'.(int)$revision['id'].'"/><button>Send to editorial review</button></form></td></tr>';
    layout('AI product translations','<div class="grid"><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><h1>AI product translations</h1><p class="muted">Source content is translated into draft revisions. Nothing is published without editorial and product QA approval.</p><label>Product<select name="product_id" required>'.$options.'</select></label><label>Source language<select name="source_locale">'.implode('',array_map(static fn(string $locale):string=>'<option value="'.$locale.'">'.strtoupper($locale).'</option>',$locales)).'</select></label><fieldset><legend>Target languages</legend>'.implode('',array_map(static fn(string $locale):string=>'<label><input type="checkbox" name="target_locales[]" value="'.$locale.'" '.($locale!=='tr'?'checked':'').'> '.strtoupper($locale).'</label>',$locales)).'</fieldset><button>Generate draft translations</button></form><section class="card"><h2>Translation jobs</h2><table><tr><th>Product</th><th>Locales</th><th>Status</th><th>Error</th><th>Created</th></tr>'.$rows.'</table></section></div><section class="card"><h2>Editorial review queue</h2><p class="muted">Approval moves a draft to review; publication remains gated by Product quality.</p><table><tr><th>Product</th><th>Locale</th><th>Created</th><th>Action</th></tr>'.$revisionRows.'</table></section>');
}

product_ai_admin_route();
