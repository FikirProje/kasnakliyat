# TofiDesk global marketplace — remaining launch gates

This document is intentionally explicit: the marketplace core is implemented,
but the product is not declared production-ready until the following gates are
closed.

The product boundaries and launch contract are recorded in
[`PRODUCT_PLATFORM_BLUEPRINT.md`](PRODUCT_PLATFORM_BLUEPRINT.md).

## Implemented core

- First-party catalogue for applications, themes and add-ons.
- Product-owned plans, prices, releases, downloads and localized SEO/schema.
- Buyer account, verification, password reset, orders, invoices, support and
  domain-bound licences.
- Signed release checks, package traversal/PHP checks, update history,
  rollback targets and revocation/audit events.
- Buyer-requested licence domain replacement and licence transfer with
  administrator approval, verified target checks and an event/audit trail.
- Admin anomaly and lawful review queues for activation/domain churn and failed
  update/signature signals.
- 14 locale storefront metadata and RTL direction for Arabic and Persian.
- 14-locale installer, customer account mail templates, customer admin UI and
  licence-centre admin shell; runtime coverage renders 32 routes in every locale.
- Kas Nakliyat customer/demo runtime kept separate from the generic marketplace
  package.

## Must be closed before public paid launch

1. Run the read-only marketplace audit against the actual Plesk SQLite file and
   resolve every blocking result for the application and first theme.
2. Configure PayTR secrets only in the server environment, then replay signed
   and invalid callbacks and verify idempotent licence issuance.
3. Complete one real chain: account → order → payment → invoice → licence →
   domain activation → download → install → update → rollback.
4. Verify SMTP delivery for account verification, password reset, receipts and
   support notifications.
5. Take and restore a production backup; verify the scheduled worker and failed
   update alerts.
6. Perform native-language review of commercial, legal and product copy. The
   translation structure exists; machine output is not a substitute for review.
7. Upload the current product-center package. The read-only check on
   2026-11-05 found HTTP 200 responses, no new `/health.json` product-center
   marker, and a live `/shop` that still serves the old transport-only
   hero/theme copy; local source and package seed are industry-neutral.

## Deliberately not enabled yet

Third-party seller onboarding must remain closed until seller verification,
commission and payout ledger, malware quarantine, copyright/takedown, refund
responsibility and appeal history exist. Until then TofiDesk is the verified
publisher, which is the safer launch model for the first Nexa Business Theme product.

## Recommended next marketplace upgrades

- Bundle/upgrade pricing is implemented in Product Center as product bundles,
  component order items and licence entitlements; verify the first commercial
  bundle through Product quality before publishing it.
- Compatibility matrix visible before purchase (PHP, database, extensions and
  CMS release channel) is implemented in the public product detail and
  signed-release quality gate; verify the rendered copy on the deployed host.
- Changelog subscriptions and release-alert preferences are implemented; SMTP
  delivery remains a deployment gate, with the local outbox retaining messages
  safely until it is configured. Customer-facing download history is included.
- Seller mode only after the controls above are implemented.

The external gates are intentionally deferred, not silently marked as passed.
