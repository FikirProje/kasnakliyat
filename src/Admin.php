<?php
declare(strict_types=1);

function admin_roles(string $section): array
{
    return match ($section) {
        'settings', 'email-templates', 'notification-rules', 'integrations', 'security', 'anomalies', 'users', 'audit', 'backup', 'mail', 'languages', 'themes', 'licenses', 'updates', 'modules', 'plugins', 'menus', 'forms', 'locations', 'legal' => ['admin'],
        'content', 'content-models', 'editor', 'media', 'gallery', 'seo', 'jobs', 'taxonomies' => ['admin', 'editor'],
        'requests', 'appointments' => ['admin', 'operator'],
        default => ['admin', 'editor', 'operator'],
    };
}

function admin_allowed(string $section, array $user): bool
{
    if (function_exists('platform_user_can') && db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='platform_roles'")->fetchColumn()) return platform_user_can($user, 'admin.section.'.$section);
    return in_array($user['role'], admin_roles($section), true);
}

function admin_status(string $status): string
{
    $labels = [
        'tr'=>['new'=>'Yeni talep','quoted'=>'Teklif verildi','proposed'=>'Tarih önerildi','confirmed'=>'Kesinleşti','rejected'=>'Reddedildi','draft'=>'Taslak','review'=>'İncelemede','published'=>'Yayında','queued'=>'Sırada','running'=>'Çalışıyor','completed'=>'Tamamlandı','failed'=>'Hata','sent'=>'Gönderildi','pending'=>'Bekliyor'],
        'en'=>['new'=>'New request','quoted'=>'Quote sent','proposed'=>'Date proposed','confirmed'=>'Confirmed','rejected'=>'Rejected','draft'=>'Draft','review'=>'In review','published'=>'Published','queued'=>'Queued','running'=>'Running','completed'=>'Completed','failed'=>'Failed','sent'=>'Sent','pending'=>'Pending'],
        'de'=>['new'=>'Neue Anfrage','quoted'=>'Angebot gesendet','proposed'=>'Termin vorgeschlagen','confirmed'=>'Bestätigt','rejected'=>'Abgelehnt','draft'=>'Entwurf','review'=>'In Prüfung','published'=>'Veröffentlicht','queued'=>'Warteschlange','running'=>'Läuft','completed'=>'Abgeschlossen','failed'=>'Fehler','sent'=>'Gesendet','pending'=>'Ausstehend'],
        'fr'=>['new'=>'Nouvelle demande','quoted'=>'Devis envoyé','proposed'=>'Date proposée','confirmed'=>'Confirmé','rejected'=>'Refusé','draft'=>'Brouillon','review'=>'En révision','published'=>'Publié','queued'=>'En attente','running'=>'En cours','completed'=>'Terminé','failed'=>'Erreur','sent'=>'Envoyé','pending'=>'En attente'],
        'es'=>['new'=>'Nueva solicitud','quoted'=>'Presupuesto enviado','proposed'=>'Fecha propuesta','confirmed'=>'Confirmado','rejected'=>'Rechazado','draft'=>'Borrador','review'=>'En revisión','published'=>'Publicado','queued'=>'En cola','running'=>'En curso','completed'=>'Completado','failed'=>'Error','sent'=>'Enviado','pending'=>'Pendiente'],
        'it'=>['new'=>'Nuova richiesta','quoted'=>'Preventivo inviato','proposed'=>'Data proposta','confirmed'=>'Confermato','rejected'=>'Rifiutato','draft'=>'Bozza','review'=>'In revisione','published'=>'Pubblicato','queued'=>'In coda','running'=>'In corso','completed'=>'Completato','failed'=>'Errore','sent'=>'Inviato','pending'=>'In attesa'],
        'pt'=>['new'=>'Novo pedido','quoted'=>'Orçamento enviado','proposed'=>'Data proposta','confirmed'=>'Confirmado','rejected'=>'Rejeitado','draft'=>'Rascunho','review'=>'Em revisão','published'=>'Publicado','queued'=>'Em fila','running'=>'Em curso','completed'=>'Concluído','failed'=>'Erro','sent'=>'Enviado','pending'=>'Pendente'],
        'nl'=>['new'=>'Nieuw verzoek','quoted'=>'Offerte verzonden','proposed'=>'Datum voorgesteld','confirmed'=>'Bevestigd','rejected'=>'Afgewezen','draft'=>'Concept','review'=>'In beoordeling','published'=>'Gepubliceerd','queued'=>'In wachtrij','running'=>'Bezig','completed'=>'Voltooid','failed'=>'Fout','sent'=>'Verzonden','pending'=>'In afwachting'],
        'ru'=>['new'=>'Новый запрос','quoted'=>'Предложение отправлено','proposed'=>'Дата предложена','confirmed'=>'Подтверждено','rejected'=>'Отклонено','draft'=>'Черновик','review'=>'На проверке','published'=>'Опубликовано','queued'=>'В очереди','running'=>'Выполняется','completed'=>'Завершено','failed'=>'Ошибка','sent'=>'Отправлено','pending'=>'Ожидает'],
        'ar'=>['new'=>'طلب جديد','quoted'=>'أُرسل العرض','proposed'=>'تم اقتراح التاريخ','confirmed'=>'مؤكد','rejected'=>'مرفوض','draft'=>'مسودة','review'=>'قيد المراجعة','published'=>'منشور','queued'=>'في الانتظار','running'=>'قيد التنفيذ','completed'=>'مكتمل','failed'=>'خطأ','sent'=>'مُرسل','pending'=>'معلّق'],
        'fa'=>['new'=>'درخواست جدید','quoted'=>'پیشنهاد ارسال شد','proposed'=>'تاریخ پیشنهاد شد','confirmed'=>'تأیید شد','rejected'=>'رد شد','draft'=>'پیش‌نویس','review'=>'در حال بررسی','published'=>'منتشر شد','queued'=>'در صف','running'=>'در حال اجرا','completed'=>'تکمیل شد','failed'=>'خطا','sent'=>'ارسال شد','pending'=>'در انتظار'],
        'zh'=>['new'=>'新请求','quoted'=>'报价已发送','proposed'=>'已提出日期','confirmed'=>'已确认','rejected'=>'已拒绝','draft'=>'草稿','review'=>'审核中','published'=>'已发布','queued'=>'排队中','running'=>'进行中','completed'=>'已完成','failed'=>'错误','sent'=>'已发送','pending'=>'待处理'],
        'ja'=>['new'=>'新しい依頼','quoted'=>'見積もり送信済み','proposed'=>'日程提案済み','confirmed'=>'確定','rejected'=>'拒否','draft'=>'下書き','review'=>'審査中','published'=>'公開済み','queued'=>'待機中','running'=>'実行中','completed'=>'完了','failed'=>'エラー','sent'=>'送信済み','pending'=>'保留中'],
        'ko'=>['new'=>'새 요청','quoted'=>'견적 전송됨','proposed'=>'일정 제안됨','confirmed'=>'확정됨','rejected'=>'거부됨','draft'=>'초안','review'=>'검토 중','published'=>'게시됨','queued'=>'대기 중','running'=>'진행 중','completed'=>'완료됨','failed'=>'오류','sent'=>'전송됨','pending'=>'보류 중'],
    ];
    $lang = (string)($_REQUEST['lang'] ?? $_SESSION['admin_locale'] ?? setting('admin_locale','en'));
    $labels = $labels[$lang] ?? $labels['en'];
    return '<span class="badge badge-'.e($status).'">'.e($labels[$status] ?? $status).'</span>';
}

function admin_field(string $label, string $name, string $value = '', string $type = 'text', bool $required = false): void
{
    echo '<label>'.e($label).'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" '.($required ? 'required' : '').'></label>';
}

function admin_textarea(string $label, string $name, string $value = '', int $rows = 4): void
{
    echo '<label>'.e($label).'<textarea name="'.e($name).'" rows="'.$rows.'">'.e($value).'</textarea></label>';
}

function admin_select(string $label, string $name, array $choices, string $selected = ''): void
{
    echo '<label>'.e($label).'<select name="'.e($name).'">';
    foreach ($choices as $value => $text) echo '<option value="'.e($value).'" '.((string)$value === $selected ? 'selected' : '').'>'.e($text).'</option>';
    echo '</select></label>';
}

function admin_action(string $action): void
{
    echo csrf_field().'<input type="hidden" name="action" value="'.e($action).'">';
}

function admin_lang(): string
{
    $lang = (string)($_REQUEST['lang'] ?? 'tr');
    if (!array_key_exists($lang, admin_locales())) throw new InvalidArgumentException('Geçersiz dil.');
    return $lang;
}

function admin_user_lang(array $user): string
{
    $requested = (string)($_REQUEST['lang'] ?? '');
    if ($requested !== '' && array_key_exists($requested, admin_locales())) return $requested;
    $saved = row('SELECT preference_value FROM user_preferences WHERE user_id=? AND preference_key=?', [(int)$user['id'], 'admin_locale']);
    $lang = (string)($saved['preference_value'] ?? setting('admin_locale', 'en'));
    return array_key_exists($lang, admin_locales()) ? $lang : 'en';
}

function admin_save_locale(array $user): void
{
    $lang = (string)($_POST['lang'] ?? '');
    if (!array_key_exists($lang, admin_locales())) throw new InvalidArgumentException('Geçersiz dil.');
    query("INSERT INTO user_preferences(user_id,preference_key,preference_value,updated_at) VALUES(?,?,?,datetime('now')) ON CONFLICT(user_id,preference_key) DO UPDATE SET preference_value=excluded.preference_value,updated_at=datetime('now')", [(int)$user['id'], 'admin_locale', $lang]);
    $_SESSION['admin_locale'] = $lang;
    flash('success', admin_t('language_saved', $lang));
}

function admin_input(string $key, int $max = 1000): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value) > $max) throw new InvalidArgumentException('Alan çok uzun: '.$key);
    return $value;
}

function admin_save_request(): void
{
    $id = (int)($_POST['id'] ?? 0);
    $request = row('SELECT * FROM requests WHERE id = ?', [$id]);
    if (!$request) throw new InvalidArgumentException('Talep bulunamadı.');
    $status = admin_input('status', 20);
    $allowed = $request['type'] === 'appointment' ? ['new','proposed','confirmed','rejected'] : ['new','quoted','rejected'];
    if (!in_array($status, $allowed, true)) throw new InvalidArgumentException('Geçersiz talep durumu.');
    $date = admin_input('preferred_date', 10);
    $slot = admin_input('slot', 5);
    if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4)))) throw new InvalidArgumentException('Geçerli tarih girin.');
    if ($slot !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $slot)) throw new InvalidArgumentException('Geçerli saat girin.');
    if (in_array($status, ['confirmed','proposed'], true) && ($date === '' || $slot === '' || strtotime($date.' '.$slot) <= time())) throw new InvalidArgumentException('Kesinleştirme ve öneri için gelecekteki bir tarih ve saat seçin.');
    $price = admin_input('price', 30);
    if ($price !== '' && (!is_numeric($price) || (float)$price < 0)) throw new InvalidArgumentException('Fiyat sıfır veya pozitif bir sayı olmalı.');
    $note = admin_input('admin_note', 4000);
    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($status === 'confirmed' && row("SELECT id FROM requests WHERE type = 'appointment' AND status = 'confirmed' AND preferred_date = ? AND slot = ? AND id <> ?", [$date,$slot,$id])) throw new InvalidArgumentException('Bu tarih ve saatte kesinleşmiş başka bir randevu var. Başka saat önerin.');
        query("UPDATE requests SET status=?, preferred_date=?, slot=?, price=?, admin_note=?, updated_at=datetime('now') WHERE id=?", [$status,$date,$slot,$price,$note,$id]);
        db()->exec('COMMIT');
    } catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
    audit('request.updated', 'Talep #'.$id.' → '.$status);
    if ($status !== $request['status'] || $date !== (string)$request['preferred_date'] || $slot !== (string)$request['slot']) {
        [$subject, $message] = admin_request_mail_text((string)($request['lang'] ?? 'tr'), setting('business_name', 'TofiDesk'), (string)$request['reference'], $status, $date, $slot, $price, setting('currency_code', 'EUR'));
        enqueue_mail($request['email'], $subject, $message, $id);
    }
    flash('success', 'Talep güncellendi. Durum değişikliği varsa e-posta kuyruğuna eklendi.');
}

function admin_save_translation(): void
{
    $id = (int)($_POST['id'] ?? 0);
    $article = row('SELECT * FROM articles WHERE id=?', [$id]);
    if (!$article) throw new InvalidArgumentException('İçerik bulunamadı.');
    $lang = admin_lang();
    $title = admin_input('title', 180);
    $slug = admin_input('slug', 180);
    $body = admin_input('body', 100000);
    if ($title === '' || $body === '' || !preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/uD', $slug)) throw new InvalidArgumentException('Başlık, gövde ve geçerli URL gerekli. URL için sözcükleri tireyle ayırın.');
    $status = admin_input('status', 20);
    if (!in_array($status, ['draft','review','published'], true)) throw new InvalidArgumentException('Geçersiz yayın durumu.');
    $scheduled = admin_input('scheduled_at', 30);
    if ($scheduled !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $scheduled);
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $scheduled) throw new InvalidArgumentException('Planlama tarihi geçersiz.');
        $scheduled = $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    $cover = (int)($_POST['cover_media_id'] ?? 0);
    if ($cover && (!row("SELECT id FROM media WHERE id=? AND purpose='library'",[$cover]) || row('SELECT media_id FROM request_media WHERE media_id=?',[$cover]))) throw new InvalidArgumentException('Kapak için kütüphaneden herkese açık bir görsel seçin.');
    $expected = (int)($_POST['revision'] ?? 0);
    $fields = [$title,$slug,admin_input('excerpt',1000),$body,admin_input('seo_title',180),admin_input('seo_description',400),admin_input('keywords',1000),$status,$scheduled ?: null];
    db()->exec('BEGIN IMMEDIATE');
    try {
        $current = row('SELECT * FROM translations WHERE article_id=? AND lang=?', [$id,$lang]);
        if (($current && (int)$current['revision'] !== $expected) || (!$current && $expected !== 0)) throw new InvalidArgumentException('Bu dil başka bir oturumda güncellendi. Sayfayı yenileyin; metninizin üzerine yazılmadı.');
        if (row('SELECT id FROM translations WHERE lang=? AND slug=? AND article_id<>?',[$lang,$slug,$id])) throw new InvalidArgumentException('Bu dilde bu URL zaten kullanılıyor.');
        $source = row('SELECT revision FROM translations WHERE article_id=? AND lang=?', [$id,$article['source_lang']]);
        $sourceRevision = $lang === $article['source_lang'] ? $expected + 1 : (int)($source['revision'] ?? 0);
        if ($current) {
            query("UPDATE translations SET title=?,slug=?,excerpt=?,body=?,seo_title=?,seo_description=?,keywords=?,status=?,scheduled_at=?,source_revision=?,revision=revision+1,updated_at=datetime('now') WHERE id=? AND revision=?", [...$fields,$sourceRevision,$current['id'],$expected]);
            if ($current['slug'] !== $slug && $current['slug'] !== '') {
                query('DELETE FROM redirects WHERE lang=? AND old_slug=?',[$lang,$current['slug']]);
                query('INSERT INTO redirects(lang,old_slug,new_slug) VALUES(?,?,?)',[$lang,$current['slug'],$slug]);
            }
        } else {
            query("INSERT INTO translations(article_id,lang,title,slug,excerpt,body,seo_title,seo_description,keywords,status,scheduled_at,source_revision,revision,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,datetime('now'))",[$id,$lang,...$fields,$sourceRevision]);
        }
        query('UPDATE translations SET cover_media_id=? WHERE article_id=? AND lang=?',[$cover ?: null,$id,$lang]);
        query("UPDATE articles SET category=?, updated_at=datetime('now') WHERE id=?",[admin_input('category',80),$id]);
        db()->exec('COMMIT');
    } catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
    audit('content.saved', '#'.$id.' '.$lang.' '.$status);
    flash('success','Dil sürümü kaydedildi. Diğer dillere dokunulmadı.');
}

