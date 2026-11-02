<?php
declare(strict_types=1);

function admin_roles(string $section): array
{
    return match ($section) {
        'settings', 'email-templates', 'integrations', 'security', 'users', 'audit', 'backup', 'mail', 'languages', 'themes', 'licenses' => ['admin'],
        'content', 'editor', 'media', 'gallery', 'seo', 'jobs' => ['admin', 'editor'],
        'requests', 'appointments' => ['admin', 'operator'],
        default => ['admin', 'editor', 'operator'],
    };
}

function admin_allowed(string $section, array $user): bool
{
    return in_array($user['role'], admin_roles($section), true);
}

function admin_status(string $status): string
{
    $labels = ['tr'=>['new'=>'Yeni talep','quoted'=>'Teklif verildi','proposed'=>'Tarih önerildi','confirmed'=>'Kesinleşti','rejected'=>'Reddedildi','draft'=>'Taslak','review'=>'İncelemede','published'=>'Yayında','queued'=>'Sırada','running'=>'Çalışıyor','completed'=>'Tamamlandı','failed'=>'Hata','sent'=>'Gönderildi','pending'=>'Bekliyor'],'en'=>['new'=>'New request','quoted'=>'Quote sent','proposed'=>'Date proposed','confirmed'=>'Confirmed','rejected'=>'Rejected','draft'=>'Draft','review'=>'In review','published'=>'Published','queued'=>'Queued','running'=>'Running','completed'=>'Completed','failed'=>'Failed','sent'=>'Sent','pending'=>'Pending']];
    $lang = (string)($_REQUEST['lang'] ?? 'en');
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
        $message = 'Talep referansınız: '.$request['reference']."\nDurum: ".strip_tags(admin_status($status))."\nTarih / saat: ".$date.' '.$slot;
        if ($price !== '') $message .= "\nTeklif: ".$price.' TRY';
        enqueue_mail($request['email'], 'Kaş Nakliyat · '.$request['reference'], $message, $id);
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
    $checks=['SMTP yapılandırması'=>setting('smtp_host')!==''&&setting('mail_from')!=='','OpenAI anahtarı'=>envv('OPENAI_API_KEY')!=='','SMS sağlayıcısı'=>($smsProvider==='none'||$smsReady),'WhatsApp sağlayıcısı'=>($waProvider==='none'||$waReady),'PayTR anahtarı'=>envv('PAYTR_MERCHANT_KEY')!=='','Stripe webhook'=>envv('STRIPE_WEBHOOK_SECRET')!=='','Cron/worker'=>is_file(ROOT.'/bin/console.php')];
    ?><div class="split"><section class="panel"><h2>Bildirim kanalları</h2><p class="muted">Sağlayıcı sırları sunucu ortamından okunur; panelde yalnız durum gösterilir.</p><form method="post"><?php admin_action('integrations'); admin_field('SMTP sunucusu','smtp_host',setting('smtp_host')); admin_field('SMTP portu','smtp_port',setting('smtp_port','587'),'number'); admin_field('SMTP kullanıcı adı','smtp_user',setting('smtp_user')); admin_select('SMS sağlayıcısı','sms_provider',['none'=>'Kapalı','netgsm'=>'NetGSM','iletimerkezi'=>'İleti Merkezi','twilio'=>'Twilio'],setting('sms_provider','none')); admin_field('SMS gönderen adı/numarası','sms_sender',setting('sms_sender')); admin_select('WhatsApp sağlayıcısı','whatsapp_provider',['none'=>'Kapalı','cloud'=>'Meta WhatsApp Cloud API','twilio'=>'Twilio WhatsApp'],setting('whatsapp_provider','none')); admin_select('E-posta bildirimi','notification_email_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_email_enabled','1')); admin_select('SMS bildirimi','notification_sms_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_sms_enabled','0')); admin_select('WhatsApp bildirimi','notification_whatsapp_enabled',['1'=>'Açık','0'=>'Kapalı'],setting('notification_whatsapp_enabled','0')); ?><button>Ayarları kaydet</button></form><form method="post" class="panel"><?php admin_action('test_smtp'); admin_field('Test alıcısı','test_recipient',setting('contact_email'),'email',true) ?><button class="secondary">SMTP testini kuyruğa al</button></form><form method="post" class="panel"><?php admin_action('test_notification'); admin_select('Kanal','channel',['sms'=>'SMS','whatsapp'=>'WhatsApp'],'sms'); admin_field('Alıcı telefon / WhatsApp','recipient',''); admin_field('Test mesajı','message','TofiDesk bildirim test mesajı.'); ?><button class="secondary">SMS/WhatsApp testini kuyruğa al</button></form></section><section class="panel"><h2>Entegrasyon sağlığı</h2><ul class="checklist"><?php foreach($checks as $label=>$ok): ?><li><?= $ok?'✓':'○' ?> <?=e($label)?> <span class="badge"><?= $ok?'Hazır':'Test modu / eksik' ?></span></li><?php endforeach ?></ul><p class="notice">Gerçek anahtarları sunucu ortamına ekleyin; ZIP paketlerine sır dahil edilmez.</p></section></div><?php
}

