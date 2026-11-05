<?php
declare(strict_types=1);

require __DIR__.'/../license-center/public/order_state.php';

$allowed = [
    ['pending', 'paid'],
    ['pending', 'failed'],
    ['pending', 'cancelled'],
    ['paid', 'refunded'],
    ['paid', 'paid'],
];
foreach ($allowed as [$from, $to]) {
    if (!order_transition_allowed($from, $to)) throw new RuntimeException("Expected transition {$from} -> {$to} to be allowed.");
}

$blocked = [
    ['failed', 'paid'],
    ['cancelled', 'paid'],
    ['refunded', 'paid'],
    ['paid', 'cancelled'],
    ['pending', 'refunded'],
    ['pending', 'awaiting_payment'],
];
foreach ($blocked as [$from, $to]) {
    if (order_transition_allowed($from, $to)) throw new RuntimeException("Unexpected transition {$from} -> {$to} was allowed.");
}

echo "PASS commerce order state machine: payment/refund transitions are explicit".PHP_EOL;
