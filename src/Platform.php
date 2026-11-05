<?php
declare(strict_types=1);

/** Sector-neutral extension primitives shared by themes, plugins and packs. */
function platform_module(string $slug): ?array
{
    return row('SELECT * FROM platform_modules WHERE slug=? AND status=?', [$slug, 'enabled']);
}

function platform_module_settings(string $slug): array
{
    $module = row('SELECT id FROM platform_modules WHERE slug=?', [$slug]);
    if (!$module) return [];
    $settings = [];
    foreach (rows('SELECT setting_key,value_json FROM platform_module_settings WHERE module_id=? ORDER BY setting_key', [(int)$module['id']]) as $item) {
        $value = json_decode((string)$item['value_json'], true);
        $settings[(string)$item['setting_key']] = is_array($value) ? $value : $value;
    }
    return $settings;
}

function platform_save_module_setting(int $moduleId, string $key, mixed $value, ?int $userId = null): void
{
    if ($moduleId <= 0 || !preg_match('/^[a-z][a-z0-9_.-]{1,80}$/i', $key)) throw new InvalidArgumentException('Invalid module setting.');
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 20000) throw new InvalidArgumentException('Module setting is too large.');
    query('INSERT INTO platform_module_settings(module_id,setting_key,value_json,updated_by,updated_at) VALUES(?,?,?,?,datetime(\'now\')) ON CONFLICT(module_id,setting_key) DO UPDATE SET value_json=excluded.value_json,updated_by=excluded.updated_by,updated_at=datetime(\'now\')', [$moduleId,$key,$encoded,$userId]);
}

function platform_allowed_events(): array
{
    return ['content.created','content.saved','content.published','search.reindex','order.paid','license.issued','update.applied','booking.created','enrollment.created','application.created'];
}

/** Provider-neutral integration catalogue. Secrets remain environment-only. */
function platform_adapter_definitions(): array
{
    return [
        'ai'=>['kind'=>'ai','name'=>'AI content and translation','required_env'=>['AI_API_KEY'],'capabilities'=>['generate','translate','draft_only']],
        'smtp'=>['kind'=>'smtp','name'=>'SMTP email delivery','required_env'=>['SMTP_HOST','SMTP_USER','SMTP_PASSWORD'],'capabilities'=>['send','queue','verification']],
        'sms'=>['kind'=>'sms','name'=>'SMS notification provider','required_env'=>['SMS_API_KEY'],'capabilities'=>['send','templates','status']],
        'whatsapp'=>['kind'=>'whatsapp','name'=>'WhatsApp notification provider','required_env'=>['WHATSAPP_ACCESS_TOKEN','WHATSAPP_PHONE_NUMBER_ID'],'capabilities'=>['send','templates','status']],
        'maps'=>['kind'=>'maps','name'=>'Maps and geocoding provider','required_env'=>['MAPS_API_KEY'],'capabilities'=>['geocode','autocomplete','distance','static_map']],
        'payment'=>['kind'=>'payment','name'=>'Payment provider','required_env'=>['PAYTR_MERCHANT_ID','PAYTR_MERCHANT_KEY','PAYTR_MERCHANT_SALT'],'capabilities'=>['checkout','callback','refund']],
        'storage'=>['kind'=>'storage','name'=>'Object storage provider','required_env'=>['STORAGE_ENDPOINT','STORAGE_ACCESS_KEY','STORAGE_SECRET_KEY'],'capabilities'=>['upload','signed_url','delete']],
        'search'=>['kind'=>'search','name'=>'Search index provider','required_env'=>['SEARCH_ENDPOINT'],'capabilities'=>['index','facets','suggestions']],
    ];
}

function platform_adapter_sync_catalog(): void
{
    foreach (platform_adapter_definitions() as $slug=>$definition) {
        query('INSERT INTO platform_adapters(slug,kind,name,version,status,config_json,capabilities_json,updated_at) VALUES(?,?,?,?,?,?,?,datetime(\'now\')) ON CONFLICT(slug) DO UPDATE SET kind=excluded.kind,name=excluded.name,version=excluded.version,capabilities_json=excluded.capabilities_json,updated_at=datetime(\'now\')', [$slug,$definition['kind'],$definition['name'],'1.0.0','disabled','{}',json_encode($definition['capabilities'],JSON_UNESCAPED_SLASHES)]);
    }
}

function platform_adapter_status(string $slug): array
{
    $definitions=platform_adapter_definitions(); if (!isset($definitions[$slug])) throw new InvalidArgumentException('Unknown platform adapter.');
    $definition=$definitions[$slug]; $missing=[];
    // The notification adapters use provider-specific credentials. Reporting
    // only the generic SMS_API_KEY would mark a correctly configured NetGSM,
    // İleti Merkezi or Twilio installation as unhealthy in the admin panel.
    if ($slug === 'sms' && function_exists('setting')) {
        $provider = (string)setting('sms_provider', 'none');
        $groups = [
            'netgsm'=>['NETGSM_USER','NETGSM_PASSWORD','NETGSM_HEADER'],
            'iletimerkezi'=>['ILETIMERKEZI_API_KEY','ILETIMERKEZI_API_HASH','ILETIMERKEZI_SENDER'],
            'twilio'=>['TWILIO_ACCOUNT_SID','TWILIO_AUTH_TOKEN','TWILIO_SMS_FROM'],
        ];
        if (isset($groups[$provider])) foreach ($groups[$provider] as $key) if (trim(envv($key,''))==='') $missing[]=$key;
        elseif ($provider !== 'none') $missing[]='SMS provider credentials';
    } elseif ($slug === 'whatsapp' && function_exists('setting')) {
        $provider = (string)setting('whatsapp_provider', 'none');
        $groups = [
            'cloud'=>['WHATSAPP_ACCESS_TOKEN','WHATSAPP_PHONE_NUMBER_ID'],
            'twilio'=>['TWILIO_ACCOUNT_SID','TWILIO_AUTH_TOKEN','TWILIO_WHATSAPP_FROM'],
        ];
        if (isset($groups[$provider])) foreach ($groups[$provider] as $key) if (trim(envv($key,''))==='') $missing[]=$key;
        elseif ($provider !== 'none') $missing[]='WhatsApp provider credentials';
    } else {
        foreach ($definition['required_env'] as $key) if (trim(envv($key,''))==='') $missing[]=$key;
    }
    $configured=$missing===[]; $stored=row('SELECT * FROM platform_adapters WHERE slug=?',[$slug]);
    return ['slug'=>$slug,'kind'=>$definition['kind'],'name'=>$definition['name'],'configured'=>$configured,'status'=>$configured?'ready':'degraded','missing_env'=>$missing,'capabilities'=>$definition['capabilities'],'enabled'=>(bool)($stored&&$stored['status']==='enabled')];
}

function platform_adapter_statuses(): array
{
    platform_adapter_sync_catalog(); $out=[]; foreach (array_keys(platform_adapter_definitions()) as $slug) $out[] = platform_adapter_status($slug); return $out;
}

/** Provider-neutral map links. No provider request is made until the visitor
 * opens the generated link or the explicitly configured embed URL. */
