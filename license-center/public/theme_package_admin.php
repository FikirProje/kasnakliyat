<?php
declare(strict_types=1);

function theme_package_admin_route(): void {
    global $path;
    if ($path !== '/themes/package') return;
    login_required();
    $themes = q("SELECT id,name,version FROM themes ORDER BY name")->fetchAll();
    $themeProducts = q("SELECT id,name,slug FROM products WHERE kind='theme' AND status<>'archived' ORDER BY name")->fetchAll();
    $parentPackages = q("SELECT p.id,p.theme_id,p.version,t.name FROM theme_packages p JOIN themes t ON t.id=p.theme_id WHERE p.status IN ('review','published') ORDER BY t.name,p.version DESC")->fetchAll();
    $rollbackPackages = q("SELECT p.id,p.theme_id,p.version,t.name FROM theme_packages p JOIN themes t ON t.id=p.theme_id WHERE p.status IN ('review','published','revoked') ORDER BY t.name,p.version DESC")->fetchAll();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $themeId = (int)($_POST['theme_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $version = trim((string)($_POST['version'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $changelog = mb_substr(trim((string)($_POST['changelog'] ?? '')), 0, 10000);
        $parentPackageId = max(0, (int)($_POST['parent_package_id'] ?? 0));
        $rollbackOf = max(0, (int)($_POST['rollback_of'] ?? 0));
        $previewVideo = trim((string)($_POST['preview_video_url'] ?? ''));
        $themeConfig = trim((string)($_POST['theme_config_json'] ?? '{}')) ?: '{}';
        $logoUrl = trim((string)($_POST['logo_url'] ?? ''));
        $fontFamily = mb_substr(trim((string)($_POST['font_family'] ?? '')), 0, 120);
        $primaryColor = strtoupper(trim((string)($_POST['primary_color'] ?? '')));
        $accentColor = strtoupper(trim((string)($_POST['accent_color'] ?? '')));
        $sectionsJson = trim((string)($_POST['sections_json'] ?? ''));
        $requirements = ['min_php'=>trim((string)($_POST['min_php'] ?? '8.2.0')),'browser'=>trim((string)($_POST['browser'] ?? '')),'notes'=>trim((string)($_POST['requirements'] ?? ''))];
        if ($productId > 0 && !one("SELECT id FROM products WHERE id=? AND kind='theme' AND status<>'archived'", [$productId])) throw new RuntimeException('Selected marketplace product is not a theme.');
        if (!$themeId && $productId > 0) {
            $product = one('SELECT name FROM products WHERE id=?', [$productId]);
            q("INSERT INTO themes(name,locale,status) VALUES(?,?, 'published')", [$product['name'] ?? 'Marketplace theme', 'global']);
            $themeId = (int)lc_db()->lastInsertId();
        }
        if (!$themeId || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) || !in_array($status,['draft','review','published','revoked'],true)) throw new RuntimeException('Theme package details are invalid.');
        if ($previewVideo !== '' && !filter_var($previewVideo, FILTER_VALIDATE_URL)) throw new RuntimeException('Preview video URL is invalid.');
        $themeConfigData = json_decode($themeConfig, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($themeConfigData)) throw new RuntimeException('Theme configuration must be a JSON object.');
        if ($logoUrl !== '' && !filter_var($logoUrl, FILTER_VALIDATE_URL)) throw new RuntimeException('Theme logo URL is invalid.');
        foreach (['primary color'=>$primaryColor,'accent color'=>$accentColor] as $label=>$color) if ($color !== '' && !preg_match('/^#[0-9A-F]{6}$/', $color)) throw new RuntimeException('Theme '.$label.' must be a six-digit hex color.');
        $sections = $sectionsJson === '' ? null : json_decode($sectionsJson, true, 32, JSON_THROW_ON_ERROR);
        if ($sections !== null && !is_array($sections)) throw new RuntimeException('Theme sections must be a JSON array.');
        foreach (['logo'=>$logoUrl,'font'=>$fontFamily,'primary_color'=>$primaryColor,'accent_color'=>$accentColor] as $key=>$value) if ($value !== '') $themeConfigData[$key] = $value;
        if ($sections !== null) $themeConfigData['sections'] = $sections;
        $themeConfig = json_encode($themeConfigData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if ($parentPackageId > 0 && !one("SELECT id FROM theme_packages WHERE id=? AND theme_id=? AND status IN ('review','published')", [$parentPackageId,$themeId])) throw new RuntimeException('Child theme parent must be a review or published package of the same theme.');
        if ($rollbackOf > 0 && !one("SELECT id FROM theme_packages WHERE id=? AND theme_id=?", [$rollbackOf,$themeId])) throw new RuntimeException('Rollback target must belong to the same theme.');
        if (!isset($_FILES['package']) || !is_array($_FILES['package']) || (int)($_FILES['package']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('A theme ZIP package is required.');
        if ((int)$_FILES['package']['size'] > 52428800 || !is_uploaded_file((string)$_FILES['package']['tmp_name'])) throw new RuntimeException('Theme package is too large or invalid.');
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZIP support is required to publish a theme package.');
        $zip = new ZipArchive();
        if ($zip->open((string)$_FILES['package']['tmp_name']) !== true) throw new RuntimeException('Theme package ZIP cannot be opened.');
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($name === '' || str_contains($name,'..') || str_starts_with($name,'/') || preg_match('/\.(php|phtml|phar|cgi|pl|py|sh)$/i',$name)) { $zip->close(); throw new RuntimeException('Theme package contains an unsafe executable or path.'); }
        }
        $zip->close();
        $theme = one('SELECT id,name FROM themes WHERE id=?',[$themeId]);
        if (!$theme) throw new RuntimeException('Theme not found.');
        $dir = rtrim(lc_env('LICENSE_PACKAGE_PATH', LC_ROOT.'/storage/theme-packages'),'/').'/'.$themeId;
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Theme package storage is not writable.');
        $filename = 'theme-'.$themeId.'-'.str_replace(['.','+'],['_','_'],$version).'-'.bin2hex(random_bytes(6)).'.zip';
        $target = $dir.'/'.$filename;
        if (!move_uploaded_file((string)$_FILES['package']['tmp_name'],$target)) throw new RuntimeException('Theme package could not be stored.');
        @chmod($target,0600);
        $sha256 = hash_file('sha256', $target);
        $signingKey = lc_env('LICENSE_PACKAGE_SIGNING_KEY');
        if ($status === 'published' && $signingKey === '') { @unlink($target); throw new RuntimeException('A package signing key is required before publishing a theme release.'); }
        $signature = $signingKey === '' ? '' : hash_hmac('sha256', $sha256.'|'.$themeId.'|'.$version, $signingKey);
        q('INSERT INTO theme_packages(theme_id,product_id,version,package_name,storage_path,sha256,signature,requirements_json,changelog,status,rollback_of,parent_package_id,preview_video_url,theme_config_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$themeId,$productId ?: null,$version,$filename,$target,$sha256,$signature,json_encode($requirements,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$changelog,$status,$rollbackOf ?: null,$parentPackageId ?: null,$previewVideo,$themeConfig]);
        header('Location: ./themes/package?saved=1'); exit;
    }
    $options='<option value="0">Use marketplace theme product</option>'; foreach($themes as $theme) $options.='<option value="'.(int)$theme['id'].'">Legacy: '.esc($theme['name']).' (v'.esc($theme['version']).')</option>';
    $productOptions='<option value="0">No marketplace product</option>'; foreach($themeProducts as $product) $productOptions.='<option value="'.(int)$product['id'].'">'.esc($product['name']).' · '.esc($product['slug']).'</option>';
    $parentOptions='<option value="0">Base theme release</option>'; foreach($parentPackages as $parent) $parentOptions.='<option value="'.(int)$parent['id'].'">'.esc($parent['name'].' · v'.$parent['version']).'</option>';
    $rollbackOptions='<option value="0">No rollback target</option>'; foreach($rollbackPackages as $rollback) $rollbackOptions.='<option value="'.(int)$rollback['id'].'">'.esc($rollback['name'].' · v'.$rollback['version']).'</option>';
    $rows=''; foreach(q('SELECT p.*,t.name,parent.version parent_version,mp.name product_name FROM theme_packages p JOIN themes t ON t.id=p.theme_id LEFT JOIN products mp ON mp.id=p.product_id LEFT JOIN theme_packages parent ON parent.id=p.parent_package_id ORDER BY p.created_at DESC LIMIT 100')->fetchAll() as $package) $rows.='<tr><td>'.esc($package['product_name'] ?: $package['name']).'</td><td>'.esc($package['version']).'</td><td>'.esc($package['status']).'</td><td>'.esc($package['parent_version'] ? 'v'.$package['parent_version'] : 'Base').'</td><td>'.($package['preview_video_url']!==''?'<a href="'.esc($package['preview_video_url']).'">Preview</a>':'—').'</td><td><code>'.esc(substr($package['sha256'],0,16)).'…</code></td><td>'.esc($package['created_at']).'</td></tr>';
    layout('Theme packages','<div class="card"><h1>Theme package releases</h1><p class="muted">Upload a signed-ready ZIP. PHP and path traversal entries are rejected; packages are stored outside the public document root and can be linked to a marketplace theme product.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.csrf().'"> <label>Marketplace theme product<select name="product_id">'.$productOptions.'</select></label><label>Legacy theme fallback<select name="theme_id">'.$options.'</select></label><label>Version<input name="version" value="1.0.0" required></label><label>Parent release<select name="parent_package_id">'.$parentOptions.'</select></label><label>Rollback target<select name="rollback_of">'.$rollbackOptions.'</select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="review">Review</option><option value="published">Published</option><option value="revoked">Revoked</option></select></label><label>Minimum PHP<input name="min_php" value="8.2.0"></label><label>Browser requirements<input name="browser"></label><label>Requirements notes<textarea name="requirements"></textarea></label><label>Preview video URL<input type="url" name="preview_video_url" placeholder="https://..."></label><h2>Theme editor defaults</h2><p class="muted">These values are delivered to the installer as the theme configuration and can be overridden by the customer.</p><label>Logo URL<input type="url" name="logo_url" placeholder="https://.../logo.svg"></label><label>Font family<input name="font_family" placeholder="Inter, system-ui"></label><label>Primary colour<input name="primary_color" placeholder="#0D2E42" pattern="#[0-9A-Fa-f]{6}"></label><label>Accent colour<input name="accent_color" placeholder="#FF9149" pattern="#[0-9A-Fa-f]{6}"></label><label>Sections JSON<textarea name="sections_json" placeholder="[{&quot;key&quot;:&quot;hero&quot;,&quot;enabled&quot;:true}]">[]</textarea></label><label>Advanced theme configuration JSON<textarea name="theme_config_json">{"logo":"","font":"","primary_color":"","accent_color":"","sections":[]}</textarea></label><label>Changelog<textarea name="changelog"></textarea></label><label>Theme ZIP<input type="file" name="package" accept=".zip,application/zip" required></label><button>Store theme release</button></form></div><div class="card"><h2>Theme releases</h2><table><tr><th>Theme / product</th><th>Version</th><th>Status</th><th>Parent</th><th>Video</th><th>SHA-256</th><th>Created</th></tr>'.$rows.'</table></div>');
}
theme_package_admin_route();
