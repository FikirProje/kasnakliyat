# TofiDesk global marketplace – test report

Date: 2026-11-05

## Verified locally

- 14 locale admin shell, status, sections and mail labels
- 14 locale installer strings and installer runtime rendering
- 14 locale licence-centre preflight/error rendering with RTL direction
- 14 locale account email templates
- Generic marketplace demo data: 3 products × 14 locales with SEO fields
- Industry-neutral Product Center seed; Kas Nakliyat remains in the separate application package
- Product quality gate, signed-release/archive audit and customer-data scrub
- Product-first commerce schema contract: product/plan/order-item/licence/download identity
- Commerce tax calculation and order state transitions
- Licence domain replacement and transfer review workflow
- Application installer profile persistence
- PHP lint: application and licence-centre source trees, 0 failures
- Reference sector smoke: transport, real-estate and restaurant packs each expose data-driven content types, fields, settings and 14-locale manifests.
- Tokenless installer, buyer security and route coverage
- PayTR checkout surface: full-page hosted redirect, no provider iframe
- Manual licence issuance is product/plan-centric; no new licence defaults to the legacy transport product
- Theme/application update download path regression checked; release kind and package path are selected explicitly
- License-centre runtime: 14 locales × 32 routes

## Package prepared

- File: `outputs/product-tofidesk-license-center.zip`
- SHA-256: `DBCA41AFF4B507BAEADF4A076BFEC94F27288F554D78A1A183B32C1D398A6561`
- Target: `product.tofidesk.com` document root
- Clean FileZilla folder: `outputs/product-tofidesk-license-center-filezilla-20261105-9`

The visitor-facing gallery API was also regression-tested after hardening JSON
encoding for legacy/invalid media metadata. The test confirms publication,
localized metadata, AJAX JSON and public WebP delivery.

Additional local acceptance checks passed:

- 14-locale admin, installer, account-mail, product metadata and preflight coverage.
- Product commerce schema, tax/currency, catalogue discovery and quality-gate checks.
- Paid-order fulfillment idempotency: invoice, licence entitlements and download.
- Release audit, update security, manual licence, PayTR surface and marketplace archive checks.
- PHP syntax validation for the application and licence-centre source trees.
- Sector-neutral platform smoke: module registry, generic content types, 14 localized entry records, 195-country baseline, hierarchical city locations and search registry.
- Sector content administration: manifest-driven entry creation with location, locale, publication workflow and schema validation; editor permission and 14-language admin section coverage passed.
- Developer SDK smoke: three validated sector manifests, safe read-only discovery and bundled module synchronisation.
- Universal platform audit: all checks passed, including module/plugin manifests, manifest-declared fields for all 11 sector packs, SDK theme, versioned API, authenticated REST form submission, cron maintenance entrypoint, 14 locales, RTL, migration chain, release tests and package presence.
- Global goal acceptance contract: 14 locales, 11 sector manifests, core/API/SDK/commerce/deployment surfaces.
- Discovery smoke: locale-aware favorites, comparison, moderated reviews and weighted popularity metrics/API for products, themes, plugins and sector content.
- Data-driven permission smoke: administrator/editor/operator login and section authorization passed through the permission registry, including module permissions.
- Product Center archive hygiene: runtime databases, sessions, backups, media and outbox/test records are excluded; only `storage/.gitkeep` is shipped.
- Deployment archive separation: application, marketplace and Product Center roots have distinct entrypoints; cross-upload/mixed-package layouts are rejected by `tests/deployment_archive_contract.py`.
- Deterministic Product Center packaging: repeated builds from identical source produce the same ZIP SHA-256.
- Deployment identity probe: public `/health.json` returns product, release, SQLite/PDO, storage and installer-lock status without requiring login; response headers identify `cms` versus `product-center`.
- First-party application release smoke: the CLI release path was executed against a temporary SQLite database and its signed compatibility manifest was verified.
- Product Center install-path contract: root/public entrypoints, clean `/install` rewrite and separation from the application `src/bootstrap.php` were verified.
- Product Center missing-extension runtime check: `php -n` against both root entrypoints returned the readable requirements screen instead of a fatal 500.

Current application archives:

- `outputs/product-tofidesk-app-deployment.zip` — SHA-256 `A9B6A1474C93DB4614936FC5D013A5B2F0D6E68C5A9D4569E307FB11F2B75447`
- `outputs/product-tofidesk-marketplace.zip` — SHA-256 `F0C7093F5ED11A22604B5E77E69AA88561DD5AD277927DF00F5B0DDF2EDF75D7`
- `outputs/product-tofidesk-license-center.zip` — SHA-256 `DBCA41AFF4B507BAEADF4A076BFEC94F27288F554D78A1A183B32C1D398A6561`
- `tests/release_alerts_smoke.py` — buyer registration, licensed-product preference save and SQLite persistence passed.

## Deliberately excluded

PayTR live credentials/webhook, SMTP delivery, maps, webmaster verification and other external service credentials are not tested here, as requested.

The XAMPP PHP 8.2 CLI has PDO SQLite enabled. The complete local PHP smoke set (32/32) and the application/Product Center runtime checks pass locally; provider credentials and live deployment remain intentionally external gates.

## Live status before deployment (read-only check: 2026-11-05)

The 2026-11-05 read-only live check responds with HTTP 200, but `product.tofidesk.com/health.json` does not expose the new `product-center` deployment marker and the live `/shop?lang=tr` response still contains the legacy “Taşımacılık sitelerini güvenle satın” transport-only copy. The live smoke check therefore fails intentionally. This is a deployment-state issue; it will clear only after the clean Product Center package is uploaded to the product host.

The Kas Nakliyat application remains a separate customer/demo package and must not be mixed into the product-center upload.
- First-party application release smoke: the CLI release path was executed against a temporary SQLite database and its signed compatibility manifest was verified.
- Admin surface contract: settings, integrations, content studio, SEO, language, theme, licence, update, module, location, security, backup and audit routes are wired into the application admin surface.
- Product Center surface contract: catalogue, commerce, licence, release, support, quality, AI and operations routes are wired and carry the Product Center deployment marker.
