# Release audit — global marketplace readiness

Audit date: 2026-11-04. This is an evidence-based release gate, not a marketing checklist.

## Validation run

- PHP syntax checks: `src/`, `public/index.php`, and public templates passed.
- Installer security flow: tokenless first-admin install and lock-after-install passed.
- Runtime locale coverage: the main installer and license-centre installer each rendered all 14 locales; account reset/verification mail rendered 14 locales × 2 templates; the license-centre HTTP runtime coverage exercises 31 public/admin/account/content routes × 14 locales with direction checks, including the localized cookie-consent banner and licence-change review queue.
- Marketplace release gate: the read-only audit accepts a complete signed product fixture and blocks the same product when its release signature is removed; the product-quality approval screen also requires a published licence plan.
- Commerce rate gate: checkout and public price display prefer the newest admin-recorded `currency_rates` entry and retain provider/observation history, with environment rates only as fresh-install fallback.
- Gallery publication/AJAX/media flow: passed.
- Backup/restore integrity, recovery copy and traversal rejection: passed.
- The broader smoke suite had previously passed 17 groups. It must be run again after every merge to `main`.

## Release blockers

### P0 — must resolve before a marketplace release

1. **Do not use a chat-shared API key.** A key supplied in conversation must be revoked and replaced through the deployment secret store. PHP exception traces can include function arguments in server logs unless `zend.exception_ignore_args=1` is set; no secret should reach application logs.
2. **Resolved — generic archive isolation.** The marketplace profile excludes Kaş customer assets, configuration and database/runtime content, injects neutral TofiDesk assets, and passes `tests/release_audit.py`.
3. **Resolved — theme custom CSS and CSP.** The public layout emits the validated custom-theme block with the per-request `csp_nonce()` and the bootstrap policy allows that nonce; the policy does not use `unsafe-inline`. The admin rejects closing tags and oversized CSS values.
4. **Implemented in source — 14-language admin/installer evidence.** Installer, buyer account, public catalogue, product detail, content/policy pages and the covered admin operations now have fourteen-locale dictionaries and automated runtime coverage. A human native-speaker review of long-tail copy is still recommended before advertising the translations as professionally edited.

### P1 — required before the claimed feature set

1. **Mostly resolved for distribution.** Generic marketplace copy, installer text, buyer email templates, neutral demo-theme artwork and AI/product profile context are separated from the Kas Nakliyat profile; AI editorial and image prompts now receive content type, sector and market context and explicitly reject unsupported transport/local-service claims. The Kas-specific strings remain intentionally in the demo application. Final archive inspection must still confirm no customer data is packaged.
2. **Resolved in the current source.** Installation country profiles now provide locale, fallback locale, time zone, currency, date format, phone prefix, address conventions and RTL direction; the selected profile is persisted during setup.
3. **Resolved in the current source.** Versioned signed theme packages now carry logo/font/colour/section configuration, preview media, requirements, changelog, parent-release and rollback metadata. The client applies validated theme defaults without allowing arbitrary PHP editing.
4. **Resolved in source.** Validated social links render as accessible footer/header controls; invalid or empty URLs are omitted.
5. **Resolved in source, live mailbox test pending.** Inbound IMAP now records `In-Reply-To`/`References` thread keys, imports only bounded safe MIME attachments into private storage, and purges attachment files with retained messages. Credentials remain environment-only and are never written to the application database. A real mailbox acceptance test is still required.
6. Add a production deployment acceptance test for HTTPS, SMTP delivery, AI egress and external verification meta tags. Local tests intentionally do not prove these integrations.

### P2 — release quality work

9. **Live deployment check (2026-11-05):** the currently hosted `product.tofidesk.com` still serves the older transport-specific `/shop` hero and does not expose the `product-center` `/health.json` marker. The source tree and final package have industry-neutral marketplace copy; the strengthened live smoke test rejects the stale deployment until the current Product Center package is uploaded.

1. **Resolved in source, editorial review pending.** The generic marketplace seed now includes the TofiDesk CMS application and the industry-neutral Nexa Business Theme as real product-catalogue records, with product selection, version/licence and installation guidance in all fourteen locales and localized slugs/SEO fields. The Kas Nakliyat customer/demo profile stays in its separate application package. Native-speaker review is still required before advertising language quality as human-reviewed.
2. Add a changelog, upgrade/migration guide, support policy, licence inventory and third-party attribution bundle.
3. Add screenshot/demo fixtures that do not show a real customer, supplied logo, address or contact data.
4. Test PHP 8.2–8.4 and documented shared-hosting/web-server combinations, including SQLite permissions and cron/worker operation.

7. **Resolved in the current source.** Product detail pages now emit a validated JSON-LD graph containing Product/SoftwareApplication, Offer, and any approved FAQ/Breadcrumb schemas, while localized title, description, keywords, canonical and OG image are applied to the document head.

8. **Added in the current source.** Theme product pages render published screenshot/OG/icon assets as responsive previews and keep video assets playable; the catalog smoke test protects this behavior.

## Existing strengths

- CSRF, rate limiting, upload MIME/decode checks, role boundaries, private customer media, scheduled publication, optimistic content revisions and backup integrity are covered by automated tests.
- SMTP is queued rather than sent in a request; failed work is retained for review.
- Public SEO foundations include canonical URLs, sitemap, hreflang, Open Graph, X card metadata and JSON-LD.

## Marketplace release decision

The generic marketplace ZIP is isolated from the Kaş Nakliyat deployment and passes the automated archive scan. Production credentials, legal text, payment configuration and native-language editorial review remain release/deployment gates.

Implementation note: public social profile links are now validated and rendered as accessible footer links; missing or invalid profile URLs are omitted.

The final marketplace gate is `license-center/bin/marketplace-audit.php`. It is
read-only and reports, per published product, the 14 locale SEO records, price,
demo/document/screenshot assets, published licence plan and a verified
signature/hash-matching release. A blocked result must be resolved before the
final marketplace archive is created.

Controlled licence changes are also implemented: buyers can request a domain
replacement or licence transfer, administrators approve or reject the request,
verified target accounts are required for transfers, collisions are blocked,
and the decision is written to the licence event log.