function admin_apply_job(): void
{
    $job = row("SELECT * FROM jobs WHERE id=? AND status='completed'",[(int)($_POST['id'] ?? 0)]);
    if (!$job) throw new InvalidArgumentException('Tamamlanan iş bulunamadı.');
    $data = json_decode((string)$job['result'], true);
    if (!is_array($data)) throw new InvalidArgumentException('İş sonucu geçerli içerik değil.');
    if (($data['kind'] ?? '') === 'image') {
        $mediaId = (int)($data['media_id'] ?? 0);
        if (!row("SELECT id FROM media WHERE id=? AND purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=media.id)",[$mediaId])) throw new InvalidArgumentException('Üretilen görsel bulunamadı.');
        $current = row('SELECT * FROM translations WHERE article_id=? AND lang=?',[$job['article_id'],$job['lang']]);
        if (!$current) throw new InvalidArgumentException('Önce bu dil için bir metin taslağı kaydedin. Sonra görseli ekleyin.');
        $changed = query("UPDATE translations SET cover_media_id=?,status='draft',scheduled_at=NULL,revision=revision+1,updated_at=datetime('now') WHERE id=? AND revision=?",[$mediaId,$current['id'],(int)($_POST['revision']??0)])->rowCount();
        if (!$changed) throw new InvalidArgumentException('Dil sürümü değişti. Sayfayı yenileyip tekrar inceleyin.');
        audit('ai.cover_applied','İş #'.$job['id']);
        flash('success','Üretilen kapak taslağa eklendi. İşletmeye ait gerçek fotoğraf olarak sunmayın.');
        redirect('/admin/editor?id='.$job['article_id'].'&lang='.$job['lang']);
    }
    foreach (['title','slug','body'] as $field) if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') throw new InvalidArgumentException('Üretilen sonuç eksik; yeniden oluşturun.');
    if (!preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/uD', $data['slug'])) throw new InvalidArgumentException('Üretilen URL geçersiz. Yeni bir üretim isteyin.');
    $article = row('SELECT * FROM articles WHERE id=?',[$job['article_id']]);
    if (!$article) throw new InvalidArgumentException('İçerik silinmiş.');
    $expected = (int)($_POST['revision'] ?? 0);
    db()->exec('BEGIN IMMEDIATE');
    try {
        $current = row('SELECT * FROM translations WHERE article_id=? AND lang=?',[$job['article_id'],$job['lang']]);
        if (($current && (int)$current['revision'] !== $expected) || (!$current && $expected !== 0)) throw new InvalidArgumentException('Dil sürümü değişmiş. Sayfayı yenileyip güncel metni inceleyin.');
        if (row('SELECT id FROM translations WHERE lang=? AND slug=? AND article_id<>?',[$job['lang'],$data['slug'],$job['article_id']])) throw new InvalidArgumentException('Üretilen URL başka içerikte kullanılıyor.');
        $source = row('SELECT revision FROM translations WHERE article_id=? AND lang=?',[$job['article_id'],$article['source_lang']]);
        $sourceRevision = $job['lang'] === $article['source_lang'] ? $expected+1 : (int)($source['revision'] ?? 0);
        $fields = [];
        foreach (['title','slug','excerpt','body','seo_title','seo_description','keywords'] as $field) $fields[] = is_array($data[$field] ?? null) ? implode(', ', $data[$field]) : (string)($data[$field] ?? '');
        if ($current) {
            query("UPDATE translations SET title=?,slug=?,excerpt=?,body=?,seo_title=?,seo_description=?,keywords=?,status='draft',scheduled_at=NULL,source_revision=?,revision=revision+1,updated_at=datetime('now') WHERE id=?",[...$fields,$sourceRevision,$current['id']]);
            if ($current['slug']!==$data['slug'] && $current['slug']!=='') {
                query('DELETE FROM redirects WHERE lang=? AND old_slug=?',[$job['lang'],$current['slug']]);
                query('INSERT INTO redirects(lang,old_slug,new_slug) VALUES(?,?,?)',[$job['lang'],$current['slug'],$data['slug']]);
            }
        } else {
            query("INSERT INTO translations(article_id,lang,title,slug,excerpt,body,seo_title,seo_description,keywords,status,source_revision,revision,updated_at) VALUES(?,?,?,?,?,?,?,?,?,'draft',?,1,datetime('now'))",[$job['article_id'],$job['lang'],...$fields,$sourceRevision]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
    audit('ai.applied', 'İş #'.$job['id'].' editör tarafından taslağa alındı');
    flash('success','Sonuç taslağa alındı. Yayın için dil editöründe inceleyip onaylayın.');
    redirect('/admin/editor?id='.$job['article_id'].'&lang='.$job['lang']);
}

function admin_integrations_page(): void
{
    $smsProvider=setting('sms_provider','none'); $waProvider=setting('whatsapp_provider','none');
    $smsReady=($smsProvider==='netgsm'&&envv('NETGSM_USER')!==''&&envv('NETGSM_PASSWORD')!==''&&envv('NETGSM_HEADER')!=='')||($smsProvider==='iletimerkezi'&&envv('ILETIMERKEZI_API_KEY')!==''&&envv('ILETIMERKEZI_API_HASH')!==''&&envv('ILETIMERKEZI_SENDER')!=='')||($smsProvider==='twilio'&&envv('TWILIO_ACCOUNT_SID')!==''&&envv('TWILIO_AUTH_TOKEN')!==''&&envv('TWILIO_SMS_FROM')!=='');
    $waReady=($waProvider==='cloud'&&envv('WHATSAPP_ACCESS_TOKEN')!==''&&envv('WHATSAPP_PHONE_NUMBER_ID')!=='')||($waProvider==='twilio'&&envv('TWILIO_ACCOUNT_SID')!==''&&envv('TWILIO_AUTH_TOKEN')!==''&&envv('TWILIO_WHATSAPP_FROM')!=='');
    $checks=['SMTP yapılandırması'=>setting('smtp_host')!==''&&setting('mail_from')!=='','AI sağlayıcısı'=>envv('AI_API_KEY')!=='' || (envv('AI_BASE_URL')!=='' && setting('ai_provider','openai')==='compatible'),'SMS sağlayıcısı'=>($smsProvider==='none'||$smsReady),'WhatsApp sağlayıcısı'=>($waProvider==='none'||$waReady),'PayTR anahtarı'=>envv('PAYTR_MERCHANT_KEY')!=='','Stripe webhook'=>envv('STRIPE_WEBHOOK_SECRET')!=='','Cron/worker'=>is_file(ROOT.'/bin/console.php')];
    $adapterStatuses = function_exists('platform_adapter_statuses') ? platform_adapter_statuses() : [];
    ?><div class="split"><section class="panel"><h2>Bildirim kanalları</h2><p class="muted">Sağlayıcı sırları sunucu ortamından okunur; panelde yalnız durum gösterilir.</p><form method="post"><?php admin_action('integrations'); admin_field('SMTP sunucusu','smtp_host',setting('smtp_host')); admin_field('SMTP portu','smtp_port',setting('smtp_port','587'),'number'); admin_field('SMTP kullanıcı adı','smtp_user',setting('smtp_user')); admin_select('SMS sağlayıcısı','sms_provider',['none'=>'Kapalı','netgsm'=>'NetGSM','iletimerkezi'=>'İleti Merkezi','twilio'=>'Twilio'],setting('sms_provider','none')); admin_field('SMS gönderen adı/numarası','sms_sender',setting('sms_sender')); admin_select('WhatsApp sağlayıcısı','whatsapp_provider',['none'=>'Kapalı','cloud'=>'Meta WhatsApp Cloud API','twilio'=>'Twilio WhatsApp'],setting('whatsapp_provider','none')); admin_select('E-posta bildirimi','notification_email_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_email_enabled','1')); admin_select('SMS bildirimi','notification_sms_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_sms_enabled','0')); admin_select('WhatsApp bildirimi','notification_whatsapp_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_whatsapp_enabled','0')); ?><button>Ayarları kaydet</button></form><form method="post" class="panel"><?php admin_action('test_smtp'); admin_field('Test alıcısı','test_recipient',setting('contact_email'),'email',true) ?><button class="secondary">SMTP testini kuyruğa al</button></form><form method="post" class="panel"><?php admin_action('test_notification'); admin_select('Kanal','channel',['sms'=>'SMS','whatsapp'=>'WhatsApp'],'sms'); admin_field('Alıcı telefon / WhatsApp','recipient',''); admin_field('Test mesajı','message','TofiDesk bildirim test mesajı.'); ?><button class="secondary">SMS/WhatsApp testini kuyruğa al</button></form></section><section class="panel"><h2>Entegrasyon sağlığı</h2><ul class="checklist"><?php foreach($checks as $label=>$ok): ?><li><?= $ok?'✓':'○' ?> <?=e($label)?> <span class="badge"><?= $ok?'Hazır':'Test modu / eksik' ?></span></li><?php endforeach ?></ul><h3>Adaptör kataloğu</h3><p class="muted">AI, SMTP, SMS, WhatsApp, harita, ödeme, depolama ve arama adaptörleri sağlayıcıdan bağımsızdır. Gizli değerler asla panelde gösterilmez.</p><ul class="checklist"><?php foreach($adapterStatuses as $adapter): ?><li><?= $adapter['configured']?'✓':'○' ?> <code><?=e($adapter['slug'])?></code> <span class="badge"><?=e($adapter['status'])?></span><?= $adapter['missing_env']!==[]?' <small>Eksik: '.e(implode(', ',$adapter['missing_env'])).'</small>':'' ?></li><?php endforeach ?></ul><p class="notice">Gerçek anahtarları sunucu ortamına ekleyin; ZIP paketlerine sır dahil edilmez.</p></section></div><?php
}

function admin_security_page(array $user): void
{
    $pending=(string)($_SESSION['totp_pending']??'');
    $active=!empty($user['totp_secret']);
    $secret=$pending!==''?$pending:(string)($user['totp_secret']??'');
    $uri=$secret!==''?'otpauth://totp/'.rawurlencode((string)setting('business_name','TofiDesk')).':'.rawurlencode((string)$user['email']).'?secret='.$secret.'&issuer='.rawurlencode((string)setting('business_name','TofiDesk')):'';
    ?><div class="split"><section class="panel"><h2>Hesap güvenliği</h2><p class="muted">Yönetici girişleri için TOTP tabanlı iki aşamalı doğrulama.</p><p>Durum: <span class="badge"><?= $active?'Etkin':'Etkin değil' ?></span></p><?php if(!$active): ?><form method="post"><?php admin_action('totp_start'); ?><button>2FA kurulumu başlat</button></form><?php if($pending!==''): ?><div class="notice"><p>Authenticator uygulamanıza şu anahtarı ekleyin:</p><code><?= e($secret) ?></code><p class="small">Destekleyen uygulamada bu URI kullanılabilir:</p><code><?= e($uri) ?></code><form method="post"><?php admin_action('totp_confirm'); admin_field('6 haneli doğrulama kodu','totp_code','','text',true); ?><button>2FA’yı etkinleştir</button></form></div><?php endif; ?><?php else: ?><form method="post"><?php admin_action('totp_disable'); admin_field('Mevcut 6 haneli kod','totp_code','','text',true); ?><button class="secondary">2FA’yı devre dışı bırak</button></form><?php endif; ?></section><section class="panel"><h2>Oturum politikası</h2><p>Oturum çerezleri HttpOnly, SameSite=Lax ve HTTPS ortamında Secure olarak ayarlanır. Başarılı girişte oturum kimliği yenilenir.</p><form method="post"><?php admin_action('revoke_sessions'); ?><button class="secondary">Tüm yönetici oturumlarını sonlandır</button></form><p class="muted">Bu işlem diğer cihazlardaki yönetici oturumlarını da geçersiz kılar.</p></section></div><?php
}

function admin_anomalies_page(): void
{
    $failedLogins = rows("SELECT ip, count(*) AS attempts, max(created_at) AS last_attempt FROM login_attempts WHERE created_at >= datetime('now','-24 hours') GROUP BY ip HAVING count(*) >= 5 ORDER BY attempts DESC, last_attempt DESC LIMIT 50");
    $failedJobs = rows("SELECT id, lang, mode, error, updated_at FROM jobs WHERE status='failed' ORDER BY updated_at DESC, id DESC LIMIT 50");
    $failedMail = rows("SELECT id, recipient, subject, attempts, last_error, created_at FROM mail_queue WHERE status='failed' ORDER BY created_at DESC, id DESC LIMIT 50");
    $securityAudit = rows("SELECT a.*, u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.action LIKE 'security.%' OR a.action LIKE '%license%' OR a.action LIKE '%session%' ORDER BY a.id DESC LIMIT 100");
    $count = count($failedLogins) + count($failedJobs) + count($failedMail);
    ?><p class="intro">Başarısız giriş kümeleri, kuyruk hataları ve lisans/oturum olayları burada birleştirilir. Bu ekran teşhis ve müdahale içindir; kayıtlar otomatik olarak silinmez.</p>
    <div class="stat-grid"><div class="stat"><span>İncelenecek uyarı</span><strong><?= (int)$count ?></strong><small>Son 24 saat ve açık hata kayıtları</small></div><div class="stat"><span>Şüpheli IP</span><strong><?= (int)count($failedLogins) ?></strong><small>En az 5 başarısız giriş</small></div><div class="stat"><span>Başarısız işler</span><strong><?= (int)count($failedJobs) ?></strong><small>AI/kuyruk üretim işleri</small></div><div class="stat"><span>Başarısız e-posta</span><strong><?= (int)count($failedMail) ?></strong><small>SMTP işçi kuyruğu</small></div></div>
    <section class="panel"><h2>Şüpheli giriş kümeleri</h2><?php if (!$failedLogins): ?><p class="muted">Son 24 saatte eşik üstü başarısız giriş yok.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>IP</th><th>Deneme</th><th>Son olay</th><th>Önlem</th></tr></thead><tbody><?php foreach ($failedLogins as $item): ?><tr><td><code><?= e((string)$item['ip']) ?></code></td><td><span class="badge badge-failed"><?= (int)$item['attempts'] ?></span></td><td><?= e((string)$item['last_attempt']) ?></td><td>Rate-limit ve 2FA günlüklerini inceleyin.</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <div class="split"><section class="panel"><h2>Başarısız işler</h2><?php if (!$failedJobs): ?><p class="muted">Başarısız iş yok.</p><?php else: ?><div class="request-list"><?php foreach ($failedJobs as $item): ?><details class="panel"><summary><strong>İş #<?= (int)$item['id'] ?> · <?= e(strtoupper((string)$item['lang'])) ?></strong><span class="badge badge-failed">failed</span></summary><p><?= e((string)($item['error'] ?: 'Hata ayrıntısı yok')) ?></p><small><?= e((string)$item['updated_at']) ?> · <?= e((string)$item['mode']) ?></small></details><?php endforeach; ?></div><?php endif; ?></section><section class="panel"><h2>Başarısız e-posta</h2><?php if (!$failedMail): ?><p class="muted">Başarısız e-posta yok.</p><?php else: ?><div class="request-list"><?php foreach ($failedMail as $item): ?><details class="panel"><summary><strong><?= e((string)$item['subject']) ?></strong><span class="badge badge-failed">failed</span></summary><p><?= e((string)$item['recipient']) ?> · <?= e((string)($item['last_error'] ?: 'Hata ayrıntısı yok')) ?></p><small><?= (int)$item['attempts'] ?> deneme · <?= e((string)$item['created_at']) ?></small></details><?php endforeach; ?></div><?php endif; ?></section></div>
    <section class="panel"><h2>Lisans ve oturum denetim olayları</h2><?php if (!$securityAudit): ?><p class="muted">Güvenlik kapsamına giren yeni audit olayı yok.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Zaman</th><th>İşlem</th><th>Ayrıntı</th><th>Aktör</th></tr></thead><tbody><?php foreach ($securityAudit as $item): ?><tr><td><?= e((string)$item['created_at']) ?></td><td><code><?= e((string)$item['action']) ?></code></td><td><?= e((string)$item['detail']) ?></td><td><?= e((string)($item['actor'] ?: 'Sistem')) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php
}

function admin_taxonomies_page(): void
{
    $taxonomies=rows("SELECT tx.*, (SELECT count(*) FROM taxonomy_terms tt WHERE tt.taxonomy_id=tx.id) term_count FROM taxonomies tx WHERE tx.status='enabled' ORDER BY tx.name");
    ?><div class="split"><section class="panel"><h2>Kategori ve etiket altyapısı</h2><p class="muted">Sektör modülleri taksonomi adlarını sağlar; bu ekran terimleri ve hiyerarşiyi yönetir. Kayıtlar arama, facet ve SEO filtrelerinde kullanılabilir.</p><form method="post"><?php admin_action('create_taxonomy'); admin_field('Taksonomi slug','taxonomy_slug','','text',true); admin_field('Taksonomi adı','taxonomy_name','','text',true); admin_select('Hiyerarşik yapı','taxonomy_hierarchical',['0'=>'Düz liste','1'=>'Üst/alt kategori'],'0'); ?><button>Taksonomiyi kaydet</button></form></section><section class="panel"><h2>Yeni terim</h2><?php $taxonomyChoices=[]; foreach($taxonomies as $tax) $taxonomyChoices[(string)$tax['slug']]=(string)$tax['name'].' · '.$tax['slug']; if($taxonomyChoices): ?><form method="post"><?php admin_action('create_term'); admin_select('Taksonomi','taxonomy_slug',$taxonomyChoices,array_key_first($taxonomyChoices)); admin_field('Terim slug','term_slug','','text',true); admin_field('Terim adı','term_name','','text',true); admin_select('Dil','term_locale',admin_locales(),admin_lang()); admin_field('Üst terim ID (isteğe bağlı)','term_parent_id','','number'); admin_textarea('Üst veri (JSON)','term_metadata','{}',4); ?><button>Terimi kaydet</button></form><?php else: ?><p class="muted">Önce bir taksonomi oluşturun veya modülleri senkronize edin.</p><?php endif; ?></section></div><?php
    if(!$taxonomies){admin_empty('Henüz taksonomi yok.','Modül senkronizasyonu veya yukarıdaki form ile kategori altyapısı oluşturun.');return;}
    ?><section class="panel"><h2>Taksonomiler ve terimler</h2><?php foreach($taxonomies as $tax): $terms=rows('SELECT id,slug,name,locale,parent_id FROM taxonomy_terms WHERE taxonomy_id=? ORDER BY locale,name',[(int)$tax['id']]); ?><details class="panel"><summary><strong><?=e((string)$tax['name'])?></strong> <code><?=e((string)$tax['slug'])?></code> · <?= (int)$tax['term_count'] ?> terim</summary><div class="table-wrap"><table><thead><tr><th>ID</th><th>Terim</th><th>Dil</th><th>Üst</th></tr></thead><tbody><?php foreach($terms as $term): ?><tr><td>#<?= (int)$term['id'] ?></td><td><strong><?=e((string)$term['name'])?></strong><small><code><?=e((string)$term['slug'])?></code></small></td><td><?=e(strtoupper((string)$term['locale']))?></td><td><?= $term['parent_id'] ? '#'.(int)$term['parent_id'] : '—' ?></td></tr><?php endforeach; if(!$terms): ?><tr><td colspan="4">Henüz terim yok.</td></tr><?php endif; ?></tbody></table></div></details><?php endforeach; ?></section><?php
}

function admin_menus_page(): void
{
    $menus = rows('SELECT * FROM platform_menus ORDER BY locale,slug');
    ?><section class="split"><section class="panel"><h2>Menü oluştur / güncelle</h2><p class="muted">Her dil için ayrı menü kaydı oluşturun. Menü URL’leri yalnızca HTTPS veya site içi yolları kabul eder.</p><form method="post"><?php admin_action('menu_save'); admin_field('Menü slug','menu_slug','','text',true); admin_select('Dil','menu_locale',admin_locales(),admin_lang()); admin_field('Menü adı','menu_name','','text',true); ?><button>Menüyü kaydet</button></form></section><section class="panel"><h2>Menüler</h2><?php if (!$menus): ?><p class="muted">Henüz menü yok.</p><?php else: ?><table><thead><tr><th>Menü</th><th>Dil</th><th>Öğe</th></tr></thead><tbody><?php foreach($menus as $menu): $items=rows('SELECT * FROM platform_menu_items WHERE menu_id=? ORDER BY sort_order,id',[(int)$menu['id']]); ?><tr><td><strong><?=e((string)$menu['name'])?></strong><br><code><?=e((string)$menu['slug'])?></code></td><td><?=e(strtoupper((string)$menu['locale']))?></td><td><?=count($items)?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section></section><?php
    foreach ($menus as $menu) {
        $items = rows('SELECT * FROM platform_menu_items WHERE menu_id=? ORDER BY sort_order,id', [(int)$menu['id']]);
        ?><section class="panel"><div class="section-heading"><h2><?=e((string)$menu['name'])?> · <?=e(strtoupper((string)$menu['locale']))?></h2><code><?=e((string)$menu['slug'])?></code></div><form method="post" class="split"><?php admin_action('menu_item'); ?><input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>"><div><?php admin_field('Etiket','item_label','','text',true); admin_field('Hedef URL','item_url','','text',true); ?></div><div><?php admin_field('Sıra','item_order','0','number'); admin_select('Hedef','item_target',['_self'=>'Aynı pencere','_blank'=>'Yeni pencere'],'_self'); ?><button>Menü öğesi ekle</button></div></form><?php if($items): ?><table><thead><tr><th>Etiket</th><th>URL</th><th>Hedef</th><th>Sıra</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=e((string)$item['label'])?></td><td><code><?=e((string)$item['target_url'])?></code></td><td><?=e((string)$item['target'])?></td><td><?= (int)$item['sort_order'] ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><p class="muted">Bu menüde henüz öğe yok.</p><?php endif; ?></section><?php
    }
}

function admin_forms_page(): void
{
    $forms = rows('SELECT * FROM platform_forms ORDER BY locale,slug');
    ?><section class="split"><section class="panel"><h2>Form oluştur / güncelle</h2><p class="muted">Formlar sektör modülleri ve tema bölümleri tarafından API veya web bileşeni olarak kullanılabilir.</p><form method="post"><?php admin_action('form_save'); admin_field('Form slug','form_slug','','text',true); admin_select('Dil','form_locale',admin_locales(),admin_lang()); admin_field('Form adı','form_name','','text',true); admin_textarea('Başarı mesajı','form_success','Başvurunuz alındı.',2); ?><button>Formu kaydet</button></form></section><section class="panel"><h2>Formlar</h2><?php if (!$forms): ?><p class="muted">Henüz form yok.</p><?php else: ?><table><thead><tr><th>Form</th><th>Dil</th><th>Alan</th><th>Başvuru</th></tr></thead><tbody><?php foreach($forms as $form): $fieldCount=(int)(row('SELECT count(*) AS n FROM platform_form_fields WHERE form_id=?',[(int)$form['id']])['n']??0); $submissionCount=(int)(row('SELECT count(*) AS n FROM platform_form_submissions WHERE form_id=?',[(int)$form['id']])['n']??0); ?><tr><td><strong><?=e((string)$form['name'])?></strong><br><code><?=e((string)$form['slug'])?></code></td><td><?=e(strtoupper((string)$form['locale']))?></td><td><?= $fieldCount ?></td><td><?= $submissionCount ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section></section><?php
    foreach ($forms as $form) {
        $fields = rows('SELECT * FROM platform_form_fields WHERE form_id=? ORDER BY sort_order,id', [(int)$form['id']]);
        $submissions = rows('SELECT * FROM platform_form_submissions WHERE form_id=? ORDER BY created_at DESC,id DESC LIMIT 20', [(int)$form['id']]);
        ?><section class="panel"><div class="section-heading"><h2><?=e((string)$form['name'])?> · <?=e(strtoupper((string)$form['locale']))?></h2><code><?=e((string)$form['slug'])?></code></div><form method="post" class="split"><?php admin_action('form_field'); ?><input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>"><div><?php admin_field('Alan slug','field_slug','','text',true); admin_field('Etiket','field_label','','text',true); admin_select('Alan türü','field_type',['text'=>'Metin','textarea'=>'Uzun metin','email'=>'E-posta','url'=>'URL','number'=>'Sayı','boolean'=>'Evet/Hayır','date'=>'Tarih','datetime'=>'Tarih/saat','json'=>'JSON'],'text'); ?></div><div><?php admin_select('Zorunlu','field_required',['0'=>'Hayır','1'=>'Evet'],'0'); admin_field('Sıra','field_order','0','number'); admin_textarea('Yapılandırma JSON','field_config','{}',3); ?><button>Alan ekle</button></div></form><?php if($fields): ?><h3>Alanlar</h3><table><thead><tr><th>Alan</th><th>Tür</th><th>Zorunlu</th><th>Sıra</th></tr></thead><tbody><?php foreach($fields as $field): ?><tr><td><?=e((string)$field['label'])?><br><code><?=e((string)$field['slug'])?></code></td><td><?=e((string)$field['field_type'])?></td><td><?=!empty($field['required'])?'Evet':'Hayır'?></td><td><?= (int)$field['sort_order'] ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?><?php if($submissions): ?><h3>Son başvurular</h3><table><thead><tr><th>Tarih</th><th>Dil</th><th>Durum</th><th>Veri</th></tr></thead><tbody><?php foreach($submissions as $submission): ?><tr><td><?=e((string)$submission['created_at'])?></td><td><?=e(strtoupper((string)$submission['locale']))?></td><td><?=e((string)$submission['status'])?></td><td><pre class="job-result"><?=e((string)$submission['payload_json'])?></pre></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section><?php
    }
}

function admin_modules_page(?string $kind = null): void
{
    $kind = in_array($kind, ['sector', 'plugin'], true) ? $kind : null;
    $manifests = platform_discover_module_manifests($kind === 'plugin' ? ROOT.'/plugins' : ($kind === 'sector' ? ROOT.'/modules' : null));
    $heading = $kind === 'plugin' ? 'Güvenli eklenti ve SDK yönetimi' : ($kind === 'sector' ? 'Güvenli modül ve sektör paketi yönetimi' : 'Güvenli modül, eklenti ve sektör paketi yönetimi');
    $command = $kind === 'plugin' ? 'php bin/plugin.php validate plugins' : ($kind === 'sector' ? 'php bin/module.php validate modules' : 'php bin/module.php validate');
    $rows = '';
    foreach ($manifests as $item) {
        $manifest = is_array($item['manifest']) ? $item['manifest'] : [];
        $slug = (string)($manifest['slug'] ?? basename((string)$item['path']));
        $registered = row('SELECT * FROM platform_modules WHERE slug=?', [$slug]);
        $errors = $item['errors'];
        $status = (string)($registered['status'] ?? 'unregistered');
        $action = $registered && $errors === [] ? '<form method="post" class="inline-form">'.admin_action('module_status').'<input type="hidden" name="id" value="'.(int)$registered['id'].'"><input type="hidden" name="status" value="'.($status==='enabled'?'disabled':'enabled').'"><button class="secondary">'.($status==='enabled'?'Devre dışı bırak':'Etkinleştir').'</button></form>' : '—';
        $rows .= '<tr><td><strong>'.e((string)($manifest['name'] ?? $slug)).'</strong><br><code>'.e($slug).'</code></td><td>'.e((string)($manifest['kind'] ?? 'unknown')).'</td><td>'.e((string)($manifest['version'] ?? '—')).'</td><td>'.e($status).'</td><td>'.($errors===[]?'<span class="badge badge-published">Geçerli</span>':'<span class="badge badge-failed">'.e(implode(', ', $errors)).'</span>').'</td><td>'.$action.'</td></tr>';
    }
    ?><section class="panel"><h2><?=e($heading)?></h2><p class="muted">Manifestler yalnızca okunur ve doğrulanır. Paket kodu kalite kapısı, imza, uyumluluk ve yönetici onayı olmadan çalıştırılmaz.</p><p><code><?=e($command)?></code> · Sektör ve eklenti paketleri çekirdeği kopyalamadan içerik türleri, taksonomiler ve ayarlar sağlar.</p></section><section class="panel"><table><thead><tr><th>Modül / Eklenti</th><th>Tür</th><th>Sürüm</th><th>Durum</th><th>Manifest</th><th>İşlem</th></tr></thead><tbody><?= $rows ?: '<tr><td colspan="6">Henüz paket yok.</td></tr>' ?></tbody></table></section><?php
    foreach ($manifests as $item) {
        $manifest=is_array($item['manifest'])?$item['manifest']:[]; $slug=(string)($manifest['slug']??''); $registered=$slug!==''?row('SELECT * FROM platform_modules WHERE slug=?',[$slug]):null; $schema=(array)($manifest['settings']??[]); $fields=(array)($manifest['fields']??[]);
        if (!$registered || $item['errors']!==[]) continue;
        if ($fields !== []) {
            $fieldRows=''; $fieldCount=0;
            foreach ($fields as $type=>$definitions) {
                foreach ((array)$definitions as $definition) {
                    if (!is_array($definition)) continue;
                    $fieldCount++;
                    $config=(array)($definition['config']??[]);
                    $fieldRows.='<tr><td><code>'.e((string)$type).'</code></td><td><strong>'.e((string)($definition['label']??$definition['slug']??'')).'</strong><br><code>'.e((string)($definition['slug']??'')).'</code></td><td>'.e((string)($definition['field_type']??'text')).'</td><td>'.e($config===[]?'—':json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</td></tr>';
                }
            }
            ?><section class="panel"><div class="section-heading"><h2><?=e((string)($manifest['name']??$slug))?> · içerik veri modeli</h2><span class="badge badge-published"><?= (int)$fieldCount ?> alan</span></div><p class="muted">Bu alanlar manifestten güvenli biçimde senkronize edilir; tema yalnızca bu veri modelini sunum katmanında kullanır.</p><div class="table-wrap"><table><thead><tr><th>İçerik tipi</th><th>Alan</th><th>Tür</th><th>Kısıt / yapılandırma</th></tr></thead><tbody><?=$fieldRows?></tbody></table></div></section><?php
        }
        if (!$schema) continue;
        $stored=platform_module_settings($slug);
        ?><section class="panel"><h2><?=e((string)($manifest['name']??$slug))?> · sektör ayarları</h2><p class="muted">Bu ayarlar yalnızca bu pakete aittir ve çekirdek kodunu değiştirmez.</p><?php foreach($schema as $key=>$definition): $definition=is_array($definition)?$definition:[]; $value=$stored[$key]??($definition['default']??''); $json=is_string($value)?$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?><form method="post" class="panel"><?php admin_action('module_setting'); ?><input type="hidden" name="module_id" value="<?= (int)$registered['id'] ?>"><input type="hidden" name="setting_key" value="<?=e((string)$key)?>"><label><?=e((string)($definition['label']??$key))?><small><?=e((string)($definition['description']??''))?></small><textarea name="setting_value" rows="3" maxlength="20000"><?=e((string)$json)?></textarea></label><button>Sektör ayarını kaydet</button></form><?php endforeach; ?></section><?php
    }
}

function admin_locations_page(array $user): void
{
    $lang = admin_user_lang($user);
    $parentId = max(0, (int)($_GET['parent_id'] ?? 0));
    $parent = $parentId > 0 ? row('SELECT * FROM platform_locations WHERE id=?', [$parentId]) : null;
    if ($parentId > 0 && !$parent) throw new InvalidArgumentException('Konum bulunamadı.');
    $roots = rows("SELECT * FROM platform_locations WHERE parent_id IS NULL ORDER BY name");
    $items = $parentId > 0 ? platform_location_children($parentId) : $roots;
    $rows = '';
    foreach ($items as $item) {
        $childCount = (int)(row('SELECT count(*) AS total FROM platform_locations WHERE parent_id=?', [(int)$item['id']])['total'] ?? 0);
        $rows .= '<tr><td><strong>'.e((string)$item['name']).'</strong><br><code>'.e((string)$item['country_code'].' · '.(string)$item['level']).'</code></td><td>'.e((string)$item['slug']).'</td><td>'.e((string)($item['postal_code'] ?? '—')).'</td><td>'.(int)$childCount.'</td><td><a class="button ghost" href="/admin/locations?parent_id='.(int)$item['id'].'">'.e(admin_location_t('open',$lang)).'</a></td></tr>';
    }
    $back = $parent && !empty($parent['parent_id']) ? '<a href="/admin/locations?parent_id='.(int)$parent['parent_id'].'">'.e(admin_location_t('back_up',$lang)).'</a>' : ($parent ? '<a href="/admin/locations">'.e(admin_location_t('back_countries',$lang)).'</a>' : '');
    ?><section class="panel"><h2><?=e(admin_location_t('heading',$lang))?></h2><p class="muted"><?=e(admin_location_t('intro',$lang))?></p><p><?= $back ?></p><form method="post" class="panel"><h3><?=e(admin_location_t('import_heading',$lang))?></h3><p class="small muted"><?=e(admin_location_t('import_help',$lang))?> <code>{"locations":[{"key":"tr","country_code":"TR","level":"country","name":"Türkiye","slug":"tr"}]}</code>. <?=e(admin_location_t('parent_help',$lang))?></p><?php admin_action('import_locations'); ?><textarea name="locations_json" rows="8" maxlength="2000000" required placeholder='{"locations":[]}'></textarea><button><?=e(admin_location_t('import_button',$lang))?></button></form></section><section class="panel"><h2><?= $parent ? e((string)$parent['name']) : e(admin_location_t('countries',$lang)) ?></h2><table><thead><tr><th><?=e(admin_location_t('location',$lang))?></th><th><?=e(admin_location_t('slug',$lang))?></th><th><?=e(admin_location_t('postal',$lang))?></th><th><?=e(admin_location_t('children',$lang))?></th><th></th></tr></thead><tbody><?= $rows ?: '<tr><td colspan="5">'.e(admin_location_t('empty',$lang)).'</td></tr>' ?></tbody></table></section><?php
}

function handle_admin(string $path): void
{
    header('Cache-Control: no-store');
    $section = trim(substr($path, strlen('/admin')), '/') ?: 'dashboard';
    if ($section === 'login') { admin_login_page(); return; }
    $user = require_login();
    if (!in_array($section, ['dashboard','logout','requests','appointments','content','content-models','editor','media','gallery','seo','settings','security','anomalies','email-templates','notification-rules','integrations','users','jobs','audit','backup','mail','languages','themes','licenses','updates','modules','plugins','menus','forms','locations','taxonomies','legal'], true)) { http_response_code(404); admin_layout('Bulunamadı','dashboard',$user,'<div class="panel">Bu sayfa bulunamadı.</div>'); return; }
    if (!admin_allowed($section, $user)) render_error(403, 'Bu işlem için yetkiniz yok.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        try {
            admin_post($section, $user);
        } catch (InvalidArgumentException $e) { flash('error',$e->getMessage()); }
        catch (Throwable $e) { error_log('Admin action failed: '.$e->getMessage()); flash('error','İşlem tamamlanamadı. Alanları ve sunucu günlüklerini kontrol edin.'); }
        $destination = '/admin'.($section === 'dashboard' ? '' : '/'.$section);
        if ($section === 'editor') $destination .= '?id='.(int)($_POST['id'] ?? 0).'&lang='.rawurlencode((string)($_POST['lang'] ?? 'tr'));
        redirect($destination);
    }
    if ($section === 'logout') { http_response_code(405); header('Allow: POST'); return; }
    $titles=array_fill_keys(['dashboard','requests','appointments','content','content-models','editor','media','gallery','seo','settings','email-templates','notification-rules','integrations','security','anomalies','users','jobs','audit','backup','mail','languages','themes','licenses','updates','modules','plugins','menus','forms','locations','taxonomies','legal'], '');
    foreach (array_keys($titles) as $key) $titles[$key]=admin_section_title($key, admin_user_lang($user));
    ob_start();
    match($section) {
        'requests' => admin_requests_page($section), 'appointments' => admin_appointments_page(),
        'content' => admin_content_page(), 'content-models'=>admin_content_models_page_v2(), 'editor'=>admin_editor_page(), 'media'=>admin_media_page(), 'gallery'=>admin_gallery_page(), 'seo'=>admin_seo_page(),
        'settings'=>admin_settings_page(), 'email-templates'=>admin_email_templates_page(), 'notification-rules'=>admin_notification_rules_page(), 'integrations'=>admin_integrations_page(), 'security'=>admin_security_page($user), 'anomalies'=>admin_anomalies_page(), 'users'=>admin_users_page(), 'jobs'=>admin_jobs_page(), 'audit'=>admin_audit_page(),
        'backup'=>admin_backup_page(), 'mail'=>admin_mail_page(), 'languages'=>admin_languages_page(), 'themes'=>admin_themes_page(), 'licenses'=>admin_licenses_page(), 'updates'=>admin_updates_page(), 'modules'=>admin_modules_page('sector'), 'plugins'=>admin_modules_page('plugin'), 'menus'=>admin_menus_page(), 'forms'=>admin_forms_page(), 'locations'=>admin_locations_page($user), 'taxonomies'=>admin_taxonomies_page(), 'legal'=>admin_legal_page(), default=>admin_dashboard($user),
    };
    admin_layout($titles[$section],$section,$user,(string)ob_get_clean());
}

function admin_post(string $section, array $user): void
{
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'set_admin_locale') { admin_save_locale($user); return; }
    if ($section === 'logout') { logout_user(); redirect('/admin/login'); }
    if ($section === 'locations' && $action === 'import_locations') {
        $raw = trim((string)($_POST['locations_json'] ?? ''));
        if ($raw === '' || strlen($raw) > 2000000) throw new InvalidArgumentException('Konum JSON verisi boş veya çok büyük.');
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $records = is_array($payload) && isset($payload['locations']) ? $payload['locations'] : $payload;
        if (!is_array($records) || count($records) > 10000) throw new InvalidArgumentException('Konum listesi geçersiz veya çok büyük.');
        $keys=[]; foreach ($records as $record) { if (!is_array($record) || trim((string)($record['key'] ?? '')) === '') throw new InvalidArgumentException('Her konum için benzersiz key gerekir.'); $key=trim((string)$record['key']); if (isset($keys[$key])) throw new InvalidArgumentException('Konum key tekrarlanıyor: '.$key); $keys[$key]=true; }
        $resolved=[]; $remaining=array_values($records); db()->beginTransaction();
        try {
            while ($remaining) {
                $next=[]; $progress=false;
                foreach ($remaining as $record) {
                    $parentKey=trim((string)($record['parent_key']??''));
                    if ($parentKey!=='' && !isset($resolved[$parentKey])) { $next[]=$record; continue; }
                    $location=$record; unset($location['key'],$location['parent_key']);
                    if ($parentKey!=='') $location['parent_id']=$resolved[$parentKey];
                    $resolved[(string)$record['key']]=platform_upsert_location($location); $progress=true;
                }
                if (!$progress && $next) throw new InvalidArgumentException('Konum parent_key çözülemedi: '.(string)($next[0]['parent_key']??''));
                $remaining=$next;
            }
            db()->commit();
        } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
        audit('platform.locations_imported', (string)count($resolved)); flash('success',count($resolved).' konum kaydı doğrulanarak içe aktarıldı.'); return;
    }
    if ($section === 'menus' && $action === 'menu_save') {
        $slug = strtolower(admin_input('menu_slug', 80));
        $locale = admin_input('menu_locale', 10);
        $name = admin_input('menu_name', 180);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,78}$/D', $slug) || !isset(admin_locales()[$locale]) || $name === '') throw new InvalidArgumentException('Menü slug, dil ve ad alanlarını kontrol edin.');
        $id = platform_upsert_menu($slug, $locale, $name);
        audit('platform.menu_saved', '#'.$id.' '.$slug.'/'.$locale);
        flash('success', 'Menü kaydedildi.');
        return;
    }
    if ($section === 'menus' && $action === 'menu_item') {
        $menuId = (int)($_POST['menu_id'] ?? 0);
        $label = admin_input('item_label', 180);
        $url = admin_input('item_url', 1000);
        $target = admin_input('item_target', 10);
        $order = (int)($_POST['item_order'] ?? 0);
        if ($menuId < 1 || $label === '' || $url === '' || !in_array($target, ['_self','_blank'], true) || $order < 0 || $order > 10000) throw new InvalidArgumentException('Menü öğesi alanlarını kontrol edin.');
        $id = platform_add_menu_item($menuId, ['label'=>$label, 'target_url'=>$url, 'target'=>$target, 'sort_order'=>$order]);
        audit('platform.menu_item_saved', '#'.$id.' menu #'.$menuId);
        flash('success', 'Menü öğesi eklendi.');
        return;
    }
    if ($section === 'forms' && $action === 'form_save') {
        $slug = strtolower(admin_input('form_slug', 80));
        $locale = admin_input('form_locale', 10);
        $name = admin_input('form_name', 180);
        $success = admin_input('form_success', 500);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,78}$/D', $slug) || !isset(admin_locales()[$locale]) || $name === '') throw new InvalidArgumentException('Form slug, dil ve ad alanlarını kontrol edin.');
        $id = platform_upsert_form($slug, $locale, $name, $success);
        audit('platform.form_saved', '#'.$id.' '.$slug.'/'.$locale);
        flash('success', 'Form kaydedildi.');
        return;
    }
    if ($section === 'forms' && $action === 'form_field') {
        $formId = (int)($_POST['form_id'] ?? 0);
        $slug = strtolower(admin_input('field_slug', 80));
        $label = admin_input('field_label', 180);
        $type = admin_input('field_type', 20);
        $required = ($_POST['field_required'] ?? '0') === '1';
        $order = (int)($_POST['field_order'] ?? 0);
        $rawConfig = trim((string)($_POST['field_config'] ?? '{}'));
        $config = json_decode($rawConfig === '' ? '{}' : $rawConfig, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config)) throw new InvalidArgumentException('Form alanı yapılandırması JSON nesnesi olmalı.');
        if ($formId < 1 || $slug === '' || $label === '' || $order < 0 || $order > 10000) throw new InvalidArgumentException('Form alanı bilgilerini kontrol edin.');
        $id = platform_add_form_field($formId, ['slug'=>$slug, 'label'=>$label, 'field_type'=>$type, 'required'=>$required, 'config'=>$config, 'sort_order'=>$order]);
        audit('platform.form_field_saved', '#'.$id.' form #'.$formId);
        flash('success', 'Form alanı eklendi.');
        return;
    }
    if (in_array($section, ['modules','plugins'], true) && $action === 'module_status') {
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??'disabled');
        if (!in_array($status,['enabled','disabled'],true) || !row('SELECT id FROM platform_modules WHERE id=?',[$id])) throw new InvalidArgumentException('Modül durumu geçersiz.');
        query('UPDATE platform_modules SET status=?,updated_at=datetime(\'now\') WHERE id=?',[$status,$id]); audit('platform.module_status', '#'.$id.' '.$status); flash('success','Modül durumu güncellendi.'); return;
    }
    if (in_array($section, ['modules','plugins'], true) && $action === 'module_setting') {
        $id=(int)($_POST['module_id']??0); $key=admin_input('setting_key',80); $raw=admin_input('setting_value',20000);
        if (!row('SELECT id FROM platform_modules WHERE id=? AND kind IN (\'sector\',\'plugin\')',[$id])) throw new InvalidArgumentException('Modül bulunamadı.');
        $value=json_decode($raw,true);
        if (json_last_error()!==JSON_ERROR_NONE) throw new InvalidArgumentException('Ayar JSON biçiminde olmalı.');
        platform_save_module_setting($id,$key,$value,(int)$user['id']); audit('platform.module_setting',$key); flash('success','Modül ayarı kaydedildi.'); return;
    }
    if ($section === 'taxonomies' && $action === 'create_taxonomy') {
        $slug=admin_input('taxonomy_slug',90); $name=admin_input('taxonomy_name',180); $hierarchical=($_POST['taxonomy_hierarchical']??'0')==='1';
        $id=platform_upsert_taxonomy($slug,$name,$hierarchical); audit('platform.taxonomy_saved','#'.$id.' '.$slug); flash('success','Taksonomi kaydedildi.'); return;
    }
    if ($section === 'taxonomies' && $action === 'create_term') {
        $taxonomy=admin_input('taxonomy_slug',90); $slug=admin_input('term_slug',110); $name=admin_input('term_name',180); $locale=admin_input('term_locale',10); $parent=max(0,(int)($_POST['term_parent_id']??0)); $raw=trim((string)($_POST['term_metadata']??''));
        $metadata=$raw===''?[]:json_decode($raw,true,512,JSON_THROW_ON_ERROR); if(!is_array($metadata)) throw new InvalidArgumentException('Terim üst verisi JSON nesnesi olmalı.');
        $id=platform_upsert_term($taxonomy,$slug,$name,$locale,$parent>0?$parent:null,$metadata); audit('platform.taxonomy_term_saved','#'.$id.' '.$taxonomy.'/'.$slug); flash('success','Taksonomi terimi kaydedildi.'); return;
    }
    if ($section === 'content-models' && in_array($action, ['create_entry','update_entry'], true)) {
        $entryId=(int)($_POST['entry_id']??0);
        $type=admin_input('content_type',100); $slug=admin_input('entry_slug',120); $raw=trim((string)($_POST['entry_data']??'')); $termsRaw=trim((string)($_POST['entry_term_ids']??'[]')); $title=admin_input('entry_title',220); $summary=admin_input('entry_summary',2000); $body=admin_input('entry_body',100000); $lang=admin_input('entry_lang',10); $status=admin_input('entry_status',10); $locationId=max(0,(int)($_POST['entry_location_id']??0));
        if (!platform_content_type($type)) throw new InvalidArgumentException('İçerik modeli bulunamadı.');
        if ($raw==='') $data=[]; else { $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR); if(!is_array($data)) throw new InvalidArgumentException('Alan verisi JSON nesnesi olmalı.'); }
        $termIds=$termsRaw===''?[]:json_decode($termsRaw,true,512,JSON_THROW_ON_ERROR); if(!is_array($termIds) || array_filter($termIds,static fn(mixed $id):bool=>filter_var($id,FILTER_VALIDATE_INT)===false || (int)$id<1)!==[]) throw new InvalidArgumentException('Terim ID listesi pozitif tam sayılardan oluşmalı.'); $termIds=array_values(array_map('intval',$termIds));
        if (!isset(admin_locales()[$lang]) || !in_array($status,['draft','review','published'],true) || $title==='') throw new InvalidArgumentException('Dil, yayın durumu ve başlık zorunludur.');
        if ($action === 'create_entry') {
            $entryId=platform_create_entry($type,$slug,$data,(int)$user['id'],$locationId>0?$locationId:null);
            audit('platform.content_entry_created','#'.$entryId.' '.$type);
            flash('success','Sektör içeriği oluşturuldu ve '.$status.' durumuna alındı.');
        } else {
            if ($entryId < 1) throw new InvalidArgumentException('Düzenlenecek sektör kaydı seçilmedi.');
            $entry=row('SELECT ce.id,ct.slug AS content_type FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id WHERE ce.id=?',[$entryId]);
            if (!$entry || (string)$entry['content_type'] !== $type) throw new InvalidArgumentException('Sektör kaydı bulunamadı veya içerik modeli değiştirilemez.');
            platform_update_entry($entryId,$slug,$data,$locationId>0?$locationId:null);
            audit('platform.content_entry_updated','#'.$entryId.' '.$type);
            flash('success','Sektör içeriği güncellendi ve '.$status.' durumuna alındı.');
        }
        platform_save_translation($entryId,$lang,['title'=>$title,'summary'=>$summary,'body'=>$body,'seo'=>['title'=>$title,'description'=>$summary]],$status); platform_set_entry_terms($entryId,$termIds); return;
    }
    if (in_array($section,['requests','appointments'],true) && $action === 'request') { admin_save_request(); return; }
    if ($section === 'appointments' && $action === 'calendar_event') {
        $title=admin_input('event_title',160); $start=admin_input('start_date',10); $end=admin_input('end_date',10) ?: $start; $notes=admin_input('event_notes',2000); $color=admin_input('event_color',20);
        $startDate=DateTimeImmutable::createFromFormat('!Y-m-d',$start); $endDate=DateTimeImmutable::createFromFormat('!Y-m-d',$end);
        if($title===''||!$startDate||$startDate->format('Y-m-d')!==$start||!$endDate||$endDate->format('Y-m-d')!==$end||$end<$start||!preg_match('/^#[0-9a-f]{6}$/i',$color)) throw new InvalidArgumentException('Takvim etkinliği bilgileri geçersiz.');
        query('INSERT INTO calendar_events(title,start_date,end_date,notes,color,created_by) VALUES(?,?,?,?,?,?)',[$title,$start,$end,$notes,$color,$user['id']]); audit('calendar.event_created',$title); flash('success','Takvim etkinliği eklendi.'); return;
    }
    if (in_array($section,['requests','appointments'],true) && $action === 'reply') {
        $id=(int)($_POST['id']??0); $request=row('SELECT * FROM requests WHERE id=?',[$id]);
        $subject=admin_input('subject',180); $message=admin_input('message',10000);
        if(!$request || !filter_var($request['email'],FILTER_VALIDATE_EMAIL) || $subject==='' || $message==='') throw new InvalidArgumentException('Talep, konu veya mesaj geçersiz.');
        enqueue_mail($request['email'],$subject,$message,$id); audit('request.reply_queued','#'.$id); flash('success','Yanıt SMTP kuyruğuna alındı. Gönderim durumu E-posta kuyruğunda görünür.'); return;
    }
    if ($section === 'content' && $action === 'create') {
        $kind=admin_input('kind',20); $lang=admin_lang();
        if (!in_array($kind,['article','service','page'],true)) throw new InvalidArgumentException('İçerik türü geçersiz.');
        query("INSERT INTO articles(kind,source_lang,category,created_at,updated_at) VALUES(?,?,?,datetime('now'),datetime('now'))",[$kind,$lang,admin_input('category',80)]);
        $id=(int)db()->lastInsertId(); audit('content.created','#'.$id);
        redirect('/admin/editor?id='.$id.'&lang='.$lang);
    }
    if ($section === 'editor' && $action === 'save') { admin_save_translation(); return; }
    if ($section === 'languages' && $action === 'create_language') {
        $code=strtolower(admin_input('code',20)); $native=admin_input('native_name',80); $english=admin_input('english_name',80); $direction=admin_input('direction',3);
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/D',$code) || isset(admin_locales()[$code]) || $native==='' || $english==='' || !in_array($direction,['ltr','rtl'],true)) throw new InvalidArgumentException('Dil kodu, adlar veya yazı yönü geçersiz; mevcut paketlerin kodları tekrar kullanılamaz.');
        query("INSERT INTO site_languages(code,native_name,english_name,direction,status,ui_status,ui_catalog,created_at,updated_at) VALUES(?,?,?,?, 'disabled','draft','',datetime('now'),datetime('now'))",[$code,$native,$english,$direction]);
        audit('language.created',$code); flash('success','Yeni dil paketi taslak olarak eklendi. Arayüz sözlüğünü hazırlayın ve inceleyin.'); return;
    }
    if ($section === 'languages' && $action === 'translate_interface') {
        $code=admin_input('code',20); $language=row('SELECT * FROM site_languages WHERE code=?',[$code]); if(!$language) throw new InvalidArgumentException('Özel dil paketi bulunamadı.');
        $dictionary=ai_translate_interface_catalog($code,$language['english_name']);
        query("UPDATE site_languages SET ui_catalog=?,ui_status='review',updated_at=datetime('now') WHERE code=?",[json_encode($dictionary,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$code]); audit('language.ai_translated',$code); flash('success','AI arayüz sözlüğünü inceleme durumunda hazırladı. Yayınlamadan önce sözlüğü kontrol edin.'); return;
    }
    if ($section === 'languages' && $action === 'save_language') {
        $code=admin_input('code',20); $language=row('SELECT * FROM site_languages WHERE code=?',[$code]); if(!$language) throw new InvalidArgumentException('Özel dil paketi bulunamadı.');
        $raw=admin_input('ui_catalog',180000); $uiStatus=admin_input('ui_status',10); $status=admin_input('status',10);
        if (!in_array($uiStatus,['draft','review','published'],true)||!in_array($status,['enabled','disabled'],true)) throw new InvalidArgumentException('Dil paketi durumu geçersiz.');
        if ($raw !== '') { try { $dictionary=json_decode($raw,true,512,JSON_THROW_ON_ERROR); validate_interface_catalog($dictionary); } catch (JsonException) { throw new InvalidArgumentException('Arayüz sözlüğü geçerli JSON olmalı.'); } } else $dictionary=[];
        if (($uiStatus==='published' || $status==='enabled') && !$dictionary) throw new InvalidArgumentException('Yayın veya etkinleştirme için eksiksiz arayüz sözlüğü gerekli.');
        if ($uiStatus!=='published') $status='disabled';
        query("UPDATE site_languages SET status=?,ui_status=?,ui_catalog=?,updated_at=datetime('now') WHERE code=?",[$status,$uiStatus,$raw,$code]); audit('language.saved',$code.' '.$status.'/'.$uiStatus); flash('success','Dil paketi kaydedildi.'); return;
    }
    if ($section === 'editor' && $action === 'generate') {
        $id=(int)($_POST['id'] ?? 0); $article=row('SELECT * FROM articles WHERE id=?',[$id]);
        if (!$article) throw new InvalidArgumentException('İçerik bulunamadı.');
        $mode=admin_input('mode',20); if (!in_array($mode,['write','improve','localize','image'],true)) throw new InvalidArgumentException('Üretim türü geçersiz.');
        $prompt=admin_input('prompt',6000); if ($prompt === '') throw new InvalidArgumentException('İş için konu veya yönerge girin.');
        $langs=$_POST['langs'] ?? []; if (!is_array($langs) || !$langs) throw new InvalidArgumentException('Yayınlanacak en az bir dil seçin.');
        foreach ($langs as $lang) if (!is_string($lang)||!isset(admin_locales()[$lang])) throw new InvalidArgumentException('Yayın dili geçersiz.');
        $langs=array_values(array_unique($langs));
        db()->exec('BEGIN IMMEDIATE');
        try {
            $pending=(int)(row("SELECT count(*) n FROM jobs WHERE status IN ('queued','running')")['n']??0);
            if ($pending+count($langs)>100) throw new InvalidArgumentException('Bu dil grubu kuyruk sınırını aşıyor. Önce mevcut işleri tamamlayın veya daha az dil seçin. Hiçbir yeni iş eklenmedi.');
            foreach ($langs as $lang) enqueue_ai_job($id,$lang,$mode,$prompt);
            audit('ai.batch_queued','#'.$id.' '.implode(',',$langs));
            db()->exec('COMMIT');
        } catch (Throwable $error) { db()->exec('ROLLBACK'); throw $error; }
        flash('success',count($langs).' dil için işler kuyruğa alındı. Çıktılar otomatik yayınlanmaz.'); return;
    }
    if ($section === 'jobs' && $action === 'apply') { admin_apply_job(); return; }
    if ($section === 'jobs' && $action === 'retry') {
        $job=row("SELECT * FROM jobs WHERE id=? AND status='failed'",[(int)($_POST['id'] ?? 0)]);
        if (!$job) throw new InvalidArgumentException('Yeniden denenebilir iş bulunamadı.');
        enqueue_ai_job((int)$job['article_id'],$job['lang'],$job['mode'],$job['prompt']);
        audit('ai.retry','Önceki başarısız iş #'.$job['id']); flash('success','Önceki hata ve deneme kaydı korundu. Yeni iş kuyruğa alındı.'); return;
    }
    if ($section === 'media' && $action === 'upload') { $id=media_upload($_FILES['photo'] ?? [],admin_lang(),'library'); audit('media.uploaded','#'.$id); flash('success','Görsel yüklendi.'); return; }
    if ($section === 'media' && $action === 'metadata') {
        $id=(int)($_POST['id'] ?? 0); $lang=admin_lang(); if (!row("SELECT id FROM media WHERE id=? AND purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=media.id)",[$id])) throw new InvalidArgumentException('Görsel bulunamadı.');
        query('INSERT INTO media_translations(media_id,lang,title,alt,caption) VALUES(?,?,?,?,?) ON CONFLICT(media_id,lang) DO UPDATE SET title=excluded.title,alt=excluded.alt,caption=excluded.caption',[$id,$lang,admin_input('title',180),admin_input('alt',500),admin_input('caption',1000)]);
        audit('media.metadata','#'.$id.' '.$lang); flash('success','Dil metinleri kaydedildi.'); return;
    }
    if ($section === 'gallery' && $action === 'gallery') {
        $mediaId=(int)($_POST['media_id'] ?? 0); $status=admin_input('status',20); $order=(int)($_POST['sort_order'] ?? 0);
        if (!in_array($status,['draft','published'],true) || $order < -100000 || $order > 100000) throw new InvalidArgumentException('Galeri durumu veya sırası geçersiz.');
        if (!row("SELECT id FROM media WHERE id=? AND purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=media.id)",[$mediaId])) throw new InvalidArgumentException('Yalnızca gerçek, kütüphanedeki görseller galeriye eklenebilir.');
        query("INSERT INTO gallery_items(media_id,status,sort_order,updated_at) VALUES(?,?,?,datetime('now')) ON CONFLICT(media_id) DO UPDATE SET status=excluded.status,sort_order=excluded.sort_order,updated_at=excluded.updated_at",[$mediaId,$status,$order]);
        audit('gallery.saved','#'.$mediaId.' '.$status); flash('success','Galeri kaydı güncellendi.'); return;
    }
    if ($section === 'themes' && $action === 'theme') {
        $id=(int)($_POST['id']??0); $name=admin_input('name',100); $locale=admin_input('locale',20); $primary=admin_input('primary_color',7); $accent=admin_input('accent_color',7); $css=admin_input('custom_css',12000); $status=admin_input('status',10); $active=(string)($_POST['is_active']??'0')==='1';
        if($name==='' || ($locale!=='global' && !isset(admin_locales()[$locale])) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$primary) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$accent) || !in_array($status,['draft','published'],true) || str_contains(strtolower($css),'</style')) throw new InvalidArgumentException('Tema alanlarını kontrol edin.');
        db()->exec('BEGIN IMMEDIATE'); try { if($active){ if($id) query('UPDATE themes SET is_active=0 WHERE id=?',[$id]); query('UPDATE themes SET is_active=0 WHERE locale=?',[$locale]); $status='published'; } if($id){query("UPDATE themes SET name=?,locale=?,primary_color=?,accent_color=?,custom_css=?,status=?,is_active=?,updated_at=datetime('now') WHERE id=?",[$name,$locale,$primary,$accent,$css,$status,$active?1:0,$id]);} else {query('INSERT INTO themes(name,locale,primary_color,accent_color,custom_css,status,is_active) VALUES(?,?,?,?,?,?,?)',[$name,$locale,$primary,$accent,$css,$status,$active?1:0]);} db()->exec('COMMIT'); } catch(Throwable $e){db()->exec('ROLLBACK'); throw $e;}
        audit('theme.saved',$name); flash('success','Tema kaydedildi. Etkin tema ziyaretçi sitesinde kullanılır.'); return;
    }
    if ($section === 'themes' && $action === 'theme_demo_import') {
        $slug=admin_input('theme_slug',80); $raw=admin_input('demo_json',180000);
        try { $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR); } catch (JsonException) { throw new InvalidArgumentException('Demo JSON geçerli değil.'); }
        if (!is_array($payload)) throw new InvalidArgumentException('Demo verisi bir JSON nesnesi olmalı.');
        $result=theme_import_demo($slug,$payload,(int)$user['id']); audit('theme.demo_imported',$slug.' #'.$result['count']); flash('success','Demo güvenli biçimde taslak olarak içe aktarıldı: '.$result['count'].' içerik.'); return;
    }
    if ($section === 'themes' && $action === 'theme_sdk_config') {
        $slug=admin_input('sdk_slug',80); if ($slug!=='' && theme_manifest_by_slug($slug)===null) throw new InvalidArgumentException('Geçerli bir SDK teması seçin.');
        $tokenRaw=admin_input('sdk_tokens',18000); $sectionRaw=admin_input('sdk_sections',24000);
        try { $tokens=$tokenRaw===''?[]:json_decode($tokenRaw,true,512,JSON_THROW_ON_ERROR); $sections=$sectionRaw===''?[]:json_decode($sectionRaw,true,512,JSON_THROW_ON_ERROR); } catch (JsonException) { throw new InvalidArgumentException('Tema token ve section ayarları geçerli JSON olmalı.'); }
        if (!is_array($tokens) || theme_validate_design_tokens($tokens)!==[]) throw new InvalidArgumentException('Tema token JSON alanları güvenli değil.');
        if (!is_array($sections) || count($sections)>50) throw new InvalidArgumentException('Tema section ayarları geçersiz.');
        foreach ($sections as $key=>$value) if (!is_string($key) || !preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/',$key) || !is_array($value)) throw new InvalidArgumentException('Section override anahtarları geçersiz.');
        save_setting('theme_sdk_slug',$slug); save_setting('theme_sdk_tokens_json',json_encode($tokens,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); save_setting('theme_sdk_sections_json',json_encode($sections,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        audit('theme.sdk_configured',$slug); flash('success','SDK tema ayarları doğrulandı ve kaydedildi.'); return;
    }
    if ($section === 'updates' && $action === 'download_update') {
        $version=admin_input('version',40); $sha=admin_input('sha256',64); if(!preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/',$version)||!preg_match('/^[a-f0-9]{64}$/i',$sha)) throw new InvalidArgumentException('Güncelleme sürümü veya özeti geçersiz.');
        $path=update_download(['version'=>$version,'sha256'=>$sha]); audit('update.downloaded',$version); flash('success','Paket indirildi ve SHA-256 doğrulandı: '.basename($path).'. Uygulama adımı henüz başlatılmadı; önce yedek alın.'); return;
    }
    if ($section === 'updates' && $action === 'apply_update') {
        $version=admin_input('version',40); $check=update_check(true); $release=is_array($check['update']??null)?$check['update']:null; if(!$release||($release['version']??'')!==$version)throw new InvalidArgumentException('Sürüm yeniden doğrulanamadı; tekrar kontrol edin.'); $minPhp=(string)($release['min_php']??''); $migration=(int)($release['migration_version']??0); if(!preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/',$version)) throw new InvalidArgumentException('Güncelleme sürümü geçersiz.');
        $backup=update_apply($version,$minPhp,$migration); flash('success','Güncelleme uygulandı. Veri yedeği: '.basename($backup)); return;
    }
    if ($section === 'licenses' && $action === 'license') {
        $server=admin_input('license_server_url',300); $key=admin_input('license_key',100);
        if($server!=='' && (!filter_var($server,FILTER_VALIDATE_URL)||!str_starts_with(strtolower($server),'https://'))) throw new InvalidArgumentException('Lisans sunucusu HTTPS adresi olmalı.');
        save_setting('license_server_url',rtrim($server,'/')); save_setting('license_key',$key); $result=license_check_remote(); audit('license.checked',license_domain()); flash($result['active']?'success':'error',$result['message']); return;
    }
    if ($section === 'notification-rules' && $action === 'notification_rules') { foreach(['notify_request_created','notify_appointment_created'] as $key) save_setting($key,($_POST[$key]??'0')==='1'?'1':'0'); audit('notification.rules_updated'); flash('success','Bildirim kuralları kaydedildi.'); return; }
    if ($section === 'email-templates' && $action === 'mail_templates') { foreach(['mail_subject_request','mail_subject_appointment','mail_subject_verification'] as $key) save_setting($key,admin_input($key,180)); foreach(['mail_body_request','mail_body_appointment','mail_body_verification'] as $key) save_setting($key,admin_input($key,5000)); audit('mail.templates_updated'); flash('success','E-posta şablonları kaydedildi.'); return; }
    if ($section === 'settings' && $action === 'ai_settings') {
        $provider=admin_input('ai_provider',20); $model=admin_input('ai_model',120);
        if (!in_array($provider,['openai','compatible'],true) || $model==='') throw new InvalidArgumentException('AI sağlayıcısı ve model gerekli.');
        save_setting('ai_provider',$provider); save_setting('ai_model',$model); audit('settings.ai_updated',$provider); flash('success','Yapay zekâ sağlayıcısı kaydedildi.'); return;
    }
    if ($section === 'settings' && $action === 'map_settings') {
        $map=admin_input('map_embed_url',1000);
        if ($map!=='' && (!filter_var($map,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($map,PHP_URL_SCHEME)),['https'],true))) throw new InvalidArgumentException('Harita adresi HTTPS URL olmalı.');
        $provider=admin_input('maps_provider',30);
        if (!in_array($provider,platform_map_providers(),true)) throw new InvalidArgumentException('Geçersiz harita sağlayıcısı.');
        save_setting('map_embed_url',$map); save_setting('maps_provider',$provider); save_setting('location_label',admin_input('location_label',180)); audit('settings.map_updated',$provider); flash('success','Harita ayarları kaydedildi.'); return;
    }
    if ($section === 'settings' && $action === 'settings') {
        $keys=['business_name','product_industry','product_location','country_code','currency_code','timezone','location_label','contact_phone','contact_email','whatsapp','address','site_description','logo_url','favicon_url','pwa_name','pwa_description','hero_image_url','theme_primary','theme_accent','theme_custom_css','facebook_url','instagram_url','linkedin_url','youtube_url','pinterest_url','pinterest_verify','google_site_verification','bing_site_verification','yandex_site_verification','analytics_id','ai_provider','ai_model','mail_from','smtp_host','smtp_port','smtp_user','mail_subject_request','mail_subject_appointment','mail_subject_verification','mail_body_request','mail_body_appointment','mail_body_verification'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $_POST)) continue;
            $value=admin_input($key,1000);
            if (in_array($key,['contact_email','mail_from'],true) && $value !== '' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-posta adresini kontrol edin.');
            if (in_array($key,['facebook_url','instagram_url','linkedin_url','youtube_url','pinterest_url'],true) && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value),'https://'))) throw new InvalidArgumentException('Sosyal medya bağlantıları HTTPS adresi olmalı.');
            if (in_array($key,['logo_url','favicon_url'],true) && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value),'https://'))) throw new InvalidArgumentException('Logo ve favicon bağlantıları HTTPS adresi olmalı.');
            if ($key==='hero_image_url' && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value),'https://'))) throw new InvalidArgumentException('Hero görseli HTTPS adresi olmalı.');
            if (in_array($key,['theme_primary','theme_accent'],true) && $value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/D',$value)) throw new InvalidArgumentException('Tema renkleri altı haneli HEX değeri olmalı.');
            if ($key==='theme_custom_css' && (str_contains(strtolower($value),'</style') || mb_strlen($value)>12000)) throw new InvalidArgumentException('Özel CSS geçersiz veya çok uzun.');
            if ($key==='ai_provider' && !in_array($value,['openai','compatible'],true)) throw new InvalidArgumentException('Geçersiz API sağlayıcısı.');
            if ($key==='smtp_port' && $value!=='' && (!ctype_digit($value) || (int)$value<1 || (int)$value>65535)) throw new InvalidArgumentException('Geçersiz SMTP portu.');
            if ($key==='analytics_id' && $value!=='' && !preg_match('/^G-[A-Z0-9]+$/D',$value)) throw new InvalidArgumentException('Analytics kimliği G- ile başlamalıdır.');
            if ($key==='country_code' && $value!=='' && !preg_match('/^[A-Z]{2}$/D',$value)) throw new InvalidArgumentException('Ülke kodu ISO 3166-1 iki harf olmalı.');
            if ($key==='currency_code' && $value!=='' && !preg_match('/^[A-Z]{3}$/D',$value)) throw new InvalidArgumentException('Para birimi ISO 4217 üç harf olmalı.');
            if ($key==='timezone' && $value!=='' && !in_array($value,DateTimeZone::listIdentifiers(),true)) throw new InvalidArgumentException('Geçersiz saat dilimi.');
        }
        foreach ($keys as $key) if (array_key_exists($key, $_POST)) save_setting($key,admin_input($key,1000));
        audit('settings.updated','İşletme ve entegrasyon bilgileri'); flash('success','Ayarlar kaydedildi. Gizli anahtarlar sunucu ortamından okunur.'); return;
    }
    if ($section === 'integrations' && $action === 'integrations') {
        foreach (['smtp_host','smtp_port','smtp_user','sms_provider','sms_sender','whatsapp_provider','notification_email_enabled','notification_sms_enabled','notification_whatsapp_enabled'] as $key) save_setting($key,admin_input($key,1000));
        audit('integrations.updated','Bildirim ve entegrasyon ayarları'); flash('success','Entegrasyon ayarları kaydedildi. Gizli anahtarlar sunucu ortamından okunur.'); return;
    }
    if ($section === 'security' && $action === 'revoke_sessions') { save_setting('auth_session_epoch',(string)time()); $_SESSION['auth_session_epoch']=setting('auth_session_epoch'); audit('security.sessions_revoked'); flash('success','Tüm yönetici oturumları sonlandırıldı.'); return; }
    if ($section === 'security' && $action === 'totp_start') { $_SESSION['totp_pending']=totp_secret(); audit('security.totp_started'); flash('success','Kurulum anahtarı oluşturuldu. Authenticator uygulamanıza ekleyip kodu doğrulayın.'); return; }
    if ($section === 'security' && $action === 'totp_confirm') { $secret=(string)($_SESSION['totp_pending']??'');$code=admin_input('totp_code',6);if($secret===''||!totp_valid($secret,$code))throw new InvalidArgumentException('Doğrulama kodu geçersiz veya kurulum süresi doldu.');query('UPDATE users SET totp_secret=? WHERE id=?',[$secret,$user['id']]);unset($_SESSION['totp_pending']);audit('security.totp_enabled');flash('success','İki aşamalı doğrulama etkinleştirildi.');return; }
    if ($section === 'security' && $action === 'totp_disable') { $code=admin_input('totp_code',6);$current=row('SELECT totp_secret FROM users WHERE id=?',[$user['id']]);if(!$current||!$current['totp_secret']||!totp_valid($current['totp_secret'],$code))throw new InvalidArgumentException('Mevcut doğrulama kodu geçersiz.');query('UPDATE users SET totp_secret=NULL WHERE id=?',[$user['id']]);audit('security.totp_disabled');flash('success','İki aşamalı doğrulama devre dışı bırakıldı.');return; }
    if ($section === 'integrations' && $action === 'test_smtp') {
        $recipient=admin_input('test_recipient',254); if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Test alıcısı geçerli bir e-posta olmalı.');
        enqueue_mail($recipient,'TofiDesk SMTP test','Bu mesaj TofiDesk entegrasyon merkezinden oluşturulan SMTP testidir.',null); audit('smtp.test_queued',$recipient); flash('success','SMTP test mesajı kuyruğa alındı. Gönderim sonucunu E-posta kuyruğunda izleyin.'); return;
    }
    if ($section === 'integrations' && $action === 'test_notification') {
        $channel=admin_input('channel',20); $recipient=admin_input('recipient',180); $message=admin_input('message',2000);
        $id=enqueue_notification($channel,$recipient,$message); audit('notification.test_queued',$channel.' #'.$id); flash('success','Bildirim test kuyruğuna alındı. Sağlayıcı anahtarı yoksa gerçek gönderim yapılmaz.'); return;
    }
    if ($section === 'legal' && $action === 'legal') {
        $lang=admin_lang();
        save_setting('legal_terms_'.$lang,admin_input('legal_terms',10000));
        save_setting('legal_sla_'.$lang,admin_input('legal_sla',10000));
        audit('legal.updated',$lang); flash('success','Yasal metinler kaydedildi.'); return;
    }
    if ($section === 'seo' && $action === 'homepage') {
    $lang=admin_lang(); foreach(['homepage_eyebrow'=>180,'homepage_title'=>180,'homepage_intro'=>1000,'homepage_service_title'=>180,'homepage_service_intro'=>1000,'homepage_regions_title'=>180,'homepage_regions_text'=>1000,'homepage_process_title'=>180,'homepage_seo_title'=>180,'homepage_seo_description'=>400,'legal_controller'=>5000,'legal_processing'=>10000,'legal_rights'=>5000,'legal_terms'=>10000,'legal_sla'=>10000] as $field=>$limit) save_setting($field.'_'.$lang,admin_input($field,$limit));
        audit('homepage.updated',$lang); flash('success','Ana sayfa dil metinleri kaydedildi.'); return;
    }
    if ($section === 'users' && $action === 'user') {
        $name=admin_input('name',100); $email=admin_input('email',180); $password=(string)($_POST['password'] ?? ''); $role=admin_input('role',20);
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0") || !in_array($role,['admin','editor','operator'],true)) throw new InvalidArgumentException('Ad, geçerli e-posta, rol ve en az 12 karakterlik (en fazla 72 bayt) parola gerekli.');
        if (row('SELECT id FROM users WHERE lower(email)=lower(?)',[$email])) throw new InvalidArgumentException('Bu e-posta zaten kayıtlı.');
        query("INSERT INTO users(name,email,password_hash,role,created_at) VALUES(?,?,?,?,datetime('now'))",[$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT),$role]);
        audit('user.created',$email.' '.$role); flash('success','Kullanıcı oluşturuldu.'); return;
    }
    if ($section === 'mail' && $action === 'retry_mail') {
        $mail=row("SELECT * FROM mail_queue WHERE id=? AND status='failed'",[(int)($_POST['id']??0)]);
        if (!$mail) throw new InvalidArgumentException('Başarısız gönderim bulunamadı.');
        enqueue_mail($mail['recipient'],$mail['subject'],$mail['body'],$mail['request_id']===null?null:(int)$mail['request_id']);
        audit('mail.retry','Önceki başarısız gönderim #'.$mail['id']); flash('success','Hata geçmişi korundu; yeni gönderim kuyruğa alındı.'); return;
    }
    if ($section === 'backup' && $action === 'backup') { $path=create_backup(); audit('backup.created',basename($path)); flash('success','Yedek oluşturuldu: '.basename($path).'. Dosya sunucudaki korumalı yedek dizininde.'); return; }
    throw new InvalidArgumentException('Geçersiz işlem.');
}

