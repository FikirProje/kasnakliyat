<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$module = file_get_contents($root.'/license-center/public/license_requests.php');
$index = file_get_contents($root.'/license-center/public/index.php');
$adminI18n = file_get_contents($root.'/license-center/public/admin_i18n.php');
$accountRoutes = file_get_contents($root.'/license-center/public/account_routes.php');
foreach ([
    'license_change_requests',
    'domain_replace',
    'license_transfer',
    'licence.change.requested',
    'licence.change.approved',
    'licence.change.rejected',
    'email_verified_at IS NOT NULL',
    'review_note',
] as $needle) {
    if (strpos($module, $needle) === false) throw new RuntimeException('Missing licence change safeguard: '.$needle);
}
foreach (['license_requests.php','license_requests_route();','./license-requests','./account/license-requests'] as $needle) {
    if (strpos($index, $needle) === false) throw new RuntimeException('Missing licence request route wiring: '.$needle);
}
if (substr_count($adminI18n, "'Licence change requests'=>") !== 14 || substr_count($accountRoutes, "'Licence change requests'=>") !== 14) {
    throw new RuntimeException('Licence change request labels are not covered by all 14 locales.');
}
echo "PASS licence domain replacement and transfer review workflow\n";
