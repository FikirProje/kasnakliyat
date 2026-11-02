<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
$checks=['PHP 8.2+'=>version_compare(PHP_VERSION,'8.2.0','>='),'PDO SQLite'=>extension_loaded('pdo_sqlite'),'SQLite3'=>extension_loaded('sqlite3'),'cURL'=>function_exists('curl_init'),'OpenSSL'=>extension_loaded('openssl'),'ZipArchive'=>class_exists('ZipArchive'),'mbstring'=>extension_loaded('mbstring'),'GD'=>extension_loaded('gd'),'HTTPS'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'];
$root=dirname(__DIR__);$storage=$root.'/storage';$checks['storage yazılabilir']=is_dir($storage)&&is_writable($storage);$checks['backup dizini yazılabilir']=is_dir($storage.'/backups')&&is_writable($storage.'/backups');
echo "TOFIDESK HOSTING CHECK\n======================\nPHP: ".PHP_VERSION."\nDocument root: ".$root."\n\n";$ok=true;foreach($checks as $label=>$passed){$ok=$ok&&$passed;echo ($passed?'PASS ':'FAIL ').$label."\n";}echo "\nRESULT: ".($ok?'READY':'NOT READY')."\n";if(!$ok)echo "Enable the missing PHP extensions and grant private storage write access, then run this check again.\n";
