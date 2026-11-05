<?php
declare(strict_types=1);

// Controlled domain replacement and licence transfer workflow. Customers do
// not edit ownership directly; every change is reviewable and auditable.
lc_db()->exec("CREATE TABLE IF NOT EXISTS license_change_requests(
    id INTEGER PRIMARY KEY,
    license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    buyer_id INTEGER NOT NULL REFERENCES buyers(id) ON DELETE CASCADE,
    request_type TEXT NOT NULL CHECK(request_type IN ('domain_replace','license_transfer')),
    old_domain TEXT NOT NULL DEFAULT '', new_domain TEXT NOT NULL DEFAULT '',
    target_buyer_email TEXT NOT NULL DEFAULT '', reason TEXT NOT NULL,
    evidence TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'open'
      CHECK(status IN ('open','approved','rejected','cancelled')),
    review_note TEXT NOT NULL DEFAULT '', reviewed_by INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    reviewed_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS license_change_requests_status ON license_change_requests(status,created_at);
CREATE INDEX IF NOT EXISTS license_change_requests_buyer ON license_change_requests(buyer_id,status);");

function license_request_domain(string $value): string { return host($value); }

function license_requests_route(): void
{
    global $path;
    if (!in_array($path, ['/account/license-requests','/license-requests'], true)) return;
    $admin = $path === '/license-requests';
    if ($admin) login_required(); else $buyer = buyer_required();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (!$admin) {
            $licenseId = (int)($_POST['license_id'] ?? 0);
            $type = (string)($_POST['request_type'] ?? 'domain_replace');
            $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 2000);
            $evidence = mb_substr(trim((string)($_POST['evidence'] ?? '')), 0, 4000);
            $license = one("SELECT l.*,p.name product_name FROM licenses l LEFT JOIN products p ON p.id=l.product_id WHERE l.id=? AND l.buyer_id=? AND l.status='active'", [$licenseId,$buyer['id']]);
            if (!$license || !in_array($type,['domain_replace','license_transfer'],true) || $reason === '') throw new RuntimeException('Licence, request type and reason are required.');
            $old = $new = $target = '';
            if ($type === 'domain_replace') {
                $old = license_request_domain((string)($_POST['old_domain'] ?? ''));
                $new = license_request_domain((string)($_POST['new_domain'] ?? ''));
                if ($old === $new || !one('SELECT id FROM domains WHERE license_id=? AND domain=?',[$licenseId,$old])) throw new RuntimeException('The old domain must already be bound and differ from the new domain.');
                if (one('SELECT id FROM domains WHERE domain=? AND license_id<>?',[$new,$licenseId])) throw new RuntimeException('The new domain is already bound to another licence.');
            } else {
                $target = strtolower(trim((string)($_POST['target_buyer_email'] ?? '')));
                if (!filter_var($target,FILTER_VALIDATE_EMAIL) || $target === strtolower((string)$buyer['email'])) throw new RuntimeException('Enter a different, valid target customer email.');
            }
            q('INSERT INTO license_change_requests(license_id,buyer_id,request_type,old_domain,new_domain,target_buyer_email,reason,evidence) VALUES(?,?,?,?,?,?,?,?)',[$licenseId,$buyer['id'],$type,$old,$new,$target,$reason,$evidence]);
            q('INSERT INTO events(license_id,action,detail) VALUES(?,?,?)',[$licenseId,'licence.change.requested',$type.' · '.$reason]);
            header('Location: ./license-requests?submitted=1'); exit;
        }

        $requestId = (int)($_POST['request_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $note = mb_substr(trim((string)($_POST['review_note'] ?? '')),0,2000);
        $request = one("SELECT * FROM license_change_requests WHERE id=? AND status='open'",[$requestId]);
        if (!$request || !in_array($decision,['approve','reject'],true)) throw new RuntimeException('Open licence change request not found.');
        if ($decision === 'reject') {
            if ($note === '') throw new RuntimeException('A rejection note is required.');
            q("UPDATE license_change_requests SET status='rejected',review_note=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$note,(int)($_SESSION['admin']??0) ?: null,$requestId]);
            q('INSERT INTO events(license_id,action,detail) VALUES(?,?,?)',[(int)$request['license_id'],'licence.change.rejected',$note]);
            header('Location: ./license-requests?reviewed=1'); exit;
        }
        if ($request['request_type'] === 'domain_replace') {
            $old = one('SELECT id FROM domains WHERE license_id=? AND domain=?',[(int)$request['license_id'],(string)$request['old_domain']]);
            if (!$old) throw new RuntimeException('The requested old domain is no longer bound.');
            if (one('SELECT id FROM domains WHERE domain=? AND license_id<>?',[(string)$request['new_domain'],(int)$request['license_id']])) throw new RuntimeException('The requested new domain is now bound to another licence.');
            q('UPDATE domains SET domain=?,last_seen_at=CURRENT_TIMESTAMP WHERE id=?',[(string)$request['new_domain'],(int)$old['id']]);
            $detail = (string)$request['old_domain'].' → '.(string)$request['new_domain'];
        } else {
            $target = one('SELECT id,email FROM buyers WHERE lower(email)=? AND email_verified_at IS NOT NULL',[strtolower((string)$request['target_buyer_email'])]);
            if (!$target) throw new RuntimeException('The target customer must exist and have a verified email address.');
            q('UPDATE licenses SET buyer_id=? WHERE id=?',[(int)$target['id'],(int)$request['license_id']]);
            $detail = 'Transferred to '.(string)$target['email'];
        }
        q("UPDATE license_change_requests SET status='approved',review_note=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$note,(int)($_SESSION['admin']??0) ?: null,$requestId]);
        q('INSERT INTO events(license_id,action,detail) VALUES(?,?,?)',[(int)$request['license_id'],'licence.change.approved',$detail]);
        header('Location: ./license-requests?reviewed=1'); exit;
    }

    if ($admin) {
        $rows='';
        foreach (q("SELECT r.*,l.key_hint,b.email buyer_email FROM license_change_requests r JOIN licenses l ON l.id=r.license_id JOIN buyers b ON b.id=r.buyer_id ORDER BY CASE r.status WHEN 'open' THEN 0 ELSE 1 END,r.created_at DESC LIMIT 300")->fetchAll() as $row) {
            $target=$row['request_type']==='domain_replace'?$row['old_domain'].' → '.$row['new_domain']:$row['target_buyer_email'];
            $action=$row['status']==='open'?'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><input type="hidden" name="request_id" value="'.(int)$row['id'].'"/><input name="review_note" placeholder="Review note"><button name="decision" value="approve">Approve</button><button class="secondary" name="decision" value="reject">Reject</button></form>':esc((string)$row['review_note']);
            $rows.='<tr><td>#'.(int)$row['id'].' · '.esc($row['key_hint']).'<br><small>'.esc($row['buyer_email']).'</small></td><td>'.esc($row['request_type']).'</td><td>'.esc($target).'</td><td>'.nl2br(esc($row['reason'])).'</td><td>'.esc($row['status']).'</td><td>'.$action.'</td></tr>';
        }
        layout('Licence change requests','<div class="card"><h1>Licence change requests</h1><p class="muted">Approve only after ownership and support checks. Every decision is written to the licence event log.</p><table><tr><th>Licence / buyer</th><th>Type</th><th>Target</th><th>Reason</th><th>Status</th><th>Review</th></tr>'.($rows?:'<tr><td colspan="6">No requests.</td></tr>').'</table></div>'); return;
    }

    $licenses=q("SELECT l.id,l.key_hint,p.name product_name FROM licenses l LEFT JOIN products p ON p.id=l.product_id WHERE l.buyer_id=? AND l.status='active' ORDER BY l.id DESC",[$buyer['id']])->fetchAll();
    $options=''; foreach($licenses as $license)$options.='<option value="'.(int)$license['id'].'">'.esc($license['product_name']?:'Product').' · '.esc($license['key_hint']).'</option>';
    $rows=''; foreach(q('SELECT * FROM license_change_requests WHERE buyer_id=? ORDER BY created_at DESC LIMIT 100',[$buyer['id']])->fetchAll() as $row)$rows.='<tr><td>'.esc($row['request_type']).'</td><td>'.esc(trim($row['old_domain'].' '.$row['new_domain'].' '.$row['target_buyer_email'])).'</td><td>'.esc($row['status']).'</td><td>'.esc($row['created_at']).'</td><td>'.esc($row['review_note']).'</td></tr>';
    buyer_layout('Licence change requests','<h1>Licence change requests</h1><p class="muted">Request a domain replacement or transfer. Changes become active only after administrator review.</p><form class="card" method="post"><input type="hidden" name="csrf" value="'.csrf().'"/><label>Licence<select name="license_id" required>'.$options.'</select></label><label>Request type<select name="request_type" onchange="document.querySelectorAll(\'.domain-fields,.transfer-fields\').forEach(function(e){e.hidden=e.classList.contains(this.value===\'domain_replace\'?\'transfer-fields\':\'domain-fields\');},this)"><option value="domain_replace">Replace domain</option><option value="license_transfer">Transfer licence</option></select></label><div class="domain-fields"><label>Current domain<input name="old_domain" placeholder="old.example.com"></label><label>New domain<input name="new_domain" placeholder="new.example.com"></label></div><div class="transfer-fields" hidden><label>Target customer email<input type="email" name="target_buyer_email"></label></div><label>Reason<textarea name="reason" required maxlength="2000"></textarea></label><label>Evidence / support note<textarea name="evidence" maxlength="4000"></textarea></label><button>Submit request</button></form><div class="card"><h2>Request history</h2><table><tr><th>Type</th><th>Target</th><th>Status</th><th>Created</th><th>Review note</th></tr>'.($rows?:'<tr><td colspan="5">No requests yet.</td></tr>').'</table></div>');
}
