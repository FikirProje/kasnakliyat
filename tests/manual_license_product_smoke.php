<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../license-center/public/manual_license_admin.php');
if ($source === false) throw new RuntimeException('Manual licence handler source could not be read.');
foreach (['product_id','plan_id','products','license_plans','product_id,plan_id','tofidesk-cms'] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Product-centric manual licensing is missing: '.$required);
}
if (str_contains($source, "['transport-cms'") || str_contains($source, "'transport-cms'")) throw new RuntimeException('Legacy transport product is still used for new manual licences.');
$legacyFallback = file_get_contents(__DIR__.'/../license-center/public/index.php');
if ($legacyFallback === false || str_contains($legacyFallback, "['transport-cms','pending'")) throw new RuntimeException('Legacy transport product is still used by the fallback manual licence route.');
echo "PASS manual licence issuance is product and plan centric\n";
