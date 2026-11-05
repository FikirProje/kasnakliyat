<?php
declare(strict_types=1);

$path = '__commerce_test__';
require __DIR__.'/../license-center/public/paytr_checkout.php';

$exclusive = checkout_tax_breakdown(10000, 1000, ['rate_basis_points'=>2000, 'inclusive'=>0]);
if ($exclusive['taxable_minor'] !== 9000 || $exclusive['tax_minor'] !== 1800 || $exclusive['total_minor'] !== 10800) {
    throw new RuntimeException('Exclusive tax calculation is incorrect.');
}
$inclusive = checkout_tax_breakdown(10000, 1000, ['rate_basis_points'=>2000, 'inclusive'=>1]);
if ($inclusive['taxable_minor'] !== 9000 || $inclusive['tax_minor'] !== 1500 || $inclusive['total_minor'] !== 9000) {
    throw new RuntimeException('Inclusive tax calculation is incorrect.');
}
$none = checkout_tax_breakdown(10000, 1000, null);
if ($none['taxable_minor'] !== 9000 || $none['tax_minor'] !== 0 || $none['total_minor'] !== 9000) {
    throw new RuntimeException('Tax-free calculation is incorrect.');
}
echo "PASS commerce tax calculation: inclusive/exclusive/discount cases".PHP_EOL;
