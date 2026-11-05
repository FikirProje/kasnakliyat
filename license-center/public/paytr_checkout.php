<?php
declare(strict_types=1);

// PayTR hosted full-page checkout. Credentials stay in the private environment.
if (($path ?? '') === '/payment/success') { buyer_required(); buyer_layout('Payment received', '<div class="card"><h1>Payment received</h1><p>Your payment was sent to PayTR. The order will appear as paid after the signed merchant callback is received.</p><a class="button" href="./account">Open account</a></div>'); }
if (($path ?? '') === '/payment/failed') { buyer_required(); buyer_layout('Payment not completed', '<div class="card"><h1>Payment not completed</h1><p>No licence or download was issued. You can try again from pricing.</p><a class="button" href="./pricing">Back to pricing</a></div>'); }
if (($path ?? '') !== '/checkout') return;

/** Resolve the newest administrator-recorded rate, with environment values as
 * a safe fallback for a fresh install. Rates are quote units per base unit. */
function checkout_currency_rate(string $base, string $quote): float
{
    $base = strtoupper($base); $quote = strtoupper($quote);
    if ($base === $quote) return 1.0;
    $direct = one('SELECT rate FROM currency_rates WHERE base_currency=? AND quote_currency=? ORDER BY observed_at DESC,id DESC LIMIT 1', [$base,$quote]);
    if ($direct && (float)$direct['rate'] > 0) return (float)$direct['rate'];
    $inverse = one('SELECT rate FROM currency_rates WHERE base_currency=? AND quote_currency=? ORDER BY observed_at DESC,id DESC LIMIT 1', [$quote,$base]);
    if ($inverse && (float)$inverse['rate'] > 0) return 1 / (float)$inverse['rate'];
    $env = ['TL'=>(float)lc_env('PAYTR_RATE_TL','35'), 'USD'=>(float)lc_env('PAYTR_RATE_USD','1.08'), 'EUR'=>1.0, 'GBP'=>(float)lc_env('PAYTR_RATE_GBP','0.86'), 'RUB'=>(float)lc_env('PAYTR_RATE_RUB','100')];
    if ($base === 'EUR' && isset($env[$quote]) && $env[$quote] > 0) return $env[$quote];
    if ($quote === 'EUR' && isset($env[$base]) && $env[$base] > 0) return 1 / $env[$base];
    if (isset($env[$base], $env[$quote]) && $env[$base] > 0) return $env[$quote] / $env[$base];
    return 1.0;
}

function checkout_tax_breakdown(int $subtotal, int $discount, ?array $profile): array
{
    $taxable = max(0, $subtotal - $discount);
    if (!$profile) return ['taxable_minor'=>$taxable, 'tax_minor'=>0, 'total_minor'=>max(1, $taxable)];
    $basisPoints = max(0, (int)($profile['rate_basis_points'] ?? 0));
    if ((int)($profile['inclusive'] ?? 0) === 1) {
        $tax = $basisPoints > 0 ? (int)round($taxable * $basisPoints / (10000 + $basisPoints)) : 0;
        return ['taxable_minor'=>$taxable, 'tax_minor'=>$tax, 'total_minor'=>max(1, $taxable)];
    }
    $tax = (int)round($taxable * $basisPoints / 10000);
    return ['taxable_minor'=>$taxable, 'tax_minor'=>$tax, 'total_minor'=>max(1, $taxable + $tax)];
}

