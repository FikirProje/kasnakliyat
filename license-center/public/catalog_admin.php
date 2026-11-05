<?php
declare(strict_types=1);

ob_start(static function (string $html): string {
    $html = str_replace('<a href="./admin">Dashboard</a><a href="./themes">Themes</a>', '<a href="./admin">Dashboard</a><a href="./products">Products</a><a href="./product-bundles">Bundles</a><a href="./themes">Themes</a>', $html);
    $html = str_replace('<h2>14-language content and SEO</h2>', '<label>Currency<input name="currency" value="EUR" maxlength="3"></label><label>Price in minor units<input type="number" name="price_minor" value="0" min="0"></label><label>Demo URL<input type="url" name="demo_url"></label><label>Documentation URL<input type="url" name="docs_url"></label><label>Screenshot URL<input type="url" name="screenshot_url"></label><label>Preview video URL<input type="url" name="video_url" placeholder="https://.../preview.mp4"></label><h2>14-language content and SEO</h2>', $html);
    $productId = (int)($_GET['id'] ?? 0);
    $html = preg_replace_callback('/(<label>Title.*?<\/label>)/s', static function (array $match) use ($productId): string {
        if (!preg_match('/name="title_([a-z]{2})"/', $match[1], $localeMatch)) return $match[1];
        $locale = $localeMatch[1];
        $translation = $productId > 0 ? one('SELECT slug FROM product_translations WHERE product_id=? AND locale=?', [$productId, $locale]) : null;
        $slug = esc((string)($translation['slug'] ?? ''));
        return $match[1].'<label>Localized slug<input name="slug_'.$locale.'" value="'.$slug.'" pattern="[a-z0-9-]+" maxlength="120"></label>';
    }, $html) ?? $html;
    return preg_replace_callback('/(<label>JSON-LD schema.*?<\/textarea><\/label>)/s', static function (array $match) use ($productId): string {
        if (!preg_match('/name="schema_json_([a-z]{2})"/', $match[1], $localeMatch)) return $match[1];
        $locale = $localeMatch[1];
        $translation = $productId > 0 ? one('SELECT faq_schema_json,breadcrumb_schema_json FROM product_translations WHERE product_id=? AND locale=?', [$productId, $locale]) : null;
        $faq = esc((string)($translation['faq_schema_json'] ?? '{}'));
        $breadcrumb = esc((string)($translation['breadcrumb_schema_json'] ?? '{}'));
        return $match[1].'<label>FAQ JSON-LD<textarea name="faq_schema_json_'.$locale.'" maxlength="12000">'.$faq.'</textarea></label><label>Breadcrumb JSON-LD<textarea name="breadcrumb_schema_json_'.$locale.'" maxlength="12000">'.$breadcrumb.'</textarea></label>';
    }, $html) ?? $html;
});

