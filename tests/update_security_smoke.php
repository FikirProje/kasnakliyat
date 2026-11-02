<?php
declare(strict_types=1);

function envv(string $key, string $fallback = ''): string { return $GLOBALS['test_env'][$key] ?? $fallback; }
function storage_path(string $suffix = ''): string { return sys_get_temp_dir().($suffix !== '' ? DIRECTORY_SEPARATOR.$suffix : ''); }
function setting(string $key, mixed $fallback = null): mixed { return $fallback; }

require __DIR__.'/../src/UpdateClient.php';

$manifest = ['version' => '1.2.0', 'sha256' => str_repeat('a', 64)];
$release = ['manifest' => $manifest, 'signature' => ''];
$GLOBALS['test_env']['RELEASE_SIGNING_PUBLIC_KEY'] = '';
if (update_verify_signature($release)) throw new RuntimeException('Unsigned configuration was accepted.');
$release['signature'] = 'RSA-SHA256.'.base64_encode('tampered');
$GLOBALS['test_env']['RELEASE_SIGNING_PUBLIC_KEY'] = 'not-a-public-key';
if (update_verify_signature($release)) throw new RuntimeException('A tampered release signature was accepted.');

echo "PASS signed update verification and fail-closed policy\n";