function platform_map_providers(): array
{
    return ['openstreetmap','google','bing'];
}

function platform_map_url(array $location, ?string $provider = null): string
{
    $provider = $provider ?? (function_exists('setting') ? setting('maps_provider','openstreetmap') : 'openstreetmap');
    $provider = strtolower(trim((string)$provider));
    if (!in_array($provider, platform_map_providers(), true)) $provider = 'openstreetmap';
    $latitude = isset($location['latitude']) && is_numeric($location['latitude']) ? (float)$location['latitude'] : null;
    $longitude = isset($location['longitude']) && is_numeric($location['longitude']) ? (float)$location['longitude'] : null;
    if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $latitude = null;
    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;
    $query = trim((string)($location['query'] ?? $location['address'] ?? $location['name'] ?? ''));
    if ($latitude !== null && $longitude !== null) $query = $latitude.','.$longitude;
    if ($query === '') throw new InvalidArgumentException('Map location requires coordinates or a query.');
    $encoded = rawurlencode($query);
    return match ($provider) {
        'google' => 'https://www.google.com/maps/search/?api=1&query='.$encoded,
        'bing' => 'https://www.bing.com/maps?q='.$encoded,
        default => $latitude !== null && $longitude !== null
            ? 'https://www.openstreetmap.org/?mlat='.rawurlencode((string)$latitude).'&mlon='.rawurlencode((string)$longitude).'#map=14/'.rawurlencode((string)$latitude).'/'.rawurlencode((string)$longitude)
            : 'https://www.openstreetmap.org/search?query='.$encoded,
    };
}

function platform_sync_permission_catalog(): void
{
    if (!db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='platform_roles'")->fetchColumn()) return;
    $roles=['admin'=>'Administrator','editor'=>'Editor','operator'=>'Operator']; foreach($roles as $slug=>$name) query('INSERT OR IGNORE INTO platform_roles(slug,name) VALUES(?,?)',[$slug,$name]);
    $sections=['dashboard','settings','email-templates','notification-rules','integrations','security','anomalies','users','audit','backup','mail','languages','themes','licenses','updates','modules','plugins','menus','forms','locations','taxonomies','legal','content','content-models','editor','media','gallery','seo','jobs','requests','appointments'];
    foreach($sections as $section){$permission='admin.section.'.$section;query('INSERT OR IGNORE INTO platform_permissions(slug,name) VALUES(?,?)',[$permission,'Admin section: '.$section]);$permissionId=(int)(row('SELECT id FROM platform_permissions WHERE slug=?',[$permission])['id']??0); foreach(['admin'=>true,'editor'=>$section==='dashboard'||in_array($section,['content','content-models','taxonomies','editor','media','gallery','seo','jobs'],true),'operator'=>$section==='dashboard'||in_array($section,['requests','appointments'],true)] as $role=>$allowed) if($allowed){$roleId=(int)(row('SELECT id FROM platform_roles WHERE slug=?',[$role])['id']??0);query('INSERT OR IGNORE INTO platform_role_permissions(role_id,permission_id) VALUES(?,?)',[$roleId,$permissionId]);}}
    foreach(platform_discover_module_manifests() as $item){$manifest=$item['manifest']??[];if($item['errors']!==[]||!is_array($manifest))continue;foreach((array)($manifest['permissions']??[]) as $permission){if(!preg_match('/^[a-z][a-z0-9_.-]{1,100}$/',(string)$permission))continue;$slug='module.'.(string)$permission;query('INSERT OR IGNORE INTO platform_permissions(slug,name) VALUES(?,?)',[$slug,'Module permission: '.$permission]);$rid=(int)(row('SELECT id FROM platform_roles WHERE slug=?',['admin'])['id']??0);$pid=(int)(row('SELECT id FROM platform_permissions WHERE slug=?',[$slug])['id']??0);query('INSERT OR IGNORE INTO platform_role_permissions(role_id,permission_id) VALUES(?,?)',[$rid,$pid]);}}
}

function platform_user_can(array $user,string $permission): bool
{
    $role=(string)($user['role']??''); if($role==='admin' && $permission!=='') return true;
    if(!preg_match('/^[a-z][a-z0-9_.-]{1,120}$/',$permission)) return false;
    return (int)(query('SELECT count(*) FROM platform_role_permissions rp JOIN platform_roles r ON r.id=rp.role_id JOIN platform_permissions p ON p.id=rp.permission_id WHERE r.slug=? AND r.status=? AND p.slug=? AND p.status=?',[$role,'enabled',$permission,'enabled'])->fetchColumn())>0;
}

function platform_cache_set(string $key, mixed $value, int $ttl = 300): void
{
    if (!preg_match('/^[a-z0-9][a-z0-9:._-]{1,180}$/i', $key)) throw new InvalidArgumentException('Invalid cache key.');
    $ttl=max(1,min($ttl,86400)); $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); if(strlen($json)>500000) throw new InvalidArgumentException('Cache value is too large.');
    query('INSERT INTO platform_cache(cache_key,value_json,expires_at) VALUES(?,?,?) ON CONFLICT(cache_key) DO UPDATE SET value_json=excluded.value_json,expires_at=excluded.expires_at,created_at=CURRENT_TIMESTAMP',[$key,$json,time()+$ttl]);
}

function platform_cache_get(string $key): mixed
{
    $item=row('SELECT value_json,expires_at FROM platform_cache WHERE cache_key=?',[$key]); if(!$item) return null; if((int)$item['expires_at']<time()){query('DELETE FROM platform_cache WHERE cache_key=?',[$key]);return null;} $value=json_decode((string)$item['value_json'],true); return json_last_error()===JSON_ERROR_NONE?$value:null;
}

function platform_cache_forget(string $key): void { query('DELETE FROM platform_cache WHERE cache_key=?',[$key]); }

/** One bounded, idempotent maintenance pass for cPanel/Plesk cron. */
function platform_maintenance(int $queueLimit = 10): array
{
    $expiredCache = (int) db()->exec('DELETE FROM platform_cache WHERE expires_at < '.(int)time());
    $staleQueue = (int) db()->exec("UPDATE platform_queue SET status='queued',claimed_at=NULL,available_at=0,last_error='Recovered stale worker claim.' WHERE status='running' AND claimed_at < datetime('now','-15 minutes')");
    $queue = platform_process_queue($queueLimit);
    return ['expired_cache'=>$expiredCache,'recovered_queue'=>$staleQueue,'queue'=>$queue];
}

