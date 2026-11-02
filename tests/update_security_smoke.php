<?php
declare(strict_types=1);

function envv(string $key, string $fallback = ''): string { return $GLOBALS['test_env'][$key] ?? $fallback; }
function storage_path(string $suffix = ''): string { return sys_get_temp_dir().($suffix !== '' ? DIRECTORY_SEPARATOR.$suffix : ''); }
function setting(string $key, mixed $fallback = null): mixed { return $fallback; }

require __DIR__.'/../src/UpdateClient.php';

$manifest = ['version' => '1.2.0', 'sha256' => str_repeat('a', 64)];
$private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
if ($private === false || !openssl_pkey_export($private, $privatePem)) throw new RuntimeException('RSA test key could not be generated.');
$details = openssl_pkey_get_details($private);
if (!is_array($details) || !isset($details['key'])) throw new RuntimeException('RSA public key could not be read.');
$canonical = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (!openssl_sign($canonical, $signature, $privatePem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('RSA test signature failed.');
$release = ['manifest' => $manifest, 'signature' => 'RSA-SHA256.'.base64_encode($signature)];

$GLOBALS['test_env']['RELEASE_SIGNING_PUBLIC_KEY'] = $details['key'];
if (!update_verify_signature($release)) throw new RuntimeException('A valid RSA release signature was rejected.');
$GLOBALS['test_env']['RELEASE_SIGNING_PUBLIC_KEY'] = '';
if (update_verify_signature($release)) throw new RuntimeException('Unsigned configuration was accepted.');
$release['signature'] = 'RSA-SHA256.'.base64_encode('tampered');
$GLOBALS['test_env']['RELEASE_SIGNING_PUBLIC_KEY'] = $details['key'];
if (update_verify_signature($release)) throw new RuntimeException('A tampered release signature was accepted.');

echo "PASS signed update verification and fail-closed policy\n";
