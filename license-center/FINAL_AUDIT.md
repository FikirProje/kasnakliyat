# TofiDesk final audit matrix

This is the pre-release evidence map. A release is not considered ready until the listed checks pass in the target PHP-FPM environment.

Latest local verification: license-center smoke, generic marketplace 14-locale demo
coverage, release archive audit, signed
update policy, 14-locale admin coverage, 195 country profiles, 31 integration
assertions, product SEO-head checks, locale-specific catalogue labels, product
order line-item identity and public PHP syntax all passed using the XAMPP PHP
runtime. The standalone tokenless installer now has a complete 14-locale
dictionary with no placeholder English. PayTR currency selection now preserves
the selected storefront currency when `PAYTR_ALLOW_CURRENCY_SELECTION=1`.
Refund handling now records an auditable negative adjustment, voids the invoice,
revokes order-linked licences and disables their download URL transactionally.
The license-centre runtime matrix now covers 30 routes × 14 locales, including
critical admin/buyer labels and the cookie-consent banner; published licence
plans are consistently required by both the publication QA gate and the final
marketplace audit.
The license-centre installer remains in the release for update compatibility but
returns HTTP 403 once an administrator exists; additional settings/SEO/publishing
admin copy is covered by the deep 14-locale admin translation sample.
The
XAMPP installation emits unrelated duplicate-module startup warnings; the
target Plesk PHP-FPM environment must still be checked without those warnings.

| Requirement | Evidence in source | Verification |
|---|---|---|
| Customer registration, login, verification and reset | `public/index.php`, `public/account_routes.php` | `tests/license_center_smoke.py` |
| Buyer and sales-center language coverage | `public/account_routes.php` account, sales and common dictionaries | PHP syntax + license-center route smoke; 14-locale admin/account/sales translation maps |
| Customer-owned orders, invoices, licences and downloads | buyer-scoped queries in `public/index.php` | smoke route and ownership assertions |
| Product-linked order line items | `public/index.php`, `public/paytr_checkout.php` | `order_items` schema and checkout insertion assertion |
| Refund, revocation and download shutdown | `public/refund_admin.php` | idempotent refund adjustment, invoice void, licence revocation and download shutdown assertions |
| Theme catalogue, versioning, preview and checkout | `public/index.php`, `public/theme_checkout.php` | syntax + license-center smoke |
| Product plans and explicit licence types | `public/account_routes.php`, `public/license_plan_admin.php` | published plan schema, single/multi/unlimited/subscription/lifetime types, buyer entitlements |
| Theme media, editor defaults and publication gate | `public/catalog_admin.php`, `public/theme_package_admin.php`, `public/product_quality_admin.php`, `public/catalog_public.php`, `src/License.php`, `templates/public/layout.php` | demo, documentation, screenshot and preview-video assets; theme video required before approval; signed logo/font/colour/section defaults are applied locally; theme package releases support parent and rollback metadata |
| Product/release publishing | `public/release_admin.php`, `bin/release.php` | SHA-256/signature validation and syntax checks |
| Signed update check and download | `public/updates.php`, `src/UpdateClient.php` | `tests/update_security_smoke.php` |
| Backup, maintenance mode, migrations and rollback | `src/UpdateClient.php`, `src/Integrations.php`, `bin/restore.py` | installer/restore/integration tests |
| Domain, installation and seat policy | `public/index.php`, `public/updates.php` | licence smoke and update security tests |
| Cache, grace period, rate limit and revocation | `src/License.php`, `public/updates.php` | update security test |
| Review, notification and support audit trail | `public/notifications_admin.php`, `public/release_admin.php` | route/syntax smoke |
| Inbound support mail threads and private attachments | `src/InboundMail.php`, migration 15 | bounded MIME import, `In-Reply-To`/`References` thread keys, retention cleanup; live mailbox acceptance still required |
| 14-language SEO, hreflang, canonical, OG and JSON-LD | `public/index.php`, `public/account_routes.php`, `public/catalog_public.php` | locale smoke, product SEO-head assertion and rendered target-host check |
| cPanel/Plesk low-privilege deployment | `DEPLOYMENT_MAP.md`, tokenless installer and `.htaccess` | target PHP-FPM preflight |
| Package secret/runtime exclusion | `bin/package.py`, release audit | `tests/release_audit.py` |
| Industry-neutral marketplace starter | `src/bootstrap.php`, `src/Database.php`, `src/I18n.php`, `bin/package.py` | customer config is excluded from marketplace archives; generic profile, starter theme and public-copy overlay are used until the buyer configures a sector |
| Live HTTPS storefront/demo availability | `tests/live_http_smoke.ps1` | product home/login/shop/pricing and demo URL HTTP 200 smoke |

The final distribution archives must be generated once, after the target hosting preflight and all required tests pass. Secrets, customer databases, sessions, logs and development backups must never be included.