function catalog_admin_route(): void {
global $path;
if (!in_array($path, ['/products','/product-edit','/product-bundles'], true)) return;
login_required();
$catalogLocales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
$saveTaxonomy = static function (int $productId, string $categories, string $tags): void {
    q('DELETE FROM product_category_links WHERE product_id=?', [$productId]);
    q('DELETE FROM product_tag_links WHERE product_id=?', [$productId]);
    foreach (array_filter(array_map('trim', explode(',', $categories))) as $name) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($slug === '') continue;
        q('INSERT INTO product_categories(slug,name) VALUES(?,?) ON CONFLICT(slug) DO UPDATE SET name=excluded.name', [$slug,$name]);
        $row = one('SELECT id FROM product_categories WHERE slug=?', [$slug]);
        if ($row) q('INSERT OR IGNORE INTO product_category_links(product_id,category_id) VALUES(?,?)', [$productId,$row['id']]);
    }
    foreach (array_filter(array_map('trim', explode(',', $tags))) as $name) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($slug === '') continue;
        q('INSERT INTO product_tags(slug,name) VALUES(?,?) ON CONFLICT(slug) DO UPDATE SET name=excluded.name', [$slug,$name]);
        $row = one('SELECT id FROM product_tags WHERE slug=?', [$slug]);
        if ($row) q('INSERT OR IGNORE INTO product_tag_links(product_id,tag_id) VALUES(?,?)', [$productId,$row['id']]);
    }
};
$catalogReady = static function (array $input) use ($catalogLocales): array {
    $missing = [];
    foreach ($catalogLocales as $locale) {
        foreach (['title'=>'title','description'=>'description','seo title'=>'seo_title','SEO description'=>'seo_description','keywords'=>'keywords','canonical URL'=>'canonical_url','OG image URL'=>'og_image','JSON-LD schema'=>'schema_json','FAQ schema'=>'faq_schema_json','Breadcrumb schema'=>'breadcrumb_schema_json'] as $label => $field) {
            if (trim((string)($input[$field.'_'.$locale] ?? '')) === '') { $missing[] = strtoupper($locale).' '.$label; }
        }
        if (json_decode((string)($input['schema_json_'.$locale] ?? ''), true) === null) $missing[] = strtoupper($locale).' invalid JSON-LD';
        if (json_decode((string)($input['faq_schema_json_'.$locale] ?? ''), true) === null) $missing[] = strtoupper($locale).' invalid FAQ schema';
        if (json_decode((string)($input['breadcrumb_schema_json_'.$locale] ?? ''), true) === null) $missing[] = strtoupper($locale).' invalid Breadcrumb schema';
        foreach (['canonical_url'=>'canonical URL','og_image'=>'OG image URL'] as $urlField => $urlLabel) if (!filter_var(trim((string)($input[$urlField.'_'.$locale] ?? '')), FILTER_VALIDATE_URL)) $missing[] = strtoupper($locale).' invalid '.$urlLabel;
    }
    if ((int)($input['price_minor'] ?? 0) <= 0) $missing[] = 'price';
    foreach (['demo_url'=>'demo URL','docs_url'=>'documentation URL','screenshot_url'=>'screenshot URL'] as $field => $label) {
        if (!filter_var(trim((string)($input[$field] ?? '')), FILTER_VALIDATE_URL)) $missing[] = $label;
    }
    return $missing;
};
if ($path === '/product-bundles') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $bundleProductId = (int)($_POST['product_id'] ?? 0);
        $code = strtolower(trim((string)($_POST['code'] ?? '')));
        $discount = max(0, min(100, (int)($_POST['discount_percent'] ?? 0)));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!$bundleProductId || !preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/', $code) || !in_array($status, ['draft','published','archived'], true)) throw new RuntimeException('Bundle details are invalid.');
        if ($status === 'published') throw new RuntimeException('Publish the bundle through Product quality after its products and plans are ready.');
        if (!one('SELECT id FROM products WHERE id=? AND status<>?', [$bundleProductId, 'archived'])) throw new RuntimeException('Bundle product is not available.');
        $items = [];
        foreach (array_filter(array_map('trim', explode(',', (string)($_POST['item_product_ids'] ?? '')))) as $rawId) {
            $itemId = (int)$rawId;
            if ($itemId > 0 && $itemId !== $bundleProductId && one('SELECT id FROM products WHERE id=? AND status<>?', [$itemId, 'archived'])) $items[$itemId] = true;
        }
        if (count($items) < 2) throw new RuntimeException('A bundle needs at least two published product components.');
        q('INSERT INTO product_bundles(product_id,code,discount_percent,status) VALUES(?,?,?,?) ON CONFLICT(product_id) DO UPDATE SET code=excluded.code,discount_percent=excluded.discount_percent,status=excluded.status,updated_at=CURRENT_TIMESTAMP', [$bundleProductId,$code,$discount,$status]);
        $bundle = one('SELECT id FROM product_bundles WHERE product_id=?', [$bundleProductId]);
        q('DELETE FROM product_bundle_items WHERE bundle_id=?', [$bundle['id']]);
        $sort = 0; foreach (array_keys($items) as $itemId) q('INSERT INTO product_bundle_items(bundle_id,product_id,quantity,sort_order) VALUES(?,?,1,?)', [$bundle['id'],$itemId,$sort++]);
        header('Location: ./product-bundles?saved=1'); exit;
    }
    $products = q("SELECT id,name,kind,status FROM products WHERE status<>'archived' ORDER BY name")->fetchAll();
    $options = ''; foreach ($products as $product) $options .= '<option value="'.(int)$product['id'].'">'.esc($product['name']).' · '.esc($product['kind']).' · '.esc($product['status']).'</option>';
    $bundles = q("SELECT b.*,p.name product_name FROM product_bundles b JOIN products p ON p.id=b.product_id ORDER BY b.updated_at DESC")->fetchAll();
    $rows = ''; foreach ($bundles as $bundle) { $items = q('SELECT p.name FROM product_bundle_items i JOIN products p ON p.id=i.product_id WHERE i.bundle_id=? ORDER BY i.sort_order', [$bundle['id']])->fetchAll(); $rows .= '<tr><td>'.esc($bundle['product_name']).'<br><small>'.esc($bundle['code']).'</small></td><td>'.esc(implode(', ', array_column($items, 'name'))).'</td><td>'.(int)$bundle['discount_percent'].'%</td><td>'.esc($bundle['status']).'</td></tr>'; }
    layout('Product bundles','<div class="card"><h1>Product bundles and upgrades</h1><p class="muted">Combine an application, theme or add-on into one catalogue product. Publication remains behind the Product quality gate.</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><label>Bundle product<select name="product_id">'.$options.'</select></label><label>Bundle code<input name="code" placeholder="cms-launch-pack" required></label><label>Component product IDs<input name="item_product_ids" placeholder="12,13,14" required></label><label>Discount percent<input type="number" name="discount_percent" value="10" min="0" max="100"/></label><label>Status<select name="status"><option value="draft">Draft</option><option value="archived">Archived</option></select></label><button>Save bundle</button></form></div><div class="card"><h2>Bundles</h2><table><tr><th>Bundle</th><th>Components</th><th>Discount</th><th>Status</th></tr>'.($rows ?: '<tr><td colspan="4">No bundles yet.</td></tr>').'</table></div>');
}
if ($path === '/product-edit') {
    $productId = (int)($_GET['id'] ?? 0);
    $product = one('SELECT * FROM products WHERE id=?', [$productId]);
    if (!$product) { http_response_code(404); layout('Product not found','<div class="card"><h1>Product not found</h1></div>'); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if ($name === '' || !in_array($status, ['draft','published','archived'], true)) throw new RuntimeException('Product details are invalid.');
        // Publication is deliberately owned by /product-quality.  The editor
        // may save a complete draft, but it must not bypass the signed-release
        // and published-plan gate by posting status=published directly.
        if ($status === 'published') throw new RuntimeException('Use Product quality to approve and publish this product after all release checks pass.');
        q('UPDATE products SET name=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?', [$name,$status,$productId]);
        foreach ($catalogLocales as $locale) {
            $values = [trim((string)($_POST['title_'.$locale] ?? '')),trim((string)($_POST['summary_'.$locale] ?? '')),trim((string)($_POST['description_'.$locale] ?? '')),trim((string)($_POST['slug_'.$locale] ?? '')),trim((string)($_POST['seo_title_'.$locale] ?? '')),trim((string)($_POST['seo_description_'.$locale] ?? '')),trim((string)($_POST['keywords_'.$locale] ?? '')),trim((string)($_POST['canonical_url_'.$locale] ?? '')),trim((string)($_POST['og_image_'.$locale] ?? '')),trim((string)($_POST['schema_json_'.$locale] ?? '{}')),trim((string)($_POST['faq_schema_json_'.$locale] ?? '{}')),trim((string)($_POST['breadcrumb_schema_json_'.$locale] ?? '{}'))];
            if ($values[3] === '') $values[3] = $product['slug'];
            $existing = one('SELECT id FROM product_translations WHERE product_id=? AND locale=?', [$productId,$locale]);
            if ($existing) q('UPDATE product_translations SET title=?,summary=?,description=?,slug=?,seo_title=?,seo_description=?,keywords=?,canonical_url=?,og_image=?,schema_json=?,faq_schema_json=?,breadcrumb_schema_json=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?', [$values[0],$values[1],$values[2],$values[3],$values[4],$values[5],$values[6],$values[7],$values[8],$values[9],$values[10],$values[11],$status === 'published' ? 'published' : 'draft',$existing['id']]);
            elseif (implode('', $values) !== '') q('INSERT INTO product_translations(product_id,locale,title,summary,description,slug,seo_title,seo_description,keywords,canonical_url,og_image,schema_json,faq_schema_json,breadcrumb_schema_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$productId,$locale,$values[0],$values[1],$values[2],$values[3],$values[4],$values[5],$values[6],$values[7],$values[8],$values[9],$values[10],$values[11],$status === 'published' ? 'published' : 'draft']);
        }
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));
        $priceMinor = max(0, (int)($_POST['price_minor'] ?? 0));
        if (preg_match('/^[A-Z]{3}$/', $currency)) q('INSERT INTO product_prices(product_id,currency,amount_minor) VALUES(?,?,?) ON CONFLICT(product_id,currency) DO UPDATE SET amount_minor=excluded.amount_minor,updated_at=CURRENT_TIMESTAMP', [$productId,$currency,$priceMinor]);
        foreach ([['demo','Demo URL','demo_url'],['document','Documentation URL','docs_url'],['screenshot','Screenshot URL','screenshot_url'],['video','Preview video URL','video_url']] as [$kind,$label,$field]) {
            $url = trim((string)($_POST[$field] ?? ''));
            if ($url !== '' && filter_var($url,FILTER_VALIDATE_URL)) q('INSERT INTO product_assets(product_id,kind,label,url,status) VALUES(?,?,?,?,?)', [$productId,$kind,$label,$url,'published']);
        }
        $categories = implode(', ', array_column(q('SELECT pc.name FROM product_category_links pcl JOIN product_categories pc ON pc.id=pcl.category_id WHERE pcl.product_id=? ORDER BY pc.name', [$productId])->fetchAll(), 'name'));
        $tags = implode(', ', array_column(q('SELECT pt.name FROM product_tag_links ptl JOIN product_tags pt ON pt.id=ptl.tag_id WHERE ptl.product_id=? ORDER BY pt.name', [$productId])->fetchAll(), 'name'));
        $saveTaxonomy($productId, (string)($_POST['categories'] ?? $categories), (string)($_POST['tags'] ?? $tags));
        header('Location: ./product-edit?id='.$productId.'&saved=1'); exit;
    }
    $translations = [];
    foreach (q('SELECT * FROM product_translations WHERE product_id=?', [$productId])->fetchAll() as $translation) $translations[$translation['locale']] = $translation;
    $localeFields = '';
    foreach ($catalogLocales as $locale) { $t=$translations[$locale]??[]; $localeFields .= '<details'.($locale === 'tr' ? ' open' : '').'><summary>'.esc(strtoupper($locale)).' · content and SEO</summary><label>Title<input name="title_'.$locale.'" value="'.esc($t['title']??'').'" maxlength="220"></label><label>Summary<input name="summary_'.$locale.'" value="'.esc($t['summary']??'').'" maxlength="1000"></label><label>Description<textarea name="description_'.$locale.'" maxlength="30000">'.esc($t['description']??'').'</textarea></label><label>SEO title<input name="seo_title_'.$locale.'" value="'.esc($t['seo_title']??'').'" maxlength="100"></label><label>SEO description<input name="seo_description_'.$locale.'" value="'.esc($t['seo_description']??'').'" maxlength="220"></label><label>Keywords<input name="keywords_'.$locale.'" value="'.esc($t['keywords']??'').'" maxlength="1000"></label><label>Canonical URL<input type="url" name="canonical_url_'.$locale.'" value="'.esc($t['canonical_url']??'').'"></label><label>OG image URL<input type="url" name="og_image_'.$locale.'" value="'.esc($t['og_image']??'').'"></label><label>JSON-LD schema<textarea name="schema_json_'.$locale.'" maxlength="12000">'.esc($t['schema_json']??'{}').'</textarea></label></details>'; }
    $editCategories = implode(', ', array_column(q('SELECT pc.name FROM product_category_links pcl JOIN product_categories pc ON pc.id=pcl.category_id WHERE pcl.product_id=? ORDER BY pc.name', [$productId])->fetchAll(), 'name'));
    $editTags = implode(', ', array_column(q('SELECT pt.name FROM product_tag_links ptl JOIN product_tags pt ON pt.id=ptl.tag_id WHERE ptl.product_id=? ORDER BY pt.name', [$productId])->fetchAll(), 'name'));
    layout('Edit product','<div class="card"><h1>Edit product</h1><p class="muted">'.esc($product['slug']).' · '.esc($product['kind']).'</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"> <label>Product name<input name="name" value="'.esc($product['name']).'" required maxlength="180"></label><label>Status<select name="status"><option value="draft"'.($product['status']==='draft'?' selected':'').'>Draft</option><option value="published"'.($product['status']==='published'?' selected':'').'>Published</option><option value="archived"'.($product['status']==='archived'?' selected':'').'>Archived</option></select></label><label>Categories<input name="categories" value="'.esc($editCategories).'" placeholder="business, cms, ecommerce"></label><label>Tags<input name="tags" value="'.esc($editTags).'" placeholder="responsive, multilingual, seo"></label><h2>14-language content and SEO</h2>'.$localeFields.'<button>Save product</button></form></div>');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $kind = (string)($_POST['kind'] ?? 'application');
    $status = (string)($_POST['status'] ?? 'draft');
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/', $slug)) throw new RuntimeException('Product slug must contain lowercase letters, numbers and hyphens.');
    if ($name === '' || !in_array($kind, ['application','theme','addon'], true) || !in_array($status, ['draft','published','archived'], true)) throw new RuntimeException('Product details are invalid.');
    // New products always enter the editorial queue.  This prevents a
    // partially translated or unsigned product from becoming saleable before
    // the quality dashboard has verified its assets, plans and release hash.
    if ($status === 'published') throw new RuntimeException('Create the product as a draft, then approve it from Product quality.');
    q('INSERT INTO products(slug,name,kind,status) VALUES(?,?,?,?)', [$slug,$name,$kind,$status]);
    $productId = (int)lc_db()->lastInsertId();
    foreach ($catalogLocales as $locale) {
        $title = trim((string)($_POST['title_'.$locale] ?? ''));
        $summary = trim((string)($_POST['summary_'.$locale] ?? ''));
        $description = trim((string)($_POST['description_'.$locale] ?? ''));
        $localizedSlug = strtolower(trim((string)($_POST['slug_'.$locale] ?? $slug)));
        $seoTitle = trim((string)($_POST['seo_title_'.$locale] ?? ''));
        $seoDescription = trim((string)($_POST['seo_description_'.$locale] ?? ''));
        $keywords = trim((string)($_POST['keywords_'.$locale] ?? ''));
        $canonical = trim((string)($_POST['canonical_url_'.$locale] ?? ''));
        $ogImage = trim((string)($_POST['og_image_'.$locale] ?? ''));
        $schemaJson = trim((string)($_POST['schema_json_'.$locale] ?? '{}'));
        $faqSchemaJson = trim((string)($_POST['faq_schema_json_'.$locale] ?? '{}'));
        $breadcrumbSchemaJson = trim((string)($_POST['breadcrumb_schema_json_'.$locale] ?? '{}'));
        if ($title === '' && $summary === '' && $description === '' && $seoTitle === '' && $seoDescription === '' && $keywords === '' && $canonical === '' && $ogImage === '' && $schemaJson === '{}' && $faqSchemaJson === '{}' && $breadcrumbSchemaJson === '{}') continue;
        q('INSERT INTO product_translations(product_id,locale,title,summary,description,slug,seo_title,seo_description,keywords,canonical_url,og_image,schema_json,faq_schema_json,breadcrumb_schema_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$productId,$locale,$title,$summary,$description,$localizedSlug !== '' ? $localizedSlug : $slug,$seoTitle,$seoDescription,$keywords,$canonical,$ogImage,$schemaJson,$faqSchemaJson,$breadcrumbSchemaJson,$status === 'published' ? 'published' : 'draft']);
    }
    $saveTaxonomy($productId, (string)($_POST['categories'] ?? ''), (string)($_POST['tags'] ?? ''));
    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));
    $priceMinor = max(0, (int)($_POST['price_minor'] ?? 0));
    if (preg_match('/^[A-Z]{3}$/', $currency)) q('INSERT INTO product_prices(product_id,currency,amount_minor) VALUES(?,?,?)', [$productId,$currency,$priceMinor]);
    foreach ([['demo','Demo URL','demo_url'],['document','Documentation URL','docs_url'],['screenshot','Screenshot URL','screenshot_url'],['video','Preview video URL','video_url']] as [$kind,$label,$field]) {
        $url = trim((string)($_POST[$field] ?? ''));
        if ($url !== '' && filter_var($url,FILTER_VALIDATE_URL)) q('INSERT INTO product_assets(product_id,kind,label,url,status) VALUES(?,?,?,?,?)', [$productId,$kind,$label,$url,'published']);
    }
    header('Location: ./products?saved=1');
    exit;
}
$products = q('SELECT p.*,COUNT(DISTINCT t.id) translation_count,COUNT(DISTINCT a.id) asset_count FROM products p LEFT JOIN product_translations t ON t.product_id=p.id LEFT JOIN product_assets a ON a.product_id=p.id GROUP BY p.id ORDER BY p.updated_at DESC,p.id DESC')->fetchAll();
$rows = '';
foreach ($products as $product) $rows .= '<tr><td><a href="./product-edit?id='.(int)$product['id'].'"><strong>'.esc($product['name']).'</strong></a><br><small>'.esc($product['slug']).'</small></td><td>'.esc($product['kind']).'</td><td>'.esc($product['status']).'</td><td>'.(int)$product['translation_count'].' / 14</td><td>'.(int)$product['asset_count'].'</td><td>'.esc($product['updated_at']).'</td></tr>';
$localeFields = '';
foreach ($catalogLocales as $locale) $localeFields .= '<details'.($locale === 'tr' ? ' open' : '').'><summary>'.esc(strtoupper($locale)).' · localized SEO and content</summary><label>Title<input name="title_'.$locale.'" maxlength="220"></label><label>Summary<input name="summary_'.$locale.'" maxlength="1000"></label><label>Description<textarea name="description_'.$locale.'" maxlength="30000"></textarea></label><label>SEO title<input name="seo_title_'.$locale.'" maxlength="100"></label><label>SEO description<input name="seo_description_'.$locale.'" maxlength="220"></label><label>Keywords<input name="keywords_'.$locale.'" maxlength="1000"></label><label>Canonical URL<input type="url" name="canonical_url_'.$locale.'"></label><label>OG image URL<input type="url" name="og_image_'.$locale.'"></label><label>JSON-LD schema<textarea name="schema_json_'.$locale.'" maxlength="12000">{}</textarea></label></details>';
layout('Product catalogue','<div class="card"><h1>Product catalogue</h1><p class="muted">Create software, themes and add-ons as first-class products. Each locale has its own content and SEO record; publication remains a separate editorial decision.</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"> <label>Product name<input name="name" required maxlength="180"></label><label>Slug<input name="slug" required pattern="[a-z0-9-]+" maxlength="100"></label><label>Type<select name="kind"><option value="application">Software</option><option value="theme">Theme</option><option value="addon">Add-on</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label><label>Categories<input name="categories" placeholder="business, cms, ecommerce"></label><label>Tags<input name="tags" placeholder="responsive, multilingual, seo"></label><h2>14-language content and SEO</h2>'.$localeFields.'<button>Create product</button></form></div><div class="card"><h2>Products</h2><table><tr><th>Product</th><th>Type</th><th>Status</th><th>Translations</th><th>Assets</th><th>Updated</th></tr>'.$rows.'</table></div>');
}
catalog_admin_route();
