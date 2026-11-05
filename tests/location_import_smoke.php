<?php
declare(strict_types=1);
$db=tempnam(sys_get_temp_dir(),'location-'); $storage=sys_get_temp_dir().'/location-'.bin2hex(random_bytes(4));
putenv('DB_PATH='.$db); putenv('STORAGE_PATH='.$storage); require __DIR__.'/../src/bootstrap.php'; migrate();
$country=(int)(row("SELECT id FROM platform_locations WHERE country_code='TR' AND level='country'")['id']??0); if($country<=0) throw new RuntimeException('TR country seed missing.');
$file=$storage.'/locations.json'; file_put_contents($file,json_encode(['locations'=>[
    ['key'=>'tr','country_code'=>'TR','level'=>'country','name'=>'Turkey','slug'=>'tr'],
    ['key'=>'tr-akdeniz','parent_key'=>'tr','country_code'=>'TR','level'=>'region','name'=>'Akdeniz','slug'=>'akdeniz'],
    ['key'=>'tr-antalya','parent_key'=>'tr-akdeniz','country_code'=>'TR','level'=>'province','name'=>'Antalya','slug'=>'antalya'],
    ['key'=>'tr-kas','parent_key'=>'tr-antalya','country_code'=>'TR','level'=>'district','name'=>'Kaş','slug'=>'kas'],
    ['key'=>'tr-cukurbagli','parent_key'=>'tr-kas','country_code'=>'TR','level'=>'neighborhood','name'=>'Çukurbağlı','slug'=>'cukurbagli'],
]],JSON_UNESCAPED_UNICODE));
$php=PHP_BINARY; $flags=['-d','extension=php_sqlite3.dll','-d','extension=php_pdo_sqlite.dll']; $cmd=[$php,...$flags,__DIR__.'/../bin/location.php','import',$file]; $env=array_merge($_ENV,['DB_PATH'=>$db,'STORAGE_PATH'=>$storage]); $process=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__.'/..',$env); if(!is_resource($process)) throw new RuntimeException('Location importer did not start.'); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); foreach($pipes as $pipe) fclose($pipe); if(proc_close($process)!==0) throw new RuntimeException($err);
$leaf=row("SELECT l.* FROM platform_locations l WHERE l.slug='cukurbagli' AND l.level='neighborhood'"); if(!$leaf || (int)$leaf['parent_id']<=0) throw new RuntimeException('Hierarchical location import failed.');
echo "PASS hierarchical country/region/province/district/neighborhood import\n";