function admin_security_page(array $user): void
{
    $pending=(string)($_SESSION['totp_pending']??'');
    $active=!empty($user['totp_secret']);
    $secret=$pending!==''?$pending:(string)($user['totp_secret']??'');
    $uri=$secret!==''?'otpauth://totp/'.rawurlencode((string)setting('business_name','TofiDesk')).':'.rawurlencode((string)$user['email']).'?secret='.$secret.'&issuer='.rawurlencode((string)setting('business_name','TofiDesk')):'';
    ?><div class="split"><section class="panel"><h2>Hesap güvenliği</h2><p class="muted">Yönetici girişleri için TOTP tabanlı iki aşamalı doğrulama.</p><p>Durum: <span class="badge"><?= $active?'Etkin':'Etkin değil' ?></span></p><?php if(!$active): ?><form method="post"><?php admin_action('totp_start'); ?><button>2FA kurulumu başlat</button></form><?php if($pending!==''): ?><div class="notice"><p>Authenticator uygulamanıza şu anahtarı ekleyin:</p><code><?= e($secret) ?></code><p class="small">Destekleyen uygulamada bu URI kullanılabilir:</p><code><?= e($uri) ?></code><form method="post"><?php admin_action('totp_confirm'); admin_field('6 haneli doğrulama kodu','totp_code','','text',true); ?><button>2FA’yı etkinleştir</button></form></div><?php endif; ?><?php else: ?><form method="post"><?php admin_action('totp_disable'); admin_field('Mevcut 6 haneli kod','totp_code','','text',true); ?><button class="secondary">2FA’yı devre dışı bırak</button></form><?php endif; ?></section><section class="panel"><h2>Oturum politikası</h2><p>Oturum çerezleri HttpOnly, SameSite=Lax ve HTTPS ortamında Secure olarak ayarlanır. Başarılı girişte oturum kimliği yenilenir.</p><form method="post"><?php admin_action('revoke_sessions'); ?><button class="secondary">Tüm yönetici oturumlarını sonlandır</button></form><p class="muted">Bu işlem diğer cihazlardaki yönetici oturumlarını da geçersiz kılar.</p></section></div><?php
}

function handle_admin(string $path): void
{
    header('Cache-Control: no-store');
    $section = trim(substr($path, strlen('/admin')), '/') ?: 'dashboard';
    if ($section === 'login') { admin_login_page(); return; }
    $user = require_login();
    if (!in_array($section, ['dashboard','logout','requests','appointments','content','editor','media','gallery','seo','settings','email-templates','integrations','users','jobs','audit','backup','mail','languages','themes','licenses'], true)) { http_response_code(404); admin_layout('Bulunamadı','dashboard',$user,'<div class="panel">Bu sayfa bulunamadı.</div>'); return; }
    require_role(admin_roles($section));
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
    $titles=['dashboard'=>'Genel bakış','requests'=>'Teklif talepleri','appointments'=>'Randevu takvimi','content'=>'İçerik stüdyosu','editor'=>'Dil editörü','media'=>'Görsel kütüphanesi','gallery'=>'Galeri','seo'=>'SEO merkezi','settings'=>'İşletme ve entegrasyonlar','email-templates'=>'E-posta şablonları','integrations'=>'Entegrasyon merkezi','security'=>'Hesap güvenliği','users'=>'Kullanıcılar','jobs'=>'Yapay zekâ işleri','audit'=>'İşlem kayıtları','backup'=>'Yedekleme','mail'=>'E-posta kuyruğu','languages'=>'Dil paketleri','themes'=>'Tema kütüphanesi','licenses'=>'Lisans'];
    ob_start();
    match($section) {
        'requests','appointments' => admin_requests_page($section),
        'content' => admin_content_page(), 'editor'=>admin_editor_page(), 'media'=>admin_media_page(), 'gallery'=>admin_gallery_page(), 'seo'=>admin_seo_page(),
        'settings'=>admin_settings_page(), 'email-templates'=>admin_email_templates_page(), 'integrations'=>admin_integrations_page(), 'security'=>admin_security_page($user), 'users'=>admin_users_page(), 'jobs'=>admin_jobs_page(), 'audit'=>admin_audit_page(),
        'backup'=>admin_backup_page(), 'mail'=>admin_mail_page(), 'languages'=>admin_languages_page(), 'themes'=>admin_themes_page(), 'licenses'=>admin_licenses_page(), default=>admin_dashboard($user),
    };
    admin_layout($titles[$section],$section,$user,(string)ob_get_clean());
}

