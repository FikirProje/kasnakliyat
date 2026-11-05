<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../license-center/public/paytr_checkout.php');
if ($source === false) throw new RuntimeException('PayTR checkout source could not be read.');
if (stripos($source, '<iframe') !== false || stripos($source, 'iFrameResize') !== false) {
    throw new RuntimeException('PayTR checkout must not embed a provider iframe.');
}
if (!str_contains($source, "header('Location: '.\$paymentUrl, true, 303)")) {
    throw new RuntimeException('PayTR checkout must redirect to the hosted payment page.');
}
foreach (['checkout_currency_rate','checkout_tax_breakdown','order_items','billing_addresses'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('PayTR checkout lost required commerce integration: '.$required);
    }
}
echo "PASS PayTR checkout uses full-page hosted redirect without iframe\n";