function platform_queue_handlers(): array { static $handlers=[]; return $handlers; }
function platform_register_queue_handler(string $handler, callable $callback): void
{
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,100}$/',$handler)) throw new InvalidArgumentException('Invalid queue handler.');
    $GLOBALS['tofidesk_queue_handlers'][$handler]=$callback;
}
function platform_queue_push(string $queue,string $handler,array $payload=[],?string $idempotencyKey=null,int $delay=0): int
{
    if(!preg_match('/^[a-z0-9][a-z0-9_.-]{1,80}$/i',$queue)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,100}$/',$handler)) throw new InvalidArgumentException('Invalid queue entry.');
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); if(strlen($json)>500000) throw new InvalidArgumentException('Queue payload is too large.');
    query('INSERT OR IGNORE INTO platform_queue(queue_name,handler,payload_json,idempotency_key,available_at) VALUES(?,?,?,?,?)',[$queue,$handler,$json,$idempotencyKey,$delay>0?time()+min($delay,86400):0]); $id=$idempotencyKey!==null?row('SELECT id FROM platform_queue WHERE idempotency_key=?',[$idempotencyKey]):['id'=>db()->lastInsertId()]; return (int)($id['id']??0);
}
function platform_process_queue(int $limit=10): array
{
    $done=0;$failed=0;$limit=max(1,min($limit,50)); $handlers=$GLOBALS['tofidesk_queue_handlers']??[];
    for($i=0;$i<$limit;$i++){ $item=row("SELECT * FROM platform_queue WHERE status='queued' AND available_at<=? ORDER BY id LIMIT 1",[time()]); if(!$item)break; query("UPDATE platform_queue SET status='running',attempts=attempts+1,claimed_at=datetime('now') WHERE id=? AND status='queued'",[(int)$item['id']]); try { if(!isset($handlers[$item['handler']])) throw new RuntimeException('Queue handler is not registered.'); $payload=json_decode((string)$item['payload_json'],true,512,JSON_THROW_ON_ERROR); ($handlers[$item['handler']])($payload); query("UPDATE platform_queue SET status='completed',completed_at=datetime('now'),claimed_at=NULL,last_error='' WHERE id=?",[(int)$item['id']]);$done++; } catch(Throwable $error) { $attempts=(int)$item['attempts']+1; $status=$attempts>=3?'failed':'queued'; query('UPDATE platform_queue SET status=?,available_at=?,claimed_at=NULL,last_error=? WHERE id=?',[$status,time()+min(300*$attempts,3600),mb_substr($error->getMessage(),0,800),(int)$item['id']]); if($status==='failed')$failed++; } }
    return ['completed'=>$done,'failed'=>$failed];
}

function platform_register_hook(int $moduleId, string $event, string $handler, int $priority = 100): void
{
    $module = row('SELECT * FROM platform_modules WHERE id=? AND status=?', [$moduleId, 'enabled']);
    if (!$module || !in_array($event, platform_allowed_events(), true) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,100}$/', $handler) || !function_exists($handler)) throw new InvalidArgumentException('Invalid or unapproved platform hook.');
    $manifest = json_decode((string)$module['manifest_json'], true);
    if (!is_array($manifest) || !in_array($event, (array)($manifest['hooks'] ?? []), true)) throw new InvalidArgumentException('Hook is not declared by the module manifest.');
    $priority = max(0, min($priority, 1000));
    query('INSERT INTO platform_hooks(module_id,event,priority,handler,status) VALUES(?,?,?,?,?) ON CONFLICT(module_id,event,handler) DO UPDATE SET priority=excluded.priority,status=excluded.status', [$moduleId,$event,$priority,$handler,'enabled']);
}

function platform_register_module(array $manifest): int
{
    $slug = strtolower(trim((string)($manifest['slug'] ?? '')));
    $name = trim((string)($manifest['name'] ?? $slug));
    $version = trim((string)($manifest['version'] ?? '1.0.0'));
    $kind = (string)($manifest['kind'] ?? 'plugin');
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug) || $name === '' || !preg_match('/^\d+\.\d+\.\d+([.-][a-z0-9.-]+)?$/i', $version)) throw new InvalidArgumentException('Invalid platform module manifest.');
    if (!in_array($kind, ['core','sector','theme','plugin'], true)) throw new InvalidArgumentException('Invalid platform module kind.');
    $manifestErrors = function_exists('platform_module_manifest_errors') ? platform_module_manifest_errors($manifest) : [];
    if ($manifestErrors !== []) throw new InvalidArgumentException('Invalid platform module manifest: '.implode(',', $manifestErrors));
    query('INSERT INTO platform_modules(slug,name,version,kind,manifest_json,status,source,updated_at) VALUES(?,?,?,?,?,?,?,datetime(\'now\')) ON CONFLICT(slug) DO UPDATE SET name=excluded.name,version=excluded.version,kind=excluded.kind,manifest_json=excluded.manifest_json,status=CASE WHEN platform_modules.status IN (\'disabled\',\'quarantined\') THEN platform_modules.status ELSE excluded.status END,source=excluded.source,updated_at=datetime(\'now\')', [
        $slug, $name, $version, $kind, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'enabled', (string)($manifest['source'] ?? 'first-party'),
    ]);
    return (int)(row('SELECT id FROM platform_modules WHERE slug=?', [$slug])['id'] ?? 0);
}

function platform_webhook_url(string $url): string
{
    $url=trim($url); $parts=parse_url($url); $scheme=strtolower((string)($parts['scheme']??'')); $host=(string)($parts['host']??'');
    $allowHttp=envv('APP_ENV','development')==='testing';
    if ($host==='' || !in_array($scheme,$allowHttp?['https','http']:['https'],true) || isset($parts['user'],$parts['pass']) || preg_match('/[\r\n]/',$url)) throw new InvalidArgumentException('Webhook URL must be a public HTTPS endpoint.');
    if (filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new InvalidArgumentException('Private webhook targets are not allowed.');
    return mb_substr($url,0,2000);
}

function platform_webhook_key(): string
{
    $secret=envv('APP_SECRET',''); if(strlen($secret)<32) throw new RuntimeException('APP_SECRET must be configured before webhooks are enabled.'); return hash('sha256',$secret,true);
}

function platform_webhook_encrypt(string $secret): string
{
    if(strlen($secret)<24) throw new InvalidArgumentException('Webhook secret must be at least 24 characters.'); $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($secret,'aes-256-gcm',platform_webhook_key(),OPENSSL_RAW_DATA,$iv,$tag); if($cipher===false) throw new RuntimeException('Webhook secret encryption failed.'); return base64_encode($iv.$tag.$cipher);
}

function platform_webhook_decrypt(string $encoded): string
{
    $raw=base64_decode($encoded,true); if($raw===false||strlen($raw)<28) throw new RuntimeException('Webhook secret is invalid.'); $iv=substr($raw,0,12); $tag=substr($raw,12,16); $cipher=substr($raw,28); $plain=openssl_decrypt($cipher,'aes-256-gcm',platform_webhook_key(),OPENSSL_RAW_DATA,$iv,$tag); if($plain===false) throw new RuntimeException('Webhook secret could not be decrypted.'); return $plain;
}

function platform_register_webhook(int $moduleId,string $event,string $targetUrl,string $secret): int
{
    if(!row('SELECT id FROM platform_modules WHERE id=? AND status=?',[$moduleId,'enabled'])||!in_array($event,platform_allowed_events(),true)) throw new InvalidArgumentException('Webhook module or event is invalid.');
    $manifest=json_decode((string)row('SELECT manifest_json FROM platform_modules WHERE id=?',[$moduleId])['manifest_json'],true); if(!is_array($manifest)||!in_array($event,(array)($manifest['hooks']??[]),true)) throw new InvalidArgumentException('Webhook event is not declared by the module.');
    $url=platform_webhook_url($targetUrl); $encrypted=platform_webhook_encrypt($secret); $hash=hash('sha256',$secret); query('INSERT INTO platform_webhooks(module_id,event,target_url,secret_hash,secret_ciphertext,status) VALUES(?,?,?,?,?,?)',[$moduleId,$event,$url,$hash,$encrypted,'enabled']); return (int)db()->lastInsertId();
}

function platform_dispatch_webhooks(string $event,array $payload=[]): int
{
    if(!in_array($event,platform_allowed_events(),true)) return 0; $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); $count=0;
    foreach(rows("SELECT id FROM platform_webhooks WHERE event=? AND status='enabled'",[$event]) as $webhook){$key=$event.':'.hash('sha256',$json).':'.(int)$webhook['id']; query('INSERT OR IGNORE INTO platform_webhook_deliveries(webhook_id,event,idempotency_key,payload_json) VALUES(?,?,?,?)',[(int)$webhook['id'],$event,$key,$json]); if((int)db()->query('SELECT changes()')->fetchColumn()===1)$count++;} return $count;
}

