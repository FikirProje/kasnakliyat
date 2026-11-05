# TofiDesk global product platform blueprint

This is the product decision record for the first-party marketplace. It keeps
the customer/demo application separate from the global catalogue and prevents
licence, theme and software delivery from becoming three unrelated systems.

## Product boundaries

- `product.tofidesk.com` is the marketplace, buyer account, catalogue, checkout,
  licence centre, release centre and support centre.
- `kasnakliyat.com` is the first customer application and the first real demo
  product. It must never be used as the marketplace brand or default industry
  copy.
- The first launch is first-party only: TofiDesk is the publisher of the
  catalogue. Third-party sellers stay disabled until seller verification,
  payout accounting, malware quarantine, copyright takedown and appeals exist.

## One product model

Every saleable record is a `product` with a `kind` of `application`, `theme` or
`addon`. A product owns its plans, prices, localized content/SEO, assets,
releases, compatibility requirements, downloads and licence entitlements.
Orders point to `product_id` and `plan_id`; the legacy product text is retained
only for backward compatibility. A paid order creates the entitlement and the
licence, and every download/update is tied to that same product and release.

## Publication contract

An item cannot become saleable until the quality review confirms:

1. all 14 locale records: title, description, slug, SEO title/description,
   keywords, canonical, OG image and valid Product/SoftwareApplication/Offer,
   FAQ and Breadcrumb JSON-LD;
2. an active price and a published licence plan;
3. demo, documentation and screenshot assets;
4. a signed, hash-verified release package with a readable changelog;
5. compatibility information: PHP, database, required extensions, CMS range,
   migration level and supported release channel;
6. for themes, preview video, package requirements and parent/override data;
7. an explicit editorial approval event.

Machine translation always creates a draft revision. It never publishes or
overwrites an approved locale without an editor action.

## Licence and update rules

- A licence is issued only from a paid/approved entitlement and is bound to an
  approved domain limit.
- Domain replacement, transfer, refund, revocation and restoration are
  explicit audited actions; none is inferred from a request alone.
- Installations report only the minimum technical identity required for update
  eligibility. The centre records failed signatures, excessive activation,
  domain churn and unusual download patterns for review.
- Updates are signed, hash checked, backed up before migration and reversible
  through the previous release/rollback target.
- Scheduled maintenance is portable across cPanel and Plesk: configure
  `php bin/cron.php` from the panel scheduler. It performs a bounded queue
  pass, expires cache entries and recovers worker claims that were interrupted.

## Global storefront rules

- Language selection is explicit and persisted; IP detection may suggest a
  language but must never override the visitor's choice.
- Currency is selected independently from language. Prices, tax treatment and
  invoice currency are recorded on the order, not recalculated later from the
  current exchange rate.
- Product pages expose compatibility, licence scope, update/support period,
  refund policy, changelog, demo, documentation, reviews and related products
  before checkout.
- SEO is locale-specific with canonical and reciprocal hreflang links. Schema
  must describe the actual product kind, offer and availability; no transport
  claim is allowed for a generic product.

## Recommended launch sequence

1. Resolve the actual Plesk database/storage/permissions audit.
2. Publish the first signed `TofiDesk CMS` application and `Nexa Business Theme`
   demo records through the quality review.
3. Verify SMTP, backup/worker and customer support delivery.
4. Run the real PayTR callback and idempotency tests.
5. Run one end-to-end purchase, licence, installation, update and rollback.
6. Only after these gates pass, enable public paid traffic and consider bundles,
   subscriptions and approved external publishers.

## Deliberate non-goals for the first launch

The centre does not silently scan arbitrary servers or disable installations
based only on telemetry. Revocation requires a documented technical,
contractual or legal reason and an auditable administrator decision. This is
safer for customers and gives TofiDesk evidence for support and disputes.
