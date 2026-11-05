# TofiDesk global marketplace release map

This is the release boundary for the global software and theme marketplace. Nexa Business Theme is the seeded industry-neutral demo product; Kas Nakliyat customer data and sector copy must never be part of the generic distribution.

## Already covered in the codebase

- Product catalogue with `application`, `theme` and `addon` kinds.
- Product-owned prices, licence plans, entitlements, domain binding, downloads and release records.
- Signed application/theme packages, SHA-256 validation, update history, rollback and revocation/review states.
- 14 locale product fields: title, summary, description, slug, SEO title/description/keywords, canonical, OG image, JSON-LD, FAQ and breadcrumb schema.
- Marketplace discovery: search, categories, compare, favourites, related products, reviews and popularity metrics.
- Marketplace discovery also has first-class product tags and tag filtering, with neutral demo taxonomy seeded for the CMS and theme products.
- Published release requirements are shown on the product detail page before purchase.
- Products without a verified, signed and hash-matching release remain preview-only; checkout cannot create a sale for an incomplete product.
- `license-center/bin/marketplace-audit.php` provides a read-only final gate that reports 14-locale SEO, commercial assets, pricing, licence plans and verified release readiness per product before packaging or publishing.
- Buyer account, verification, password reset, profile language, orders, invoices, downloads and support tickets.
- Human approval gate for AI-generated translations and publication-quality checks before a product can be published.
- Product publication is now owned exclusively by the quality dashboard; the catalogue editor can save drafts but cannot bypass signed-release, hash, asset, 14-locale SEO or published-plan checks.
- Tokenless installer that becomes inert after the first administrator is created.
- Multi-currency display, tax profiles, coupons, manual payments and provider webhooks. PayTR live credentials remain a launch-time configuration step.
- Checkout and the public price switch now consume the newest administrator-recorded `currency_rates` value (with environment defaults only as a fresh-install fallback), so the provider and observation history are auditable.
- Separate generic marketplace copy and Kas Nakliyat demo/customer runtime.

## Recommended before opening a third-party seller programme

These are deliberately not enabled as an unreviewed public feature yet:

1. Seller/author accounts with verification, public author pages and product ownership.
2. Commission ledger, payout profile, settlement state and downloadable seller statements.
3. Seller-level support SLA, refund responsibility, copyright/takedown report and appeal workflow.
4. Product moderation history, malware scan result, package quarantine and reviewer assignment.
5. Commercial policy pages for VAT/invoices, refund windows, licence scope, update entitlement and support boundaries.
6. Version compatibility matrix (PHP, database, extensions, supported CMS versions) shown before purchase.
7. Operational monitoring: webhook replay, failed update alerts, anomaly review for domain/installation changes and audit export.

The audit command is intentionally read-only and does not turn a preview product
into a saleable one. This prevents a catalogue record from being mistaken for a
deliverable package, which is especially important for the first Nexa Business Theme
demo theme.

## Launch recommendation

Start with TofiDesk as the only verified publisher. Finish PayTR and one complete real purchase chain, publish the first Nexa Business Theme/TofiDesk application pair, then enable seller onboarding only after the ledger, moderation and takedown workflows are implemented. This avoids selling a product while ownership, payout or legal review records are still ambiguous.