function admin_login_page(): void
{
    if (current_user()) redirect('/admin');
    $lang = is_string($_GET['lang'] ?? null) && array_key_exists($_GET['lang'], core_locales()) ? (string)$_GET['lang'] : 'tr';
    $error='';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf_verify();
        if (login_user((string)($_POST['email']??''),(string)($_POST['password']??''),(string)($_POST['totp']??''))) redirect('/admin');
        $error=admin_login_t('error',$lang);
    }
    $brand=setting('business_name','TofiDesk');
    ?><!doctype html><html lang="<?=e($lang)?>" dir="<?=e(locale_direction($lang))?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e(admin_t('management',$lang))?> · <?=e($brand)?></title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-overrides.css"></head><body class="login-body"><main class="login-card"><a class="brand" href="/<?=e($lang)?>/"><?=e($brand)?></a><p class="eyebrow"><?=e(admin_t('management',$lang))?></p><h1><?=e(admin_t('welcome',$lang))?></h1><p><?=e(admin_login_t('intro',$lang))?></p><?php if($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif ?><?php foreach(take_flash() as $notice): ?><p class="notice <?= e($notice['kind']) ?>" role="status"><?= e(admin_translate_html((string)$notice['message'],$lang)) ?></p><?php endforeach ?><form method="post"><?= csrf_field() ?><label><?=e(admin_t('email',$lang))?><input type="email" name="email" autocomplete="username" required></label><label><?=e(admin_t('password',$lang))?><input type="password" name="password" autocomplete="current-password" required></label><label><?=e(admin_login_t('verification',$lang))?> <small>(<?=e(admin_login_t('enabled',$lang))?>)</small><input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label><button type="submit"><?=e(admin_t('login',$lang))?> <span>↗</span></button></form><p class="muted small"><?=e(admin_login_t('first_admin',$lang))?></p><p><label><?=e(admin_t('language',$lang))?><select onchange="location.search='?lang='+encodeURIComponent(this.value)"><?php foreach(core_locales() as $code=>$label): ?><option value="<?=e($code)?>" <?=$code===$lang?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></label></p><a class="text-link" href="/<?=e($lang)?>/">← <?=e(admin_t('open_site',$lang))?></a></main><aside class="login-art"><div class="route-orbit"></div><p><?=e(admin_login_t('every_product',$lang))?></p><span><?=e(admin_login_t('global',$lang))?></span></aside></body></html><?php
}

