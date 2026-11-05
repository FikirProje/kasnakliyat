<?php
declare(strict_types=1);

// Refunds are deliberately handled before the legacy orders renderer below.
// The operation is idempotent and revokes every entitlement created by that order.
if (($path ?? '') === '/orders/refund' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $orderId = (int)($_POST['id'] ?? 0);
    $order = one("SELECT * FROM orders WHERE id=? AND status='paid'", [$orderId]);
    if (!$order) throw new RuntimeException('Only a paid order can be refunded.');

    $db = lc_db();
    $db->beginTransaction();
    try {
        $existing = one("SELECT id FROM order_adjustments WHERE order_id=? AND kind='refund' LIMIT 1", [$orderId]);
        if (!$existing) {
            q('INSERT INTO order_adjustments(order_id,kind,label,amount_minor,currency,metadata_json) VALUES(?,?,?,?,?,?)', [
                $orderId, 'refund', 'Full order refund', -(int)$order['amount_minor'], $order['currency'],
                json_encode(['admin_id' => $_SESSION['admin'] ?? null], JSON_UNESCAPED_SLASHES),
            ]);
        }
        if (!order_transition_allowed((string)$order['status'], 'refunded')) throw new RuntimeException('This order cannot be refunded from its current status.');
        q("UPDATE orders SET status='refunded' WHERE id=? AND status='paid'", [$orderId]);
        $licenses = q("SELECT id FROM licenses WHERE order_id=? AND status<>'revoked'", [$orderId])->fetchAll();
        foreach ($licenses as $license) {
            q("UPDATE licenses SET status='revoked' WHERE id=?", [$license['id']]);
            if (!one('SELECT id FROM license_revocations WHERE license_id=? AND reason LIKE ?', [$license['id'], 'Order '.$order['reference'].' refunded%'])) {
                q('INSERT INTO license_revocations(license_id,reason,revoked_by) VALUES(?,?,?)', [$license['id'], 'Order '.$order['reference'].' refunded', $_SESSION['admin'] ?? null]);
            }
            q('INSERT INTO events(license_id,action,detail) VALUES(?,?,?)', [$license['id'], 'licence.refunded', 'Order '.$order['reference']]);
        }
        // Keep the audit row, but make the old account/download endpoint unusable too.
        q("UPDATE downloads SET url='' WHERE order_id=?", [$orderId]);
        q("UPDATE invoices SET status='void' WHERE order_id=?", [$orderId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    header('Location: ./orders?refunded=1');
    exit;
}
