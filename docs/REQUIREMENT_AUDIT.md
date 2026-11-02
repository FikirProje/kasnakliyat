# TofiDesk requirement audit

This is a release evidence map, not a claim that live commercial launch is
complete. Provider credentials, business legal text and production-domain
checks remain deployment responsibilities.

## Verified in the current repository

- Buyer registration, login/logout, email verification, password reset, profile
  locale preference, password change, support tickets and session revocation.
- 14 locales, RTL handling, locale preference persistence, canonical,
  hreflang, Open Graph, X cards, JSON-LD, sitemap and robots.
- Theme catalogue, data-only theme upload, preview, pricing, licence type,
  versions, changelog, domain binding, protected downloads and licence API.
- Orders, invoices, refunds, payment-event idempotency and signed PayTR,
  iyzico and Stripe webhook validation.
- Blog/news/content drafts, scheduled publication, AI job queue, editor
  approval, media isolation and backup/restore integrity.
- Admin roles, TOTP, audit log, SMTP queue/test, SMS/WhatsApp test mode,
  provider health, worker, retention policy and cookie-consent analytics.
- Tokenless installers, Plesk/cPanel deployment guidance and release ZIP
  secret/runtime-data exclusion.

Evidence:

- tests/smoke.py: 17/17 groups passed with SQLite/GD enabled.
- tests/integrations.php: 31/31 assertions passed.
- tests/smtp_smoke.py, tests/release_audit.py and
  tests/license_center_smoke.py: passed.

## Must be configured and verified before live sales

- Production HTTPS/DNS, PHP extensions, writable private storage and cron.
- SMTP/SMS/WhatsApp/OpenAI/payment/Envato credentials in server environment
  only; run each provider health/test action after configuration.
- Business-specific KVKK/GDPR, privacy, terms, refund and SLA wording.
- Search Console/Bing/Yandex verification, analytics consent and production
  sitemap submission.
- Real purchase, signed callback, invoice, licence activation and restore
  rehearsal on a staging domain.

Never copy test credentials or customer data into a release archive.
