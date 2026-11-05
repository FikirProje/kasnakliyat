<?php
declare(strict_types=1);

/** Product-aware payment callback; legacy global download fallback is bypassed. */
function payment_webhook_product(string $provider): never
{
    $db = lc_db();
    $transactionStarted = false;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        rate_limit('webhook:'.$provider.':'.$ip, 30, 60);
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) $payload = $_POST;
        $eventKey = (string)($payload['event_id'] ?? $payload['id'] ?? $payload['merchant_oid'] ?? $payload['paymentId'] ?? '');
        if ($eventKey === '') throw new RuntimeException('Payment event id is required.');
        $valid = payment_signature_valid($provider, $payload, $raw);
        q('INSERT OR IGNORE INTO payment_events(provider,event_key,payload,signature_valid) VALUES(?,?,?,?)', [$provider, $eventKey, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $valid ? 1 : 0]);
        if (!$valid) throw new RuntimeException('Invalid payment signature.');
        $status = strtolower((string)($payload['status'] ?? $payload['payment_status'] ?? $payload['event_type'] ?? ''));
        $reference = (string)($payload['order_reference'] ?? $payload['merchant_oid'] ?? $payload['paymentConversationId'] ?? '');
        $order = $reference !== '' ? one('SELECT * FROM orders WHERE reference=?', [$reference]) : null;
        $issuedLicenseKey = null;
        if ($order && in_array($status, ['paid','success','succeeded','completed'], true)) {
            if (!$db->inTransaction()) { $db->beginTransaction(); $transactionStarted = true; }
            q("UPDATE orders SET status='paid',paid_at=datetime('now'),provider=?,provider_reference=? WHERE id=? AND status<>'paid'", [$provider, $eventKey, $order['id']]);
            $issuedLicenseKey = fulfill_paid_order($order, $provider, $eventKey);
            if ($transactionStarted) $db->commit();
        }
        reply(['accepted'=>true,'idempotent'=>true,'event_id'=>$eventKey,'license_key'=>$issuedLicenseKey]);
    } catch (Throwable $e) {
        if ($transactionStarted && $db->inTransaction()) $db->rollBack();
        reply(['accepted'=>false,'error'=>$e->getMessage()], 422);
    }
}
