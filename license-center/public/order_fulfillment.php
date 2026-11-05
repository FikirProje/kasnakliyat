<?php
declare(strict_types=1);

/**
 * Complete a paid order exactly once. Payment providers and manual review use
 * this same entitlement boundary: invoice, licence, plan rights and download.
 */
/**
 * Resolve the package belonging to the purchased product. Product-centre
 * orders must never use the old global PRODUCT_DOWNLOAD_URL: that value is
 * only retained for pre-catalogue legacy orders.
 */
function fulfillment_release(array $order): ?array
{
    $productId = (int)($order['product_id'] ?? 0);
    if ($productId <= 0) {
        $url = trim(lc_env('PRODUCT_DOWNLOAD_URL'));
        return $url !== '' && filter_var($url, FILTER_VALIDATE_URL)
            ? ['url' => $url, 'version' => (string)lc_env('PRODUCT_VERSION', '1.0.0'), 'filename' => (string)lc_env('PRODUCT_FILENAME', 'tofidesk-package.zip')]
            : null;
    }

    try {
        $product = one('SELECT id,kind,status FROM products WHERE id=?', [$productId]);
    } catch (Throwable $e) {
        // Older installations may not have the catalogue tables yet; keep
        // their legacy fulfillment path working during a schema migration.
        $url = trim(lc_env('PRODUCT_DOWNLOAD_URL'));
        return $url !== '' && filter_var($url, FILTER_VALIDATE_URL)
            ? ['url' => $url, 'version' => (string)lc_env('PRODUCT_VERSION', '1.0.0'), 'filename' => (string)lc_env('PRODUCT_FILENAME', 'tofidesk-package.zip')]
            : null;
    }
    if (!$product || (string)$product['status'] !== 'published') throw new RuntimeException('The purchased product is not published.');

    $kind = (string)$product['kind'];
    if ($kind === 'theme') {
        $release = one("SELECT id,version,package_name FROM theme_packages WHERE product_id=? AND status='published' ORDER BY created_at DESC,id DESC LIMIT 1", [$productId]);
        $releaseKind = 'theme';
    } else {
        $release = one("SELECT id,version,package_name FROM application_releases WHERE product_id=? AND channel='stable' AND status='published' ORDER BY created_at DESC,id DESC LIMIT 1", [$productId]);
        $releaseKind = $kind === 'addon' ? 'addon' : 'application';
    }
    if (!$release) throw new RuntimeException('No published release is available for the purchased product.');

    $base = rtrim(lc_env('APP_URL', 'https://product.tofidesk.com'), '/');
    $url = $base.'/account/download?license_id={LICENSE_ID}&release_id='.(int)$release['id'].'&release_kind='.rawurlencode($releaseKind);
    return ['url' => $url, 'release_id' => (int)$release['id'], 'release_kind' => $releaseKind, 'version' => (string)$release['version'], 'filename' => basename((string)$release['package_name'])];
}