$buyer = buyer_required();
$plans = ['Starter'=>[14900,1], 'Studio'=>[29900,3], 'Agency'=>[59900,10]];
$requestedPlan = trim((string)($_GET['plan'] ?? $_POST['plan'] ?? 'Starter'));
$plan = isset($plans[$requestedPlan]) ? $requestedPlan : 'Starter';
[$baseAmount, $domainLimit] = $plans[$plan];
$productSlug = strtolower(trim((string)($_POST['product'] ?? $_GET['product'] ?? '')));
$productSlug = $productSlug !== '' ? $productSlug : 'tofidesk-cms';
$product = $productSlug !== '' ? one("SELECT * FROM products WHERE slug=? AND status='published'", [$productSlug]) : null;
$productId = $product ? (int)$product['id'] : null;
$planId = null;
$productLabel = 'TofiDesk '.$plan;
$productBaseCurrency = 'EUR';
$bundleItems = [];
if ($product) {
    $releaseRows = $product['kind'] === 'theme'
        ? q("SELECT storage_path,sha256,signature,theme_id,version FROM theme_packages WHERE product_id=? AND status='published'", [$productId])->fetchAll()
        : q("SELECT package_path,sha256,signature,manifest_json,version FROM application_releases WHERE product_id=? AND channel='stable' AND status='published'", [$productId])->fetchAll();
    $releaseReady = false;
    foreach ($releaseRows as $releaseRow) {
        $packagePath = (string)($releaseRow['storage_path'] ?? $releaseRow['package_path'] ?? '');
        $hash = strtolower((string)($releaseRow['sha256'] ?? ''));
        if (is_file($packagePath) && preg_match('/^[a-f0-9]{64}$/', $hash) && hash_equals($hash, strtolower((string)hash_file('sha256', $packagePath))) && release_package_signature_valid($releaseRow, $product['kind'] === 'theme' ? 'theme' : 'application')) { $releaseReady = true; break; }
    }
    if (!$releaseReady) buyer_layout('Product release pending', '<div class="card"><h1>Product release pending</h1><p class="muted">This product is visible for preview, but checkout opens after its signed release package passes publication checks.</p><a class="button" href="./shop">Back to marketplace</a></div>');
    $productPrice = one("SELECT amount_minor,currency FROM product_prices WHERE product_id=? AND active=1 ORDER BY CASE WHEN currency='EUR' THEN 0 ELSE 1 END,id LIMIT 1", [$productId]);
    if (!$productPrice) throw new RuntimeException('This product is not available for purchase yet.');
    $baseAmount = (int)$productPrice['amount_minor'];
    $productBaseCurrency = strtoupper((string)$productPrice['currency']);
    $productLabel = (string)$product['name'];
    $productPlan = one("SELECT id,code,name,domain_limit,amount_minor,currency FROM license_plans WHERE product_id=? AND code=? AND status='published' LIMIT 1", [$productId, strtolower($requestedPlan)]);
    if (!$productPlan && strtolower($requestedPlan) !== strtolower($plan)) $productPlan = one("SELECT id,code,name,domain_limit,amount_minor,currency FROM license_plans WHERE product_id=? AND code=? AND status='published' LIMIT 1", [$productId, strtolower($plan)]);
    if (!$productPlan) $productPlan = one("SELECT id,code,name,domain_limit,amount_minor,currency FROM license_plans WHERE product_id=? AND status='published' ORDER BY amount_minor,id LIMIT 1", [$productId]);
    if ($productPlan) {
        $plan = (string)$productPlan['code'];
        $planId = (int)$productPlan['id'];
        $domainLimit = (int)$productPlan['domain_limit'];
        $baseAmount = (int)$productPlan['amount_minor'];
        $productBaseCurrency = strtoupper((string)$productPlan['currency']);
        $productLabel .= ' · '.(function_exists('lc_plan_name') ? lc_plan_name($productPlan, function_exists('lc_public_locale') ? lc_public_locale() : 'en') : (string)$productPlan['name']);
    }
    $bundle = one("SELECT id,discount_percent FROM product_bundles WHERE product_id=? AND status='published' LIMIT 1", [$productId]);
    if ($bundle) {
        $bundleItems = q("SELECT p.id,p.slug,p.name,p.kind FROM product_bundle_items i JOIN products p ON p.id=i.product_id AND p.status='published' WHERE i.bundle_id=? ORDER BY i.sort_order,i.product_id", [$bundle['id']])->fetchAll();
        if (count($bundleItems) < 2) throw new RuntimeException('This bundle is not sale-ready: at least two published components are required.');
    }
}
$locale = function_exists('lc_public_locale') ? lc_public_locale() : 'en';
$defaultCurrency = strtoupper(lc_env('PAYTR_DEFAULT_CURRENCY', $locale === 'tr' ? 'TL' : ($locale === 'en' ? 'USD' : 'EUR')));
$currency = strtoupper((string)($_POST['currency'] ?? $_GET['currency'] ?? $defaultCurrency));
if ($currency === 'TRY') $currency = 'TL';
$currencies = ['TL'=>'₺','USD'=>'$','EUR'=>'€','GBP'=>'£','RUB'=>'₽'];
if (!isset($currencies[$currency])) $currency = 'USD';
$directPrice = $product ? one("SELECT amount_minor,currency FROM product_prices WHERE product_id=? AND currency=? AND active=1 LIMIT 1", [$productId, $currency]) : null;
if ($directPrice) {
    $baseAmount = (int)$directPrice['amount_minor'];
    $productBaseCurrency = strtoupper((string)$directPrice['currency']);
}
$countryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['country_code'] ?? $_GET['country_code'] ?? '')), 0, 2));
$taxNumber = trim((string)($_POST['tax_number'] ?? $_GET['tax_number'] ?? ''));
$couponCode = strtoupper(trim((string)($_POST['coupon'] ?? $_GET['coupon'] ?? '')));
$subtotal = $directPrice ? max(1, $baseAmount) : max(1, (int)round($baseAmount * checkout_currency_rate($productBaseCurrency, $currency)));
$taxAmount = 0;
$taxProfile = $countryCode !== '' ? one('SELECT rate_basis_points,inclusive FROM tax_profiles WHERE country_code=? AND region=? AND active=1 ORDER BY id DESC LIMIT 1', [$countryCode, '']) : null;
if ($taxProfile) $taxAmount = (int)round($subtotal * ((int)$taxProfile['rate_basis_points']) / 10000);
$discountAmount = 0;
$coupon = $couponCode !== '' ? one("SELECT * FROM coupons WHERE code=? AND active=1 AND (starts_at IS NULL OR starts_at<=CURRENT_TIMESTAMP) AND (ends_at IS NULL OR ends_at>=CURRENT_TIMESTAMP) AND (usage_limit=0 OR used_count<usage_limit)", [$couponCode]) : null;
if ($coupon) {
    if ($coupon['discount_type'] === 'percent') $discountAmount = (int)round($subtotal * min(100, (int)$coupon['discount_value']) / 100);
    elseif ($coupon['currency'] === '' || $coupon['currency'] === $currency) $discountAmount = min($subtotal, (int)$coupon['discount_value']);
}
$taxBreakdown = checkout_tax_breakdown($subtotal, $discountAmount, $taxProfile);
$taxAmount = $taxBreakdown['tax_minor'];
$amount = $taxBreakdown['total_minor'];
$currencySymbol = $currencies[$currency];
$merchantId = trim(lc_env('PAYTR_MERCHANT_ID'));
$merchantKey = trim(lc_env('PAYTR_MERCHANT_KEY'));
$merchantSalt = trim(lc_env('PAYTR_MERCHANT_SALT'));

