<?php
declare(strict_types=1);
function update_installation_id(): string { $path=storage_path('installation.id'); $id=is_file($path)?trim((string)file_get_contents($path)):''; if(!preg_match('/^[a-f0-9]{32}$/',$id)){ $id=bin2hex(random_bytes(16)); @file_put_contents($path,$id,LOCK_EX); @chmod($path,0600); } return $id; }
function update_check(bool $force=false): array {
    $cachePath=storage_path('update-check.json'); $cached=is_file($cachePath)?json_decode((string)file_get_contents($cachePath),true):null; $now=time();
    if(!$force && is_array($cached) && (int)($cached['checked_at_unix']??0)>$now-900)return $cached;
    $key=trim((string)setting('license_key','')); $server=rtrim((string)setting('license_server_url',envv('LICENSE_SERVER_URL','https://product.tofidesk.com')),'/');
    if($key===''||!filter_var($server,FILTER_VALIDATE_URL))return ['configured'=>false,'message'=>'Lisans anahtarı ve lisans merkezi ayarlanmalı.'];
    $payload=['license_key'=>$key,'domain'=>license_domain(),'product'=>envv('PRODUCT_SLUG','transport-cms'),'installation_id'=>update_installation_id(),'installed_version'=>envv('APP_VERSION','1.0.0'),'migration_version'=>envv('MIGRATION_VERSION','1'),'php_version'=>PHP_VERSION];
    $ch=curl_init($server.'/updates.php'); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_SSL_VERIFYPEER=>true]); $raw=curl_exec($ch); $error=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch); $result=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($result)||$code<200||$code>=300){if(is_array($cached)&&(int)($cached['checked_at_unix']??0)>$now-604800){$cached['offline']=true;$cached['message']='Lisans merkezi geçici olarak erişilemiyor; son güvenli sonuç gösteriliyor.';return $cached;}return ['configured'=>true,'active'=>false,'offline'=>true,'message'=>'Güncelleme kontrolü yapılamadı.'.($error?' Sunucu bağlantısını kontrol edin.':'')];}
    $result['configured']=true;$result['checked_at_unix']=$now;$result['checked_at']=date('c');@file_put_contents($cachePath,json_encode($result),LOCK_EX);@chmod($cachePath,0600);return $result;
}
function update_download(array $release): string
{
    $key=trim((string)setting('license_key','')); $server=rtrim((string)setting('license_server_url',envv('LICENSE_SERVER_URL','https://product.tofidesk.com')),'/'); $version=(string)($release['version']??'');
    if($key===''||$version===''||!filter_var($server,FILTER_VALIDATE_URL)) throw new RuntimeException('Güncelleme indirme ayarları eksik.');
    $path=storage_path('update-'.$version.'.zip'); $ch=curl_init($server.'/updates.php'); $fp=fopen($path,'wb'); if(!$fp)throw new RuntimeException('Güncelleme geçici dosyası oluşturulamadı.');
    $payload=['action'=>'download','license_key'=>$key,'domain'=>license_domain(),'product'=>envv('PRODUCT_SLUG','transport-cms'),'installation_id'=>update_installation_id(),'installed_version'=>envv('APP_VERSION','1.0.0'),'version'=>$version];
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_FILE=>$fp,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true]); $ok=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch); curl_close($ch); fclose($fp);
    if(!$ok||$code<200||$code>=300){@unlink($path);throw new RuntimeException('Güncelleme paketi indirilemedi.'.($error?' Bağlantıyı kontrol edin.':''));}
    $expected=strtolower((string)($release['sha256']??'')); if(!preg_match('/^[a-f0-9]{64}$/',$expected)||!hash_equals($expected,strtolower(hash_file('sha256',$path)))){@unlink($path);throw new RuntimeException('Güncelleme paketi SHA-256 doğrulamasından geçmedi.');}
    chmod($path,0600); return $path;
}
