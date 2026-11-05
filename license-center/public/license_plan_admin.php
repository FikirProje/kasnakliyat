<?php
declare(strict_types=1);

function license_plan_admin_route(): void
{
    global $path;
    if ($path !== '/licenses') return;
    login_required();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'create_license');
        if ($action === 'create_plan') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $product = one('SELECT id FROM products WHERE id=? AND status<>?', [$productId, 'archived']);
            $code = strtolower(trim((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['plan_name'] ?? ''));
            $licenseType = (string)($_POST['license_type'] ?? 'single_site');
            $period = (string)($_POST['billing_period'] ?? 'lifetime');
            $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));
            if (!$product || !preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $code) || $name === '' || !in_array($licenseType, ['single_site','multi_site','unlimited','subscription','lifetime'], true) || !in_array($period, ['lifetime','monthly','yearly'], true) || !preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new RuntimeException('Plan details are invalid.');
            }
            q('INSERT INTO license_plans(product_id,code,name,license_type,billing_period,duration_days,domain_limit,support_days,update_days,currency,amount_minor,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
                $productId, $code, $name, $licenseType, $period, max(0, (int)($_POST['duration_days'] ?? 0)), max(1, min(500, (int)($_POST['domain_limit'] ?? 1))), max(0, (int)($_POST['support_days'] ?? 365)), max(0, (int)($_POST['update_days'] ?? 365)), $currency, max(0, (int)($_POST['amount_minor'] ?? 0)), 'published'
            ]);
            $planId = (int)lc_db()->lastInsertId();
            $locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
            foreach ($locales as $locale) {
                $localizedName = trim((string)($_POST['plan_name_'.$locale] ?? ''));
                $localizedDescription = trim((string)($_POST['plan_description_'.$locale] ?? ''));
                if ($localizedName !== '') q('INSERT INTO license_plan_translations(plan_id,locale,name,description,status) VALUES(?,?,?,?,?)', [$planId, $locale, $localizedName, $localizedDescription, 'published']);
            }
            header('Location: ./licenses?plan_saved=1'); exit;
        }

        if ($action === 'update_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $plan = $planId > 0 ? one('SELECT * FROM license_plans WHERE id=? AND status<>?', [$planId, 'archived']) : null;
            $name = trim((string)($_POST['plan_name'] ?? ''));
            $licenseType = (string)($_POST['license_type'] ?? 'single_site');
            $period = (string)($_POST['billing_period'] ?? 'lifetime');
            $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));
            $status = (string)($_POST['status'] ?? 'published');
            if (!$plan || $name === '' || !in_array($licenseType, ['single_site','multi_site','unlimited','subscription','lifetime'], true) || !in_array($period, ['lifetime','monthly','yearly'], true) || !in_array($status, ['draft','published','archived'], true) || !preg_match('/^[A-Z]{3}$/', $currency)) throw new RuntimeException('Plan update details are invalid.');
            q('UPDATE license_plans SET name=?,license_type=?,billing_period=?,duration_days=?,domain_limit=?,support_days=?,update_days=?,currency=?,amount_minor=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?', [$name,$licenseType,$period,max(0,(int)($_POST['duration_days'] ?? 0)),max(1,min(500,(int)($_POST['domain_limit'] ?? 1))),max(0,(int)($_POST['support_days'] ?? 365)),max(0,(int)($_POST['update_days'] ?? 365)),$currency,max(0,(int)($_POST['amount_minor'] ?? 0)),$status,$planId]);
            foreach (['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'] as $locale) {
                $localizedName = trim((string)($_POST['plan_name_'.$locale] ?? ''));
                $localizedDescription = trim((string)($_POST['plan_description_'.$locale] ?? ''));
                if ($localizedName === '') q('DELETE FROM license_plan_translations WHERE plan_id=? AND locale=?', [$planId,$locale]);
                else q('INSERT INTO license_plan_translations(plan_id,locale,name,description,status) VALUES(?,?,?,?,?) ON CONFLICT(plan_id,locale) DO UPDATE SET name=excluded.name,description=excluded.description,status=excluded.status,updated_at=CURRENT_TIMESTAMP', [$planId,$locale,$localizedName,$localizedDescription,'published']);
            }
            header('Location: ./licenses?plan_updated=1'); exit;
        }

        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = $planId > 0 ? one('SELECT p.*,pr.name product_name FROM license_plans p JOIN products pr ON pr.id=p.product_id WHERE p.id=? AND p.status=?', [$planId, 'published']) : null;
        $productId = (int)($_POST['product_id'] ?? 0);
        $product = $productId > 0 ? one('SELECT * FROM products WHERE id=? AND status=?', [$productId, 'published']) : null;
        if ($plan) $product = one('SELECT * FROM products WHERE id=?', [$plan['product_id']]);
        if (!$product) throw new RuntimeException('Choose a published product or a published licence plan.');
        $limit = $plan ? (int)$plan['domain_limit'] : max(1, min(50, (int)($_POST['domain_limit'] ?? 1)));
        $productName = $plan ? (string)$plan['product_name'].' · '.(string)$plan['name'] : (string)$product['name'];
        q('INSERT INTO licenses(product,product_id,plan_id,key_hash,key_hint,status,domain_limit,theme_id,source) VALUES(?,?,?,?,?,?,?,?,?)', [$productName, $product['id'], $plan['id'] ?? null, 'pending', 'pending-'.bin2hex(random_bytes(8)), 'active', $limit, null, 'manual']);
        [$id, $key, $secret] = issue_key();
        q('UPDATE licenses SET key_hash=?,key_hint=?,support_until=?,updates_until=? WHERE id=?', [password_hash($secret, PASSWORD_DEFAULT), 'TDL-'.$id, $plan && (int)$plan['support_days'] > 0 ? date('Y-m-d H:i:s', strtotime('+'.(int)$plan['support_days'].' days')) : null, $plan && (int)$plan['update_days'] > 0 ? date('Y-m-d H:i:s', strtotime('+'.(int)$plan['update_days'].' days')) : null, $id]);
        if ($plan) {
            foreach (['license_type'=>(string)$plan['license_type'], 'domain_limit'=>(string)$plan['domain_limit'], 'support_days'=>(string)$plan['support_days'], 'update_days'=>(string)$plan['update_days'], 'billing_period'=>(string)$plan['billing_period']] as $entitlement => $value) q('INSERT OR REPLACE INTO license_entitlements(license_id,entitlement,value,expires_at) VALUES(?,?,?,?,?)', [$id, $entitlement, $value, null]);
        }
        $_SESSION['issued_key'] = $key;
        header('Location: ./licenses'); exit;
    }

    $products = q("SELECT id,name,kind FROM products WHERE status='published' ORDER BY name")->fetchAll();
    $productOptions = '<option value="">Choose product</option>';
    foreach ($products as $product) $productOptions .= '<option value="'.(int)$product['id'].'">'.esc($product['name']).' · '.esc($product['kind']).'</option>';
    $plans = q("SELECT l.*,p.name product_name FROM license_plans l JOIN products p ON p.id=l.product_id WHERE l.status='published' ORDER BY p.name,l.amount_minor")->fetchAll();
    $planOptions = '<option value="">Manual product licence</option>';
    foreach ($plans as $plan) $planOptions .= '<option value="'.(int)$plan['id'].'">'.esc($plan['product_name'].' · '.$plan['name'].' · '.strtoupper($plan['currency']).' '.number_format(((int)$plan['amount_minor'])/100,2)).'</option>';
    $planRows = '';
    foreach ($plans as $plan) $planRows .= '<tr><td>'.esc($plan['product_name']).'</td><td>'.esc($plan['name']).'<br><small>'.esc($plan['code']).'</small></td><td>'.esc($plan['license_type']).'<br><small>'.esc($plan['billing_period']).'</small></td><td>'.(int)$plan['domain_limit'].'</td><td>'.esc(strtoupper($plan['currency'])).' '.number_format(((int)$plan['amount_minor'])/100,2).'</td></tr>';
    $planEditForms = '';
    foreach ($plans as $plan) {
        $translationRows = [];
        foreach (q('SELECT locale,name,description FROM license_plan_translations WHERE plan_id=? ORDER BY locale', [(int)$plan['id']])->fetchAll() as $translation) $translationRows[(string)$translation['locale']] = $translation;
        $planEditForms .= '<details class="card"><summary>'.esc($plan['product_name'].' · '.$plan['name'].' ('.$plan['code'].')').' — edit</summary><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"> <input type="hidden" name="action" value="update_plan"><input type="hidden" name="plan_id" value="'.(int)$plan['id'].'"><label>Plan name<input name="plan_name" value="'.esc($plan['name']).'" required></label><label>Amount (minor units)<input type="number" name="amount_minor" value="'.(int)$plan['amount_minor'].'" min="0"></label><label>Currency<input name="currency" value="'.esc($plan['currency']).'" maxlength="3"></label><label>Billing period<select name="billing_period"><option value="lifetime"'.($plan['billing_period']==='lifetime'?' selected':'').'>Lifetime</option><option value="yearly"'.($plan['billing_period']==='yearly'?' selected':'').'>Yearly</option><option value="monthly"'.($plan['billing_period']==='monthly'?' selected':'').'>Monthly</option></select></label><label>Licence type<select name="license_type">'.implode('', array_map(static fn(string $value): string => '<option value="'.esc($value).'"'.($value===$plan['license_type']?' selected':'').'>'.esc($value).'</option>', ['single_site','multi_site','unlimited','subscription','lifetime'])).'</select></label><label>Domain limit<input type="number" name="domain_limit" value="'.(int)$plan['domain_limit'].'" min="1" max="500"></label><label>Support days<input type="number" name="support_days" value="'.(int)$plan['support_days'].'" min="0"></label><label>Update days<input type="number" name="update_days" value="'.(int)$plan['update_days'].'" min="0"></label><label>Status<select name="status"><option value="published"'.($plan['status']==='published'?' selected':'').'>Published</option><option value="draft"'.($plan['status']==='draft'?' selected':'').'>Draft</option><option value="archived"'.($plan['status']==='archived'?' selected':'').'>Archived</option></select></label><h3>14-language plan content</h3>';
        foreach (['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'] as $locale) { $translation = $translationRows[$locale] ?? ['name'=>'','description'=>'']; $planEditForms .= '<label>'.strtoupper($locale).' name<input name="plan_name_'.esc($locale).'" value="'.esc((string)$translation['name']).'" maxlength="180"></label><label>'.strtoupper($locale).' description<textarea name="plan_description_'.esc($locale).'" maxlength="1000">'.esc((string)$translation['description']).'</textarea></label>'; }
        $planEditForms .= '<button>Save plan and translations</button></form></details>';
    }
    if ($planEditForms !== '') $planRows .= '<tr><td colspan="5">'.$planEditForms.'</td></tr>';
    $licenseRows = '';
    foreach (q('SELECT l.*,group_concat(d.domain, ", ") domains,p.name product_name,lp.name plan_name FROM licenses l LEFT JOIN domains d ON d.license_id=l.id LEFT JOIN products p ON p.id=l.product_id LEFT JOIN license_plans lp ON lp.id=l.plan_id GROUP BY l.id ORDER BY l.id DESC LIMIT 200')->fetchAll() as $license) $licenseRows .= '<tr><td>'.esc($license['key_hint']).'</td><td>'.esc($license['product_name'] ?: $license['product']).'<br><small>'.esc($license['plan_name'] ?: 'Manual').'</small></td><td>'.esc($license['status']).'</td><td>'.esc($license['domains'] ?: '—').'</td><td>'.(int)$license['domain_limit'].'</td></tr>';
    $notice = isset($_SESSION['issued_key']) ? '<div class="card"><strong>New licence key — show once:</strong><br><code>'.esc((string)$_SESSION['issued_key']).'</code></div>' : '';
    unset($_SESSION['issued_key']);
    $licenseTypeCopy = [
        'tr'=>['Lisans türü',['single_site'=>'Tek site','multi_site'=>'Çoklu site','unlimited'=>'Sınırsız','subscription'=>'Abonelik','lifetime'=>'Ömür boyu']],
        'en'=>['Licence type',['single_site'=>'Single site','multi_site'=>'Multi site','unlimited'=>'Unlimited','subscription'=>'Subscription','lifetime'=>'Lifetime']],
        'de'=>['Lizenztyp',['single_site'=>'Einzelne Website','multi_site'=>'Mehrere Websites','unlimited'=>'Unbegrenzt','subscription'=>'Abonnement','lifetime'=>'Lebenslang']],
        'fr'=>['Type de licence',['single_site'=>'Site unique','multi_site'=>'Sites multiples','unlimited'=>'Illimité','subscription'=>'Abonnement','lifetime'=>'À vie']],
        'es'=>['Tipo de licencia',['single_site'=>'Un solo sitio','multi_site'=>'Varios sitios','unlimited'=>'Ilimitada','subscription'=>'Suscripción','lifetime'=>'De por vida']],
        'it'=>['Tipo di licenza',['single_site'=>'Sito singolo','multi_site'=>'Siti multipli','unlimited'=>'Illimitata','subscription'=>'Abbonamento','lifetime'=>'A vita']],
        'pt'=>['Tipo de licença',['single_site'=>'Site único','multi_site'=>'Vários sites','unlimited'=>'Ilimitada','subscription'=>'Subscrição','lifetime'=>'Vitalícia']],
        'nl'=>['Licentietype',['single_site'=>'Eén site','multi_site'=>'Meerdere sites','unlimited'=>'Onbeperkt','subscription'=>'Abonnement','lifetime'=>'Levenslang']],
        'ru'=>['Тип лицензии',['single_site'=>'Один сайт','multi_site'=>'Несколько сайтов','unlimited'=>'Без ограничений','subscription'=>'Подписка','lifetime'=>'Бессрочная']],
        'ar'=>['نوع الترخيص',['single_site'=>'موقع واحد','multi_site'=>'عدة مواقع','unlimited'=>'غير محدود','subscription'=>'اشتراك','lifetime'=>'مدى الحياة']],
        'fa'=>['نوع مجوز',['single_site'=>'یک سایت','multi_site'=>'چند سایت','unlimited'=>'نامحدود','subscription'=>'اشتراکی','lifetime'=>'دائمی']],
        'zh'=>['许可证类型',['single_site'=>'单站点','multi_site'=>'多站点','unlimited'=>'不限数量','subscription'=>'订阅','lifetime'=>'永久']],
        'ja'=>['ライセンス種別',['single_site'=>'単一サイト','multi_site'=>'複数サイト','unlimited'=>'無制限','subscription'=>'サブスクリプション','lifetime'=>'買い切り']],
        'ko'=>['라이선스 유형',['single_site'=>'단일 사이트','multi_site'=>'다중 사이트','unlimited'=>'무제한','subscription'=>'구독','lifetime'=>'영구']],
    ][lc_public_locale()] ?? ['Licence type',['single_site'=>'Single site','multi_site'=>'Multi site','unlimited'=>'Unlimited','subscription'=>'Subscription','lifetime'=>'Lifetime']];
    $licenseTypeField = '<label>'.esc($licenseTypeCopy[0]).'<select name="license_type">'.implode('', array_map(static fn(string $value, string $label): string => '<option value="'.esc($value).'">'.esc($label).'</option>', array_keys($licenseTypeCopy[1]), array_values($licenseTypeCopy[1]))).'</select></label>';
    $localizedPlanFields = '<details><summary>14-language plan names</summary>';
    foreach (['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'] as $locale) $localizedPlanFields .= '<label>'.strtoupper($locale).' plan name<input name="plan_name_'.$locale.'" maxlength="180"></label><label>'.strtoupper($locale).' description<textarea name="plan_description_'.$locale.'" maxlength="1000"></textarea></label>';
    $localizedPlanFields .= '</details>';
    ob_start(static fn(string $html): string => str_replace('<label>Billing period', $licenseTypeField.'<label>Billing period', $html));
    layout('Licence plans and licences', $notice.'<div class="grid"><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="create_plan"><h2>Create licence plan</h2><label>Product<select name="product_id" required>'.$productOptions.'</select></label><label>Plan code<input name="code" placeholder="single-site" required></label><label>Plan name<input name="plan_name" placeholder="Single site" required></label>'.$localizedPlanFields.'<label>Billing period<select name="billing_period"><option value="lifetime">Lifetime</option><option value="yearly">Yearly</option><option value="monthly">Monthly</option></select></label><label>Amount (minor units)<input type="number" name="amount_minor" min="0" value="0"></label><label>Currency<input name="currency" value="EUR" maxlength="3"></label><label>Domain limit<input type="number" name="domain_limit" min="1" max="500" value="1"></label><label>Support days<input type="number" name="support_days" min="0" value="365"></label><label>Update days<input type="number" name="update_days" min="0" value="365"></label><button>Create plan</button></form><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"><h2>Issue licence</h2><label>Licence plan<select name="plan_id">'.$planOptions.'</select></label><label>Fallback product<select name="product_id">'.$productOptions.'</select></label><label>Domain limit (manual)<input type="number" name="domain_limit" min="1" max="50" value="1"></label><button>Issue licence</button></form></div><div class="card"><h2>Licence plans</h2><table><tr><th>Product</th><th>Plan</th><th>Period</th><th>Domains</th><th>Price</th></tr>'.$planRows.'</table></div><div class="card"><h2>Issued licences</h2><table><tr><th>Key</th><th>Product / plan</th><th>Status</th><th>Domains</th><th>Limit</th></tr>'.$licenseRows.'</table></div>');
}

license_plan_admin_route();