if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
    buyer_layout('PayTR checkout unavailable', '<div class="card"><h1>Payment is being configured</h1><p class="muted">PayTR is not configured yet. Your account is ready, but no order is created until the payment provider is enabled.</p><a class="button" href="./pricing">Back to pricing</a></div>');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $options='';foreach($currencies as $code=>$symbol)$options.='<option value="'.esc($code).'"'.($currency===$code?' selected':'').'>'.esc($code).' ('.esc($symbol).')</option>';
    buyer_layout('PayTR checkout', '<div class="card"><h1>Checkout · '.esc($productLabel).'</h1><p>Subtotal: <strong>'.esc($currencySymbol).number_format($subtotal / 100, 2, ',', '.').' '.esc($currency).'</strong> · Tax: '.esc($currencySymbol).number_format($taxAmount / 100, 2, ',', '.').' · Discount: '.esc($currencySymbol).number_format($discountAmount / 100, 2, ',', '.').'</p><p>Total: <strong>'.esc($currencySymbol).number_format($amount / 100, 2, ',', '.').' '.esc($currency).'</strong> · Domain limit: '.(int)$domainLimit.'</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="plan" value="'.esc($plan).'"/><input type="hidden" name="product" value="'.esc($productSlug).'"/><label>Payment currency<select name="currency" onchange="this.form.submit()">'.$options.'</select></label><label>Country code<input name="country_code" value="'.esc($countryCode).'" maxlength="2" placeholder="TR" required></label><label>Tax/VAT number<input name="tax_number" value="'.esc($taxNumber).'" maxlength="80"></label><label>Coupon code<input name="coupon" value="'.esc($couponCode).'" maxlength="40"></label><button>Continue to PayTR</button></form></div>');
}

