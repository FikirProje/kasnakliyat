<?php
declare(strict_types=1);

function license_domain(): string {
    $host=parse_url(app_origin(),PHP_URL_HOST) ?: '';
    return strtolower($host);
}

function license_check_remote(): array {
    $server=rtrim(setting('license_server_url'),'/'); $key=trim(setting('license_key'));
    if($server===''||$key==='') return ['configured'=>false,'active'=>false,'message'=>'Lisans sunucusu ve anahtar henüz girilmedi.'];
    if(!filter_var($server,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($server),'https://')) return ['configured'=>true,'active'=>false,'message'=>'Lisans sunucusu HTTPS adresi olmalı.'];
    $domain=license_domain(); if($domain==='') return ['configured'=>true,'active'=>false,'message'=>'Bu kurulumun alan adı belirlenemedi. APP_URL değerini ayarlayın.'];
    if(!function_exists('curl_init')) return ['configured'=>true,'active'=>false,'message'=>'Sunucuda cURL gerekli.'];
    $request=json_encode(['license_key'=>$key,'domain'=>$domain,'locale'=>setting('default_locale','en')],JSON_UNESCAPED_SLASHES);
    $curl=curl_init($server.'/api/v1/licenses/check'); curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$request,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10]);
    $body=curl_exec($curl); $error=curl_error($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    $data=is_string($body)?json_decode($body,true):null;
    if($error!==''||$code!==200||!is_array($data)||empty($data['active'])) return ['configured'=>true,'active'=>false,'message'=>$error!==''?'Lisans sunucusuna bağlanılamadı.':'Lisans doğrulanamadı.'];
    $theme=$data['theme']??null;
    if(is_array($theme) && is_array($theme['payload']??null)) {
        $payload=$theme['payload']; $locale=(string)($theme['locale']??'global'); $name='TofiDesk: '.mb_substr((string)($theme['name']??'Theme'),0,80);
        $primary=(string)($payload['primary_color']??''); $accent=(string)($payload['accent_color']??'');
        if(($locale==='global'||isset(locales()[$locale])) && preg_match('/^#[0-9a-fA-F]{6}$/D',$primary) && preg_match('/^#[0-9a-fA-F]{6}$/D',$accent)) {
            db()->beginTransaction(); try { query('UPDATE themes SET is_active=0 WHERE locale=?',[$locale]); $existing=row('SELECT id FROM themes WHERE name=?',[$name]); if($existing) query("UPDATE themes SET locale=?,primary_color=?,accent_color=?,custom_css='',status='published',is_active=1,updated_at=datetime('now') WHERE id=?",[$locale,$primary,$accent,$existing['id']]); else query("INSERT INTO themes(name,locale,primary_color,accent_color,custom_css,status,is_active) VALUES(?,?,?,?,?,'published',1)",[$name,$locale,$primary,$accent,'']); db()->commit(); } catch(Throwable $exception){if(db()->inTransaction())db()->rollBack();throw $exception;}
        }
    }
    save_setting('license_status','active'); save_setting('license_checked_at',gmdate('c')); save_setting('license_theme',is_array($theme)?(string)($theme['name']??''): '');
    return ['configured'=>true,'active'=>true,'message'=>'Lisans etkin.','theme'=>$data['theme']??null];
}
