#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);

function location_usage(): never { fwrite(STDERR, "Usage: php bin/location.php validate|import path/to/locations.json\n"); exit(2); }
function location_records(string $path): array {
    if (!is_file($path) || filesize($path) > 32 * 1024 * 1024) throw new RuntimeException('Location file is missing or too large.');
    $payload = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $records = is_array($payload) && isset($payload['locations']) ? $payload['locations'] : $payload;
    if (!is_array($records) || count($records) > 100000) throw new RuntimeException('Location payload is invalid or too large.');
    return array_values(array_filter($records, 'is_array'));
}
function location_plan(array $records): array {
    $plan=[]; $keys=[];
    foreach ($records as $record) {
        $key=trim((string)($record['key']??''));
        if ($key==='' || isset($keys[$key])) throw new RuntimeException('Location keys must be unique and non-empty.');
        $keys[$key]=true; $plan[]=$record;
    }
    return $plan;
}
$command=$argv[1]??''; $path=$argv[2]??''; if (!in_array($command,['validate','import'],true) || $path==='') location_usage();
try {
    migrate(); $records=location_plan(location_records($path));
    if ($command==='validate') {
        $keys=array_fill_keys(array_map(static fn(array $record): string => (string)$record['key'], $records), true);
        foreach ($records as $record) {
            $country=strtoupper(trim((string)($record['country_code']??''))); $level=strtolower(trim((string)($record['level']??''))); $slug=strtolower(trim((string)($record['slug']??''))); $name=trim((string)($record['name']??'')); $parentKey=trim((string)($record['parent_key']??''));
            if (!preg_match('/^[A-Z]{2}$/',$country) || !in_array($level,['country','region','province','district','neighborhood','city','postal_area'],true) || $name==='' || !preg_match('/^[a-z0-9][a-z0-9-]{1,120}$/',$slug)) throw new RuntimeException('Invalid location record: '.(string)$record['key']);
            if ($parentKey!=='' && !isset($keys[$parentKey])) throw new RuntimeException('Unknown parent_key: '.$parentKey);
        }
        echo 'PASS '.count($records).' location records\n'; exit(0);
    }
    $resolved=[]; $remaining=$records; db()->beginTransaction();
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
            if (!$progress && $next) throw new RuntimeException('Location parent_key could not be resolved: '.(string)($next[0]['parent_key']??''));
            $remaining=$next;
        }
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        throw $error;
    }
    echo 'Imported '.count($resolved).' location records.\n';
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); exit(1); }