function admin_post(string $section, array $user): void
{
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'set_admin_locale') { admin_save_locale($user); return; }
    if ($section === 'logout') { logout_user(); redirect('/admin/login'); }
    if (in_array($section,['requests','appointments'],true) && $action === 'request') { admin_save_request(); return; }
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
    if ($section === 'licenses' && $action === 'license') {
        $server=admin_input('license_server_url',300); $key=admin_input('license_key',100);
        if($server!=='' && (!filter_var($server,FILTER_VALIDATE_URL)||!str_starts_with(strtolower($server),'https://'))) throw new InvalidArgumentException('Lisans sunucusu HTTPS adresi olmalı.');
        save_setting('license_server_url',rtrim($server,'/')); save_setting('license_key',$key); $result=license_check_remote(); audit('license.checked',license_domain()); flash($result['active']?'success':'error',$result['message']); return;
    }
    if ($section === 'email-templates' && $action === 'mail_templates') { foreach(['mail_subject_request','mail_subject_appointment','mail_subject_verification'] as $key) save_setting($key,admin_input($key,180)); foreach(['mail_body_request','mail_body_appointment'] as $key) save_setting($key,admin_input($key,5000)); audit('mail.templates_updated'); flash('success','E-posta şablonları kaydedildi.'); return; }
    if ($section === 'settings' && $action === 'settings') {
        $keys=['business_name','country_code','currency_code','timezone','location_label','contact_phone','contact_email','whatsapp','address','site_description','theme_primary','theme_accent','theme_custom_css','facebook_url','instagram_url','linkedin_url','youtube_url','pinterest_url','pinterest_verify','google_site_verification','analytics_id','ai_provider','ai_model','mail_from','smtp_host','smtp_port','smtp_user','mail_subject_request','mail_subject_appointment','mail_subject_verification','mail_body_request','mail_body_appointment'];
        foreach ($keys as $key) {
            $value=admin_input($key,1000);
            if (in_array($key,['contact_email','mail_from'],true) && $value !== '' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-posta adresini kontrol edin.');
            if (in_array($key,['facebook_url','instagram_url','linkedin_url','youtube_url','pinterest_url'],true) && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value),'https://'))) throw new InvalidArgumentException('Sosyal medya bağlantıları HTTPS adresi olmalı.');
            if (in_array($key,['theme_primary','theme_accent'],true) && $value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/D',$value)) throw new InvalidArgumentException('Tema renkleri altı haneli HEX değeri olmalı.');
            if ($key==='theme_custom_css' && (str_contains(strtolower($value),'</style') || mb_strlen($value)>12000)) throw new InvalidArgumentException('Özel CSS geçersiz veya çok uzun.');
            if ($key==='ai_provider' && !in_array($value,['openai'],true)) throw new InvalidArgumentException('Geçersiz API sağlayıcısı.');
            if ($key==='smtp_port' && $value!=='' && (!ctype_digit($value) || (int)$value<1 || (int)$value>65535)) throw new InvalidArgumentException('Geçersiz SMTP portu.');
            if ($key==='analytics_id' && $value!=='' && !preg_match('/^G-[A-Z0-9]+$/D',$value)) throw new InvalidArgumentException('Analytics kimliği G- ile başlamalıdır.');
            if ($key==='country_code' && $value!=='' && !preg_match('/^[A-Z]{2}$/D',$value)) throw new InvalidArgumentException('Ülke kodu ISO 3166-1 iki harf olmalı.');
            if ($key==='currency_code' && $value!=='' && !preg_match('/^[A-Z]{3}$/D',$value)) throw new InvalidArgumentException('Para birimi ISO 4217 üç harf olmalı.');
            if ($key==='timezone' && $value!=='' && !in_array($value,DateTimeZone::listIdentifiers(),true)) throw new InvalidArgumentException('Geçersiz saat dilimi.');
        }
        foreach ($keys as $key) save_setting($key,admin_input($key,1000));
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
    if ($section === 'seo' && $action === 'homepage') {
        $lang=admin_lang(); foreach(['homepage_title'=>180,'homepage_intro'=>1000,'homepage_seo_title'=>180,'homepage_seo_description'=>400,'legal_controller'=>5000,'legal_processing'=>10000,'legal_rights'=>5000] as $field=>$limit) save_setting($field.'_'.$lang,admin_input($field,$limit));
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
    $error='';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf_verify();
        if (login_user((string)($_POST['email']??''),(string)($_POST['password']??''),(string)($_POST['totp']??''))) redirect('/admin');
        $error='Giriş yapılamadı. Bilgileri, doğrulama kodunu ve deneme sınırını kontrol edin.';
    }
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Yönetim girişi · Kaş Nakliyat</title><link rel="stylesheet" href="/assets/admin.css"></head><body class="login-body"><main class="login-card"><a class="brand" href="/tr/">KAŞ<span>NAKLİYAT</span></a><p class="eyebrow">YÖNETİM MERKEZİ</p><h1>Yeniden merhaba.</h1><p>İçeriğinizi, taleplerinizi ve çalışma takviminizi tek yerden yönetin.</p><?php if($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif ?><?php foreach(take_flash() as $notice): ?><p class="notice <?= e($notice['kind']) ?>" role="status"><?= e($notice['message']) ?></p><?php endforeach ?><form method="post"><?= csrf_field() ?><label>E-posta<input type="email" name="email" autocomplete="username" required></label><label>Parola<input type="password" name="password" autocomplete="current-password" required></label><label>Doğrulama kodu <small>(etkinse)</small><input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label><button type="submit">Güvenli giriş yap <span>↗</span></button></form><p class="muted small">İlk yönetici hesabı sunucu komutuyla oluşturulur. Varsayılan parola bulunmaz.</p><a class="text-link" href="/tr/">← Web sitesine dön</a></main><aside class="login-art"><div class="route-orbit"></div><p>Her taşımada<br>iyi bir başlangıç.</p><span>KAŞ · AKDENİZ</span></aside></body></html><?php
}

