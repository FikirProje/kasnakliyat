<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$db=tempnam(sys_get_temp_dir(),'locale-coverage-');
$storage=sys_get_temp_dir().'/locale-coverage-'.bin2hex(random_bytes(4));
putenv('DB_PATH='.$db); putenv('STORAGE_PATH='.$storage); putenv('AI_API_KEY=');
require $root.'/src/bootstrap.php';
migrate();
$php=getenv('PHP_BIN') ?: PHP_BINARY;
$command=escapeshellarg($php).' '.escapeshellarg($root.'/bin/locale-coverage.php').' --queue --languages=de,fr --limit=2';
$output=[]; $code=0; exec($command,$output,$code);
if($code!==0) throw new RuntimeException('queue command failed');
$report=json_decode(implode("\n",$output),true,512,JSON_THROW_ON_ERROR);
if(($report['queued']??0)!==2 || (int)(row("SELECT count(*) n FROM jobs WHERE mode='localize'")['n']??0)!==2) throw new RuntimeException('expected two review jobs');
echo "PASS locale coverage reports and queues review-only localization jobs\n";
