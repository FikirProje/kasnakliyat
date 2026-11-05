<?php
declare(strict_types=1);

function product_release_signature_valid(array $release): bool
{
    $signature = (string)($release['signature'] ?? '');
    $manifest = (string)($release['manifest_json'] ?? '');
    if ($signature === '' || $manifest === '') return false;
    if (str_starts_with($signature, 'HMAC-SHA256.')) {
        $secret = trim((string)lc_env('RELEASE_SIGNING_SECRET'));
        return $secret !== '' && hash_equals('HMAC-SHA256.'.hash_hmac('sha256', $manifest, $secret), $signature);
    }
    if (str_starts_with($signature, 'RSA-SHA256.')) {
        $public = trim((string)lc_env('RELEASE_SIGNING_PUBLIC_KEY'));
        $raw = base64_decode(substr($signature, strlen('RSA-SHA256.')), true);
        return $public !== '' && $raw !== false && openssl_verify($manifest, $raw, $public, OPENSSL_ALGO_SHA256) === 1;
    }
    return false;
}

function product_release_compatibility_valid(string $kind, array $release): bool
{
    $raw = (string)($kind === 'theme' ? ($release['requirements_json'] ?? '') : ($release['manifest_json'] ?? ''));
    $data = json_decode($raw, true);
    if (!is_array($data)) return false;
    $compatibility = is_array($data['compatibility'] ?? null) ? $data['compatibility'] : $data;
    $php = trim((string)($compatibility['php'] ?? $compatibility['min_php'] ?? ''));
    if ($php === '') return false;
    if ($kind === 'theme') return trim((string)($compatibility['browser'] ?? '')) !== '';
    $database = trim((string)($compatibility['database'] ?? ''));
    $extensions = $compatibility['extensions'] ?? [];
    return $database !== '' && is_array($extensions) && count(array_filter($extensions, static fn($extension): bool => trim((string)$extension) !== '')) > 0;
}

function product_quality_json_object(string $value): ?array
{
    $decoded = json_decode($value, true);
    return is_array($decoded) && $decoded !== [] ? $decoded : null;
}

function product_quality_schema_type(string $value, array $allowed): bool
{
    $schema = product_quality_json_object($value);
    if ($schema === null) return false;
    $type = $schema['@type'] ?? null;
    return is_string($type) ? in_array($type, $allowed, true) : (is_array($type) && (bool)array_intersect($allowed, $type));
}

function product_quality_schema_offer_valid(string $value): bool
{
    $schema = product_quality_json_object($value);
    if ($schema === null || !array_key_exists('offers', $schema)) return false;
    $offers = is_array($schema['offers']) && array_is_list($schema['offers']) ? $schema['offers'] : [$schema['offers']];
    foreach ($offers as $offer) {
        if (!is_array($offer) || !in_array($offer['@type'] ?? null, ['Offer','AggregateOffer'], true) || !isset($offer['priceCurrency'])) return false;
    }
    return $offers !== [];
}

function product_quality_http_url(string $value): bool
{
    return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false && (bool)preg_match('/^https?:\\/\\//i', $value);
}

