<?php
declare(strict_types=1);

$tool = file_get_contents(__DIR__.'/../license-center/bin/seed-first-party-theme.php');
$manifest = file_get_contents(__DIR__.'/../license-center/seed/nexa-business/theme.json');
if ($tool === false || $manifest === false) throw new RuntimeException('First-party theme release sources are missing.');
foreach ([
    'ZipArchive',
    'hash_file',
    "hash_hmac('sha256'",
    'LICENSE_PACKAGE_SIGNING_KEY',
    "addFromString('theme.json'",
    'Published ',
] as $required) if (!str_contains($tool, $required)) throw new RuntimeException('First-party release guard missing: '.$required);
if (str_contains($tool, 'addFile(')) throw new RuntimeException('First-party release tool must not copy arbitrary executable files.');
$decoded = json_decode($manifest, true, 32, JSON_THROW_ON_ERROR);
if (($decoded['kind'] ?? '') !== 'theme' || count($decoded['translations'] ?? []) !== 14) throw new RuntimeException('Nexa manifest is not a complete 14-locale theme manifest.');
echo "PASS first-party theme release tool, signing and data-only package guards\n";
