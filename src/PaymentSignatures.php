<?php
declare(strict_types=1);

/** PayTR callback hash for the Link API notification format. */
function paytr_callback_signature(array $payload, string $merchantKey, string $merchantSalt): string
{
    foreach (['callback_id', 'merchant_oid', 'status', 'total_amount'] as $field) {
        if (!isset($payload[$field]) || !is_scalar($payload[$field])) throw new InvalidArgumentException('PayTR callback field missing: '.$field);
    }
    $message = (string)$payload['callback_id'] . (string)$payload['merchant_oid'] . $merchantSalt . (string)$payload['status'] . (string)$payload['total_amount'];
    return base64_encode(hash_hmac('sha256', $message, $merchantKey, true));
}

function paytr_callback_valid(array $payload, string $merchantKey, string $merchantSalt): bool
{
    $given = $payload['hash'] ?? '';
    return is_string($given) && hash_equals(paytr_callback_signature($payload, $merchantKey, $merchantSalt), $given);
}

/** iyzico Webhook Signature V3, direct payment format. */
function iyzico_direct_signature(array $payload, string $secretKey): string
{
    foreach (['iyziEventType', 'paymentId', 'paymentConversationId', 'status'] as $field) {
        if (!isset($payload[$field]) || !is_scalar($payload[$field])) throw new InvalidArgumentException('iyzico webhook field missing: '.$field);
    }
    $message = $secretKey . (string)$payload['iyziEventType'] . (string)$payload['paymentId'] . (string)$payload['paymentConversationId'] . (string)$payload['status'];
    return hash_hmac('sha256', $message, $secretKey);
}

function iyzico_direct_valid(array $payload, string $secretKey, string $signature): bool
{
    return hash_equals(iyzico_direct_signature($payload, $secretKey), trim($signature));
}

/** Stripe-Signature verification for the raw request body and v1 signature. */
function stripe_signature_valid(string $rawBody, string $header, string $secret, int $now, int $tolerance = 300): bool
{
    if ($rawBody === '' || $header === '' || $secret === '' || $tolerance < 0) return false;
    $timestamp = null; $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't' && ctype_digit($value)) $timestamp = (int)$value;
        if ($key === 'v1' && preg_match('/^[a-f0-9]{64}$/i', $value)) $signatures[] = strtolower($value);
    }
    if ($timestamp === null || !$signatures || abs($now - $timestamp) > $tolerance) return false;
    $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    foreach ($signatures as $signature) if (hash_equals($expected, $signature)) return true;
    return false;
}
