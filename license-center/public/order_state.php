<?php
declare(strict_types=1);

function order_statuses(): array { return ['pending','paid','failed','refunded','cancelled']; }
function order_transition_allowed(string $from,string $to): bool {
    if (!in_array($from, order_statuses(), true) || !in_array($to, order_statuses(), true)) return false;
    if ($from === $to) return true;
    return match ($from) {
        'pending' => in_array($to, ['paid','failed','cancelled'], true),
        'paid' => $to === 'refunded',
        default => false,
    };
}
