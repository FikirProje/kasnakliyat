<?php
declare(strict_types=1);

function license_domain(): string {
    $host=parse_url(app_origin(),PHP_URL_HOST) ?: '';
    return strtolower($host);
}

function license_server_url(): string {
    $configured = rtrim((string)setting('license_server_url', ''), '/');
    // Migrate the old demo/transport endpoint without touching the licence
    // key or status. This keeps existing installations on the real Product
    // Center after an update.
    if ($configured === '' || str_contains(strtolower($configured), 'tofidesk.com/transportation')) {
        return rtrim(envv('LICENSE_SERVER_URL', 'https://product.tofidesk.com'), '/');
    }
    return $configured;
}

function license_check_remote(): array {
    $server=license_server_url(); $key=trim(setting('license_key'));
    if($server===''||$key==='') return ['configured'=>false,'active'=>false,'message'=>'Lisans sunucusu ve anahtar henüz girilmedi.'];
    if(!filter_var($server,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($server),'https://')) return ['configured'=>true,'active'=>false,'message'=>'Lisans sunucusu HTTPS adresi olmalı.'];
    $domain=license_domain(); if($domain==='') return ['configured'=>true,'active'=>false,'message'=>'Bu kurulumun alan adı belirlenemedi. APP_URL değerini ayarlayın.'];
    if(!function_exists('curl_init')) return ['configured'=>true,'active'=>false,'message'=>'Sunucuda cURL gerekli.'];
    $request=json_encode(['license_key'=>$key,'domain'=>$domain,'locale'=>setting('default_locale','en')],JSON_UNESCAPED_SLASHES);
    $curl=curl_init($server.'/api/v1/licenses/check'); curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$request,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10]);
    $body=curl_exec($curl); $error=curl_error($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    $data=is_string($body)?json_decode($body,true):null;
    if($error!==''||$code!==200||!is_array($data)||empty($data['active'])) {
        $remoteError=strtolower((string)($data['error']??'')); if(str_contains($remoteError,'revoked')||str_contains($remoteError,'not active')) return ['configured'=>true,'active'=>false,'message'=>'Lisans pasif veya iptal edilmiş.'];
        $cachePath=storage_path('license-cache.json'); $cache=is_file($cachePath)?json_decode((string)file_get_contents($cachePath),true):null;
        if(is_array($cache)&&!empty($cache['active'])&&(int)($cache['checked_at_unix']??0)>time()-1209600){$cache['degraded']=true;$cache['message']='Lisans merkezi geçici olarak erişilemiyor; grace period içinde devam ediliyor.';return $cache;}
        return ['configured'=>true,'active'=>false,'message'=>$error!==''?'Lisans sunucusuna bağlanılamadı.':'Lisans doğrulanamadı.'];
    }
    $theme=$data['theme']??null;
    if(is_array($theme) && is_array($theme['payload']??null)) {
        $payload=$theme['payload']; $locale=(string)($theme['locale']??'global'); $name='TofiDesk: '.mb_substr((string)($theme['name']??'Theme'),0,80);
        $primary=(string)($payload['primary_color']??''); $accent=(string)($payload['accent_color']??'');
        // Preserve the signed theme editor configuration locally so the
        // installation can apply the delivered brand defaults without
        // requiring a second download or exposing the package to the web.
        $themeConfig = [];
        foreach (['logo','font','primary_color','accent_color'] as $configKey) if (isset($payload[$configKey]) && is_string($payload[$configKey])) $themeConfig[$configKey] = mb_substr($payload[$configKey], 0, 240);
        if (isset($payload['sections']) && is_array($payload['sections'])) $themeConfig['sections'] = array_slice($payload['sections'], 0, 100);
        if ($themeConfig !== []) {
            save_setting('license_theme_config', json_encode($themeConfig, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            if (!empty($themeConfig['logo']) && filter_var($themeConfig['logo'], FILTER_VALIDATE_URL)) save_setting('logo_url', $themeConfig['logo']);
            if (!empty($themeConfig['font'])) save_setting('theme_font_family', preg_replace('/[^a-zA-Z0-9 ,\-]/', '', $themeConfig['font']));
            if (isset($themeConfig['sections'])) save_setting('theme_sections_json', json_encode($themeConfig['sections'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        if(($locale==='global'||isset(locales()[$locale])) && preg_match('/^#[0-9a-fA-F]{6}$/D',$primary) && preg_match('/^#[0-9a-fA-F]{6}$/D',$accent)) {
            db()->beginTransaction(); try { query('UPDATE themes SET is_active=0 WHERE locale=?',[$locale]); $existing=row('SELECT id FROM themes WHERE name=?',[$name]); if($existing) query("UPDATE themes SET locale=?,primary_color=?,accent_color=?,custom_css='',status='published',is_active=1,updated_at=datetime('now') WHERE id=?",[$locale,$primary,$accent,$existing['id']]); else query("INSERT INTO themes(name,locale,primary_color,accent_color,custom_css,status,is_active) VALUES(?,?,?,?,?,'published',1)",[$name,$locale,$primary,$accent,'']); db()->commit(); } catch(Throwable $exception){if(db()->inTransaction())db()->rollBack();throw $exception;}
        }
    }
    save_setting('license_status','active'); save_setting('license_checked_at',gmdate('c')); save_setting('license_theme',is_array($theme)?(string)($theme['name']??''): ''); $result=['configured'=>true,'active'=>true,'message'=>'Lisans etkin.','theme'=>$data['theme']??null,'checked_at_unix'=>time()]; $cachePath=storage_path('license-cache.json'); @file_put_contents($cachePath,json_encode($result),LOCK_EX); @chmod($cachePath,0600); return $result;
}