function admin_date_label(string $lang): string
{
    $formats=['tr'=>'d.m.Y','en'=>'d/m/Y','de'=>'d.m.Y','fr'=>'d/m/Y','es'=>'d/m/Y','it'=>'d/m/Y','pt'=>'d/m/Y','nl'=>'d-m-Y','ru'=>'d.m.Y','ar'=>'d/m/Y','zh'=>'Y-m-d','ja'=>'Y/m/d','ko'=>'Y. m. d.','fa'=>'Y/m/d'];
    return (new DateTimeImmutable('now',new DateTimeZone(setting('timezone','Europe/Istanbul'))))->format($formats[$lang]??'Y-m-d');
}

function admin_datetime_label(string $value, string $lang, bool $withTime = true): string
{
    if (trim($value) === '') return '';
    try {
        $date = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(setting('timezone','Europe/Istanbul')));
        $formats=['tr'=>'d.m.Y','en'=>'d/m/Y','de'=>'d.m.Y','fr'=>'d/m/Y','es'=>'d/m/Y','it'=>'d/m/Y','pt'=>'d/m/Y','nl'=>'d-m-Y','ru'=>'d.m.Y','ar'=>'d/m/Y','zh'=>'Y-m-d','ja'=>'Y/m/d','ko'=>'Y. m. d.','fa'=>'Y/m/d'];
        return $date->format($formats[$lang]??'Y-m-d').($withTime ? ' '.$date->format('H:i') : '');
    } catch (Throwable) { return $value; }
}