function admin_layout(string $title,string $section,array $user,string $body): void
{
    $lang = admin_user_lang($user);
    $direction = locale_direction($lang);
    $nav=['dashboard'=>['◫','Genel bakış'],'requests'=>['↗','Teklif talepleri'],'appointments'=>['▦','Randevular'],'content'=>['▤','İçerik stüdyosu'],'languages'=>['文','Dil paketleri'],'themes'=>['◐','Tema kütüphanesi'],'licenses'=>['◇','Lisans'],'jobs'=>['✧','Yapay zekâ işleri'],'media'=>['▧','Görsel kütüphanesi'],'gallery'=>['▦','Galeri'],'seo'=>['◎','SEO merkezi'],'mail'=>['✉','E-posta kuyruğu'],'settings'=>['⚙','Ayarlar'],'email-templates'=>['✉','E-posta şablonları'],'integrations'=>['⚡','Entegrasyonlar'],'security'=>['🔒','Hesap güvenliği'],'users'=>['♙','Kullanıcılar'],'backup'=>['↓','Yedekleme'],'audit'=>['≡','İşlem kayıtları']];
    ?><!doctype html><html lang="<?= e($lang) ?>" dir="<?= e($direction) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> · Kaş Nakliyat</title><link rel="stylesheet" href="/assets/admin.css"></head><body><a href="#main" class="skip"><?= e(admin_t('skip',$lang)) ?></a><aside class="sidebar"><a class="brand" href="/admin">KAŞ<span>NAKLİYAT</span></a><span class="sidebar-caption"><?= e(admin_t('management',$lang)) ?></span><nav aria-label="<?= e(admin_t('management',$lang)) ?>"><?php foreach($nav as $key=>[$icon,$label]): if(!admin_allowed($key,$user))continue; ?><a class="<?= ($section===$key || ($section==='editor'&&$key==='content'))?'active':'' ?>" href="/admin<?= $key==='dashboard'?'':'/'.$key ?>"><span aria-hidden="true"><?= $icon ?></span><?= e($label) ?></a><?php endforeach ?></nav><div class="sidebar-bottom"><a href="/tr/"><?= e(admin_t('open_site',$lang)) ?> ↗</a><form method="post" class="language-form"><?= admin_action('set_admin_locale'); admin_select(admin_t('language',$lang),'lang',admin_locales(),$lang) ?><button class="secondary" type="submit"><?= e(admin_t('save_language',$lang)) ?></button></form><p><?= e($user['name']) ?><small><?= e($user['role']) ?></small></p><form method="post" action="/admin/logout"><?= csrf_field() ?><button class="logout" type="submit"><?= e(admin_t('logout',$lang)) ?></button></form></div></aside><div class="workspace"><header class="topbar"><span>Kaş Nakliyat <span class="crumb">/ <?= e($title) ?></span></span><span class="topbar-right"><?= e(strtoupper($lang)) ?> <span class="avatar"><?= e(mb_substr($user['name'],0,1)) ?></span></span></header><main id="main" class="main"><div class="page-heading"><div><p class="eyebrow"><?= e(admin_t('workspace',$lang)) ?></p><h1><?= e($title) ?></h1></div><time datetime="<?= date('Y-m-d') ?>"><?= date('d.m.Y') ?></time></div><?php foreach(take_flash() as $flash): ?><p class="notice <?= e($flash['kind']) ?>" role="status"><?= e($flash['message']) ?></p><?php endforeach ?><?= $body ?></main><footer class="admin-footer">Kaş Nakliyat · Bağımsız yönetim platformu <span>Europe/Istanbul</span></footer></div></body></html><?php
}

