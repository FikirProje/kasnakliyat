# TofiDesk global marketplace — launch recommendations

## Product boundary

- `product.tofidesk.com` is the global marketplace, buyer account, checkout,
  licence centre, download centre and update centre.
- `kasnakliyat.com` is the first branded application/demo and the first product
  used to prove the complete install, licence and update flow.
- The marketplace homepage must use neutral software/theme language. Kas
  Nakliyat may appear as a featured product, but it must not define the
  marketplace taxonomy, SEO or global navigation.

## Must exist before paid public launch

1. One complete signed product pair: application plus theme, each with a
   verified release, 14 published translations, localized SEO/schema, demo,
   documentation, screenshot, price and published licence plan.
2. Buyer lifecycle: registration, verification, password reset, profile,
   checkout, order, invoice, licence, domain activation, download, install,
   update, rollback and support ticket.
3. Commercial policy: licence scope, update entitlement, support period,
   refund window, VAT/tax treatment, invoice fields, privacy, terms and
   acceptable-use/takedown policy in every storefront language.
4. Operations: SMTP delivery, cron/worker, backup and restore rehearsal,
   failed-update alerting, webhook idempotency and audit export.
5. Security: rate limits, CSRF, secure download authorization, signed release
   verification, activation anomaly review, revocation workflow and a fair
   offline/grace policy. No licensing scheme can honestly promise an
   unbreakable system; the goal is layered prevention, detection and response.
   Customer domain replacement and licence transfer now use an administrator-
   reviewed queue with verified-target checks and a licence event trail.

## High-value upgrades after the first verified launch

- Product bundles and upgrade pricing for application + theme + add-ons.
- Visible compatibility matrix for PHP, database, extensions and CMS version.
- Changelog subscriptions and release notifications.
- Product comparison, saved favourites, related products, reviews, verified
  purchase badges and sector/category landing pages.
- Admin anomaly dashboard for excessive activations, domain churn, revoked
  keys, failed signatures and repeated download abuse.
- Multi-region tax rules, invoice numbering, VAT/GST IDs and currency-rate
  history with an administrator override.

## Seller marketplace phase (do not open yet)

Third-party publishing should remain disabled until seller verification,
commission and payout ledger, malware quarantine, copyright/takedown reports,
refund responsibility, seller support SLA and appeal history are implemented.
Until then TofiDesk is the sole verified publisher, which is the safest way to
launch the first product without mixing customer ownership and seller risk.

## Current deferred gates

PayTR live credentials, real payment callbacks, SMTP delivery, production
backup/restore, worker verification and native-language editorial review are
external launch checks. They are intentionally deferred, not treated as
completed by local code tests.