function admin_layout(string $title,string $section,array $user,string $body): void
{
    $lang = admin_user_lang($user);
    $title = admin_translate_html($title, $lang);
    $body = admin_translate_html($body, $lang);
    ob_start(static function(string $html) use ($lang): string {
        $brand = trim((string)setting('business_name','TofiDesk'));
        if ($brand !== '') $html = str_replace(['Kaş Nakliyat','KAŞ NAKLİYAT'], [e($brand), e($brand)], $html);
        if ($brand === 'TofiDesk') $html = str_replace(['Every move<br>starts well.','KAŞ · AKDENİZ','Independent management platform'], ['Every product<br>starts here.','GLOBAL MARKETPLACE','Independent software and theme platform'], $html);
        $html = str_replace('https://tofidesk.com/transportation', 'https://product.tofidesk.com', $html);
        $html = str_replace(['Randevular','SEO merkezi',date('d.m.Y')],['Randevu Takvimi','SEO ve Ana Sayfa',admin_date_label($lang)],$html);
        return preg_replace_callback('/\b(20\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\b/', static fn(array $match): string => e(admin_datetime_label($match[1],$lang)), $html) ?? $html;
    });
    $direction = locale_direction($lang);
    $nav=['dashboard'=>['◫','Genel Bakış'],'requests'=>['↗','Teklif Talepleri'],'appointments'=>['▦','Randevu Takvimi'],'content'=>['▤','İçerik Stüdyosu'],'content-models'=>['◇','Sektör İçerikleri'],'taxonomies'=>['⌘','Kategori ve Etiketler'],'menus'=>['☰','Menü Yönetimi'],'forms'=>['▣','Formlar ve Başvurular'],'languages'=>['文','Dil Paketleri'],'themes'=>['◐','Tema Kütüphanesi'],'modules'=>['⬡','Modüller'],'plugins'=>['◈','Eklentiler ve SDK'],'locations'=>['⌖','Konumlar'],'licenses'=>['◇','Lisans'],'updates'=>['↻','Güncellemeler'],'jobs'=>['✧','Yapay Zekâ İşleri'],'media'=>['▧','Görsel Kütüphanesi'],'gallery'=>['▦','Galeri'],'seo'=>['◎','SEO ve Ana Sayfa'],'mail'=>['✉','E-posta Kuyruğu'],'settings'=>['⚙','Ayarlar'],'email-templates'=>['✉','E-posta Şablonları'],'notification-rules'=>['♧','Bildirim Kuralları'],'integrations'=>['⚡','Entegrasyonlar'],'security'=>['🔒','Hesap Güvenliği'],'anomalies'=>['⚠','Güvenlik Olayları'],'users'=>['♙','Kullanıcılar'],'backup'=>['↓','Yedekleme'],'audit'=>['≡','İşlem Kayıtları']];
    // Keep the most frequently needed configuration entry visible before the
    // longer module list on short screens.
    $nav=['settings'=>['⚙','Ayarlar']] + $nav;
    $nav['legal']=['§',admin_section_title('legal',$lang)];
    foreach ($nav as $key => $item) $nav[$key][1] = admin_section_title($key, $lang);
    $brandName = trim((string)setting('business_name','TofiDesk')) ?: 'TofiDesk';
    $brandWord = strtoupper($brandName);
    ?><!doctype html><html lang="<?= e($lang) ?>" dir="<?= e($direction) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> · <?= e($brandName) ?></title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-overrides.css"></head><body><a href="#main" class="skip"><?= e(admin_t('skip',$lang)) ?></a><aside class="sidebar"><a class="brand" href="/admin"><?= e($brandWord) ?></a><span class="sidebar-caption"><?= e(admin_t('management',$lang)) ?></span><nav aria-label="<?= e(admin_t('management',$lang)) ?>"><?php foreach($nav as $key=>[$icon,$label]): if(!admin_allowed($key,$user))continue; ?><a class="<?= ($section===$key || ($section==='editor'&&$key==='content'))?'active':'' ?>" href="/admin<?= $key==='dashboard'?'':'/'.$key ?>"><span aria-hidden="true"><?= $icon ?></span><?= e($label) ?></a><?php endforeach ?></nav><div class="sidebar-bottom"><a href="/tr/"><?= e(admin_t('open_site',$lang)) ?> ↗</a><form method="post" class="language-form"><?= admin_action('set_admin_locale'); admin_select(admin_t('language',$lang),'lang',admin_locales(),$lang) ?><button class="secondary" type="submit"><?= e(admin_t('save_language',$lang)) ?></button></form><p><?= e($user['name']) ?><small><?= e($user['role']) ?></small></p><form method="post" action="/admin/logout"><?= csrf_field() ?><button class="logout" type="submit"><?= e(admin_t('logout',$lang)) ?></button></form></div></aside><div class="workspace"><header class="topbar"><span><?= e($brandName) ?> <span class="crumb">/ <?= e($title) ?></span></span><span class="topbar-right"><?= e(strtoupper($lang)) ?> <span class="avatar"><?= e(mb_substr($user['name'],0,1)) ?></span></span></header><main id="main" class="main"><div class="page-heading"><div><p class="eyebrow"><?= e(admin_t('workspace',$lang)) ?></p><h1><?= e($title) ?></h1></div><time datetime="<?= date('Y-m-d') ?>"><?= e(admin_date_label($lang)) ?></time></div><?php foreach(take_flash() as $flash): ?><p class="notice <?= e($flash['kind']) ?>" role="status"><?= e(admin_translate_html((string)$flash['message'],$lang)) ?></p><?php endforeach ?><?= $body ?></main><footer class="admin-footer"><?= e($brandName) ?> · <?= e(admin_t('footer',$lang)) ?> <span><?= e(setting('timezone','UTC')) ?></span></footer></div></body></html><?php
}

function admin_dashboard(array $user): void
{
    ?><div class="welcome"><div><p class="eyebrow">HER ŞEY TEK BİR YERDE</p><h2>Bugünü birlikte planlayalım.</h2><p>Gelen talepler, yayınlanan içerikler ve yaklaşan işler için çalışma alanınız.</p></div><span class="welcome-mark" aria-hidden="true">↗</span></div><div class="stat-grid"><?php
    $stats=[];
    if(admin_allowed('requests',$user)) { $stats[]=['Yeni talepler',row("SELECT count(*) n FROM requests WHERE status='new'")['n'],'/admin/requests']; $stats[]=['Kesinleşen randevu',row("SELECT count(*) n FROM requests WHERE type='appointment' AND status='confirmed' AND preferred_date>=?",[date('Y-m-d')])['n'],'/admin/appointments']; }
    if(admin_allowed('content',$user)) { $stats[]=['Yayınlanan dil sürümü',row("SELECT count(*) n FROM translations WHERE status='published' AND (scheduled_at IS NULL OR scheduled_at='' OR scheduled_at<=datetime('now'))")['n'],'/admin/content']; $stats[]=['İnceleme bekleyen',row("SELECT count(*) n FROM translations WHERE status='review'")['n'],'/admin/content']; }
    foreach($stats as [$label,$value,$url]): ?><a class="stat" href="<?= e($url) ?>"><span><?= e($label) ?></span><strong><?= (int)$value ?></strong><small>Ayrıntıları görüntüle ↗</small></a><?php endforeach ?></div><div class="split"><section class="panel"><div class="section-heading"><h2>Çalışma akışı</h2><span class="dot-label">Canlı veriler</span></div><ol class="workflow"><li><strong>Talebi inceleyin</strong><span>Teklif ve randevu başvuruları otomatik kesinleşmez.</span></li><li><strong>İçeriği diline göre hazırlayın</strong><span>Her dilin metni, URL'si ve SEO bilgisi bağımsızdır.</span></li><li><strong>İnceleyerek yayınlayın</strong><span>Yapay zekâ çıktıları, editör onayına kadar ayrı tutulur.</span></li></ol></section><section class="panel"><h2>Yayına hazırlık</h2><ul class="checklist"><li><?= setting('contact_email') ? '✓ İletişim e-postası tanımlı' : '○ İşletme iletişim bilgilerini ekleyin' ?></li><li>○ Gerçek işletme görsellerini yükleyin</li><li>○ KVKK metnini işletmeye göre onaylayın</li><li>○ API ve SMTP bağlantılarını gerçek bilgilerle doğrulayın</li></ul><p class="muted">Gizli anahtarlar yalnızca sunucu ortamında saklanır.</p></section></div><?php
}