function admin_dashboard(array $user): void
{
    ?><div class="welcome"><div><p class="eyebrow">HER ŞEY TEK BİR YERDE</p><h2>Bugünü birlikte planlayalım.</h2><p>Gelen talepler, yayınlanan içerikler ve yaklaşan işler için çalışma alanınız.</p></div><span class="welcome-mark" aria-hidden="true">↗</span></div><div class="stat-grid"><?php
    $stats=[];
    if(admin_allowed('requests',$user)) { $stats[]=['Yeni talepler',row("SELECT count(*) n FROM requests WHERE status='new'")['n'],'/admin/requests']; $stats[]=['Kesinleşen randevu',row("SELECT count(*) n FROM requests WHERE type='appointment' AND status='confirmed' AND preferred_date>=?",[date('Y-m-d')])['n'],'/admin/appointments']; }
    if(admin_allowed('content',$user)) { $stats[]=['Yayınlanan dil sürümü',row("SELECT count(*) n FROM translations WHERE status='published' AND (scheduled_at IS NULL OR scheduled_at='' OR scheduled_at<=datetime('now'))")['n'],'/admin/content']; $stats[]=['İnceleme bekleyen',row("SELECT count(*) n FROM translations WHERE status='review'")['n'],'/admin/content']; }
    foreach($stats as [$label,$value,$url]): ?><a class="stat" href="<?= e($url) ?>"><span><?= e($label) ?></span><strong><?= (int)$value ?></strong><small>Ayrıntıları görüntüle ↗</small></a><?php endforeach ?></div><div class="split"><section class="panel"><div class="section-heading"><h2>Çalışma akışı</h2><span class="dot-label">Canlı veriler</span></div><ol class="workflow"><li><strong>Talebi inceleyin</strong><span>Teklif ve randevu başvuruları otomatik kesinleşmez.</span></li><li><strong>İçeriği diline göre hazırlayın</strong><span>Her dilin metni, URL'si ve SEO bilgisi bağımsızdır.</span></li><li><strong>İnceleyerek yayınlayın</strong><span>Yapay zekâ çıktıları, editör onayına kadar ayrı tutulur.</span></li></ol></section><section class="panel"><h2>Yayına hazırlık</h2><ul class="checklist"><li><?= setting('contact_email') ? '✓ İletişim e-postası tanımlı' : '○ İşletme iletişim bilgilerini ekleyin' ?></li><li>○ Gerçek işletme görsellerini yükleyin</li><li>○ KVKK metnini işletmeye göre onaylayın</li><li>○ API ve SMTP bağlantılarını gerçek bilgilerle doğrulayın</li></ul><p class="muted">Gizli anahtarlar yalnızca sunucu ortamında saklanır.</p></section></div><?php
}