function platform_process_webhook_queue(int $limit=10): array
{
    $sent=0;$failed=0;$limit=max(1,min($limit,50)); for($i=0;$i<$limit;$i++){ $delivery=row("SELECT d.*,w.target_url,w.secret_ciphertext FROM platform_webhook_deliveries d JOIN platform_webhooks w ON w.id=d.webhook_id WHERE d.status='queued' AND w.status='enabled' ORDER BY d.id LIMIT 1"); if(!$delivery) break; query("UPDATE platform_webhook_deliveries SET status='sending',attempts=attempts+1,claimed_at=datetime('now') WHERE id=? AND status='queued'",[(int)$delivery['id']]); try { if(!function_exists('curl_init')) throw new RuntimeException('cURL is required for webhook delivery.'); $secret=platform_webhook_decrypt((string)$delivery['secret_ciphertext']); $ch=curl_init((string)$delivery['target_url']); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=> (string)$delivery['payload_json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-TofiDesk-Event: '.(string)$delivery['event'],'X-TofiDesk-Delivery: '.(int)$delivery['id'],'X-TofiDesk-Signature: sha256='.hash_hmac('sha256',(string)$delivery['payload_json'],$secret)]]); $body=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch); curl_close($ch); if($body===false||$status<200||$status>=300) throw new RuntimeException($error!==''?$error:'Webhook returned HTTP '.$status); query("UPDATE platform_webhook_deliveries SET status='delivered',response_status=?,delivered_at=datetime('now'),claimed_at=NULL,last_error='' WHERE id=?",[$status,(int)$delivery['id']]); $sent++; } catch(Throwable $error) { $attempts=(int)$delivery['attempts']; $state=$attempts>=3?'failed':'queued'; query('UPDATE platform_webhook_deliveries SET status=?,claimed_at=NULL,last_error=? WHERE id=?',[$state,mb_substr($error->getMessage(),0,800),(int)$delivery['id']]); if($state==='failed')$failed++; } } return ['sent'=>$sent,'failed'=>$failed];
}

function platform_content_type(string $slug): ?array
{
    return row("SELECT t.* FROM content_types t LEFT JOIN platform_modules m ON m.id=t.module_id WHERE t.slug=? AND t.status='enabled' AND (t.module_id IS NULL OR m.status='enabled')", [$slug]);
}

