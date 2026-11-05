# TofiDesk global marketplace acceptance matrix

This is the release evidence map for the active global marketplace goal. It
keeps source evidence, archive evidence and live-deployment evidence separate.
Passing a local test does not prove that the same build is serving traffic.

| Requirement | Source evidence | Automated evidence | Current state |
|---|---|---|---|
| 14-language admin, installer and account mail | `src/AdminI18n.php`, `src/InstallerI18n.php`, `license-center/public/admin_i18n.php`, `src/AccountMailI18n.php` | `admin_locale_coverage.php`, `installer_locale_coverage.php`, `account_mail_locale_coverage.php`, 14×32 runtime route coverage | Implemented in source; live deployment pending |
| Country/browser/explicit locale preference | `src/I18n.php` country and browser fallback | `tests/locale_country_preference_smoke.php` | Implemented in source; live deployment pending |
| Product-first catalogue | `account_routes.php` product, plan, release, asset, price and SEO tables; `catalog_admin.php`; `catalog_public.php` | `product_commerce_schema_smoke.php`, `marketplace_audit_smoke.php`, `license_center_smoke.py` | Implemented in source |
| Product/plan-centric manual licensing | `manual_license_admin.php` product and plan selector/validation | `manual_license_product_smoke.php` | Implemented in source |
| Kas Nakliyat separated from global core | Product Center neutral seed (`nexa-business-theme`), `bin/package.py` neutral profile and `tests/release_audit.py` scrub rules | `global_profile_smoke.php`, `release_audit.py` | Implemented in generic archive; live package replacement pending |
| Codecanyon-level theme/plugin delivery | `theme_package_admin.php`, `release_admin.php`, `theme_checkout.php`, `updates.php` | theme/add-on package and release checks in `license_center_smoke.py`, `marketplace_audit_smoke.php` | Implemented in source; real signed releases still need publication |
| Per-product 14-language SEO/schema | `catalog_admin.php`, `catalog_public.php`, `product_quality_admin.php` | marketplace audit requires localized SEO and schema fields | Implemented in source; editorial review pending |
| Global commerce | `commerce_admin.php`, `commerce_calculation_smoke.php`, `commerce_state_smoke.php`, `refund_admin.php` | tax, currency-rate, discount and order-state tests | Implemented in source; provider credentials deferred |
| Sector-neutral AI workflow | `product_ai_admin.php` and product context fields | product AI/editorial workflow checks | Implemented in source; human editorial approval pending |
| Search and discovery | `catalog_public.php` search, category, tag, compare, favorite, related, popular and reviews routes | catalogue smoke coverage | Implemented in source |
| Map provider abstraction | `platform_map_providers()` and `platform_map_url()` support OpenStreetMap, Google Maps and Bing Maps without shipping secrets | `platform_core_smoke.php` map URL contract | Implemented in source |
| Universal SDK developer flow | `bin/module.php scaffold`, `bin/plugin.php`, `bin/theme.php scaffold`, `docs/PLUGIN_SDK.md`, dependency-aware manifests and quality audit | `platform_core_smoke.php`, `platform_audit_smoke.py`, goal acceptance contract | Implemented in source |
| Data-driven admin permissions | `platform_roles`, `platform_permissions`, role mapping and module permission catalog | `platform_core_smoke.php`, `smoke.py` role authorization | Implemented in source |
| Sector content administration | `src/Admin.php` `/admin/content-models`, manifest-driven field editor, locale/location/status capture | `platform_core_smoke.php`, `admin_locale_coverage.php`, platform audit | Implemented in source |
| Three reference sectors | `modules/transport`, `modules/real-estate`, `modules/restaurant` with data-driven fields, settings and 14-locale manifests | `sector_examples_smoke.php` | Implemented in source |
| Publication QA | `product_quality_admin.php`, `marketplace-audit.php` | complete-product acceptance and incomplete-release rejection | Implemented in source |
| Goal traceability | `tests/goal_acceptance_contract.py`, `bin/platform-audit.php` | 14 locales, 11 sector manifests, core/API/SDK/commerce/deployment contract | Passing locally |
| Security/anomaly operations | `src/Admin.php` `/admin/anomalies`, login/job/mail/audit aggregation | `admin_surface_contract.py`, PHP lint, platform audit | Implemented in source; live telemetry pending |

## External gates that remain deliberately open

These are deployment or business-state checks, not reasons to weaken the source
requirements:

1. Upload the current `product-tofidesk-license-center.zip` to the
   `product.tofidesk.com` document root and remove the old mixed deployment.
2. Publish real signed application/theme releases and their 14-language product
   records through Product QA.
3. Configure SMTP, backups/worker and PayTR secrets only in the server
   environment, then run the real purchase-to-update chain.
4. Perform native-language and legal copy review before paid public launch.

The current live smoke test intentionally fails if the old transport-specific
marketplace copy is still served. That failure is evidence of a stale live
deployment, not evidence that the global source package should be regressed.

## Final local artifacts

Generated after the source and archive checks on 2026-11-05:

| Artifact | SHA-256 |
|---|---|
| `product-tofidesk-license-center.zip` | `DBCA41AFF4B507BAEADF4A076BFEC94F27288F554D78A1A183B32C1D398A6561` |
| `product-tofidesk-app-deployment.zip` | `A9B6A1474C93DB4614936FC5D013A5B2F0D6E68C5A9D4569E307FB11F2B75447` |
| `product-tofidesk-marketplace.zip` | `F0C7093F5ED11A22604B5E77E69AA88561DD5AD277927DF00F5B0DDF2EDF75D7` |