require_csrf();
$reference = 'TD'.date('YmdHis').bin2hex(random_bytes(5));
$amountMinor = (string)$amount;
$basket = base64_encode(json_encode([[$productLabel, number_format($amount / 100, 2, '.', ''), 1]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$userIp = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '127.0.0.1';
$email = preg_replace('/[^a-zA-Z0-9@._+\-]/', '', (string)$buyer['email']);
$testMode = lc_env('PAYTR_TEST_MODE', '1') === '1' ? '1' : '0';
$noInstallment = lc_env('PAYTR_NO_INSTALLMENT', '0') === '1' ? '1' : '0';
$maxInstallment = preg_replace('/[^0-9]/', '', lc_env('PAYTR_MAX_INSTALLMENT', '0')) ?: '0';
$configuredCurrency = strtoupper(trim(lc_env('PAYTR_CURRENCY', '')));
if ($configuredCurrency !== '' && lc_env('PAYTR_ALLOW_CURRENCY_SELECTION', '1') !== '1' && isset($currencies[$configuredCurrency])) $currency = $configuredCurrency;
$userName = trim((string)($buyer['name'] ?: 'TofiDesk customer'));
$hashString = $merchantId.$userIp.$reference.$email.$amountMinor.$basket.$noInstallment.$maxInstallment.$currency.$testMode;
$token = base64_encode(hash_hmac('sha256', $hashString.$merchantSalt, $merchantKey, true));
$post = [
    'merchant_id'=>$merchantId, 'user_ip'=>$userIp, 'merchant_oid'=>$reference,
    'email'=>$email, 'payment_amount'=>$amountMinor, 'paytr_token'=>$token,
    'user_basket'=>$basket, 'debug_on'=>'0', 'no_installment'=>$noInstallment,
    'max_installment'=>$maxInstallment, 'user_name'=>$userName,
    'user_address'=>substr(trim(lc_env('PAYTR_DEFAULT_ADDRESS', '')), 0, 400),
    'user_phone'=>preg_replace('/[^0-9+]/', '', lc_env('PAYTR_DEFAULT_PHONE', '')),
    'merchant_ok_url'=>rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/payment/success?reference='.rawurlencode($reference),
    'merchant_fail_url'=>rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/').'/payment/failed?reference='.rawurlencode($reference),
    'timeout_limit'=>30, 'currency'=>$currency, 'test_mode'=>$testMode, 'lang'=>lc_env('PAYTR_LANG', 'tr')
];
$ch = curl_init('https://www.paytr.com/odeme/api/get-token');
curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post), CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>20, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
$raw = curl_exec($ch); $curlError = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($raw === false || $curlError !== '' || $status !== 200) throw new RuntimeException('PayTR token request failed.');
$response = json_decode((string)$raw, true);
if (!is_array($response) || ($response['status'] ?? '') !== 'success' || empty($response['token'])) throw new RuntimeException('PayTR rejected the checkout request.');
$orderStmt = q('INSERT INTO orders(buyer_id,reference,product,product_id,plan_id,amount_minor,tax_amount_minor,currency,status,provider) VALUES(?,?,?,?,?,?,?,?,?,?)', [$buyer['id'],$reference,$productLabel,$productId,$planId,$amount,$taxAmount,$currency,'pending','paytr']);
$orderId = (int)lc_db()->lastInsertId();
q('INSERT INTO order_items(order_id,product_id,plan_id,product_name,quantity,unit_amount_minor,subtotal_minor,currency,metadata_json) VALUES(?,?,?,?,?,?,?,?,?)', [$orderId,$productId,$planId,$productLabel,1,$subtotal,$subtotal,$currency,json_encode(['tax_minor'=>$taxAmount,'discount_minor'=>$discountAmount,'coupon'=>$couponCode], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
foreach ($bundleItems as $bundleItem) q('INSERT INTO order_items(order_id,product_id,product_name,quantity,unit_amount_minor,subtotal_minor,currency,metadata_json) VALUES(?,?,?,?,?,?,?,?)', [$orderId,(int)$bundleItem['id'],(string)$bundleItem['name'],1,0,0,$currency,json_encode(['bundle_component'=>true,'bundle_product_id'=>$productId,'kind'=>$bundleItem['kind']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
q('INSERT INTO billing_addresses(buyer_id,name,country_code,address_line,tax_number,is_default) VALUES(?,?,?,?,?,0)', [$buyer['id'], (string)$buyer['name'], $countryCode, 'Provided during checkout', $taxNumber]);
if ($taxAmount > 0) q('INSERT INTO order_adjustments(order_id,kind,label,amount_minor,currency) VALUES(?,?,?,?,?)', [$orderId,'tax','Tax/VAT',$taxAmount,$currency]);
if ($discountAmount > 0 && $coupon) { q('INSERT INTO order_adjustments(order_id,kind,code,label,amount_minor,currency) VALUES(?,?,?,?,?,?)', [$orderId,'discount',$couponCode,'Coupon discount',-$discountAmount,$currency]); q('INSERT INTO coupon_redemptions(coupon_id,buyer_id,order_id) VALUES(?,?,?)', [$coupon['id'],$buyer['id'],$orderId]); q('UPDATE coupons SET used_count=used_count+1 WHERE id=?', [$coupon['id']]); }
$paymentUrl = 'https://www.paytr.com/odeme/guvenli/'.rawurlencode((string)$response['token']);
// Keep the customer flow provider-hosted but full-page. Card data never enters
// this application and the storefront does not embed a third-party iframe.
header('Location: '.$paymentUrl, true, 303);
exit;
