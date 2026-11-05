<?php
declare(strict_types=1);

function commerce_admin_route(): void
{
    global $path;
    if ($path !== '/commerce') return;
    login_required();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'tax') {
            $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));
            $region = trim((string)($_POST['region'] ?? ''));
            $name = trim((string)($_POST['tax_name'] ?? 'VAT'));
            $rate = max(0, min(10000, (int)($_POST['rate_basis_points'] ?? 0)));
            if (!preg_match('/^[A-Z]{2}$/', $country) || $name === '') throw new RuntimeException('Tax profile is invalid.');
            q('INSERT INTO tax_profiles(country_code,region,tax_name,rate_basis_points,inclusive,active) VALUES(?,?,?,?,?,1) ON CONFLICT(country_code,region,tax_name) DO UPDATE SET rate_basis_points=excluded.rate_basis_points,inclusive=excluded.inclusive,active=1,updated_at=CURRENT_TIMESTAMP', [$country,$region,$name,$rate,(int)!empty($_POST['inclusive'])]);
        } elseif ($action === 'coupon') {
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $type = (string)($_POST['discount_type'] ?? 'percent');
            $value = max(0, (int)($_POST['discount_value'] ?? 0));
            $currency = strtoupper(trim((string)($_POST['currency'] ?? '')));
            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,40}$/', $code) || !in_array($type, ['percent','fixed'], true) || ($type === 'percent' && $value > 10000)) throw new RuntimeException('Coupon is invalid.');
            q('INSERT INTO coupons(code,discount_type,discount_value,currency,usage_limit,starts_at,ends_at,active) VALUES(?,?,?,?,?,?,?,1) ON CONFLICT(code) DO UPDATE SET discount_type=excluded.discount_type,discount_value=excluded.discount_value,currency=excluded.currency,usage_limit=excluded.usage_limit,starts_at=excluded.starts_at,ends_at=excluded.ends_at,active=1', [$code,$type,$value,$currency,max(0,(int)($_POST['usage_limit'] ?? 0)),trim((string)($_POST['starts_at'] ?? '')) ?: null,trim((string)($_POST['ends_at'] ?? '')) ?: null]);
        } elseif ($action === 'rate') {
            $base = strtoupper(trim((string)($_POST['base_currency'] ?? 'EUR')));
            $quote = strtoupper(trim((string)($_POST['quote_currency'] ?? 'USD')));
            $rate = trim((string)($_POST['rate'] ?? ''));
            if (!preg_match('/^[A-Z]{3}$/', $base) || !preg_match('/^[A-Z]{3}$/', $quote) || $base === $quote || !is_numeric($rate) || (float)$rate <= 0) throw new RuntimeException('Currency rate is invalid.');
            q('INSERT INTO currency_rates(base_currency,quote_currency,rate,provider) VALUES(?,?,?,?)', [$base,$quote,$rate,trim((string)($_POST['provider'] ?? 'manual')) ?: 'manual']);
        } elseif ($action === 'manual_payment') {
            $id = (int)($_POST['manual_payment_id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'pending');
            if (!in_array($status, ['pending','approved','rejected'], true)) throw new RuntimeException('Payment status is invalid.');
            $payment = one('SELECT * FROM manual_payments WHERE id=?', [$id]);
            if (!$payment) throw new RuntimeException('Manual payment not found.');
            $db = lc_db();
            $db->beginTransaction();
            try {
                q('UPDATE manual_payments SET status=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?', [$status,$id]);
                if ($status === 'approved') {
                    $order = one('SELECT * FROM orders WHERE id=?', [$payment['order_id']]);
                    if (!$order || !order_transition_allowed((string)$order['status'], 'paid')) throw new RuntimeException('This order cannot be marked as paid from its current status.');
                    q("UPDATE orders SET status='paid',paid_at=CURRENT_TIMESTAMP,provider='manual' WHERE id=? AND status='pending'", [$payment['order_id']]);
                    $order['status'] = 'paid';
                    fulfill_paid_order($order, 'manual', (string)$payment['reference']);
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        }
        header('Location: ./commerce?saved=1'); exit;
    }

    $taxRows=''; foreach(q('SELECT * FROM tax_profiles ORDER BY country_code,region')->fetchAll() as $row) $taxRows.='<tr><td>'.esc($row['country_code']).'</td><td>'.esc($row['region']).'</td><td>'.esc($row['tax_name']).'</td><td>'.number_format(((int)$row['rate_basis_points'])/100,2).'%</td><td>'.((int)$row['active']?'Active':'Off').'</td></tr>';
    $couponRows=''; foreach(q('SELECT * FROM coupons ORDER BY id DESC LIMIT 100')->fetchAll() as $row) $couponRows.='<tr><td>'.esc($row['code']).'</td><td>'.esc($row['discount_type']).'</td><td>'.(int)$row['discount_value'].'</td><td>'.(int)$row['used_count'].' / '.((int)$row['usage_limit'] ?: '∞').'</td><td>'.((int)$row['active']?'Active':'Off').'</td></tr>';
    $rateRows=''; foreach(q('SELECT * FROM currency_rates ORDER BY observed_at DESC LIMIT 100')->fetchAll() as $row) $rateRows.='<tr><td>'.esc($row['base_currency'].' → '.$row['quote_currency']).'</td><td>'.esc($row['rate']).'</td><td>'.esc($row['provider']).'</td><td>'.esc($row['observed_at']).'</td></tr>';
    $manualRows=''; foreach(q("SELECT m.*,o.reference,o.amount_minor,o.currency FROM manual_payments m JOIN orders o ON o.id=m.order_id ORDER BY m.id DESC LIMIT 100")->fetchAll() as $row) $manualRows.='<tr><td>'.esc($row['reference']).'</td><td>'.esc(strtoupper($row['currency']).' '.number_format(((int)$row['amount_minor'])/100,2)).'</td><td>'.esc($row['status']).'</td><td><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="manual_payment"/><input type="hidden" name="manual_payment_id" value="'.(int)$row['id'].'"/><select name="status"><option value="pending">pending</option><option value="approved">approved</option><option value="rejected">rejected</option></select><button>Save</button></form></td></tr>';
    layout('Commerce controls','<div class="grid"><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="tax"/><h2>Tax profile</h2><label>Country<input name="country_code" maxlength="2" placeholder="TR" required/></label><label>Region<input name="region"/></label><label>Name<input name="tax_name" value="VAT" required/></label><label>Rate basis points<input type="number" name="rate_basis_points" value="2000" min="0" max="10000"/></label><label><input type="checkbox" name="inclusive" value="1"/> Prices include tax</label><button>Save tax</button></form><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="coupon"/><h2>Coupon</h2><label>Code<input name="code" placeholder="LAUNCH20" required/></label><label>Type<select name="discount_type"><option value="percent">Percent basis points</option><option value="fixed">Fixed minor units</option></select></label><label>Value<input type="number" name="discount_value" min="0" required/></label><label>Currency<input name="currency" maxlength="3" placeholder="EUR"/></label><label>Usage limit<input type="number" name="usage_limit" min="0" value="0"/></label><button>Save coupon</button></form><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="action" value="rate"/><h2>Currency rate</h2><label>Base<input name="base_currency" value="EUR" maxlength="3"/></label><label>Quote<input name="quote_currency" value="USD" maxlength="3"/></label><label>Rate<input name="rate" placeholder="1.08" required/></label><label>Provider<input name="provider" value="manual"/></label><button>Save rate</button></form></div><div class="card"><h2>Tax profiles</h2><table><tr><th>Country</th><th>Region</th><th>Name</th><th>Rate</th><th>Status</th></tr>'.$taxRows.'</table></div><div class="card"><h2>Coupons</h2><table><tr><th>Code</th><th>Type</th><th>Value</th><th>Usage</th><th>Status</th></tr>'.$couponRows.'</table></div><div class="card"><h2>Currency rate history</h2><table><tr><th>Pair</th><th>Rate</th><th>Provider</th><th>Observed</th></tr>'.$rateRows.'</table></div><div class="card"><h2>Manual payments</h2><table><tr><th>Reference</th><th>Amount</th><th>Status</th><th>Review</th></tr>'.$manualRows.'</table></div>');
}

commerce_admin_route();