function platform_register_content_field(string $type, array $field): int
{
    $contentType=platform_content_type($type); $slug=strtolower(trim((string)($field['slug']??''))); $label=trim((string)($field['label']??'')); $fieldType=(string)($field['field_type']??'text');
    if (!$contentType || !preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/',$slug) || $label==='' || !in_array($fieldType,['text','textarea','number','boolean','date','datetime','url','media','json','location','relation'],true)) throw new InvalidArgumentException('Invalid content field.');
    $config=json_encode((array)($field['config']??[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); if(strlen($config)>20000) throw new InvalidArgumentException('Content field config is too large.');
    query('INSERT INTO content_fields(content_type_id,slug,label,field_type,config_json,sort_order) VALUES(?,?,?,?,?,?) ON CONFLICT(content_type_id,slug) DO UPDATE SET label=excluded.label,field_type=excluded.field_type,config_json=excluded.config_json,sort_order=excluded.sort_order',[(int)$contentType['id'],$slug,mb_substr($label,0,180),$fieldType,$config,max(0,min((int)($field['sort_order']??0),10000))]);
    return (int)(row('SELECT id FROM content_fields WHERE content_type_id=? AND slug=?',[(int)$contentType['id'],$slug])['id']??0);
}

function platform_content_fields(string $type): array
{
    $contentType=platform_content_type($type); return $contentType ? rows('SELECT * FROM content_fields WHERE content_type_id=? ORDER BY sort_order,id',[(int)$contentType['id']]) : [];
}

function platform_upsert_taxonomy(string $slug, string $name, bool $hierarchical = false, ?int $moduleId = null): int
{
    $slug=strtolower(trim($slug)); $name=trim($name);
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/',$slug) || $name==='') throw new InvalidArgumentException('Invalid taxonomy.');
    $hasModule = (bool)db()->query("SELECT 1 FROM pragma_table_info('taxonomies') WHERE name='module_id'")->fetchColumn();
    if ($hasModule) query('INSERT INTO taxonomies(slug,name,hierarchical,status,module_id) VALUES(?,?,?,\'enabled\',?) ON CONFLICT(slug) DO UPDATE SET name=excluded.name,hierarchical=excluded.hierarchical,status=\'enabled\',module_id=COALESCE(excluded.module_id,taxonomies.module_id)',[$slug,mb_substr($name,0,180),$hierarchical?1:0,$moduleId]);
    else query('INSERT INTO taxonomies(slug,name,hierarchical,status) VALUES(?,?,?,\'enabled\') ON CONFLICT(slug) DO UPDATE SET name=excluded.name,hierarchical=excluded.hierarchical,status=\'enabled\'',[$slug,mb_substr($name,0,180),$hierarchical?1:0]);
    return (int)(row('SELECT id FROM taxonomies WHERE slug=?',[$slug])['id']??0);
}

function platform_taxonomy(string $slug): ?array
{
    return row("SELECT t.* FROM taxonomies t LEFT JOIN platform_modules m ON m.id=t.module_id WHERE t.slug=? AND t.status='enabled' AND (t.module_id IS NULL OR m.status='enabled')",[$slug]);
}

function platform_upsert_term(string $taxonomy, string $slug, string $name, string $locale = 'tr', ?int $parentId = null, array $metadata = []): int
{
    $tax=platform_taxonomy($taxonomy); $slug=strtolower(trim($slug)); $name=trim($name);
    if (!$tax || !isset(core_locales()[$locale]) || !preg_match('/^[a-z0-9][a-z0-9_-]{1,100}$/',$slug) || $name==='') throw new InvalidArgumentException('Invalid taxonomy term.');
    if ($parentId !== null) {
        $parent=row('SELECT id,taxonomy_id FROM taxonomy_terms WHERE id=?',[$parentId]);
        if (!$parent || (int)$parent['taxonomy_id']!==(int)$tax['id'] || !(int)$tax['hierarchical']) throw new InvalidArgumentException('Invalid taxonomy term parent.');
        if ($parentId === 0) $parentId=null;
    }
    $json=json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); if(strlen($json)>50000) throw new InvalidArgumentException('Taxonomy metadata is too large.');
    query('INSERT INTO taxonomy_terms(taxonomy_id,parent_id,slug,name,locale,metadata_json) VALUES(?,?,?,?,?,?) ON CONFLICT(taxonomy_id,locale,slug) DO UPDATE SET parent_id=excluded.parent_id,name=excluded.name,metadata_json=excluded.metadata_json',[(int)$tax['id'],$parentId,$slug,mb_substr($name,0,180),$locale,$json]);
    return (int)(row('SELECT id FROM taxonomy_terms WHERE taxonomy_id=? AND locale=? AND slug=?',[(int)$tax['id'],$locale,$slug])['id']??0);
}

function platform_taxonomy_terms(string $taxonomy, string $locale = 'tr', ?int $parentId = null): array
{
    $tax=platform_taxonomy($taxonomy); if(!$tax || !isset(core_locales()[$locale])) throw new InvalidArgumentException('Invalid taxonomy or locale.');
    return rows('SELECT * FROM taxonomy_terms WHERE taxonomy_id=? AND locale=? AND parent_id IS ? ORDER BY name',[(int)$tax['id'],$locale,$parentId]);
}

function platform_entry_terms(int $entryId, ?string $taxonomy = null, ?string $locale = null): array
{
    if($entryId<=0) throw new InvalidArgumentException('Invalid content entry.');
    $sql='SELECT tt.*,tx.slug AS taxonomy_slug,tx.name AS taxonomy_name FROM taxonomy_terms tt JOIN taxonomies tx ON tx.id=tt.taxonomy_id JOIN content_term_links ctl ON ctl.term_id=tt.id WHERE ctl.entry_id=?'; $args=[$entryId];
    if($taxonomy!==null){$sql.=' AND tx.slug=?';$args[]=$taxonomy;} if($locale!==null){if(!isset(core_locales()[$locale]))throw new InvalidArgumentException('Unsupported taxonomy locale.');$sql.=' AND tt.locale=?';$args[]=$locale;}
    return rows($sql.' ORDER BY tx.slug,tt.locale,tt.name',$args);
}

function platform_set_entry_terms(int $entryId, array $termIds): void
{
    if($entryId<=0 || !row('SELECT id FROM content_entries WHERE id=?',[$entryId])) throw new InvalidArgumentException('Content entry not found.');
    $ids=array_values(array_unique(array_filter(array_map('intval',$termIds),static fn(int $id):bool=>$id>0)));
    foreach($ids as $termId) if(!row('SELECT id FROM taxonomy_terms WHERE id=?',[$termId])) throw new InvalidArgumentException('Taxonomy term not found.');
    db()->beginTransaction(); try { query('DELETE FROM content_term_links WHERE entry_id=?',[$entryId]); foreach($ids as $termId) query('INSERT INTO content_term_links(entry_id,term_id) VALUES(?,?)',[$entryId,$termId]); db()->commit(); } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
}

function platform_upsert_menu(string $slug,string $locale,string $name): int
{
    if(!isset(core_locales()[$locale]) || !preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/',$slug) || trim($name)==='') throw new InvalidArgumentException('Invalid menu.');
    query('INSERT INTO platform_menus(slug,locale,name) VALUES(?,?,?) ON CONFLICT(slug,locale) DO UPDATE SET name=excluded.name,status=\'enabled\'',[$slug,$locale,mb_substr(trim($name),0,180)]); return (int)(row('SELECT id FROM platform_menus WHERE slug=? AND locale=?',[$slug,$locale])['id']??0);
}

function platform_add_menu_item(int $menuId,array $item): int
{
    $label=trim((string)($item['label']??'')); $url=trim((string)($item['target_url']??'')); $target=(string)($item['target']??'_self'); if($menuId<=0||!row('SELECT id FROM platform_menus WHERE id=? AND status=\'enabled\'',$menuId?[$menuId]:[] )||$label===''||$url===''||!in_array($target,['_self','_blank'],true)) throw new InvalidArgumentException('Invalid menu item.');
    if(str_contains($url,"\r")||str_contains($url,"\n")||str_starts_with($url,'//')||(!str_starts_with($url,'/')&&!preg_match('#^https://#i',$url))) throw new InvalidArgumentException('Menu URL must be internal or HTTPS.');
    query('INSERT INTO platform_menu_items(menu_id,parent_id,label,target_url,target,sort_order,visible,metadata_json) VALUES(?,?,?,?,?,?,?,?)',[$menuId,isset($item['parent_id'])?(int)$item['parent_id']:null,mb_substr($label,0,180),mb_substr($url,0,2000),$target,max(0,min((int)($item['sort_order']??0),10000)),empty($item['visible'])?0:1,json_encode((array)($item['metadata']??[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]); return (int)db()->lastInsertId();
}

function platform_upsert_form(string $slug,string $locale,string $name,string $successMessage=''): int
{
    if(!isset(core_locales()[$locale])||!preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/',$slug)||trim($name)==='') throw new InvalidArgumentException('Invalid form.');
    query('INSERT INTO platform_forms(slug,locale,name,success_message) VALUES(?,?,?,?) ON CONFLICT(slug,locale) DO UPDATE SET name=excluded.name,success_message=excluded.success_message,status=\'enabled\'',[$slug,$locale,mb_substr(trim($name),0,180),mb_substr($successMessage,0,500)]); return (int)(row('SELECT id FROM platform_forms WHERE slug=? AND locale=?',[$slug,$locale])['id']??0);
}

function platform_add_form_field(int $formId,array $field): int
{
    $slug=strtolower(trim((string)($field['slug']??''))); $label=trim((string)($field['label']??'')); $type=(string)($field['field_type']??'text'); if($formId<=0||!row('SELECT id FROM platform_forms WHERE id=? AND status=\'enabled\'',[$formId])||!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/',$slug)||$label===''||!in_array($type,['text','textarea','email','url','number','boolean','date','datetime','json'],true)) throw new InvalidArgumentException('Invalid form field.');
    $config=json_encode((array)($field['config']??[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); query('INSERT INTO platform_form_fields(form_id,slug,label,field_type,required,config_json,sort_order) VALUES(?,?,?,?,?,?,?) ON CONFLICT(form_id,slug) DO UPDATE SET label=excluded.label,field_type=excluded.field_type,required=excluded.required,config_json=excluded.config_json,sort_order=excluded.sort_order',[$formId,$slug,mb_substr($label,0,180),$type,!empty($field['required'])?1:0,$config,max(0,min((int)($field['sort_order']??0),10000))]); return (int)(row('SELECT id FROM platform_form_fields WHERE form_id=? AND slug=?',[$formId,$slug])['id']??0);
}

function platform_submit_form(int $formId,string $locale,array $payload,string $sourceIp=''): int
{
    if(!isset(core_locales()[$locale])) throw new InvalidArgumentException('Unsupported form locale.'); $form=row('SELECT * FROM platform_forms WHERE id=? AND status=\'enabled\'',$formId?[$formId]:[]); if(!$form) throw new InvalidArgumentException('Form is unavailable.'); $fields=rows('SELECT * FROM platform_form_fields WHERE form_id=? ORDER BY sort_order,id',[$formId]); $clean=[];
    foreach($fields as $field){$key=(string)$field['slug'];$value=$payload[$key]??null;if((int)$field['required']&&($value===null||$value===''))throw new InvalidArgumentException('Required form field missing: '.$key);if($value===null)continue;if(in_array($field['field_type'],['json'],true)&&!is_array($value))json_decode((string)$value,true,512,JSON_THROW_ON_ERROR);if($field['field_type']==='email'&&!filter_var((string)$value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email field: '.$key);if($field['field_type']==='url'&&!filter_var((string)$value,FILTER_VALIDATE_URL))throw new InvalidArgumentException('Invalid URL field: '.$key);$clean[$key]=is_scalar($value)?mb_substr((string)$value,0,10000):$value;}
    $ipHash=$sourceIp!==''?hash_hmac('sha256',$sourceIp,envv('APP_SECRET','tofidesk-local-secret')):''; query('INSERT INTO platform_form_submissions(form_id,locale,payload_json,source_ip_hash) VALUES(?,?,?,?)',[$formId,$locale,json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$ipHash]); return (int)db()->lastInsertId();
}

function platform_upsert_location(array $location): int
{
    $country = strtoupper(trim((string)($location['country_code'] ?? '')));
    $level = strtolower(trim((string)($location['level'] ?? '')));
    $name = trim((string)($location['name'] ?? ''));
    $slug = strtolower(trim((string)($location['slug'] ?? '')));
    if (!preg_match('/^[A-Z]{2}$/', $country) || !in_array($level, ['country','region','province','district','neighborhood','city','postal_area'], true) || $name === '' || !preg_match('/^[a-z0-9][a-z0-9-]{1,120}$/', $slug)) throw new InvalidArgumentException('Invalid location record.');
    $parent = isset($location['parent_id']) ? (int)$location['parent_id'] : null;
    query('INSERT INTO platform_locations(parent_id,country_code,level,name,slug,postal_code,latitude,longitude,metadata_json) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(country_code,level,parent_id,slug) DO UPDATE SET name=excluded.name,postal_code=excluded.postal_code,latitude=excluded.latitude,longitude=excluded.longitude,metadata_json=excluded.metadata_json', [$parent,$country,$level,$name,$slug,(string)($location['postal_code'] ?? ''),$location['latitude'] ?? null,$location['longitude'] ?? null,json_encode((array)($location['metadata'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    return (int)(row('SELECT id FROM platform_locations WHERE country_code=? AND level=? AND parent_id IS ? AND slug=?', [$country,$level,$parent,$slug])['id'] ?? 0);
}

function platform_location_children(int $parentId, ?string $level = null): array
{
    if ($level !== null) return rows('SELECT * FROM platform_locations WHERE parent_id=? AND level=? ORDER BY name', [$parentId,$level]);
    return rows('SELECT * FROM platform_locations WHERE parent_id=? ORDER BY level,name', [$parentId]);
}
function platform_location_search(string $query,string $country='',int $limit=20): array
{
    $query=trim($query); if($query==='') return []; if(mb_strlen($query)>120) throw new InvalidArgumentException('Location query is too long.');
    $limit=max(1,min($limit,100)); $country=strtoupper(trim($country)); $args=['%'.$query.'%'];
    $sql="SELECT * FROM platform_locations WHERE (name LIKE ? OR slug LIKE ? OR postal_code LIKE ?)"; $args=['%'.$query.'%','%'.mb_strtolower($query).'%','%'.$query.'%'];
    if($country!==''){ if(!preg_match('/^[A-Z]{2}$/',$country)) throw new InvalidArgumentException('Invalid country code.'); $sql.=' AND country_code=?'; $args[]=$country; }
    $sql.=' ORDER BY CASE WHEN name LIKE ? THEN 0 ELSE 1 END,name LIMIT ?'; $args[]='%'.$query.'%'; $args[]=$limit;
    return rows($sql,$args);
}

function platform_validate_entry_data(string $type, array $data): array
{
    $fields = platform_content_fields($type);
    if ($fields === []) return $data;
    $definitions = [];
    foreach ($fields as $field) $definitions[(string)$field['slug']] = $field;
    foreach ($data as $key => $value) {
        if (!isset($definitions[(string)$key])) throw new InvalidArgumentException('Unknown content field: '.(string)$key);
        $field = $definitions[(string)$key]; $fieldType = (string)$field['field_type'];
        if ($value === null || $value === '') continue;
        $config = json_decode((string)($field['config_json'] ?? '{}'), true); $config = is_array($config) ? $config : [];
        $valid = match ($fieldType) {
            'number' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
            'date' => is_string($value) && (bool)DateTimeImmutable::createFromFormat('!Y-m-d', $value) && DateTimeImmutable::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value,
            'datetime' => is_string($value) && (bool)strtotime($value),
            'url' => is_string($value) && (bool)filter_var($value, FILTER_VALIDATE_URL),
            'json' => is_array($value) || is_object($value) || is_scalar($value),
            'location', 'relation', 'media' => is_int($value) || is_string($value) || is_array($value),
            default => is_scalar($value) || is_array($value),
        };
        if (!$valid) throw new InvalidArgumentException('Invalid '.$fieldType.' value for content field: '.(string)$key);
        if ($fieldType === 'number' && isset($config['min']) && (float)$value < (float)$config['min']) throw new InvalidArgumentException('Content field is below its minimum: '.(string)$key);
        if ($fieldType === 'number' && isset($config['max']) && (float)$value > (float)$config['max']) throw new InvalidArgumentException('Content field is above its maximum: '.(string)$key);
    }
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 500000) throw new InvalidArgumentException('Content data is too large.');
    return $data;
}

function platform_create_entry(string $type, string $slug, array $data = [], ?int $ownerId = null, ?int $locationId = null): int
{
    $contentType = platform_content_type($type);
    if (!$contentType || !preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/', $slug)) throw new InvalidArgumentException('Content type or slug is invalid.');
    $data = platform_validate_entry_data($type, $data);
    query('INSERT INTO content_entries(content_type_id,slug,owner_id,location_id,data_json) VALUES(?,?,?,?,?)', [$contentType['id'], $slug, $ownerId, $locationId, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    return (int)db()->lastInsertId();
}

function platform_update_entry(int $entryId, string $slug, array $data = [], ?int $locationId = null): void
{
    $entry = row('SELECT ce.*,ct.slug AS content_type FROM content_entries ce JOIN content_types ct ON ct.id=ce.content_type_id WHERE ce.id=? AND ct.status=?', [$entryId, 'enabled']);
    if (!$entry || !preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/', $slug)) throw new InvalidArgumentException('Content entry or slug is invalid.');
    $data = platform_validate_entry_data((string)$entry['content_type'], $data);
    query('UPDATE content_entries SET slug=?,location_id=?,data_json=?,updated_at=datetime(\'now\') WHERE id=?', [$slug, $locationId, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $entryId]);
}

function platform_save_translation(int $entryId, string $locale, array $translation, string $status = 'draft'): void
{
    if (!isset(core_locales()[$locale]) || !in_array($status, ['draft','review','published'], true)) throw new InvalidArgumentException('Unsupported locale or translation status.');
    $seo = is_array($translation['seo'] ?? null) ? $translation['seo'] : [];
    query('INSERT INTO content_entry_translations(entry_id,locale,title,summary,body,seo_json,status,updated_at) VALUES(?,?,?,?,?,?,?,datetime(\'now\')) ON CONFLICT(entry_id,locale) DO UPDATE SET title=excluded.title,summary=excluded.summary,body=excluded.body,seo_json=excluded.seo_json,status=excluded.status,revision=content_entry_translations.revision+1,updated_at=datetime(\'now\')', [
        $entryId, $locale, mb_substr((string)($translation['title'] ?? ''), 0, 220), mb_substr((string)($translation['summary'] ?? ''), 0, 2000), mb_substr((string)($translation['body'] ?? ''), 0, 100000), json_encode($seo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $status,
    ]);
}

function platform_search(string $locale, string $term = '', ?string $type = null, ?int $locationId = null, int $limit = 30): array
{
    return platform_search_advanced(['locale'=>$locale, 'term'=>$term, 'type'=>$type, 'location_id'=>$locationId, 'limit'=>$limit])['items'];
}

/**
 * Portable discovery contract for every sector pack. Taxonomy and location
 * filtering are data-only; a provider can replace this adapter later.
 */
function platform_search_advanced(array $filters = []): array
{
    $locale = (string)($filters['locale'] ?? 'tr');
    if (!isset(core_locales()[$locale])) throw new InvalidArgumentException('Unsupported search locale.');
    $term = trim((string)($filters['term'] ?? ''));
    $limit = max(1, min((int)($filters['limit'] ?? 30), 100));
    $types = $filters['types'] ?? ($filters['type'] ?? null);
    $types = is_array($types) ? array_values(array_filter(array_map('strval', $types))) : (($types === null || $types === '') ? [] : [(string)$types]);
    $locationId = isset($filters['location_id']) && $filters['location_id'] !== '' ? (int)$filters['location_id'] : null;
    $locationIds = [];
    if ($locationId !== null) {
        $locationIds[] = $locationId;
        if (!empty($filters['include_descendants'])) {
            $queue = [$locationId];
            while ($queue) {
                $children = platform_location_children((int)array_shift($queue));
                foreach ($children as $child) { $id=(int)$child['id']; $locationIds[]=$id; $queue[]=$id; }
                if (count($locationIds) > 5000) break;
            }
        }
    }
    $sql = 'SELECT * FROM search_documents WHERE locale=? AND status=\'published\'';
    $args = [$locale];
    if ($term !== '') { $sql .= ' AND (title LIKE ? OR body LIKE ? OR keywords LIKE ?)'; $needle = '%'.str_replace(['%','_'], ['\\%','\\_'], $term).'%'; array_push($args, $needle, $needle, $needle); }
    if ($types) { $sql .= ' AND entity_type IN ('.implode(',', array_fill(0, count($types), '?')).')'; array_push($args, ...$types); }
    if ($locationIds) { $sql .= ' AND location_id IN ('.implode(',', array_fill(0, count($locationIds), '?')).')'; array_push($args, ...$locationIds); }
    foreach ((array)($filters['term_ids'] ?? []) as $termId) {
        $termId=(int)$termId; if ($termId <= 0) continue;
        $sql .= ' AND EXISTS (SELECT 1 FROM content_term_links ctl JOIN content_entries ce ON ce.id=ctl.entry_id WHERE ctl.term_id=? AND ce.id=search_documents.entity_id AND search_documents.entity_type IN (SELECT ct.slug FROM content_types ct WHERE ct.id=ce.content_type_id))';
        $args[]=$termId;
    }
    $sort = (string)($filters['sort'] ?? 'updated');
    $sql .= match($sort) { 'title' => ' ORDER BY title COLLATE NOCASE ASC,id ASC', 'oldest' => ' ORDER BY updated_at ASC,id ASC', default => ' ORDER BY updated_at DESC,id DESC' };
    $all = rows($sql, $args);
    $radius = (float)($filters['radius_km'] ?? 0);
    if ($radius > 0 && isset($filters['latitude'],$filters['longitude'])) {
        $lat=(float)$filters['latitude']; $lon=(float)$filters['longitude'];
        $all=array_values(array_filter($all, static function(array $item) use ($lat,$lon,$radius): bool {
            if (empty($item['location_id'])) return false;
            $loc=row('SELECT latitude,longitude FROM platform_locations WHERE id=?',[(int)$item['location_id']]);
            if ($loc===null || $loc['latitude']===null || $loc['longitude']===null) return false;
            $dLat=deg2rad((float)$loc['latitude']-$lat); $dLon=deg2rad((float)$loc['longitude']-$lon);
            $a=sin($dLat/2)**2+cos(deg2rad($lat))*cos(deg2rad((float)$loc['latitude']))*sin($dLon/2)**2;
            return 6371*2*asin(min(1,sqrt($a))) <= $radius;
        }));
    }
    $facets=['types'=>[],'locations'=>[],'terms'=>[]];
    foreach ($all as $item) {
        $facets['types'][$item['entity_type']] = ($facets['types'][$item['entity_type']] ?? 0)+1;
        if (!empty($item['location_id'])) $facets['locations'][(string)$item['location_id']] = ($facets['locations'][(string)$item['location_id']] ?? 0)+1;
        $terms=rows('SELECT tx.slug AS taxonomy_slug,tt.id,tt.slug,tt.name FROM content_term_links ctl JOIN taxonomy_terms tt ON tt.id=ctl.term_id JOIN taxonomies tx ON tx.id=tt.taxonomy_id WHERE ctl.entry_id=? AND tx.status=\'enabled\'',[(int)$item['entity_id']]);
        foreach($terms as $facetTerm){$taxonomy=(string)$facetTerm['taxonomy_slug'];$termSlug=(string)$facetTerm['slug'];$facets['terms'][$taxonomy][$termSlug]=($facets['terms'][$taxonomy][$termSlug]??0)+1;}
    }
    return ['items'=>array_slice($all,0,$limit),'total'=>count($all),'facets'=>$facets,'filters'=>['locale'=>$locale,'term'=>$term,'types'=>$types,'location_ids'=>$locationIds,'sort'=>$sort]];
}

function platform_discovery_entity(string $type, int $id): array
{
    if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/i',$type)||$id<=0) throw new InvalidArgumentException('Invalid discovery entity.');
    return ['entity_type'=>$type,'entity_id'=>$id];
}
function platform_discovery_key(string $key): string
{
    $key=trim($key); if($key===''||strlen($key)>180||preg_match('/[\r\n]/',$key)) throw new InvalidArgumentException('Invalid discovery visitor key.'); return hash('sha256',$key);
}
function platform_favorite(string $visitorKey,string $type,int $id,string $locale='tr'): void
{
    platform_discovery_entity($type,$id); if(!isset(core_locales()[$locale])) throw new InvalidArgumentException('Unsupported discovery locale.'); query('INSERT OR IGNORE INTO platform_favorites(visitor_key,entity_type,entity_id,locale) VALUES(?,?,?,?)',[platform_discovery_key($visitorKey),$type,$id,$locale]);
}
function platform_unfavorite(string $visitorKey,string $type,int $id): void { platform_discovery_entity($type,$id); query('DELETE FROM platform_favorites WHERE visitor_key=? AND entity_type=? AND entity_id=?',[platform_discovery_key($visitorKey),$type,$id]); }
function platform_favorites(string $visitorKey,?string $type=null): array { $sql='SELECT entity_type,entity_id,locale,created_at FROM platform_favorites WHERE visitor_key=?';$args=[platform_discovery_key($visitorKey)];if($type!==null){$sql.=' AND entity_type=?';$args[]=$type;}return rows($sql.' ORDER BY created_at DESC',$args); }
function platform_compare_add(string $visitorKey,string $type,int $id): void
{
    platform_discovery_entity($type,$id); $key=platform_discovery_key($visitorKey); $count=(int)query('SELECT count(*) FROM platform_comparisons WHERE visitor_key=? AND entity_type=?',[$key,$type])->fetchColumn(); if($count>=4&&!row('SELECT id FROM platform_comparisons WHERE visitor_key=? AND entity_type=? AND entity_id=?',[$key,$type,$id])) throw new RuntimeException('Comparison list is limited to four items.'); query('INSERT OR IGNORE INTO platform_comparisons(visitor_key,entity_type,entity_id) VALUES(?,?,?)',[$key,$type,$id]);
}
function platform_compare_remove(string $visitorKey,string $type,int $id): void { platform_discovery_entity($type,$id); query('DELETE FROM platform_comparisons WHERE visitor_key=? AND entity_type=? AND entity_id=?',[platform_discovery_key($visitorKey),$type,$id]); }
function platform_comparisons(string $visitorKey,?string $type=null): array { $sql='SELECT entity_type,entity_id,created_at FROM platform_comparisons WHERE visitor_key=?';$args=[platform_discovery_key($visitorKey)];if($type!==null){$sql.=' AND entity_type=?';$args[]=$type;}return rows($sql.' ORDER BY created_at ASC',$args); }
function platform_review_submit(string $visitorKey,string $type,int $id,string $locale,int $rating,string $title,string $body): int
{
    platform_discovery_entity($type,$id); if(!isset(core_locales()[$locale])||$rating<1||$rating>5) throw new InvalidArgumentException('Invalid review.'); $title=mb_substr(trim($title),0,180);$body=mb_substr(trim($body),0,5000);if($body==='')throw new InvalidArgumentException('Review body is required.');$key=platform_discovery_key($visitorKey);query("INSERT INTO platform_reviews(visitor_key,entity_type,entity_id,locale,rating,title,body,status) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(visitor_key,entity_type,entity_id) DO UPDATE SET locale=excluded.locale,rating=excluded.rating,title=excluded.title,body=excluded.body,status='pending',updated_at=CURRENT_TIMESTAMP",[$key,$type,$id,$locale,$rating,$title,$body,'pending']);return (int)(row('SELECT id FROM platform_reviews WHERE visitor_key=? AND entity_type=? AND entity_id=?',[$key,$type,$id])['id']??0);
}
function platform_review_summary(string $type,int $id): array { platform_discovery_entity($type,$id);$summary=row("SELECT count(*) total,COALESCE(avg(rating),0) average FROM platform_reviews WHERE entity_type=? AND entity_id=? AND status='published'",[$type,$id])?:['total'=>0,'average'=>0];return ['total'=>(int)$summary['total'],'average'=>round((float)$summary['average'],2),'items'=>rows("SELECT locale,rating,title,body,created_at FROM platform_reviews WHERE entity_type=? AND entity_id=? AND status='published' ORDER BY created_at DESC LIMIT 50",[$type,$id])]; }
function platform_related(string $locale,string $type,int $id,int $limit=6): array { platform_discovery_entity($type,$id);if(!isset(core_locales()[$locale]))throw new InvalidArgumentException('Unsupported discovery locale.');$limit=max(1,min($limit,20));$items=rows("SELECT * FROM search_documents WHERE locale=? AND entity_type=? AND entity_id<>? AND status='published' ORDER BY updated_at DESC,id DESC LIMIT ?",[$locale,$type,$id,$limit]);return ['items'=>$items,'total'=>count($items)]; }
function platform_popularity_touch(string $type,int $id,string $locale='tr',string $metric='views',int $amount=1): void
{
    platform_discovery_entity($type,$id); if(!isset(core_locales()[$locale])) throw new InvalidArgumentException('Unsupported discovery locale.');
    if(!in_array($metric,['views','favorites','downloads'],true)) throw new InvalidArgumentException('Unsupported popularity metric.');
    $amount=max(1,min($amount,100));
    query("INSERT INTO platform_popularity(entity_type,entity_id,locale,$metric,score,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(entity_type,entity_id,locale) DO UPDATE SET $metric=$metric+excluded.$metric,score=(views*1.0)+(favorites*4.0)+(downloads*8.0),updated_at=CURRENT_TIMESTAMP",[$type,$id,$locale,$amount,$amount]);
}
function platform_popular(string $locale,string $type='',int $limit=12): array
{
    if(!isset(core_locales()[$locale])) throw new InvalidArgumentException('Unsupported discovery locale.'); $limit=max(1,min($limit,50)); $args=[$locale];
    $sql="SELECT p.*,s.title,s.body,s.keywords FROM platform_popularity p LEFT JOIN search_documents s ON s.entity_type=p.entity_type AND s.entity_id=p.entity_id AND s.locale=p.locale AND s.status='published' WHERE p.locale=?";
    if($type!==''){ if(!preg_match('/^[a-z0-9_-]{1,80}$/',$type)) throw new InvalidArgumentException('Invalid popularity type.'); $sql.=' AND p.entity_type=?'; $args[]=$type; }
    $sql.=' ORDER BY p.score DESC,p.updated_at DESC,p.id DESC LIMIT ?'; $args[]=$limit; $items=rows($sql,$args); return ['items'=>$items,'total'=>count($items)];
}

function platform_dispatch(string $event, array $payload = []): void
{
    if (!in_array($event, platform_allowed_events(), true)) return;
    foreach (rows('SELECT h.handler FROM platform_hooks h JOIN platform_modules m ON m.id=h.module_id WHERE h.event=? AND h.status=? AND m.status=? ORDER BY h.priority ASC,h.id ASC', [$event, 'enabled', 'enabled']) as $hook) {
        $handler = (string)$hook['handler'];
        if (function_exists($handler)) {
            try { $handler($payload); } catch (Throwable $error) { error_log('Platform hook failed: '.$handler.' '.$error->getMessage()); }
        }
    }
}