function admin_appointments_page(): void
{
    echo '<style>.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid #e2e9e6;border-radius:10px;overflow:hidden}.calendar-head{background:#153346;color:#fff;padding:10px;font-size:11px;text-align:center}.calendar-cell{min-height:110px;padding:8px;border:1px solid #edf1ef;background:#fff}.calendar-cell.empty{background:#f7f9f8}.calendar-cell time{font-size:12px;color:#81929a;font-weight:700}.calendar-event{border-left:3px solid #087f8c;background:#f3f7f5;border-radius:5px;padding:5px 6px;margin-top:7px;font-size:10px}.calendar-event strong,.calendar-event small{display:block;overflow-wrap:anywhere}.calendar-event small{color:#71868d;margin-top:2px}@media(max-width:800px){.calendar-cell{min-height:85px;padding:5px}.calendar-head{font-size:9px;padding:7px}.calendar-event{font-size:9px}.calendar-event small{display:none}}';
    $month=(string)($_GET['month']??date('Y-m')); $base=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01'); if(!$base)$base=new DateTimeImmutable('first day of this month');
    $monthKey=$base->format('Y-m'); $firstWeekday=(int)$base->format('N'); $days=(int)$base->format('t'); $prev=$base->modify('-1 month')->format('Y-m'); $next=$base->modify('+1 month')->format('Y-m');
    $items=rows("SELECT preferred_date date,slot,status,reference FROM requests WHERE type='appointment' AND preferred_date LIKE ? AND status IN('proposed','confirmed') ORDER BY preferred_date,slot",[$monthKey.'-%']);
    $events=rows('SELECT id,title,start_date,end_date,notes,color FROM calendar_events WHERE start_date<=? AND end_date>=? ORDER BY start_date,title',[$base->format('Y-m-t'),$base->format('Y-m-01')]); $byDate=[];
    foreach($items as $item)$byDate[$item['date']][]=['title'=>'Randevu · '.$item['reference'],'meta'=>($item['slot']?:'Saat belirtilmedi').' · '.$item['status'],'color'=>'#EF914C'];
    foreach($events as $event){$cursor=new DateTimeImmutable($event['start_date']);$until=new DateTimeImmutable($event['end_date']);while($cursor<=$until){if($cursor->format('Y-m')===$monthKey)$byDate[$cursor->format('Y-m-d')][]=['title'=>$event['title'],'meta'=>'Etkinlik','color'=>$event['color']];$cursor=$cursor->modify('+1 day');}}
    $cells=''; for($i=1;$i<$firstWeekday;$i++)$cells.='<div class="calendar-cell empty"></div>'; for($day=1;$day<=$days;$day++){ $date=$base->setDate((int)$base->format('Y'),(int)$base->format('m'),$day)->format('Y-m-d'); $cards=''; foreach($byDate[$date]??[] as $entry)$cards.='<div class="calendar-event" style="border-color:'.e($entry['color']).'"><strong>'.e($entry['title']).'</strong><small>'.e($entry['meta']).'</small></div>'; $cells.='<div class="calendar-cell"><time>'.(int)$day.'</time>'.$cards.'</div>'; }
    $months=['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık']; $monthLabel=($months[$base->format('m')]??$base->format('m')).' '.$base->format('Y');
    ?><section class="panel"><div class="section-heading"><div><h2>Randevu Takvimi</h2><p class="muted">Onaylanan randevuları ve yöneticinin eklediği özel etkinlikleri aylık görünümde yönetin.</p></div><div><a class="button secondary" href="/admin/appointments?month=<?=e($prev)?>">‹ Önceki</a> <a class="button secondary" href="/admin/appointments?month=<?=e($next)?>">Sonraki ›</a></div></div><h3><?=e($monthLabel)?></h3><div class="calendar-grid"><div class="calendar-head">Pzt</div><div class="calendar-head">Sal</div><div class="calendar-head">Çar</div><div class="calendar-head">Per</div><div class="calendar-head">Cum</div><div class="calendar-head">Cmt</div><div class="calendar-head">Paz</div><?=$cells?></div></section><section class="panel"><h2>Takvime Etkinlik Ekle</h2><form method="post" class="form-grid"><?php admin_action('calendar_event'); admin_field('Etkinlik Başlığı','event_title',''); admin_field('Başlangıç','start_date',date('Y-m-d'),'date'); admin_field('Bitiş','end_date',date('Y-m-d'),'date'); admin_field('Renk','event_color','#087F8C','color'); admin_textarea('Not','event_notes','',2); ?><button>Etkinliği Kaydet</button></form></section><h2 class="section-title">Randevu Talepleri</h2><?php admin_requests_page('appointments');
}

function admin_requests_page(string $section): void
{
    $type=$section==='appointments'?'appointment':'quote';
    $items=rows('SELECT * FROM requests WHERE type=? ORDER BY created_at DESC,id DESC LIMIT 200',[$type]);
    ?><p class="intro"><?= $type==='appointment'?'Başvuruları inceleyin, uygun saat önerin ve açık onayla kesinleştirin. Aynı saatteki iki randevu kesinleştirilemez.':'Yeni başvuruları inceleyin ve müşteriye teklif hazırlayın. Durum güncellemeleri e-posta kuyruğuna kaydedilir.' ?></p><?php if(!$items): admin_empty('Henüz başvuru yok.','Web sitesindeki formdan gelen talepler burada görünür.'); return; endif ?>
    <div class="request-list"><?php foreach($items as $item): ?><details class="panel request-card"><summary><div><strong><?= e($item['name']) ?></strong><small><?= e($item['reference']) ?> · <?= e($item['created_at']) ?></small></div><div class="summary-route"><?= e($item['origin']) ?> <span>→</span> <?= e($item['destination']) ?></div><?= admin_status($item['status']) ?></summary><div class="request-detail"><dl><dt>İletişim</dt><dd><?= e($item['email']) ?><br><?= e($item['phone']) ?></dd><dt>Yük</dt><dd><?= e($item['cargo']) ?></dd><dt>Müşteri notu</dt><dd><?= nl2br(e($item['notes'])) ?></dd></dl><?php $photos=rows('SELECT media_id FROM request_media WHERE request_id=?',[$item['id']]); if($photos): ?><div class="photo-links"><?php foreach($photos as $photo): ?><a href="/media/<?= (int)$photo['media_id'] ?>">Ekli görsel #<?= (int)$photo['media_id'] ?> ↗</a><?php endforeach ?></div><?php endif ?><form method="post"><?php admin_action('request') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><div class="form-grid"><?php admin_select('Durum','status',$type==='appointment'?['new'=>'Yeni talep','proposed'=>'Tarih öner','confirmed'=>'Kesinleştir','rejected'=>'Reddet']:['new'=>'Yeni talep','quoted'=>'Teklif verildi','rejected'=>'Reddet'],$item['status']); admin_field('Tarih','preferred_date',(string)$item['preferred_date'],'date'); admin_field('Saat','slot',(string)$item['slot'],'time'); admin_field('Teklif tutarı (TRY)','price',(string)$item['price'],'number'); ?></div><?php admin_textarea('İç not (müşteriye gönderilmez)','admin_note',(string)$item['admin_note'],2) ?><button>Talebi güncelle</button></form><form method="post" class="panel"><h3>Müşteriye e-posta yanıtı</h3><?php admin_action('reply') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php admin_field('Konu','subject',setting('business_name','TofiDesk').' · '.$item['reference']); admin_textarea('Mesaj','message','',5) ?><button class="secondary">SMTP kuyruğuna al</button></form></div></details><?php endforeach ?></div><?php
}

function admin_empty(string $title,string $text): void
{
    echo '<div class="panel empty"><span aria-hidden="true">◌</span><h2>'.e($title).'</h2><p>'.e($text).'</p></div>';
}

function admin_content_page(): void
{
    ?><div class="split"><section class="panel"><h2>Yeni içerik oluştur</h2><p class="muted">İlk kayıt taslak olarak başlar. Her dil ayrı onaylanır.</p><form method="post"><?php admin_action('create'); admin_select('İçerik türü','kind',['article'=>'Blog yazısı','service'=>'Hizmet sayfası','page'=>'Bilgi sayfası'],'article'); admin_select('Kaynak dil','lang',admin_locales(),'tr'); admin_field('Kategori','category'); ?><button>İçerik oluştur ↗</button></form></section><section class="panel"><h2>Dil paketleri. Bağımsız yayın.</h2><p>Kaynak güncellemeleri çevirilerin üzerine yazmaz. Yeni dil paketleri taslak olarak eklenir ve arayüz sözlüğü onaydan sonra yayına alınır.</p><div class="language-pills"><?php foreach(admin_locales() as $code=>$label): ?><span><?= e(strtoupper($code)) ?></span><?php endforeach ?></div><p><a href="/admin/languages">Dil paketlerini yönet ↗</a></p></section></div><?php
    $items=rows('SELECT a.*, COALESCE((SELECT title FROM translations t WHERE t.article_id=a.id AND t.lang=a.source_lang LIMIT 1),(SELECT title FROM translations t WHERE t.article_id=a.id ORDER BY t.id LIMIT 1)) title, (SELECT count(*) FROM translations t WHERE t.article_id=a.id) language_count FROM articles a ORDER BY updated_at DESC,id DESC LIMIT 200');
    if(!$items) { admin_empty('İlk içeriğinizi hazırlayın.','Henüz makale, hizmet veya bilgi sayfası eklenmedi.'); return; }
    ?><section class="panel"><div class="table-wrap"><table><thead><tr><th>İçerik</th><th>Tür / kategori</th><th>Dil sürümü</th><th>Güncelleme</th><th></th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><strong><?= e($item['title'] ?: 'Yeni içerik #'.$item['id']) ?></strong><small>Kaynak: <?= e(strtoupper($item['source_lang'])) ?></small></td><td><?= e($item['kind']) ?><small><?= e($item['category']) ?></small></td><td><?= (int)$item['language_count'] ?> / <?=count(admin_locales())?></td><td><?= e($item['updated_at']) ?></td><td><a href="/admin/editor?id=<?= (int)$item['id'] ?>&amp;lang=<?= e($item['source_lang']) ?>">Düzenle ↗</a></td></tr><?php endforeach ?></tbody></table></div></section><?php
}

function admin_content_models_page_v2(): void
{
    $types=rows("SELECT id,slug,label FROM content_types WHERE status='enabled' ORDER BY slug"); $choices=[];
    foreach($types as $type) $choices[(string)$type['slug']]=(string)$type['label'].' · '.$type['slug'];
    $editingId=max(0,(int)($_GET['id']??0));
    $editing=$editingId>0 ? row('SELECT ce.*,ct.slug AS content_type,ct.label FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id WHERE ce.id=?',[$editingId]) : null;
    $editingTranslation=$editing ? row('SELECT * FROM content_entry_translations WHERE entry_id=? AND locale=?',[$editingId,admin_lang()]) : null;
    $editingData=[]; if($editing && (string)($editing['data_json']??'')!=='') { $decoded=json_decode((string)$editing['data_json'],true); $editingData=is_array($decoded)?$decoded:[]; }
    $editingTermIds=$editing ? array_map('intval',array_column(platform_entry_terms((int)$editing['id']), 'id')) : [];
    $selectedType=(string)($editing['content_type']??array_key_first($choices));
    $selectedStatus=(string)($editingTranslation['status']??'draft');
    if(!in_array($selectedStatus,['draft','review','published'],true)) $selectedStatus='draft';
    $formAction=$editing?'update_entry':'create_entry';
    $button=$editing?'Sektör kaydını güncelle ↗':'Sektör kaydını oluştur ↗';
    ?><section class="panel"><h2>Sektör içerik stüdyosu</h2><p class="muted">Her sektör aynı çekirdek veri modeliyle yönetilir: kayıt, özel alan, kategori/etiket, konum, dil, yayın durumu ve SEO metni. Tema yalnızca sunum katmanıdır.</p><?php if(!$choices): ?><div class="notice warning">Henüz etkin bir sektör içerik modeli yok. Önce Modüller ekranından paketleri senkronize edin.</div><?php else: ?><form method="post" class="panel"><h3><?= $editing?'Sektör kaydını düzenle':'Yeni sektör kaydı' ?></h3><?php admin_action($formAction); if($editing): ?><input type="hidden" name="entry_id" value="<?= (int)$editing['id'] ?>"><?php endif; admin_select('İçerik modeli','content_type',$choices,$selectedType); admin_field('Kayıt slug','entry_slug',(string)($editing['slug']??''),'text',true); admin_field('Başlık','entry_title',(string)($editingTranslation['title']??''),'text',true); admin_textarea('Kısa açıklama','entry_summary',(string)($editingTranslation['summary']??''),3); admin_textarea('İçerik gövdesi','entry_body',(string)($editingTranslation['body']??''),6); admin_select('Dil','entry_lang',admin_locales(),admin_lang()); admin_select('Yayın durumu','entry_status',['draft'=>'Taslak','review'=>'Editör incelemesi','published'=>'Yayınla'],$selectedStatus); admin_field('Konum ID (isteğe bağlı)','entry_location_id',isset($editing['location_id'])?(string)$editing['location_id']:'','number'); admin_textarea('Terim ID’leri (JSON)','entry_term_ids',json_encode($editingTermIds,JSON_UNESCAPED_UNICODE),3); admin_textarea('Özel alan verisi (JSON)','entry_data',json_encode($editingData,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),8); ?><p class="muted small">Terim ID’lerini Kategori ve Etiketler ekranından alın. Örn. <code>[12, 18]</code>. Alan slug’ları ve terim ID’leri doğrulanır.</p><button><?= $button ?></button><?php if($editing): ?> <a class="button secondary" href="/admin/content-models">Yeni kayıt ↗</a><?php endif; ?></form><?php endif ?></section><?php
    $entries=rows("SELECT ce.id,ce.slug,ce.created_at,ce.updated_at,ct.slug type,ct.label,(SELECT count(*) FROM content_entry_translations cet WHERE cet.entry_id=ce.id) language_count FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id ORDER BY ce.id DESC LIMIT 200");
    ?><section class="panel"><h2>Son sektör kayıtları</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Model</th><th>Slug</th><th>Diller</th><th>Güncelleme</th><th></th></tr></thead><tbody><?php foreach($entries as $entry): ?><tr><td>#<?= (int)$entry['id'] ?></td><td><strong><?=e((string)$entry['label'])?></strong><small><code><?=e((string)$entry['type'])?></code></small></td><td><code><?=e((string)$entry['slug'])?></code></td><td><?= (int)$entry['language_count'] ?> / <?=count(admin_locales())?></td><td><?=e((string)($entry['updated_at']??$entry['created_at']))?></td><td><a href="/admin/content-models?id=<?= (int)$entry['id'] ?>">Düzenle ↗</a></td></tr><?php endforeach; if(!$entries): ?><tr><td colspan="6">Henüz sektör kaydı yok.</td></tr><?php endif ?></tbody></table></div></section><?php
}

function admin_content_models_page(): void
{
    $types=rows("SELECT id,slug,label FROM content_types WHERE status='enabled' ORDER BY slug"); $choices=[];
    foreach($types as $type) $choices[(string)$type['slug']]=(string)$type['label'].' · '.$type['slug'];
    ?><section class="panel"><h2>Sektör içerik stüdyosu</h2><p class="muted">Modüller içerik modelini tanımlar; bu ekran kayıt, konum, dil ve yayın durumunu çekirdeğin ortak veri modeliyle oluşturur. Tema yalnızca sunum katmanıdır.</p><?php if(!$choices): ?><div class="notice warning">Henüz etkin bir sektör içerik modeli yok. Önce Modüller ekranından paketleri senkronize edin.</div><?php else: ?><form method="post" class="panel"><h3>Yeni sektör kaydı</h3><?php admin_action('create_entry'); admin_select('İçerik modeli','content_type',$choices,array_key_first($choices)); admin_field('Kayıt slug','entry_slug','','text',true); admin_field('Başlık','entry_title','','text',true); admin_textarea('Kısa açıklama','entry_summary','',3); admin_textarea('İçerik gövdesi','entry_body','',6); admin_select('Dil','entry_lang',admin_locales(),admin_lang()); admin_select('Yayın durumu','entry_status',['draft'=>'Taslak','review'=>'Editör incelemesi','published'=>'Yayınla'], 'draft'); admin_field('Konum ID (isteğe bağlı)','entry_location_id','','number'); admin_textarea('Özel alan verisi (JSON)','entry_data','{}',8); ?><p class="muted small">Alan slug’ları Modüller ekranındaki veri modelinden alınır. Örn. <code>{"price":1299,"location":42}</code>. Bilinmeyen alanlar ve yanlış türler reddedilir.</p><button>Sektör kaydını oluştur ↗</button></form><?php endif ?></section><?php
    $entries=rows("SELECT ce.id,ce.slug,ce.created_at,ct.slug type,ct.label,(SELECT count(*) FROM content_entry_translations cet WHERE cet.entry_id=ce.id) language_count FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id ORDER BY ce.id DESC LIMIT 200");
    ?><section class="panel"><h2>Son sektör kayıtları</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Model</th><th>Slug</th><th>Diller</th><th>Oluşturma</th></tr></thead><tbody><?php foreach($entries as $entry): ?><tr><td>#<?= (int)$entry['id'] ?></td><td><strong><?=e((string)$entry['label'])?></strong><small><code><?=e((string)$entry['type'])?></code></small></td><td><code><?=e((string)$entry['slug'])?></code></td><td><?= (int)$entry['language_count'] ?> / <?=count(admin_locales())?></td><td><?=e((string)$entry['created_at'])?></td></tr><?php endforeach; if(!$entries): ?><tr><td colspan="5">Henüz sektör kaydı yok.</td></tr><?php endif ?></tbody></table></div></section><?php
}