function admin_requests_page(string $section): void
{
    $type=$section==='appointments'?'appointment':'quote';
    $items=rows('SELECT * FROM requests WHERE type=? ORDER BY created_at DESC,id DESC LIMIT 200',[$type]);
    ?><p class="intro"><?= $type==='appointment'?'Başvuruları inceleyin, uygun saat önerin ve açık onayla kesinleştirin. Aynı saatteki iki randevu kesinleştirilemez.':'Yeni başvuruları inceleyin ve müşteriye teklif hazırlayın. Durum güncellemeleri e-posta kuyruğuna kaydedilir.' ?></p><?php if(!$items): admin_empty('Henüz başvuru yok.','Web sitesindeki formdan gelen talepler burada görünür.'); return; endif ?>
    <div class="request-list"><?php foreach($items as $item): ?><details class="panel request-card"><summary><div><strong><?= e($item['name']) ?></strong><small><?= e($item['reference']) ?> · <?= e($item['created_at']) ?></small></div><div class="summary-route"><?= e($item['origin']) ?> <span>→</span> <?= e($item['destination']) ?></div><?= admin_status($item['status']) ?></summary><div class="request-detail"><dl><dt>İletişim</dt><dd><?= e($item['email']) ?><br><?= e($item['phone']) ?></dd><dt>Yük</dt><dd><?= e($item['cargo']) ?></dd><dt>Müşteri notu</dt><dd><?= nl2br(e($item['notes'])) ?></dd></dl><?php $photos=rows('SELECT media_id FROM request_media WHERE request_id=?',[$item['id']]); if($photos): ?><div class="photo-links"><?php foreach($photos as $photo): ?><a href="/media/<?= (int)$photo['media_id'] ?>">Ekli görsel #<?= (int)$photo['media_id'] ?> ↗</a><?php endforeach ?></div><?php endif ?><form method="post"><?php admin_action('request') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><div class="form-grid"><?php admin_select('Durum','status',$type==='appointment'?['new'=>'Yeni talep','proposed'=>'Tarih öner','confirmed'=>'Kesinleştir','rejected'=>'Reddet']:['new'=>'Yeni talep','quoted'=>'Teklif verildi','rejected'=>'Reddet'],$item['status']); admin_field('Tarih','preferred_date',(string)$item['preferred_date'],'date'); admin_field('Saat','slot',(string)$item['slot'],'time'); admin_field('Teklif tutarı (TRY)','price',(string)$item['price'],'number'); ?></div><?php admin_textarea('İç not (müşteriye gönderilmez)','admin_note',(string)$item['admin_note'],2) ?><button>Talebi güncelle</button></form><form method="post" class="panel"><h3>Müşteriye e-posta yanıtı</h3><?php admin_action('reply') ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php admin_field('Konu','subject','Kaş Nakliyat · '.$item['reference']); admin_textarea('Mesaj','message','',5) ?><button class="secondary">SMTP kuyruğuna al</button></form></div></details><?php endforeach ?></div><?php
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
    $lang=admin_lang(); ?><div class="split"><section class="panel"><h2>Ana sayfa dil metinleri</h2><form method="get"><?php admin_select('Dil','lang',admin_locales(),$lang) ?><button class="secondary">Dili seç</button></form><form method="post"><?php admin_action('homepage') ?><input type="hidden" name="lang" value="<?= e($lang) ?>"><?php admin_field('Ana başlık (boşsa varsayılan çeviri)','homepage_title',setting('homepage_title_'.$lang)); admin_textarea('Açıklama (boşsa varsayılan çeviri)','homepage_intro',setting('homepage_intro_'.$lang),4); admin_field('Bağımsız SEO başlığı','homepage_seo_title',setting('homepage_seo_title_'.$lang)); admin_textarea('Bağımsız meta açıklama','homepage_seo_description',setting('homepage_seo_description_'.$lang),3); admin_textarea('KVKK · veri sorumlusu ve iletişim','legal_controller',setting('legal_controller_'.$lang),3); admin_textarea('KVKK · işleme amacı, hukuki sebep, saklama ve aktarım','legal_processing',setting('legal_processing_'.$lang),6); admin_textarea('KVKK · haklar ve başvuru kanalları','legal_rights',setting('legal_rights_'.$lang),4) ?><button>Kaydet</button></form></section><section class="panel"><h2>Teknik SEO</h2><ul class="checklist"><li><a href="/sitemap.xml">Dinamik sitemap ↗</a></li><li><a href="/robots.txt">Robots yönergeleri ↗</a></li><li>Canonical ve karşılıklı hreflang bağlantıları</li><li>Yayın durumuna göre indekslenebilirlik</li><li>Open Graph, X Cards ve JSON-LD</li><li>URL değişikliklerinde 301 yönlendirme kaydı</li></ul><p class="muted">Her içerik ve dil için SEO başlığı, açıklaması ve hedef kelimeleri içerik editöründen düzenleyin. Hedef kelimeler meta keywords olarak yayınlanmaz.</p><p class="muted">Canlı alan adını APP_URL ile ayarlayın. Search Console ve Analytics kimlikleri işletme hesabıyla ayrıca doğrulanmalıdır.</p></section></div><section class="panel"><h2>Yönlendirme kayıtları</h2><?php $items=rows('SELECT * FROM redirects ORDER BY id DESC LIMIT 100'); if(!$items): ?><p class="muted">Henüz URL değişikliği yok.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Dil</th><th>Eski kısa ad</th><th>Yeni kısa ad</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?= e($item['lang']) ?></td><td><?= e($item['old_slug']) ?></td><td><?= e($item['new_slug']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php
}

function admin_themes_page(): void
{
    $themes=rows('SELECT * FROM themes ORDER BY is_active DESC,updated_at DESC'); $editing=(int)($_GET['id']??0); $theme=$editing ? row('SELECT * FROM themes WHERE id=?',[$editing]) : null;
    ?><div class="split"><section class="panel"><h2><?= $theme?'Temayı düzenle':'Yeni tema' ?></h2><p class="muted">Taslak temalar ziyaretçilere uygulanmaz. Tema dili seçildiğinde yalnız o dilde gösterilir; “tüm diller” teması diğer diller için yedektir. Etkinleştirmek temayı otomatik yayınlar.</p><form method="post"><?php admin_action('theme') ?><input type="hidden" name="id" value="<?= (int)($theme['id']??0) ?>"><?php admin_field('Tema adı','name',(string)($theme['name']??''),'text',true); admin_select('Tema dili','locale',['global'=>'Tüm diller']+admin_locales(),(string)($theme['locale']??'global')); admin_field('Ana renk','primary_color',(string)($theme['primary_color']??'#0D2E42')); admin_field('Vurgu rengi','accent_color',(string)($theme['accent_color']??'#FF9149')); admin_textarea('Özel CSS','custom_css',(string)($theme['custom_css']??''),8); admin_select('Yayın durumu','status',['draft'=>'Taslak','published'=>'Yayınla'],(string)($theme['status']??'draft')); ?><label><input type="checkbox" name="is_active" value="1" <?=!empty($theme['is_active'])?'checked':''?>> Bu dilde ziyaretçi sitesinde etkinleştir</label><button>Tema kaydet</button></form></section><section class="panel"><h2>Yayınlanan temalar</h2><?php foreach($themes as $item): ?><p><strong><?=e($item['name'])?></strong> · <?=e($item['locale']==='global'?'Tüm diller':strtoupper($item['locale']))?> · <?=e($item['status'])?> <?= $item['is_active']?'· Etkin':'' ?> <a href="/admin/themes?id=<?= (int)$item['id'] ?>">Düzenle</a></p><?php endforeach ?></section></div><?php
}

function admin_licenses_page(): void
{
    $active=setting('license_status')==='active'; ?><div class="split"><section class="panel"><h2>TofiDesk lisansı</h2><p class="muted">Lisans anahtarı bu kurulumun alan adına bağlanır. Anahtar yalnız HTTPS lisans merkeziyle doğrulanır.</p><form method="post"><?php admin_action('license'); admin_field('Lisans merkezi','license_server_url',setting('license_server_url','https://tofidesk.com/transportation'),'url',true); admin_field('Lisans anahtarı','license_key',setting('license_key'),'text',true) ?><button>Kaydet ve doğrula</button></form></section><section class="panel"><h2>Durum</h2><p class="<?= $active?'notice success':'notice' ?>"><?= $active?'Etkin lisans':'Henüz doğrulanmadı' ?></p><p>Alan adı: <strong><?=e(license_domain() ?: 'APP_URL gerekli')?></strong></p><?php if(setting('license_checked_at')):?><p class="muted">Son kontrol: <?=e(setting('license_checked_at'))?></p><?php endif?><?php if(setting('license_theme')):?><p>Merkezden gelen tema: <strong><?=e(setting('license_theme'))?></strong></p><?php endif?><p class="muted">CodeCanyon alıcıları satın alma kodlarını lisans merkezinde anahtara dönüştürür. Satın alma kodu bu panelde saklanmaz.</p></section></div><?php
}

function admin_email_templates_page(): void
{
    ?><form method="post" class="panel"><?php admin_action('mail_templates'); ?><h2>E-posta şablonları</h2><p class="muted">Şablonlar düz metin olarak kuyruğa alınır. Kullanılabilir değişkenler: <code>{reference}</code>, <code>{type}</code>.</p><?php admin_field('Teklif konusu','mail_subject_request',setting('mail_subject_request','Yeni teklif talebi')); admin_textarea('Teklif gövdesi','mail_body_request',setting('mail_body_request','Yeni {type} talebi alındı. Referans: {reference}. Ayrıntılar yönetim panelindedir.'),5); admin_field('Randevu konusu','mail_subject_appointment',setting('mail_subject_appointment','Yeni randevu talebi')); admin_textarea('Randevu gövdesi','mail_body_appointment',setting('mail_body_appointment','Yeni {type} talebi alındı. Referans: {reference}. Ayrıntılar yönetim panelindedir.'),5); admin_field('Doğrulama konusu','mail_subject_verification',setting('mail_subject_verification','E-posta doğrulama bağlantınız')); ?><button>Şablonları kaydet</button></form><?php
}

function admin_settings_page(): void
{
    ?><form method="post"><?php admin_action('settings') ?><div class="split"><section class="panel"><h2>Marka ve iletişim</h2><?php admin_field('Marka adı','business_name',setting('business_name','Kaş Nakliyat')); admin_field('Ülke kodu (ISO)','country_code',setting('country_code','TR')); admin_field('Para birimi (ISO)','currency_code',setting('currency_code','TRY')); admin_field('Saat dilimi','timezone',setting('timezone','Europe/Istanbul')); admin_field('Konum etiketi','location_label',setting('location_label')); admin_field('Telefon','contact_phone',setting('contact_phone')); admin_field('E-posta','contact_email',setting('contact_email'),'email'); admin_field('WhatsApp','whatsapp',setting('whatsapp')); admin_field('Adres','address',setting('address')); admin_textarea('İşletme açıklaması','site_description',setting('site_description'),4); admin_field('Instagram URL','instagram_url',setting('instagram_url'),'url'); admin_field('Facebook URL','facebook_url',setting('facebook_url'),'url'); admin_field('LinkedIn URL','linkedin_url',setting('linkedin_url'),'url'); admin_field('YouTube URL','youtube_url',setting('youtube_url'),'url'); admin_field('Pinterest URL','pinterest_url',setting('pinterest_url'),'url'); admin_field('Pinterest doğrulama kodu','pinterest_verify',setting('pinterest_verify')); admin_field('Google site doğrulama kodu','google_site_verification',setting('google_site_verification')); admin_field('Google Analytics 4 ID','analytics_id',setting('analytics_id'),'text',false); ?></section><section class="panel"><h2>Entegrasyon tercihleri</h2><?php admin_select('İçerik sağlayıcısı','ai_provider',['openai'=>'OpenAI'],setting('ai_provider','openai')); admin_field('Model','ai_model',setting('ai_model') ?: 'gpt-4.1-mini'); admin_field('SMTP sunucusu','smtp_host',setting('smtp_host')); admin_field('SMTP portu','smtp_port',setting('smtp_port','587'),'number'); admin_field('SMTP kullanıcı adı','smtp_user',setting('smtp_user')); admin_field('Gönderen e-posta','mail_from',setting('mail_from'),'email'); admin_field('Teklif e-posta konusu','mail_subject_request',setting('mail_subject_request','Yeni teklif talebi')); admin_field('Randevu e-posta konusu','mail_subject_appointment',setting('mail_subject_appointment','Yeni randevu talebi')); admin_field('Doğrulama e-posta konusu','mail_subject_verification',setting('mail_subject_verification','E-posta doğrulama bağlantınız')) ?><p class="notice">Gizli anahtarlar sunucu ortamında saklanır. Analytics yalnızca APP_ENV=production iken yüklenir.</p></section></div><button>Ayarları kaydet</button></form><?php
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
