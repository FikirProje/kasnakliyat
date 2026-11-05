<?php
declare(strict_types=1);

ob_start(static function (string $html): string {
    if (($GLOBALS['path'] ?? '') !== '/licenses' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return $html;
    $products = q("SELECT id,slug,name,kind FROM products WHERE status='published' ORDER BY name")->fetchAll();
    $plans = q("SELECT id,product_id,code,name FROM license_plans WHERE status='published' ORDER BY product_id,amount_minor,id")->fetchAll();
    $productOptions = '<option value="0">TofiDesk CMS (default)</option>';
    foreach ($products as $product) $productOptions .= '<option value="'.(int)$product['id'].'">'.esc($product['name'].' · '.$product['kind'].' · '.$product['slug']).'</option>';
    $planOptions = '<option value="0">No plan — use custom domain limit</option>';
    foreach ($plans as $plan) $planOptions .= '<option value="'.(int)$plan['id'].'">'.esc($plan['name'].' · '.$plan['code']).'</option>';
    $replacement = '<label>Product<select name="product_id">'.$productOptions.'</select></label><label>Licence plan<select name="plan_id">'.$planOptions.'</select></label>';
    return str_replace('<h2>Create licence</h2>', '<h2>Create licence</h2>'.$replacement, $html);
});

// Product-centred replacement for the old /licenses POST handler. The legacy
// GET page remains available for existing operators, but new manual keys must
// point to a first-class product and optional published plan.
if (($path ?? '') !== '/licenses' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

require_csrf();
$productId = (int)($_POST['product_id'] ?? 0);
$planId = (int)($_POST['plan_id'] ?? 0);
$themeId = (int)($_POST['theme_id'] ?? 0) ?: null;
$limit = max(1, min(500, (int)($_POST['domain_limit'] ?? 1)));

$product = $productId > 0
    ? one("SELECT id,slug,kind FROM products WHERE id=? AND status='published'", [$productId])
    : one("SELECT id,slug,kind FROM products WHERE slug='tofidesk-cms' AND status='published' LIMIT 1");
if (!$product) throw new RuntimeException('Choose a published product before issuing a licence.');

$plan = null;
if ($planId > 0) {
    $plan = one("SELECT * FROM license_plans WHERE id=? AND product_id=? AND status='published'", [$planId, $product['id']]);
    if (!$plan) throw new RuntimeException('The selected licence plan does not belong to the selected product.');
    $limit = (int)$plan['domain_limit'];
}
$pending = 'pending-'.bin2hex(random_bytes(8));
q('INSERT INTO licenses(product,product_id,plan_id,key_hash,key_hint,status,domain_limit,theme_id,source) VALUES(?,?,?,?,?,?,?,?,?)', [
    $product['slug'], $product['id'], $plan['id'] ?? null, 'pending', $pending, 'active', $limit, $themeId, 'manual'
]);
[$id, $key, $secret] = issue_key();
q('UPDATE licenses SET key_hash=?,key_hint=? WHERE id=?', [password_hash($secret, PASSWORD_DEFAULT), 'TDL-'.$id, $id]);
$_SESSION['issued_key'] = $key;
header('Location: ./licenses');
exit;