function product_quality_admin_route(): void
{
    global $path;
    if ($path !== '/product-quality') return;
    login_required();
    $locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
    $score = static function (int $productId) use ($locales): array {
        $translations = q('SELECT * FROM product_translations WHERE product_id=?', [$productId])->fetchAll();
        $byLocale = [];
        foreach ($translations as $translation) $byLocale[$translation['locale']] = $translation;
        $product = one('SELECT kind FROM products WHERE id=?', [$productId]);
        $missing = [];
        $localesReady = count($translations) >= count($locales);
        $seoReady = true;
        foreach ($locales as $locale) {
            $translation = $byLocale[$locale] ?? null;
            if (!$translation || (string)($translation['status'] ?? '') !== 'published') $localesReady = false;
            foreach (['title'=>'title','description'=>'description','slug'=>'localized slug','seo_title'=>'SEO title','seo_description'=>'SEO description','keywords'=>'keywords','canonical_url'=>'canonical URL','og_image'=>'OG image URL'] as $field => $label) {
                if (!$translation || (string)($translation['status'] ?? '') !== 'published' || trim((string)$translation[$field]) === '') {
                    $missing[] = strtoupper($locale).' '.$label;
                    if (in_array($field, ['canonical_url','og_image'], true)) $seoReady = false;
                    else $localesReady = false;
                }
            }
            $productTypes = (($product ?? [])['kind'] ?? '') === 'application' ? ['SoftwareApplication','Product'] : ['Product','SoftwareApplication'];
            if (!$translation || !product_quality_schema_type((string)($translation['schema_json'] ?? ''), $productTypes)) { $missing[] = strtoupper($locale).' Product/SoftwareApplication schema'; $seoReady = false; }
            if (!$translation || !product_quality_schema_offer_valid((string)($translation['schema_json'] ?? ''))) { $missing[] = strtoupper($locale).' Offer schema'; $seoReady = false; }
            if (!$translation || !product_quality_schema_type((string)($translation['faq_schema_json'] ?? ''), ['FAQPage'])) { $missing[] = strtoupper($locale).' FAQ schema'; $seoReady = false; }
            if (!$translation || !product_quality_schema_type((string)($translation['breadcrumb_schema_json'] ?? ''), ['BreadcrumbList'])) { $missing[] = strtoupper($locale).' Breadcrumb schema'; $seoReady = false; }
            if ($translation && !product_quality_http_url((string)$translation['canonical_url'])) { $missing[] = strtoupper($locale).' canonical URL'; $seoReady = false; }
            if ($translation && !product_quality_http_url((string)$translation['og_image'])) { $missing[] = strtoupper($locale).' OG image URL'; $seoReady = false; }
        }
        $price = one('SELECT id FROM product_prices WHERE product_id=? AND active=1 AND amount_minor>0', [$productId]);
        $assets = q("SELECT kind FROM product_assets WHERE product_id=? AND status='published'", [$productId])->fetchAll();
        $assetKinds = array_values(array_unique(array_map(static fn(array $asset): string => (string)$asset['kind'], $assets)));
        foreach (['demo','document','screenshot'] as $kind) if (!in_array($kind, $assetKinds, true)) $missing[] = $kind.' asset';
        if (($product['kind'] ?? '') === 'theme' && !in_array('video', $assetKinds, true)) $missing[] = 'video asset';
        $releaseReady = false;
        if (in_array(($product['kind'] ?? ''), ['application','addon'], true)) {
            foreach (q("SELECT package_path,manifest_json,sha256,signature FROM application_releases WHERE product_id=? AND channel='stable' AND status='published'", [$productId])->fetchAll() as $release) {
                $releaseReady = is_file((string)$release['package_path']) && preg_match('/^[a-f0-9]{64}$/i', (string)$release['sha256']) && product_release_compatibility_valid('application', $release) && product_release_signature_valid($release) && hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', (string)$release['package_path'])));
                if ($releaseReady) break;
            }
        } elseif (($product['kind'] ?? '') === 'theme') {
            foreach (q("SELECT package_name,theme_id,version,storage_path,requirements_json,sha256,signature FROM theme_packages WHERE product_id=? AND status='published'", [$productId])->fetchAll() as $release) {
                $signingKey = trim((string)lc_env('LICENSE_PACKAGE_SIGNING_KEY'));
                $expectedSignature = $signingKey !== '' ? hash_hmac('sha256', (string)$release['sha256'].'|'.(int)$release['theme_id'].'|'.(string)$release['version'], $signingKey) : '';
                $releaseReady = is_file((string)$release['storage_path']) && preg_match('/^[a-f0-9]{64}$/i', (string)$release['sha256']) && product_release_compatibility_valid('theme', $release) && $expectedSignature !== '' && hash_equals($expectedSignature, (string)$release['signature']) && hash_equals(strtolower((string)$release['sha256']), strtolower(hash_file('sha256', (string)$release['storage_path'])));
                if ($releaseReady) break;
            }
        } else {
            $releaseReady = in_array('download', $assetKinds, true);
        }
        if (!$releaseReady) $missing[] = 'verified signed release package';
        $planReady = (bool)one("SELECT id FROM license_plans WHERE product_id=? AND status='published' LIMIT 1", [$productId]);
        if (!$planReady) $missing[] = 'published licence plan';
        $bundleReady = true;
        $bundle = one("SELECT id,discount_percent,status FROM product_bundles WHERE product_id=? LIMIT 1", [$productId]);
        if ($bundle) {
            $bundleItems = q("SELECT p.id,p.status,p.name FROM product_bundle_items i JOIN products p ON p.id=i.product_id WHERE i.bundle_id=? ORDER BY i.sort_order,i.product_id", [(int)$bundle['id']])->fetchAll();
            if ((string)$bundle['status'] !== 'published') { $missing[] = 'published bundle'; $bundleReady = false; }
            if (count($bundleItems) < 2) { $missing[] = 'bundle components'; $bundleReady = false; }
            if ((float)$bundle['discount_percent'] < 0 || (float)$bundle['discount_percent'] > 100) { $missing[] = 'valid bundle discount'; $bundleReady = false; }
            foreach ($bundleItems as $component) {
                if ((string)$component['status'] !== 'published') { $missing[] = 'published bundle component: '.(string)$component['name']; $bundleReady = false; }
                if (!one("SELECT id FROM license_plans WHERE product_id=? AND status='published' LIMIT 1", [(int)$component['id']])) { $missing[] = 'bundle component licence: '.(string)$component['name']; $bundleReady = false; }
            }
        }
        $assetsReady = in_array('demo', $assetKinds, true) && in_array('document', $assetKinds, true) && in_array('screenshot', $assetKinds, true) && (($product['kind'] ?? '') !== 'theme' || in_array('video', $assetKinds, true));
        $commercialReady = (bool)$price && $planReady;
        $checks = [$localesReady, $seoReady, count($missing) === 0, (bool)$price, in_array('demo', $assetKinds, true), in_array('document', $assetKinds, true), in_array('screenshot', $assetKinds, true), $releaseReady, $planReady, $bundleReady];
        $passed = count(array_filter($checks));
        return ['score'=>(int)round($passed / count($checks) * 100), 'missing'=>$missing, 'translations'=>count($translations), 'assets'=>$assetKinds, 'ready'=>count($missing) === 0 && (bool)$price, 'groups'=>['locales'=>$localesReady, 'seo'=>$seoReady, 'commercial'=>$commercialReady, 'assets'=>$assetsReady, 'release'=>$releaseReady]];
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (($_POST['action'] ?? '') === 'moderate_review') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $reviewStatus = (string)($_POST['review_status'] ?? 'pending');
            if (!in_array($reviewStatus, ['pending','published','rejected'], true) || !one('SELECT id FROM product_reviews WHERE id=?', [$reviewId])) throw new RuntimeException('Review moderation data is invalid.');
            q('UPDATE product_reviews SET status=? WHERE id=?', [$reviewStatus,$reviewId]);
            header('Location: ./product-quality?reviewed=1'); exit;
        }
        $productId = (int)($_POST['product_id'] ?? 0);
        $product = one('SELECT * FROM products WHERE id=?', [$productId]);
        if (!$product) throw new RuntimeException('Product not found.');
        $result = $score($productId);
        if (!$result['ready']) throw new RuntimeException('Product is not ready for publication. Missing: '.implode(', ', array_slice($result['missing'], 0, 15)).(count($result['missing']) > 15 ? ' …' : ''));
        q("UPDATE products SET status='published',updated_at=CURRENT_TIMESTAMP WHERE id=?", [$productId]);
        q("UPDATE product_translations SET status='published',updated_at=CURRENT_TIMESTAMP WHERE product_id=?", [$productId]);
        header('Location: ./product-quality?published=1'); exit;
    }

    $rows = '';
    foreach (q('SELECT * FROM products WHERE status<>? ORDER BY updated_at DESC,id DESC', ['archived'])->fetchAll() as $product) {
        $result = $score((int)$product['id']);
        $missing = $result['missing'] ? esc(implode(', ', array_slice($result['missing'], 0, 8)).(count($result['missing']) > 8 ? ' …' : '')) : '<span class="ok">Ready</span>';
        $groupLabels = ['locales'=>'14 locales', 'seo'=>'SEO/schema', 'commercial'=>'Sales', 'assets'=>'Assets', 'release'=>'Release'];
        $groups = [];
        foreach ($groupLabels as $key => $label) $groups[] = '<span class="'.($result['groups'][$key] ? 'ok' : 'muted').'">'.($result['groups'][$key] ? '✓' : '✗').' '.esc($label).'</span>';
        $action = $result['ready'] && $product['status'] !== 'published' ? '<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="product_id" value="'.(int)$product['id'].'"><button>Approve and publish</button></form>' : ($product['status'] === 'published' ? '<span class="ok">Published</span>' : 'Complete missing fields');
        $rows .= '<tr><td><strong>'.esc($product['name']).'</strong><br><small>'.esc($product['kind']).' · '.esc($product['status']).'</small></td><td><strong>'.(int)$result['score'].'%</strong><br><small>'.implode(' · ', $groups).'</small></td><td>'.(int)count($result['assets']).' assets</td><td>'.$missing.'</td><td>'.$action.'</td></tr>';
    }
    $reviewRows=''; foreach(q("SELECT r.*,p.name product_name,b.email FROM product_reviews r JOIN products p ON p.id=r.product_id JOIN buyers b ON b.id=r.buyer_id WHERE r.status='pending' ORDER BY r.created_at ASC LIMIT 100")->fetchAll() as $review) $reviewRows.='<tr><td>'.esc($review['product_name']).'</td><td>'.str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating']).'</td><td>'.esc($review['title']).'<br>'.nl2br(esc($review['body'])).'<br><small>'.esc($review['email']).'</small></td><td><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="moderate_review"/><input type="hidden" name="review_id" value="'.(int)$review['id'].'"/><button name="review_status" value="published">Approve</button><button class="secondary" name="review_status" value="rejected">Reject</button></form></td></tr>';
    layout('Product quality review','<div class="card"><h1>Product quality review</h1><p class="muted">Every product is checked before publication: 14 locale records, localized SEO, price, demo, documentation and screenshot. Payment credentials are not part of this editorial gate.</p><table><tr><th>Product</th><th>Quality</th><th>Assets</th><th>Missing</th><th>Action</th></tr>'.$rows.'</table></div><div class="card"><h2>Review moderation</h2><p class="muted">Customer reviews remain pending until an administrator approves them.</p><table><tr><th>Product</th><th>Rating</th><th>Review</th><th>Action</th></tr>'.($reviewRows ?: '<tr><td colspan="4">No pending reviews.</td></tr>').'</table></div>');
}

product_quality_admin_route();
