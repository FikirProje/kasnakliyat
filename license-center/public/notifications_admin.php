<?php
declare(strict_types=1);

lc_db()->exec("CREATE TABLE IF NOT EXISTS admin_notifications(
    id INTEGER PRIMARY KEY,
    severity TEXT NOT NULL DEFAULT 'info' CHECK(severity IN('info','success','warning','critical')),
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    audience TEXT NOT NULL DEFAULT 'admin',
    read_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source_event_id INTEGER UNIQUE
)");
try { lc_db()->exec('ALTER TABLE admin_notifications ADD COLUMN source_event_id INTEGER'); } catch (Throwable $e) { }
lc_db()->exec('CREATE UNIQUE INDEX IF NOT EXISTS admin_notifications_source_event ON admin_notifications(source_event_id) WHERE source_event_id IS NOT NULL');

// This module is loaded before the main request dispatcher calls setup().
// Initialise once for the notifications route so a fresh installation cannot
// fail with a missing events/admin table.
if ($path === '/notifications' && function_exists('setup')) setup();

if ($path === '/notifications' && function_exists('q')) {
    foreach (q('SELECT id,action,detail,created_at FROM events WHERE id NOT IN (SELECT source_event_id FROM admin_notifications WHERE source_event_id IS NOT NULL) ORDER BY id DESC LIMIT 50')->fetchAll() as $event) {
        $severity = str_contains((string)$event['action'], 'revok') ? 'critical' : (str_contains((string)$event['action'], 'fail') ? 'warning' : 'info');
        q('INSERT OR IGNORE INTO admin_notifications(severity,title,body,source_event_id,created_at) VALUES(?,?,?,?,?)', [$severity, 'Licence event: '.(string)$event['action'], (string)$event['detail'], (int)$event['id'], (string)$event['created_at']]);
    }
    foreach (q('SELECT id,provider,event_key,signature_valid,created_at FROM payment_events WHERE id NOT IN (SELECT ABS(source_event_id) FROM admin_notifications WHERE source_event_id IS NOT NULL AND source_event_id < 0) ORDER BY id DESC LIMIT 50')->fetchAll() as $payment) {
        $valid = (int)$payment['signature_valid'] === 1;
        q('INSERT OR IGNORE INTO admin_notifications(severity,title,body,source_event_id,created_at) VALUES(?,?,?,?,?)', [
            $valid ? 'success' : 'critical',
            'Payment webhook '.($valid ? 'accepted' : 'rejected'),
            'Provider: '.(string)$payment['provider'].' · Event: '.mb_substr((string)$payment['event_key'], 0, 80),
            -((int)$payment['id']),
            (string)$payment['created_at'],
        ]);
    }
    foreach (q('SELECT id,subject,status,created_at FROM support_tickets WHERE id NOT IN (SELECT ABS(source_event_id)-100000000 FROM admin_notifications WHERE source_event_id IS NOT NULL AND source_event_id <= -100000000) ORDER BY id DESC LIMIT 50')->fetchAll() as $ticket) {
        q('INSERT OR IGNORE INTO admin_notifications(severity,title,body,source_event_id,created_at) VALUES(?,?,?,?,?)', [
            'info', 'New support ticket', (string)$ticket['subject'].' · Status: '.(string)$ticket['status'], -100000000 - (int)$ticket['id'], (string)$ticket['created_at'],
        ]);
    }
    foreach (q('SELECT id,reference,product,status,created_at FROM orders WHERE id NOT IN (SELECT ABS(source_event_id)-200000000 FROM admin_notifications WHERE source_event_id IS NOT NULL AND source_event_id <= -200000000) ORDER BY id DESC LIMIT 50')->fetchAll() as $order) {
        $severity = in_array((string)$order['status'], ['failed','refunded','cancelled'], true) ? 'warning' : 'info';
        q('INSERT OR IGNORE INTO admin_notifications(severity,title,body,source_event_id,created_at) VALUES(?,?,?,?,?)', [
            $severity, 'Order status updated', (string)$order['reference'].' · '.(string)$order['product'].' · '.(string)$order['status'], -200000000 - (int)$order['id'], (string)$order['created_at'],
        ]);
    }
}

function lc_notify(string $title, string $body = '', string $severity = 'info'): void {
    if (!in_array($severity, ['info','success','warning','critical'], true)) $severity = 'info';
    q('INSERT INTO admin_notifications(severity,title,body) VALUES(?,?,?)', [
        $severity,
        mb_substr($title, 0, 180),
        mb_substr($body, 0, 2000),
    ]);
}

if ($path !== '/notifications') return;
login_required();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) q('UPDATE admin_notifications SET read_at=CURRENT_TIMESTAMP WHERE id=?', [$id]);
    header('Location: ./notifications');
    exit;
}
$rows = '';
foreach (q('SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 200')->fetchAll() as $item) {
    $rows .= '<tr><td>'.esc($item['created_at']).'</td><td>'.esc($item['severity']).'</td><td><strong>'.esc($item['title']).'</strong><br>'.nl2br(esc($item['body'])).'</td><td>'.esc($item['read_at'] ?: 'Unread').'</td><td><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="id" value="'.(int)$item['id'].'"/><button>Mark read</button></form></td></tr>';
}
layout('Notifications','<div class="card"><h2>Admin notifications</h2><p>Operational, release, support and licence review notices. No customer secrets are shown here.</p><table><tr><th>Time</th><th>Severity</th><th>Notice</th><th>Status</th><th>Action</th></tr>'.$rows.'</table></div>');
