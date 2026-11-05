# PayTR production checklist

## Private Plesk environment

Set these values outside the ZIP and never in public files:

```env
PAYTR_MERCHANT_ID=...
PAYTR_MERCHANT_KEY=...
PAYTR_MERCHANT_SALT=...
PAYTR_TEST_MODE=1
PAYTR_DEFAULT_CURRENCY=USD
PAYTR_ALLOW_CURRENCY_SELECTION=1
PAYTR_RATE_TL=35
PAYTR_RATE_USD=1.08
PAYTR_RATE_GBP=0.86
PAYTR_RATE_RUB=100
```

Use rates maintained by the administrator. The selected checkout currency and
amount are stored with the order before the PayTR request is made.

## PayTR merchant panel

Set the callback URL to:

`https://product.tofidesk.com/api/v1/webhooks/paytr`

The success and failure URLs are generated from `APP_URL`; do not hard-code a
staging URL in production.

## Test sequence

1. Keep `PAYTR_TEST_MODE=1` and use PayTR test credentials.
2. Register a buyer and choose USD, EUR and TL separately.
3. Confirm the checkout amount, currency and order reference.
4. Complete a test payment and confirm the signed callback changes only the
   matching order to `paid`.
5. Confirm invoice, licence, domain entitlement and buyer download creation.
6. Replay the same callback; it must remain idempotent and create no duplicate
   licence or invoice.
7. Send a tampered signature; it must be rejected with no paid order.
8. After approval, set `PAYTR_TEST_MODE=0` and repeat one low-value live test.

Card data remains inside PayTR's embedded secure payment form. The application
does not store card numbers, CVV values or PayTR secrets.
