<?php
declare(strict_types=1);

$root = dirname(__DIR__);
// This is a pure market-matrix contract; do not require the application
// bootstrap or a database extension just to validate country defaults.
require $root . '/src/CountryProfiles.php';

$profiles = country_profiles();
if (count($profiles) !== 195) throw new RuntimeException('Expected exactly 195 market profiles.');
foreach ($profiles as $code => $profile) {
    if (!preg_match('/^[A-Z]{2}$/D', $code) || ($profile['code'] ?? '') !== $code) throw new RuntimeException('Invalid country code: '.$code);
    if (!preg_match('/^[A-Z]{3}$/D', (string)($profile['currency'] ?? ''))) throw new RuntimeException('Invalid currency for '.$code);
    if (!in_array((string)($profile['timezone'] ?? ''), timezone_identifiers_list(), true) && ($profile['timezone'] ?? '') !== 'UTC') throw new RuntimeException('Invalid timezone for '.$code);
    if (($profile['date_format'] ?? '') === '') throw new RuntimeException('Missing date format for '.$code);
    if (!is_string($profile['phone_prefix'] ?? null)) throw new RuntimeException('Invalid phone prefix for '.$code);
    if (country_profile(strtolower($code)) !== $profile) throw new RuntimeException('Case-insensitive lookup failed for '.$code);
}
try { country_profile('XX'); throw new RuntimeException('Invalid country was accepted.'); }
catch (InvalidArgumentException) { }

echo "PASS 195 country profiles: valid ISO-like codes, safe defaults and strict invalid-code rejection\n";