function admin_editor_page(): void
{
    $id=(int)($_GET['id'] ?? 0); $lang=admin_lang(); $article=row('SELECT * FROM articles WHERE id=?',[$id]);
    if(!$article) { admin_empty('İçerik bulunamadı.','İçerik stüdyosundan bir kayıt seçin veya oluşturun.'); return; }
    $item=row('SELECT * FROM translations WHERE article_id=? AND lang=?',[$id,$lang]) ?? [];
    $translations=rows('SELECT lang,status,revision,source_revision FROM translations WHERE article_id=?',[$id]); $byLang=[]; foreach($translations as $v)$byLang[$v['lang']]=$v;
    $source=$byLang[$article['source_lang']] ?? [];
    ?><div class="editor-lang-tabs" aria-label="Dil sürümleri"><?php foreach(admin_locales() as $code=>$label): ?><a class="<?= $lang===$code?'active':'' ?>" href="/admin/editor?id=<?= $id ?>&amp;lang=<?= e($code) ?>"><strong><?= e(strtoupper($code)) ?></strong><span><?= e($label) ?></span><small><?= isset($byLang[$code])?e(strip_tags(admin_status($byLang[$code]['status']))):'Henüz yok' ?></small></a><?php endforeach ?></div><?php if($item && $lang!==$article['source_lang'] && (int)$item['source_revision']<(int)($source['revision']??0)): ?><p class="notice warning">Kaynak metin bu dil sürümünden sonra değişti. İçeriği gözden geçirin; metniniz korundu.</p><?php endif ?><div class="editor-grid"><form method="post" class="panel editor-form"><?php admin_action('save') ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="lang" value="<?= e($lang) ?>"><input type="hidden" name="revision" value="<?= (int)($item['revision']??0) ?>"><div dir="<?= e(locale_direction($lang)) ?>"><?php admin_field('Başlık','title',(string)($item['title']??''),'text',true); admin_field('URL kısa adı','slug',(string)($item['slug']??''),'text',true); admin_textarea('Kısa açıklama','excerpt',(string)($item['excerpt']??''),3); admin_textarea('İçerik (düz metin)','body',(string)($item['body']??''),18); ?></div><details><summary>SEO alanları ve yayın ayarları</summary><?php $coverChoices=[''=>'Kapak yok']; foreach(rows("SELECT id,original_name FROM media WHERE purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=media.id) ORDER BY id DESC") as $media) $coverChoices[(string)$media['id']]=$media['original_name']; admin_select('Kapak görseli','cover_media_id',$coverChoices,(string)($item['cover_media_id']??'')); admin_field('SEO başlığı','seo_title',(string)($item['seo_title']??'')); admin_textarea('Meta açıklama','seo_description',(string)($item['seo_description']??''),3); admin_field('Hedef kelimeler (araştırma alanı)','keywords',(string)($item['keywords']??'')); admin_field('Kategori','category',(string)$article['category']); admin_select('Yayın durumu','status',['draft'=>'Taslak','review'=>'Editör incelemesi','published'=>'Yayınla / planla'],(string)($item['status']??'draft')); $scheduled=empty($item['scheduled_at'])?'':(new DateTimeImmutable($item['scheduled_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d\TH:i'); admin_field('Planlanan yayın (İstanbul saati, isteğe bağlı)','scheduled_at',$scheduled,'datetime-local'); ?><p class="muted">Taslak ve inceleme sürümleri sitede listelenmez. Boş planlama alanı hemen yayın anlamına gelir.</p></details><button>Dil sürümünü kaydet</button><?php if(($item['status']??'')==='published'): ?><a class="button secondary" href="/<?= e($lang) ?>/<?= $article['kind']==='article'?'blog':($article['kind']==='service'?'hizmetler':'sayfa') ?>/<?= e($item['slug']) ?>/">Yayın adresi ↗</a><?php endif ?></form><aside class="panel ai-panel"><p class="eyebrow">KONTROLLÜ ÜRETİM</p><h2>Yapay zekâ stüdyosu</h2><p>Çıktılar ayrı iş kaydı olarak hazırlanır. Mevcut dil metinleri onayınız olmadan değiştirilmez.</p><form method="post"><?php admin_action('generate') ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="lang" value="<?= e($lang) ?>"><?php admin_select('Çalışma biçimi','mode',['write'=>'Sıfırdan içerik yaz','improve'=>'Kaynak metni iyileştir','localize'=>'Kaynağı yerelleştir','image'=>'Makaleye özgü kapak görseli üret'],'localize'); admin_textarea('Konu, hedef kitle ve yönerge','prompt','',5) ?><fieldset><legend>Hedef diller</legend><div class="checkbox-grid"><?php foreach(admin_locales() as $code=>$label): ?><label><input type="checkbox" name="langs[]" value="<?= e($code) ?>" <?= $lang===$code?'checked':'' ?>><?= e($label) ?></label><?php endforeach ?></div></fieldset><button>Seçilen dilleri kuyruğa al</button></form><p class="muted small">Gerçek API anahtarı ve çalışan işçi gerektirir. Kullanım maliyeti sağlayıcı hesabınıza yansır.</p><a href="/admin/jobs">Üretim işlerini incele ↗</a></aside></div><?php
}

function admin_media_page(): void
{
    ?><section class="panel"><h2>Görsel yükleyin</h2><p class="muted">Yalnızca kullanım hakkına sahip olduğunuz gerçek materyalleri ekleyin. Orijinaller korunur.</p><form method="post" enctype="multipart/form-data" class="inline-form"><?php admin_action('upload') ?><label>Dosya<input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/avif" required></label><?php admin_select('Başlangıç dili','lang',admin_locales(),'tr') ?><button>Yükle</button></form></section><?php $items=rows("SELECT * FROM media WHERE purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=media.id) ORDER BY id DESC LIMIT 100"); if(!$items){admin_empty('Görsel kütüphaneniz hazır.','İlk görselinizi yükleyerek her dilde alt metin ve açıklama ekleyebilirsiniz.'); return;} $lang=admin_lang(); ?><form method="get" class="language-filter"><?php admin_select('Metin dili','lang',admin_locales(),$lang) ?><button class="secondary">Dili aç</button></form><div class="media-grid"><?php foreach($items as $item): $meta=row('SELECT * FROM media_translations WHERE media_id=? AND lang=?',[$item['id'],$lang])??[]; ?><article class="panel media-card"><img src="/media/<?= (int)$item['id'] ?>" alt="<?= e($meta['alt']??'') ?>" loading="lazy"><h3><?= e($item['original_name']) ?></h3><p class="muted small"><?= e($item['mime']) ?> · <?= (int)$item['width'] ?>×<?= (int)$item['height'] ?> · <?= number_format((int)$item['size']/1024,1) ?> KB</p><form method="post"><?php admin_action('metadata') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="lang" value="<?= e($lang) ?>"><?php admin_field('Başlık','title',(string)($meta['title']??'')); admin_field('Alternatif metin','alt',(string)($meta['alt']??'')); admin_textarea('Açıklama','caption',(string)($meta['caption']??''),2) ?><button class="secondary">Dil metnini kaydet</button></form></article><?php endforeach ?></div><?php
}

function admin_gallery_page(): void
{
    $items=rows("SELECT m.*,g.status,g.sort_order,g.id AS gallery_id FROM media m LEFT JOIN gallery_items g ON g.media_id=m.id WHERE m.purpose='library' AND NOT EXISTS(SELECT 1 FROM request_media r WHERE r.media_id=m.id) ORDER BY CASE WHEN g.id IS NULL THEN 1 ELSE 0 END,g.sort_order DESC,m.id DESC LIMIT 150");
    ?><section class="panel"><h2>Gerçek görsellerden galeri oluşturun</h2><p class="muted">Araç, ofis ve yapılan taşımalara ait kullanım hakkı sizde olan görselleri önce medya kütüphanesine yükleyin. Yayına almadığınız hiçbir görsel ziyaretçilere gösterilmez.</p></section><?php if(!$items){admin_empty('Galeri için görsel yok.','Önce görsel kütüphanesine gerçek işletme görselleri ekleyin.');return;} ?><div class="media-grid gallery-admin-grid"><?php foreach($items as $item): ?><article class="panel media-card"><img src="/media/<?= (int)$item['id'] ?>?variant=webp" alt="" loading="lazy"><h3><?=e($item['original_name'])?></h3><p class="muted small"><?=isset($item['gallery_id'])?'Galeri kaydı #'.(int)$item['gallery_id']:'Henüz galeriye eklenmedi'?></p><form method="post"><?php admin_action('gallery') ?><input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>"><?php admin_select('Yayın durumu','status',['draft'=>'Taslak / gizli','published'=>'Galeride yayınla'],(string)($item['status']??'draft')); admin_field('Sıra (büyük sayı önce)','sort_order',(string)($item['sort_order']??0),'number'); ?><button><?=isset($item['gallery_id'])?'Galeriyi güncelle':'Galeriye ekle'?></button></form></article><?php endforeach?></div><?php
}

function admin_languages_page(): void
{
    $packages = rows('SELECT * FROM site_languages ORDER BY native_name');
    ?><div class="split"><section class="panel"><h2>Yeni dil paketi</h2><p class="muted">Dil önce gizli taslak olur. Arayüz sözlüğü ve içerikler editör incelemesinden sonra yayınlanır.</p><form method="post"><?php admin_action('create_language'); admin_field('Dil kodu (ör. sv veya pt-br)','code','','text',true); admin_field('Dilin kendi adı','native_name','','text',true); admin_field('İngilizce dil adı','english_name','','text',true); admin_select('Yazı yönü','direction',['ltr'=>'Soldan sağa','rtl'=>'Sağdan sola'],'ltr'); ?><button>Dil paketi oluştur</button></form></section><section class="panel"><h2>İşleyiş</h2><ol><li>Paketi oluşturun.</li><li>AI ile arayüz sözlüğü taslağı hazırlayın.</li><li>Sözlüğü gözden geçirip yayınlayın ve etkinleştirin.</li><li>İçerik stüdyosunda bu dili seçip AI çeviri işlerini kuyruğa alın.</li></ol><p class="muted small">AI hiçbir paketi otomatik yayımlamaz. OpenAI anahtarı sunucuda tanımlı olmalıdır; kullanım sağlayıcı hesabınıza yansır.</p></section></div><section class="panel"><h2>Özel dil paketleri</h2><?php if(!$packages): ?><p class="muted">Mevcut 14 yerleşik dil paketinin yanında henüz özel paket eklenmedi.</p><?php endif ?><?php foreach($packages as $package): $dictionary=(string)$package['ui_catalog']; ?><details class="panel"><summary><strong><?=e(strtoupper($package['code']))?> · <?=e($package['native_name'])?></strong> <span class="muted"> <?=e($package['english_name'])?> · <?=e($package['direction'])?> · <?=e($package['ui_status'])?> / <?=e($package['status'])?></span></summary><div class="split"><form method="post"><?php admin_action('translate_interface') ?><input type="hidden" name="code" value="<?=e($package['code'])?>"><p>Türkçe kaynak arayüz metinleri AI ile bu dilde inceleme taslağına çevrilir.</p><button class="secondary">AI ile arayüz sözlüğü hazırla</button></form><form method="post"><?php admin_action('save_language') ?><input type="hidden" name="code" value="<?=e($package['code'])?>"><?php admin_select('Arayüz sözlüğü durumu','ui_status',['draft'=>'Taslak','review'=>'İncelemede','published'=>'Yayınla'],(string)$package['ui_status']); admin_select('Sitede görünürlük','status',['disabled'=>'Gizli','enabled'=>'Etkin'],(string)$package['status']); admin_textarea('Arayüz sözlüğü (JSON; tüm anahtarlar zorunlu)', 'ui_catalog', $dictionary, 16); ?><button>Dil paketini kaydet</button></form></div></details><?php endforeach ?></section><?php
}

function admin_seo_page(): void
{
    if (($_GET['home'] ?? '') === '1') {
        $lang=admin_lang();
        ?><section class="panel"><h2>Ana Sayfa Bölümleri</h2><p class="muted">Seçili dil için ana sayfa bölümlerini düzenleyin.</p><form method="post"><?php admin_action('homepage'); ?><input type="hidden" name="lang" value="<?=e($lang)?>"><?php admin_field('Üst başlık','homepage_eyebrow',setting('homepage_eyebrow_'.$lang)); admin_field('Ana başlık','homepage_title',setting('homepage_title_'.$lang)); admin_textarea('Ana açıklama','homepage_intro',setting('homepage_intro_'.$lang),4); admin_field('Bölgeler başlığı','homepage_regions_title',setting('homepage_regions_title_'.$lang)); admin_textarea('Bölgeler açıklaması','homepage_regions_text',setting('homepage_regions_text_'.$lang),4); admin_field('Süreç başlığı','homepage_process_title',setting('homepage_process_title_'.$lang)); ?><button>Ana Sayfa Metinlerini Kaydet</button></form><p><a href="/admin/seo">← SEO Merkezine Dön</a></p></section><?php
        ?><section class="panel"><h2>Hizmetler Bölümü</h2><form method="post"><?php admin_action('homepage'); ?><input type="hidden" name="lang" value="<?=e($lang)?>"><?php admin_field('Hizmetler başlığı','homepage_service_title',setting('homepage_service_title_'.$lang)); admin_textarea('Hizmetler açıklaması','homepage_service_intro',setting('homepage_service_intro_'.$lang),4); ?><button>Hizmetler Metnini Kaydet</button></form></section><?php
        return;
    }
    $lang=admin_lang(); ?><div class="split"><section class="panel"><h2>Ana sayfa dil metinleri</h2><form method="get"><?php admin_select('Dil','lang',admin_locales(),$lang) ?><button class="secondary">Dili seç</button></form><form method="post"><?php admin_action('homepage') ?><input type="hidden" name="lang" value="<?= e($lang) ?>"><?php admin_field('Ana başlık (boşsa varsayılan çeviri)','homepage_title',setting('homepage_title_'.$lang)); admin_textarea('Açıklama (boşsa varsayılan çeviri)','homepage_intro',setting('homepage_intro_'.$lang),4); admin_field('Bağımsız SEO başlığı','homepage_seo_title',setting('homepage_seo_title_'.$lang)); admin_textarea('Bağımsız meta açıklama','homepage_seo_description',setting('homepage_seo_description_'.$lang),3); admin_textarea('KVKK · veri sorumlusu ve iletişim','legal_controller',setting('legal_controller_'.$lang),3); admin_textarea('KVKK · işleme amacı, hukuki sebep, saklama ve aktarım','legal_processing',setting('legal_processing_'.$lang),6); admin_textarea('KVKK · haklar ve başvuru kanalları','legal_rights',setting('legal_rights_'.$lang),4) ?><button>Kaydet</button></form></section><section class="panel"><h2>Teknik SEO</h2><ul class="checklist"><li><a href="/sitemap.xml">Dinamik sitemap ↗</a></li><li><a href="/robots.txt">Robots yönergeleri ↗</a></li><li>Canonical ve karşılıklı hreflang bağlantıları</li><li>Yayın durumuna göre indekslenebilirlik</li><li>Open Graph, X Cards ve JSON-LD</li><li>URL değişikliklerinde 301 yönlendirme kaydı</li></ul><p class="muted">Her içerik ve dil için SEO başlığı, açıklaması ve hedef kelimeleri içerik editöründen düzenleyin. Hedef kelimeler meta keywords olarak yayınlanmaz.</p><p class="muted">Canlı alan adını APP_URL ile ayarlayın. Search Console ve Analytics kimlikleri işletme hesabıyla ayrıca doğrulanmalıdır.</p></section></div><section class="panel"><h2>Yönlendirme kayıtları</h2><?php $items=rows('SELECT * FROM redirects ORDER BY id DESC LIMIT 100'); if(!$items): ?><p class="muted">Henüz URL değişikliği yok.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Dil</th><th>Eski kısa ad</th><th>Yeni kısa ad</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?= e($item['lang']) ?></td><td><?= e($item['old_slug']) ?></td><td><?= e($item['new_slug']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php
    ?><section class="panel"><h2>Ana sayfa üst başlığı</h2><p class="muted">Seçili dilde boş bırakılırsa varsayılan çeviri kullanılır.</p><form method="post"><?php admin_action('homepage'); ?><input type="hidden" name="lang" value="<?=e($lang)?>"><?php admin_field('Üst başlık','homepage_eyebrow',setting('homepage_eyebrow_'.$lang)); ?><button>Üst başlığı kaydet</button></form></section><?php
}

function admin_legal_page(): void
{
    $lang=admin_lang();
    ?><div class="split"><section class="panel"><h2><?=e(admin_section_title('legal',$lang))?></h2><p class="muted">Her dil için kullanım şartları ve destek hizmet seviyesi metinlerini yönetin.</p><form method="post"><?php admin_action('legal'); ?><input type="hidden" name="lang" value="<?=e($lang)?>"><?php admin_select('Dil','lang',admin_locales(),$lang); admin_textarea('Kullanım şartları','legal_terms',setting('legal_terms_'.$lang),8); admin_textarea('Destek SLA metni','legal_sla',setting('legal_sla_'.$lang),8); ?><button>Yasal metinleri kaydet</button></form></section><section class="panel"><h2>Public bağlantılar</h2><p><a href="/<?=e($lang)?>/kullanim-sartlari/">Kullanım şartlarını aç ↗</a></p><p><a href="/<?=e($lang)?>/destek-sla/">Destek SLA sayfasını aç ↗</a></p></section></div><?php
}

function admin_themes_page(): void
{
    $themes=rows('SELECT * FROM themes ORDER BY is_active DESC,updated_at DESC'); $editing=(int)($_GET['id']??0); $theme=$editing ? row('SELECT * FROM themes WHERE id=?',[$editing]) : null; $sdkThemes=theme_discover_manifests(); $sdkSlug=setting('theme_sdk_slug',''); $sdkTokens=setting('theme_sdk_tokens_json','{}'); $sdkSections=setting('theme_sdk_sections_json','{}');
    ?><div class="split"><section class="panel"><h2><?= $theme?'Temayı düzenle':'Yeni tema' ?></h2><p class="muted">Taslak temalar ziyaretçilere uygulanmaz. Tema dili seçildiğinde yalnız o dilde gösterilir; “tüm diller” teması diğer diller için yedektir. Etkinleştirmek temayı otomatik yayınlar.</p><form method="post"><?php admin_action('theme') ?><input type="hidden" name="id" value="<?= (int)($theme['id']??0) ?>"><?php admin_field('Tema adı','name',(string)($theme['name']??''),'text',true); admin_select('Tema dili','locale',['global'=>'Tüm diller']+admin_locales(),(string)($theme['locale']??'global')); admin_field('Ana renk','primary_color',(string)($theme['primary_color']??'#0D2E42')); admin_field('Vurgu rengi','accent_color',(string)($theme['accent_color']??'#FF9149')); admin_textarea('Özel CSS','custom_css',(string)($theme['custom_css']??''),8); admin_select('Yayın durumu','status',['draft'=>'Taslak','published'=>'Yayınla'],(string)($theme['status']??'draft')); ?><label><input type="checkbox" name="is_active" value="1" <?=!empty($theme['is_active'])?'checked':''?>> Bu dilde ziyaretçi sitesinde etkinleştir</label><button>Tema kaydet</button></form></section><section class="panel"><h2>Yayınlanan temalar</h2><?php foreach($themes as $item): ?><p><strong><?=e($item['name'])?></strong> · <?=e($item['locale']==='global'?'Tüm diller':strtoupper($item['locale']))?> · <?=e($item['status'])?> <?= $item['is_active']?'· Etkin':'' ?> <a href="/admin/themes?id=<?= (int)$item['id'] ?>">Düzenle</a></p><?php endforeach ?></section></div><?php
    ?><section class="panel"><h2>Tema SDK paketleri</h2><p class="muted">Data-only manifestler burada doğrulanır. Tema bölümleri içerikten ayrıdır; child theme üst temayı miras alır. Paket kodu bu ekranda çalıştırılmaz.</p><div class="table-wrap"><table><thead><tr><th>Tema</th><th>Sürüm</th><th>Parent</th><th>Section</th><th>14 dil</th><th>Durum</th></tr></thead><tbody><?php foreach($sdkThemes as $item): $m=is_array($item['manifest'])?$item['manifest']:[]; $errs=$item['errors']; ?><tr><td><strong><?=e((string)($m['name']??basename((string)$item['path'])))?></strong><br><code><?=e((string)($m['slug']??''))?></code></td><td><?=e((string)($m['version']??'—'))?></td><td><?=e((string)($m['parent']??'—'))?></td><td><?=count((array)($m['sections']??[]))?></td><td><?=count((array)($m['translations']??[]))>=14?'✓':'○'?></td><td><?= $errs===[]?'<span class="badge badge-published">Geçerli</span>':'<span class="badge badge-failed">'.e(implode(', ',$errs)).'</span>' ?></td></tr><?php endforeach; if(!$sdkThemes): ?><tr><td colspan="6">Henüz SDK teması yok.</td></tr><?php endif; ?></tbody></table></div><p class="small"><code>php bin/theme.php validate themes</code> ile dağıtımdan önce doğrulayın.</p></section><section class="panel"><h2>Aktif SDK tema oluşturucu ayarları</h2><p class="muted">Tema paketi PHP çalıştırmaz. Sadece doğrulanmış tasarım token’ları ve section override’ları kaydedilir; tema güncellemeleri bu ayarları korur.</p><form method="post"><?php admin_action('theme_sdk_config'); admin_select('SDK teması','sdk_slug',[''=>'SDK teması seçin']+array_reduce($sdkThemes,static function(array $out,array $item):array{$m=$item['manifest']??[]; if(($item['errors']??[])===[]&&isset($m['slug']))$out[(string)$m['slug']]=(string)($m['name']??$m['slug']); return $out;},[]),$sdkSlug); admin_textarea('Tasarım token override JSON','sdk_tokens',$sdkTokens,8); admin_textarea('Section override JSON','sdk_sections',$sdkSections,8); ?><p class="small">Örnek token: <code>{"color_accent":"#126e82","radius":"12px"}</code></p><button>SDK tema ayarlarını doğrula ve kaydet</button></form></section><section class="panel"><h2>Demo içe aktarımı</h2><p class="muted">Yalnızca veri içeren JSON demo paketi kabul edilir. İçerikler yayınlanmaz; taslak olarak oluşturulur.</p><form method="post"><?php admin_action('theme_demo_import'); admin_field('Tema slug','theme_slug','nexa-business','text',true); admin_textarea('Demo JSON','demo_json','{\"pages\":[]}',8); ?><button>Demo verisini taslak olarak içe aktar</button></form></section><?php
}

function admin_updates_page(): void
{
    $result = update_check(($_GET['check'] ?? '') === '1');
    $version = setting('app_version',envv('APP_VERSION','1.0.0'));
    admin_update_packages_page();
    $preflight=update_preflight();
    admin_update_preflight_page($preflight);
    ?><div class="split"><section class="panel"><h2>Güvenli güncelleme</h2><p class="muted">Sürüm kontrolü lisans merkezine yapılır. Paket indirilmeden önce lisans, alan adı ve kurulum kimliği doğrulanır.</p><p>Kurulu sürüm: <strong><?=e($version)?></strong></p><p>Kurulum kimliği: <code><?=e(update_installation_id())?></code></p><form method="get"><input type="hidden" name="check" value="1"><button>Şimdi kontrol et</button></form></section><section class="panel"><h2>Durum</h2><?php if(!empty($result['offline'])): ?><p class="notice">⚠ <?=e($result['message']??'Lisans merkezi şu anda erişilemiyor.')?></p><?php elseif(empty($result['configured'])): ?><p class="notice"><?=e($result['message']??'Lisans ayarlarını tamamlayın.')?></p><?php elseif(empty($result['active'])): ?><p class="notice">Lisans doğrulanmadı; güncelleme sunulamaz.</p><?php elseif(!empty($result['update'])): $u=$result['update']; ?><p class="notice success">Yeni sürüm hazır: <strong><?=e($u['version']??'')?></strong></p><p><?=nl2br(e($u['changelog']??'Değişiklik notu yok.'))?></p><form method="post"><?php admin_action('download_update'); ?><input type="hidden" name="version" value="<?=e($u['version']??'')?>"><input type="hidden" name="sha256" value="<?=e($u['sha256']??'')?>"><button>Paketi indir ve doğrula</button></form><p class="muted">Bu işlem yalnızca paketi indirir; dosyaları değiştirmez. Uygulama adımı yedek ve geri alma kontrollerinden sonra yapılacak.</p><?php else: ?><p class="notice success">Sistem güncel.</p><?php endif; ?></section></div><section class="panel"><h2>Güncelleme politikası</h2><ul class="checklist"><li>Her sürüm SHA-256 ve imza ile doğrulanır.</li><li>Uygulama kurulumu otomatik zorlanmaz; yönetici onayı gerekir.</li><li>Yedek alınmadan ve migrasyon kontrol edilmeden dosya değiştirilmez.</li><li>Lisans merkezi geçici olarak kapalıysa site grace period içinde çalışmaya devam eder.</li></ul></section><?php
}

function admin_update_packages_page(): void
{
    $packages=glob(storage_path('update-*.zip'))?:[]; if(!$packages)return;
    ?><section class="panel"><h2>Doğrulanmış paketler</h2><p class="muted">Uygulama yalnızca yönetici onayıyla değişir. Her paket uygulanmadan önce otomatik veri yedeği alınır.</p><?php foreach($packages as $package): $name=basename($package); if(!preg_match('/^update-(.+)\.zip$/',$name,$m))continue; ?><form method="post" class="inline-form"><?php admin_action('apply_update'); ?><input type="hidden" name="version" value="<?=e($m[1])?>"><strong><?=e($name)?></strong><button>Yedekle ve uygula</button></form><?php endforeach; ?></section><?php
}

function admin_update_preflight_page(array $checks): void
{
    ?><section class="panel"><h2>Sunucu ön kontrolü</h2><ul class="checklist"><?php foreach($checks as $label=>$ok): ?><li><?= $ok?'✓':'○' ?> <?=e($label)?> <span class="badge"><?= $ok?'Hazır':'Eksik' ?></span></li><?php endforeach; ?></ul><p class="muted">Plesk/cPanel üzerinde güncelleme için tüm maddeler hazır olmalıdır.</p></section><?php
}

function admin_licenses_page(): void
{
    $active=setting('license_status')==='active'; ?><div class="split"><section class="panel"><h2>TofiDesk lisansı</h2><p class="muted">Lisans anahtarı bu kurulumun alan adına bağlanır. Anahtar yalnız HTTPS lisans merkeziyle doğrulanır.</p><form method="post"><?php admin_action('license'); admin_field('Lisans merkezi','license_server_url',setting('license_server_url','https://product.tofidesk.com'),'url',true); admin_field('Lisans anahtarı','license_key',setting('license_key'),'text',true) ?><button>Kaydet ve doğrula</button></form></section><section class="panel"><h2>Durum</h2><p class="<?= $active?'notice success':'notice' ?>"><?= $active?'Etkin lisans':'Henüz doğrulanmadı' ?></p><p>Alan adı: <strong><?=e(license_domain() ?: 'APP_URL gerekli')?></strong></p><?php if(setting('license_checked_at')):?><p class="muted">Son kontrol: <?=e(setting('license_checked_at'))?></p><?php endif?><?php if(setting('license_theme')):?><p>Merkezden gelen tema: <strong><?=e(setting('license_theme'))?></strong></p><?php endif?><p class="muted">CodeCanyon alıcıları satın alma kodlarını lisans merkezinde anahtara dönüştürür. Satın alma kodu bu panelde saklanmaz.</p></section></div><?php
}

function admin_notification_rules_page(): void
{
    ?><form method="post" class="panel"><?php admin_action('notification_rules'); ?><h2>Bildirim kuralları</h2><p class="muted">Olay oluştuğunda e-posta kuyruğuna bildirim alınıp alınmayacağını seçin.</p><label><input type="checkbox" name="notify_request_created" value="1" <?= setting('notify_request_created','1')==='1'?'checked':'' ?>> Yeni teklif taleplerinde bildir</label><label><input type="checkbox" name="notify_appointment_created" value="1" <?= setting('notify_appointment_created','1')==='1'?'checked':'' ?>> Yeni randevu taleplerinde bildir</label><button>Kuralları kaydet</button></form><?php
}

function admin_email_templates_page(): void
{
    ?><form method="post" class="panel"><?php admin_action('mail_templates'); ?><h2>E-posta şablonları</h2><p class="muted">Şablonlar düz metin olarak kuyruğa alınır. Kullanılabilir değişkenler: <code>{reference}</code>, <code>{type}</code>, <code>{link}</code>.</p><?php admin_field('Teklif konusu','mail_subject_request',setting('mail_subject_request','Yeni teklif talebi')); admin_textarea('Teklif gövdesi','mail_body_request',setting('mail_body_request','Yeni {type} talebi alındı. Referans: {reference}. Ayrıntılar yönetim panelindedir.'),5); admin_field('Randevu konusu','mail_subject_appointment',setting('mail_subject_appointment','Yeni randevu talebi')); admin_textarea('Randevu gövdesi','mail_body_appointment',setting('mail_body_appointment','Yeni {type} talebi alındı. Referans: {reference}. Ayrıntılar yönetim panelindedir.'),5); admin_field('Doğrulama konusu','mail_subject_verification',setting('mail_subject_verification','E-posta doğrulama bağlantınız')); admin_textarea('Doğrulama gövdesi','mail_body_verification',setting('mail_body_verification','Hesabınızı doğrulamak için bu bağlantıyı açın: {link}'),5); ?><button>Şablonları kaydet</button></form><?php
}

function admin_settings_page(): void
{
    if (($_GET['brand'] ?? '') === '1') {
        ?><section class="panel"><h2>Marka ve Ana Görsel</h2><p class="muted">Tüm adresler HTTPS olmalıdır. Boş hero görseli varsayılan görseli kullanır.</p><form method="post"><?php admin_action('settings'); admin_field('Logo URL','logo_url',setting('logo_url'),'url'); admin_field('Favicon URL','favicon_url',setting('favicon_url'),'url'); admin_field('Hero görseli URL','hero_image_url',setting('hero_image_url'),'url'); admin_field('PWA uygulama adı','pwa_name',setting('pwa_name') ?: setting('business_name','TofiDesk')); admin_textarea('PWA açıklaması','pwa_description',setting('pwa_description'),2); ?><button>Marka Ayarlarını Kaydet</button></form><p><a href="/admin/settings">← Ayarlara Dön</a></p></section><?php
        return;
    }
    echo '<p class="notice"><a href="/admin/settings?brand=1">Marka ve Ana Görsel ayarlarını aç →</a> · <a href="/admin/settings?map=1">Harita Görünümü ayarlarını aç →</a></p>';
    ?><section class="panel"><h2>Global ürün profili</h2><p class="muted">Bu profil ürün manifestlerini, yapay zekâ bağlamını ve marketplace dışa aktarımlarını besler. Marketplace kurulumu için sektörden bağımsız tutun.</p><form method="post"><?php admin_action('settings'); admin_field('Ürün sektörü','product_industry',setting('product_industry','business software and digital products')); admin_field('Ürün konumu','product_location',setting('product_location')); ?><button>Ürün profilini kaydet</button></form></section><?php
    if (($_GET['map'] ?? '') === '1') {
        ?><section class="panel"><h2>Harita Görünümü</h2><p class="muted">Harita sağlayıcısı ziyaretçi açıkça haritayı istediğinde kullanılır. Gizli API anahtarları sunucu ortamında kalır; özel HTTPS embed adresi boş bırakılırsa çekirdek sağlayıcı bağlantısı oluşturur.</p><form method="post"><?php admin_action('map_settings'); admin_select('Harita sağlayıcısı','maps_provider',['openstreetmap'=>'OpenStreetMap','google'=>'Google Maps','bing'=>'Bing Maps'],setting('maps_provider','openstreetmap')); admin_field('Konum etiketi','location_label',setting('location_label')); admin_field('Özel HTTPS harita embed adresi','map_embed_url',setting('map_embed_url'),'url'); ?><button>Harita Ayarlarını Kaydet</button></form><p><a href="/admin/settings">← Ayarlara Dön</a></p></section><?php
        return;
    }
    ?><form method="post"><?php admin_action('settings') ?><div class="split"><section class="panel"><h2>Marka ve iletişim</h2><?php admin_field('Marka adı','business_name',setting('business_name','TofiDesk')); admin_field('Ülke kodu (ISO)','country_code',setting('country_code','TR')); admin_field('Para birimi (ISO)','currency_code',setting('currency_code','USD')); admin_field('Saat dilimi','timezone',setting('timezone','UTC')); admin_field('Konum etiketi','location_label',setting('location_label')); admin_field('Telefon','contact_phone',setting('contact_phone')); admin_field('E-posta','contact_email',setting('contact_email'),'email'); admin_field('WhatsApp','whatsapp',setting('whatsapp')); admin_field('Adres','address',setting('address')); admin_textarea('İşletme açıklaması','site_description',setting('site_description'),4); admin_field('Instagram URL','instagram_url',setting('instagram_url'),'url'); admin_field('Facebook URL','facebook_url',setting('facebook_url'),'url'); admin_field('LinkedIn URL','linkedin_url',setting('linkedin_url'),'url'); admin_field('YouTube URL','youtube_url',setting('youtube_url'),'url'); admin_field('Pinterest URL','pinterest_url',setting('pinterest_url'),'url'); admin_field('Pinterest doğrulama kodu','pinterest_verify',setting('pinterest_verify')); admin_field('Google site doğrulama kodu','google_site_verification',setting('google_site_verification')); admin_field('Bing Webmaster doğrulama kodu','bing_site_verification',setting('bing_site_verification')); admin_field('Yandex Webmaster doğrulama kodu','yandex_site_verification',setting('yandex_site_verification')); admin_field('Google Analytics 4 ID','analytics_id',setting('analytics_id'),'text',false); ?></section><section class="panel"><h2>Entegrasyon tercihleri</h2><?php admin_select('İçerik sağlayıcısı','ai_provider',['openai'=>'OpenAI','compatible'=>'OpenAI uyumlu / yerel'],setting('ai_provider','openai')); admin_field('Model','ai_model',setting('ai_model') ?: 'gpt-4.1-mini'); admin_field('SMTP sunucusu','smtp_host',setting('smtp_host')); admin_field('SMTP portu','smtp_port',setting('smtp_port','587'),'number'); admin_field('SMTP kullanıcı adı','smtp_user',setting('smtp_user')); admin_field('Gönderen e-posta','mail_from',setting('mail_from'),'email'); admin_field('Teklif e-posta konusu','mail_subject_request',setting('mail_subject_request','Yeni teklif talebi')); admin_field('Randevu e-posta konusu','mail_subject_appointment',setting('mail_subject_appointment','Yeni randevu talebi')); admin_field('Doğrulama e-posta konusu','mail_subject_verification',setting('mail_subject_verification','E-posta doğrulama bağlantınız')) ?><p class="notice">Gizli anahtarlar sunucu ortamında saklanır. Analytics yalnızca APP_ENV=production iken yüklenir.</p></section></div><button>Ayarları kaydet</button></form><?php
    ?><section class="panel"><h2>Logo ve PWA</h2><p class="muted">Logo ve favicon için sunucuda erişilebilir HTTPS görsel adresleri kullanın.</p><form method="post"><?php admin_action('settings'); admin_field('Logo URL','logo_url',setting('logo_url'),'url'); admin_field('Favicon URL','favicon_url',setting('favicon_url'),'url'); admin_field('PWA uygulama adı','pwa_name',setting('pwa_name') ?: setting('business_name','TofiDesk')); admin_textarea('PWA açıklaması','pwa_description',setting('pwa_description'),2); ?><button>Marka ve PWA ayarlarını kaydet</button></form></section><section class="panel"><h2>Yapay zekâ sağlayıcısı</h2><p class="muted">OpenAI veya OpenAI uyumlu bir endpoint seçin. Uyumlu servis için <code>AI_BASE_URL</code> ortam değişkenini tanımlayın.</p><form method="post"><?php admin_action('ai_settings'); admin_select('Sağlayıcı','ai_provider',['openai'=>'OpenAI','compatible'=>'OpenAI uyumlu / yerel'],setting('ai_provider','openai')); admin_field('Model','ai_model',setting('ai_model') ?: 'gpt-4.1-mini'); ?><button>AI ayarlarını kaydet</button></form></section><?php
}

function admin_users_page(): void
{
    ?><div class="split"><section class="panel"><h2>Yeni kullanıcı</h2><form method="post"><?php admin_action('user'); admin_field('Ad soyad','name','','text',true); admin_field('E-posta','email','','email',true); admin_field('Parola (12+ karakter, en fazla 72 bayt)','password','','password',true); admin_select('Yetki','role',['operator'=>'Operasyon · talepler ve randevular','editor'=>'Editör · içerik ve görseller','admin'=>'Yönetici · tüm ayarlar'],'operator') ?><button>Kullanıcı oluştur</button></form></section><section class="panel"><h2>Erişim yetkileri</h2><p>Operasyon kullanıcıları müşteri taleplerini ve randevuları yönetir. Editörler içerik, diller, SEO ve görselleri yönetir. Yöneticiler tüm modüllere erişir.</p><p class="muted">İki aşamalı doğrulama sunucu komutuyla etkinleştirilir. Gizli doğrulama anahtarları listelenmez.</p></section></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Kullanıcı</th><th>Rol</th><th>İki aşamalı giriş</th><th>Oluşturma</th></tr></thead><tbody><?php foreach(rows('SELECT id,name,email,role,created_at,CASE WHEN totp_secret IS NOT NULL AND totp_secret<>\'\' THEN 1 ELSE 0 END AS has_totp FROM users ORDER BY id') as $item): ?><tr><td><strong><?= e($item['name']) ?></strong><small><?= e($item['email']) ?></small></td><td><?= e($item['role']) ?></td><td><?= $item['has_totp']?'Etkin':'Etkin değil' ?></td><td><?= e($item['created_at']) ?></td></tr><?php endforeach ?></tbody></table></div></section><?php
}

function admin_jobs_page(): void
{
    ?><p class="intro">Her dil ayrı bir iş olarak saklanır. İşçi komutu kuyruğu işler; tamamlanan sonuçları inceleyip açık onayla taslağa alın. Yayın işlemi her zaman dil editöründe yapılır.</p><p class="notice">İşçi: <code>php bin/console.php worker</code> · Gerçek sağlayıcı anahtarı gerektirir. Yeniden denemeler maliyet oluşturabilir.</p><?php $items=rows('SELECT * FROM jobs ORDER BY id DESC LIMIT 100'); if(!$items){admin_empty('Henüz üretim işi yok.','İçerik editöründen konu ve hedef dilleri seçerek üretim isteyin.'); return;} ?><div class="request-list"><?php foreach($items as $item): ?><details class="panel"><summary><strong>İş #<?= (int)$item['id'] ?> · <?= e(strtoupper($item['lang'])) ?> · <?= e($item['mode']) ?></strong><?= admin_status($item['status']) ?></summary><p class="muted">İçerik #<?= (int)$item['article_id'] ?> · Maliyet: sağlayıcı hesabından izlenir · Deneme: <?= (int)$item['attempts'] ?> · <?= e($item['updated_at']) ?></p><p><?= nl2br(e($item['prompt'])) ?></p><?php if($item['error']): ?><p class="notice error"><?= e($item['error']) ?></p><?php endif ?><?php if($item['status']==='completed'): $result=json_decode((string)$item['result'],true); ?><pre class="job-result"><?= e(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: (string)$item['result']) ?></pre><form method="post"><?php admin_action('apply') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php $translation=row('SELECT revision FROM translations WHERE article_id=? AND lang=?',[$item['article_id'],$item['lang']]); ?><input type="hidden" name="revision" value="<?= (int)($translation['revision']??0) ?>"><p class="muted">Bu dilde metin varsa, bu düğme mevcut metni taslak olarak değiştirir. Önce sonucu ve güncel sürümü inceleyin.</p><a href="/admin/editor?id=<?= (int)$item['article_id'] ?>&amp;lang=<?= e($item['lang']) ?>">Mevcut dili incele ↗</a> <button>Onaylı sonucu taslağa al</button></form><?php elseif($item['status']==='failed'): ?><form method="post"><?php admin_action('retry') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="secondary">Tekrar kuyruğa al</button></form><?php endif ?></details><?php endforeach ?></div><?php
}

function admin_audit_page(): void
{
    $items=rows('SELECT a.*,u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 300');
    if(!$items){admin_empty('Henüz işlem kaydı yok.','Yönetim işlemleri burada kaydedilir.');return;}
    ?><section class="panel"><div class="table-wrap"><table><thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th><th>Ayrıntı</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?= e($item['created_at']) ?></td><td><?= e($item['actor']??'Sistem') ?></td><td><?= e($item['action']) ?></td><td><?= e($item['detail']) ?></td></tr><?php endforeach ?></tbody></table></div></section><?php
}

function admin_backup_page(): void
{
    ?><section class="panel"><h2>Veriyi koruyun.</h2><p>Veritabanı ve medya dosyalarının korumalı bir yedeğini oluşturun. API anahtarlarını içeren ortam dosyaları ve oturum dosyaları yedeğe eklenmez. Veritabanı yedeği; müşteri kayıtlarını, parola özetlerini ve etkinleştirilmiş iki aşamalı giriş sırlarını içerir. Yedeği erişimi sınırlı ve şifreli bir yerde saklayın.</p><form method="post"><?php admin_action('backup') ?><button>Şimdi yedek oluştur ↓</button></form><p class="muted">Yedekler web kökünün dışında tutulur. Otomatik yedekleme için sunucuda günlük zamanlayıcı, geri yükleme için README'deki bakım adımları kullanılmalıdır. Geri yükleme, mevcut verilerin üzerine yazabileceğinden bu panelden tetiklenmez.</p></section><?php
}

function admin_mail_page(): void
{
    ?><p class="intro">Bildirimler önce kalıcı kuyruğa alınır. Sunucu işçisi SMTP üzerinden gönderir; başarısız denemeler burada saklanır.</p><p class="notice">İşçi: <code>php bin/console.php worker</code> · SMTP yapılandırması ve gerçek bağlantı testi gerektirir.</p><?php $items=rows('SELECT * FROM mail_queue ORDER BY id DESC LIMIT 200'); if(!$items){admin_empty('E-posta kuyruğu boş.','Talep ve durum bildirimleri burada görünür.');return;} ?><div class="request-list"><?php foreach($items as $item): ?><details class="panel"><summary><div><strong><?= e($item['subject']) ?></strong><small><?= e($item['recipient']) ?></small></div><?= admin_status($item['status']) ?></summary><p class="muted">Deneme: <?= (int)$item['attempts'] ?> · <?= e($item['created_at']) ?></p><pre class="job-result"><?= e($item['body']) ?></pre><?php if($item['last_error']): ?><p class="notice error"><?= e($item['last_error']) ?></p><?php endif ?><?php if($item['status']==='failed'): ?><form method="post"><?php admin_action('retry_mail') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="secondary">Tekrar kuyruğa al</button></form><?php endif ?></details><?php endforeach ?></div><?php
}

