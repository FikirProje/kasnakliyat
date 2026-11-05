# TofiDesk global marketplace final audit

## Scope

`product.tofidesk.com` is the global software, theme and licence marketplace.
`kasnakliyat.com` is a separate branded customer/demo installation. The two
deployments must never be packaged together.

## Implemented release controls

- The catalogue has first-class `application`, `theme` and `addon` products.
- Product-owned prices, licence plans, plan translations, releases, downloads,
  demos, documentation and compatibility requirements are persisted separately.
- Each supported locale has product title, description, slug, SEO title,
  description, keywords, canonical URL, OG image, JSON-LD, FAQ schema and
  breadcrumb schema fields.
- `/pricing` reads published product licence plans; it does not use legacy
  hard-coded Starter/Studio/Agency data.
- Product JSON-LD emits one `Offer` per published licence plan.
- AI translations are drafts until editorial approval and product QA approval.
- Product publication is owned by `/product-quality`. The product editor,
  application release uploader and legacy theme form cannot bypass that gate.
- A saleable product requires all 14 locale records, valid localized SEO,
  price, demo, documentation, screenshot, compatible release asset, valid
  SHA-256/signature and at least one published licence plan.
- Theme ZIPs reject PHP and path traversal entries and support parent releases,
  rollback targets, video, requirements and structured theme configuration.
- Buyer downloads and updates verify product identity, release hash and package
  signature; revocation and installation review leave an audit event.
- Buyers can opt into per-product release and changelog alerts from their
  account; the preference is stored per buyer/product/locale and is safe when
  SMTP is still in outbox mode.
- Paid fulfilment resolves the purchased product kind to its own latest
  published release: themes use `theme_packages`, applications and add-ons use
  `application_releases`; the old global download URL is retained only for
  pre-catalogue legacy orders.
- Payment webhooks use the same product-aware fulfillment boundary, so manual
  approval and provider callbacks cannot issue different package types.
- Generic marketplace archives remove customer database, credentials, media and
  Kas Nakliyat identifiers before packaging.
- The empty-profile public copy layer now neutralizes legacy transport-sector
  vocabulary across the supported locales; a configured customer profile still
  intentionally restores that customer’s own sector copy.

## Verified local checks

Run from the repository root:

```text
php -l license-center/public/catalog_public.php
php -l license-center/public/catalog_admin.php
php -l license-center/public/release_admin.php
php -l license-center/public/paytr_checkout.php
python tests/license_center_smoke.py
python tests/release_audit.py
php tests/admin_locale_coverage.php
php tests/installer_locale_coverage.php
php tests/account_mail_locale_coverage.php
php tests/commerce_calculation_smoke.php
```

The local Windows PHP CLI may not have PDO SQLite enabled. That is an
environment limitation; the Plesk PHP-FPM runtime must still pass the same
checks before deployment.

## Intentionally deferred launch gates

These require production credentials or external state and must be completed
last:

1. PayTR merchant credentials, signed callback and duplicate webhook replay.
2. Real purchase -> paid order -> invoice -> licence -> download -> install ->
   update -> rollback chain.
3. SMTP delivery for verification, password reset, receipts and support.
4. Production backup/restore and scheduled worker verification.
5. Native-language editorial review of commercial copy and legal policies.

Third-party seller onboarding is not enabled by default. It should only be
opened after seller verification, commission/payout ledger, package malware
quarantine, copyright/takedown and appeal workflows are implemented.