function fulfill_paid_order(array $order, string $provider, string $eventKey): ?string
{
    $buyer = one('SELECT name,email FROM buyers WHERE id=?', [(int)$order['buyer_id']]);
    if (!$buyer) throw new RuntimeException('Buyer record not found for order.');

    if (!one('SELECT id FROM invoices WHERE order_id=?', [(int)$order['id']])) {
        q('INSERT INTO invoices(order_id,invoice_number,customer_name,customer_email,currency,subtotal_minor,tax_amount_minor,total_minor) VALUES(?,?,?,?,?,?,?,?)', [
            $order['id'], 'INV-'.date('Y').'-'.$order['id'], $buyer['name'], $buyer['email'], $order['currency'],
            max(0, (int)$order['amount_minor'] - (int)$order['tax_amount_minor']), (int)$order['tax_amount_minor'], (int)$order['amount_minor'],
        ]);
    }

    $existing = one('SELECT id FROM licenses WHERE order_id=? LIMIT 1', [(int)$order['id']]);
    $licenseId = $existing ? (int)$existing['id'] : 0;
    $issuedKey = null;
    if (!$existing) {
        $purchaseHash = hash('sha256', $provider.'|'.$eventKey);
        q('INSERT INTO licenses(product,key_hash,key_hint,status,domain_limit,source,purchase_code_hash,buyer_id,order_id) VALUES(?,?,?,?,?,?,?,?,?)', [
            $order['product'], 'pending', 'PAY-'.$order['id'], 'active', 1, $provider, $purchaseHash, $order['buyer_id'], $order['id'],
        ]);
        [$licenseId, $issuedKey, $licenseSecret] = issue_key();
        q('UPDATE licenses SET key_hash=?,key_hint=? WHERE id=?', [password_hash($licenseSecret, PASSWORD_DEFAULT), 'TDL-'.$licenseId, $licenseId]);
        if (array_key_exists('product_id', $order)) q('UPDATE licenses SET product_id=?,plan_id=? WHERE id=?', [$order['product_id'] ?: null, $order['plan_id'] ?: null, $licenseId]);
        if (!empty($order['plan_id'])) {
            $plan = one("SELECT * FROM license_plans WHERE id=? AND status='published'", [(int)$order['plan_id']]);
            if ($plan) {
                $support = (int)$plan['support_days'] > 0 ? date('Y-m-d H:i:s', strtotime('+'.(int)$plan['support_days'].' days')) : null;
                $updates = (int)$plan['update_days'] > 0 ? date('Y-m-d H:i:s', strtotime('+'.(int)$plan['update_days'].' days')) : null;
                q('UPDATE licenses SET domain_limit=?,support_until=?,updates_until=? WHERE id=?', [(int)$plan['domain_limit'], $support, $updates, $licenseId]);
                foreach (['domain_limit'=>(string)$plan['domain_limit'],'support_days'=>(string)$plan['support_days'],'update_days'=>(string)$plan['update_days'],'billing_period'=>(string)$plan['billing_period']] as $entitlement=>$value) {
                    q('INSERT OR REPLACE INTO license_entitlements(license_id,entitlement,value,expires_at) VALUES(?,?,?,?)', [$licenseId, $entitlement, $value, null]);
                }
            }
        }
        if (!empty($order['product_id'])) {
            // Bundle tables are added by the catalogue migration. During a
            // rolling upgrade an older installation may not have them yet;
            // the purchased product and plan must still fulfil safely.
            try {
                $bundle = one("SELECT id FROM product_bundles WHERE product_id=? AND status='published' LIMIT 1", [(int)$order['product_id']]);
            } catch (Throwable $e) {
                $bundle = null;
            }
            if ($bundle) foreach (rows('SELECT p.id,p.slug FROM product_bundle_items i JOIN products p ON p.id=i.product_id AND p.status=\'published\' WHERE i.bundle_id=? ORDER BY i.sort_order,i.product_id', [(int)$bundle['id']]) as $component) {
                q('INSERT OR REPLACE INTO license_entitlements(license_id,entitlement,value,expires_at) VALUES(?,?,?,?)', [$licenseId, 'bundle_component_'.(int)$component['id'], (string)$component['slug'], null]);
            }
        }
    }

    $release = fulfillment_release($order);
    if ($release && !empty($release['url']) && !one('SELECT id FROM downloads WHERE order_id=? LIMIT 1', [(int)$order['id']])) {
        $downloadUrl = str_replace('{LICENSE_ID}', (string)$licenseId, (string)$release['url']);
        q('INSERT INTO downloads(buyer_id,product,product_id,version,filename,url,order_id) VALUES(?,?,?,?,?,?,?)', [
            $order['buyer_id'], $order['product'], $order['product_id'] ?? null, (string)$release['version'],
            (string)$release['filename'], $downloadUrl, $order['id'],
        ]);
    }
    return $issuedKey;
}
