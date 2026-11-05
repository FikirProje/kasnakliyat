<?php
declare(strict_types=1);

$schema = file_get_contents(__DIR__.'/../license-center/public/account_routes.php');
$index = file_get_contents(__DIR__.'/../license-center/public/index.php');
$checkout = file_get_contents(__DIR__.'/../license-center/public/paytr_checkout.php');
$fulfillment = file_get_contents(__DIR__.'/../license-center/public/order_fulfillment.php');

$tables = [
    'products', 'license_plans', 'application_releases', 'theme_packages',
    'product_assets', 'product_translations', 'product_prices', 'order_items',
    'tax_profiles', 'coupons', 'currency_rates', 'billing_addresses',
];
foreach ($tables as $table) {
    if (!str_contains($schema.$index, 'CREATE TABLE IF NOT EXISTS '.$table)) {
        throw new RuntimeException('Missing product-commerce table: '.$table);
    }
}
foreach (['product_id','plan_id'] as $column) {
    if (!str_contains($checkout, $column) || !str_contains($fulfillment, $column)) {
        throw new RuntimeException('Product identity is not preserved through checkout/fulfillment: '.$column);
    }
}
foreach (['order_items', 'INSERT INTO orders', 'INSERT INTO licenses', 'INSERT INTO downloads'] as $needle) {
    if (!str_contains($checkout.$fulfillment, $needle)) {
        throw new RuntimeException('Missing commercial chain contract: '.$needle);
    }
}
if (!str_contains($checkout, 'order_items(order_id,product_id,plan_id')) {
    throw new RuntimeException('Checkout does not write a product/plan order item.');
}
echo 'PASS product-first commerce schema: catalogue, plans, order items, fulfilment and licence identity are linked.'.PHP_EOL;
