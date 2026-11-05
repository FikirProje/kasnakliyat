# TofiDesk — global software, theme and plugin platform goal

## Outcome

Ship a sector-neutral, marketplace-ready CMS foundation and a separate TofiDesk sales, licensing and theme-delivery centre. Kas Nakliyat is the first reference product, not the platform identity. A buyer can discover software, themes and plugins for any sector in their language, purchase them, receive a licence only after verified payment or marketplace purchase verification, install them on an allowed domain, receive assigned themes/add-ons and manage their account without support intervention.

## Completion criteria

- The public storefront, buyer account, checkout, licences, invoices, support and product catalogue are available in Turkish, English, German, French, Spanish, Italian, Portuguese, Dutch, Russian, Arabic, Chinese, Japanese, Korean and Persian. Arabic and Persian render RTL.
- The core is sector-neutral: content types, custom fields, taxonomies, menus, forms, media, locations, search, hooks, webhooks, roles and permissions are data-driven; sector packs and plugins add behavior without forking the core.
- The SDK supports safe theme manifests, sections, design tokens, child themes, demo data, versioned releases, signed packages, rollback and dependency metadata. Authors can build themes and extensions while the platform owns commerce, SEO, locale, search, licensing and updates.
- Global discovery supports country/region/city/province/district/neighborhood hierarchies, locale-aware search facets, radius-ready location data, canonical/hreflang, Open Graph, JSON-LD, sitemap and robots controls.
- Browser language chooses the initial storefront locale; the visible language selector and the buyer profile can override it.
- A product order has an immutable order reference, amount, currency, tax/invoice fields, payment status and audit history.
- PayTR payment requests are created server-side. A licence is issued only after PayTR’s signed callback has been validated with environment-only merchant credentials. Failed, pending or spoofed callbacks never issue a licence.
- The buyer portal lists owned products, licence state, approved domains, invoices, payments, downloads and support tickets.
- CodeCanyon purchase codes are verified by the author-side Envato API before an equivalent licence is issued. Author credentials never enter buyer installations or Git.
- An administrator can publish, retire and assign data-only themes per locale. An eligible installed product receives only the theme assigned to its active licence.
- Product downloads, customer contact data, invoices and support messages are access-controlled, auditable and excluded from marketplace release archives.
- Storefront and buyer portal pass desktop, tablet, mobile, RTL, locale, payment-callback security and licensing integration tests before release.

## Delivery order

1. Sector-neutral core, module/plugin registry, theme SDK, menus, forms, locations, search and webhook queue.
2. Localized storefront and locale preference.
3. Buyer accounts and authenticated portal.
4. Orders, invoices and support tickets.
5. PayTR signed checkout/callback integration and safe licence issuance.
6. Envato author-sale verification.
7. Theme catalogue, assignment and client delivery.
8. Full cross-device, 14-locale and payment security verification.

## Current state

The repository already contains the standalone licence-centre deployment package, public product page, product-first commerce model, contact/purchase request capture, administrator licence issuance, domain activation, localized-theme data delivery, signed release/update records, sector-neutral core registries and the client-side licence panel. Real PayTR credentials, external map/AI/SMTP credentials and live payment/webhook verification remain deployment gates; they are intentionally not hard-coded into the packages.
