<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../license-center/public/index.php');
if ($source === false) throw new RuntimeException('Marketplace source could not be read.');
foreach (['tofidesk-cms', 'nexa-business-theme', 'normalize_legacy_marketplace_records', 'TofiDesk Software Marketplace'] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Global marketplace profile is missing: '.$required);
}
foreach (['kas-nakliyat-theme', 'https://kasnakliyat.com'] as $forbiddenSeed) {
    if (str_contains($source, $forbiddenSeed)) throw new RuntimeException('Customer-specific seed leaked into Product Center: '.$forbiddenSeed);
}
echo "PASS Product Center seed is industry-neutral and Kas Nakliyat remains external\n";
