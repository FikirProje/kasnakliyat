from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INDEX = (ROOT / "license-center" / "public" / "index.php").read_text(encoding="utf-8")
APP_ADMIN = (ROOT / "src" / "Admin.php").read_text(encoding="utf-8")
LICENSE_CLIENT = (ROOT / "src" / "License.php").read_text(encoding="utf-8")
UPDATE_CLIENT = (ROOT / "src" / "UpdateClient.php").read_text(encoding="utf-8")
INSTALLER = (ROOT / "license-center" / "public" / "installer.php").read_text(encoding="utf-8")
INSTALLER_FALLBACK = ROOT / "license-center" / "installer.php"
ACCOUNT_ROUTES = (ROOT / "license-center" / "public" / "account_routes.php").read_text(encoding="utf-8")
LICENSE_PLAN_ADMIN = (ROOT / "license-center" / "public" / "license_plan_admin.php").read_text(encoding="utf-8")
assert ACCOUNT_ROUTES.count("'Commerce controls'=>") >= 14 and "Product quality review" in ACCOUNT_ROUTES, "sales and licence admin locale dictionary is incomplete"
ENV = (ROOT / "license-center" / ".env.example").read_text(encoding="utf-8")

locales = ["tr", "en", "de", "fr", "es", "it", "pt", "ru", "ar", "fa", "nl", "zh", "ja", "ko"]
assert all(f"'{locale}'" in INDEX for locale in locales), "locale coverage missing"
assert "HTTP_CF_IPCOUNTRY" in INDEX and "GEOIP_COUNTRY_CODE" in INDEX and "countryMap" in INDEX, "country-based IP locale suggestion missing"
assert "HTTP_ACCEPT_LANGUAGE" in INDEX, "Accept-Language locale fallback missing"
assert "LICENSE_INSTALL_TOKEN" not in ENV, "obsolete installer token remains"
assert "X-TofiDesk-Product: product-center" in INDEX and "X-TofiDesk-Release" in INDEX, "deployment identity headers missing"
assert "if ($lcRequestPath === '/health.json')" in INDEX and "'product' => 'product-center'" in INDEX, "public health JSON probe missing"
assert "if ($lcRequestPath === '/robots.txt')" in INDEX and "if ($lcRequestPath === '/sitemap.xml')" in INDEX and "require __DIR__ . '/seo.php'" in INDEX, "built-in server SEO fallback missing"
assert "LC_ROOT . '/storage'" in INDEX, "health probe must inspect the licence-centre storage directory"
assert "Tokenless first setup" in INSTALLER, "tokenless installer copy missing"
assert "http_response_code(403)" in INSTALLER and "Keep the installer file" in INSTALLER, "installer endpoint must lock after first setup"
assert "first administrator setup" not in INSTALLER and "after first setup" not in INSTALLER, "installer placeholder English leaked into locale dictionaries"
assert INSTALLER_FALLBACK.is_file(), "license centre rewrite-independent installer missing"
assert 'onchange="if(this.value)location.href=this.value"' in INSTALLER and 'class="sr-only"' in INSTALLER, "installer locale selector missing"
assert all(("'"+locale+"'=>'") in INSTALLER for locale in locales), "installer locale names missing"
assert "buyer_session_epoch" in INDEX, "buyer session revocation missing"
assert "account_mail_i18n.php" in ACCOUNT_ROUTES and "function account_mail_text" in (ROOT / "license-center" / "public" / "account_mail_i18n.php").read_text(encoding="utf-8"), "standalone license-centre account mail templates missing"
assert "lc_send_account_email" in ACCOUNT_ROUTES, "account mail transport missing"
assert "lc_smtp_send" in ACCOUNT_ROUTES, "SMTP transport missing"
assert "function lc_account_mail_text" in ACCOUNT_ROUTES, "localized account mail templates missing"
assert "AccountMailI18n" in (ROOT / "src" / "bootstrap.php").read_text(encoding="utf-8") and (ROOT / "src" / "AccountMailI18n.php").is_file(), "shared account mail i18n module missing"
for locale in locales:
    assert f"'{locale}' => ['reset'=>" in ACCOUNT_ROUTES, f"account email templates missing for {locale}"
assert "storage/outbox" in ACCOUNT_ROUTES, "test-mode outbox missing"
assert "error_log" not in ACCOUNT_ROUTES, "account tokens must never be written to logs"
assert "buyer-auth:" in ACCOUNT_ROUTES and "rate_limit" in ACCOUNT_ROUTES, "buyer auth rate limiting missing"
assert "license_plan_domain_limit" in ACCOUNT_ROUTES and "Agency" in ACCOUNT_ROUTES and "Studio" in ACCOUNT_ROUTES, "plan domain entitlements missing"
assert "locale-select" in ACCOUNT_ROUTES and "onchange=\"if(this.value)location.href=this.value\"" in ACCOUNT_ROUTES, "modern locale selector missing"
assert "Global tema ve yazılım marketplace" in ACCOUNT_ROUTES and "Dahil edilen yazılım özellikleri" in ACCOUNT_ROUTES, "Turkish storefront copy missing"
assert "lc_cookie_consent" in ACCOUNT_ROUTES and "data-lc-cookie" in ACCOUNT_ROUTES, "sales cookie consent missing"
assert "googletagmanager" in INDEX and "lc_cookie_consent" in ACCOUNT_ROUTES, "sales analytics consent gate missing"
for metric in ["orders", "revenue", "downloads", "webhook_errors"]:
    assert metric in INDEX, f"sales dashboard metric missing: {metric}"
for feature in ["theme_versions", "changelog", "preview_url", "price_minor", "license_type", "/theme-preview", "/theme-versions"]:
    assert feature in INDEX, f"theme sales feature missing: {feature}"
for catalog_table in ["product_translations", "product_assets", "product_prices", "product_categories", "product_tag_links", "product_reviews", "product_favorites", "license_plans", "license_entitlements", "release_subscriptions"]:
    assert catalog_table in ACCOUNT_ROUTES, f"product catalogue schema missing: {catalog_table}"
assert "license_plan_translations" in ACCOUNT_ROUTES and "lc_plan_name" in ACCOUNT_ROUTES and "lc_plan_description" in ACCOUNT_ROUTES, "localized product licence plan metadata missing"
assert "update_plan" in LICENSE_PLAN_ADMIN and "Save plan and translations" in LICENSE_PLAN_ADMIN, "licence plan editing and localized updates missing"
RELEASE_ADMIN = (ROOT / "license-center" / "public" / "release_admin.php").read_text(encoding="utf-8")
assert "release_package_signature_valid" in RELEASE_ADMIN and "Manifest JSON, compatibility requirements and a valid" in RELEASE_ADMIN, "application release signature and compatibility gate missing"
assert "Product publication belongs exclusively to" in RELEASE_ADMIN and "'draft'" in RELEASE_ADMIN, "application release upload can bypass product quality publication gate"
assert "application_release_archive_safe" in RELEASE_ADMIN and "536870912" in RELEASE_ADMIN and "preg_match('#(^|/)\\.\\.(/|$)#" in RELEASE_ADMIN, "application release archive safety gate missing"
PAYTR_CHECKOUT = (ROOT / "license-center" / "public" / "paytr_checkout.php").read_text(encoding="utf-8")
assert "checkout_tax_breakdown" in PAYTR_CHECKOUT and "inclusive" in PAYTR_CHECKOUT, "inclusive/exclusive tax calculation missing"
for seo_field in ["seo_title", "seo_description", "keywords", "slug", "canonical_url", "og_image", "schema_json", "faq_schema_json", "breadcrumb_schema_json"]:
    assert seo_field in ACCOUNT_ROUTES, f"localized product SEO field missing: {seo_field}"
for commerce_table in ["billing_addresses", "tax_profiles", "coupons", "coupon_redemptions", "order_adjustments", "currency_rates", "manual_payments"]:
    assert commerce_table in ACCOUNT_ROUTES, f"commerce schema missing: {commerce_table}"
ROUTES_SOURCE = INDEX + ACCOUNT_ROUTES
assert "buyer_release_alerts_route" in INDEX + ACCOUNT_ROUTES and "/account/release-alerts" in ROUTES_SOURCE, "buyer release notification preferences missing"
for route in ["/shop", "/pricing", "/checkout", "/account/register", "/account/forgot-password", "/account/profile", "/account/security", "/account/support", "/orders", "/themes"]:
    assert route in ROUTES_SOURCE, f"route missing: {route}"
assert "/notifications" in INDEX and (ROOT / "license-center" / "public" / "notifications_admin.php").is_file(), "notifications route missing"
THEME_CHECKOUT = (ROOT / "license-center" / "public" / "theme_checkout.php").read_text(encoding="utf-8")
assert THEME_CHECKOUT.count("product?slug") >= 1 and "INSERT INTO orders" not in THEME_CHECKOUT, "legacy theme checkout must redirect into the first-class product checkout"
assert "category'=>'Website theme'" in INDEX and "Software and theme marketplace" in INDEX, "marketplace storefront must use neutral global copy and product-oriented theme schema"
for stale_copy in ["Transport business platform", "Sell transport websites with confidence.", "Themes made for transport brands"]:
    assert stale_copy not in INDEX, f"legacy transport storefront copy leaked into global marketplace source: {stale_copy}"
CATALOG_ADMIN = (ROOT / "license-center" / "public" / "catalog_admin.php").read_text(encoding="utf-8")
assert "catalog_admin_route" in CATALOG_ADMIN and "/product-edit" in CATALOG_ADMIN and "product_translations" in CATALOG_ADMIN and "14-language" in CATALOG_ADMIN, "product catalogue admin missing"
assert "Use Product quality to approve and publish" in CATALOG_ADMIN and "Create the product as a draft" in CATALOG_ADMIN, "catalog editor can bypass the product quality publication gate"
CATALOG_PUBLIC = (ROOT / "license-center" / "public" / "catalog_public.php").read_text(encoding="utf-8")
assert "catalog_public_route" in CATALOG_PUBLIC and "SoftwareApplication" in CATALOG_PUBLIC and "product_prices" in CATALOG_PUBLIC and "product_assets" in CATALOG_PUBLIC, "public product detail missing"
assert "if ($path === '/pricing')" in CATALOG_PUBLIC and "FROM license_plans lp JOIN products" in CATALOG_PUBLIC, "pricing page must consume product-owned published plans"
assert "The storefront uses the ISO code TRY" in CATALOG_PUBLIC and "if ($currency === 'TRY') $currency = 'TL'" in (ROOT / "license-center" / "public" / "paytr_checkout.php").read_text(encoding="utf-8"), "TRY/TL currency normalization missing"
assert "Marketplace search" in CATALOG_PUBLIC and "product_translations" in CATALOG_PUBLIC and "kind" in CATALOG_PUBLIC, "marketplace search missing"
assert "account/favorite" in CATALOG_PUBLIC and "account/favorites" in CATALOG_PUBLIC and "product_reviews" in CATALOG_PUBLIC, "customer discovery actions missing"
assert "seo_description" in CATALOG_PUBLIC and "og:image" in CATALOG_PUBLIC and "canonical_url" in CATALOG_PUBLIC, "product SEO head injection missing"
for locale in ["es", "it", "pt", "nl", "ru", "ar", "fa", "zh", "ja", "ko"]:
    assert "'" + locale + "'=>" in CATALOG_PUBLIC, f"catalog labels missing for {locale}"
assert "product_category_links" in CATALOG_PUBLIC and "Related products" in CATALOG_PUBLIC, "category and related-product discovery missing"
assert "faq_schema_json" in CATALOG_PUBLIC and "breadcrumb_schema_json" in CATALOG_PUBLIC, "FAQ and breadcrumb schema output missing"
assert "'@graph'=>$schemaGraph" in CATALOG_PUBLIC and "'@type'=>'Offer'" in CATALOG_PUBLIC and "'url'=>$schema['url']" in CATALOG_PUBLIC, "product detail JSON-LD graph and offer output missing"
assert "offerItems" in CATALOG_PUBLIC and "lc_plan_name" in CATALOG_PUBLIC, "product plan offers are missing from product JSON-LD"
assert "SoftwareApplication" in CATALOG_PUBLIC and "'@type'=>$schemaType" in CATALOG_PUBLIC, "software/product JSON-LD type output missing"
assert "localized_slug" in CATALOG_PUBLIC, "localized product slug lookup missing"
assert "function lc_alternate_links" in INDEX and "http_build_query($hrefQuery)" in INDEX and "product_translations" in INDEX, "product hreflang links must preserve localized slugs"
assert "license_plans WHERE product_id" in CATALOG_PUBLIC and "choose_plan" in CATALOG_PUBLIC and "catalog_plan_term" in CATALOG_PUBLIC, "published product plans are not visible and localized on the product detail page"
assert "requirements_json" in CATALOG_PUBLIC and "Compatibility and requirements" in CATALOG_PUBLIC and "min_php" in CATALOG_PUBLIC and "releaseReady" in CATALOG_PUBLIC and "preview_only" in CATALOG_PUBLIC, "published product compatibility and safe sales gate are not shown"
LICENSE_INDEX = (ROOT / "license-center" / "public" / "index.php").read_text(encoding="utf-8")
assert "legacy /themes form accepted data-only JSON" in LICENSE_INDEX and "./themes/package" in LICENSE_INDEX and "true, 307" in LICENSE_INDEX, "legacy theme upload is not routed through signed ZIP package workflow"
PAYTR_CHECKOUT = (ROOT / "license-center" / "public" / "paytr_checkout.php").read_text(encoding="utf-8")
assert "tofidesk-cms" in LICENSE_INDEX and "product_prices" in LICENSE_INDEX and "product_id" in PAYTR_CHECKOUT and "strtolower($plan)" in PAYTR_CHECKOUT and "Product release pending" in PAYTR_CHECKOUT, "checkout is not linked to a signed product release gate"
assert "setting('license_server_url','https://product.tofidesk.com')" in APP_ADMIN, "CMS licence settings must default to the Product Center, not a legacy transport URL"
assert "function license_server_url()" in LICENSE_CLIENT and "tofidesk.com/transportation" in LICENSE_CLIENT and "license_server_url()" in UPDATE_CLIENT, "existing installations must migrate licence and update traffic to the Product Center"
for asset in ["theme-urban.svg", "theme-nordic.svg", "theme-mediterranean.svg"]:
    asset_text = (ROOT / "license-center" / "public" / "assets" / asset).read_text(encoding="utf-8")
    assert not any(term in asset_text for term in ["Freight", "CITY MOVES", "MODERN MOVERS", "TRANSPORT CMS"]), f"sector-specific demo label remains in {asset}"
PRODUCT_AI = (ROOT / "license-center" / "public" / "product_ai_admin.php").read_text(encoding="utf-8")
assert "product_translation_jobs" in ACCOUNT_ROUTES and "product_translation_revisions" in ACCOUNT_ROUTES, "product translation revision schema missing"
assert "product_ai_admin_route" in PRODUCT_AI and "Generate draft translations" in PRODUCT_AI and "status=\\'draft\\'" in PRODUCT_AI and "sectorContext" in PRODUCT_AI and "taxonomy context" in PRODUCT_AI, "AI product translation workflow missing sector context"
PRODUCT_QA = (ROOT / "license-center" / "public" / "product_quality_admin.php").read_text(encoding="utf-8")
assert "product_quality_admin_route" in PRODUCT_QA and "Approve and publish" in PRODUCT_QA and "14 locale" in PRODUCT_QA and "verified signed release package" in PRODUCT_QA and "published licence plan" in PRODUCT_QA and "hash_file" in PRODUCT_QA, "product publication QA gate missing signed release or plan verification"
assert "theme_packages WHERE product_id" in ACCOUNT_ROUTES and "release_kind" in ACCOUNT_ROUTES and "release integrity check failed" in ACCOUNT_ROUTES, "licensed theme download path missing"
THEME_PACKAGE_ADMIN = (ROOT / "license-center" / "public" / "theme_package_admin.php").read_text(encoding="utf-8")
assert "theme_packages" in THEME_PACKAGE_ADMIN and "ZipArchive" in THEME_PACKAGE_ADMIN and "path traversal" in THEME_PACKAGE_ADMIN.lower() or "str_contains($name,'..')" in THEME_PACKAGE_ADMIN, "safe theme package upload missing"
assert "parent_package_id" in THEME_PACKAGE_ADMIN and "preview_video_url" in THEME_PACKAGE_ADMIN and "theme_config_json" in THEME_PACKAGE_ADMIN and "rollback_of" in THEME_PACKAGE_ADMIN, "child theme, rollback and theme configuration metadata missing"
assert "logo_url" in THEME_PACKAGE_ADMIN and "font_family" in THEME_PACKAGE_ADMIN and "primary_color" in THEME_PACKAGE_ADMIN and "accent_color" in THEME_PACKAGE_ADMIN and "sections_json" in THEME_PACKAGE_ADMIN, "structured theme editor configuration missing"
LICENSE_CLIENT = (ROOT / "src" / "License.php").read_text(encoding="utf-8")
PUBLIC_LAYOUT = (ROOT / "templates" / "public" / "layout.php").read_text(encoding="utf-8")
assert "license_theme_config" in LICENSE_CLIENT and "theme_font_family" in PUBLIC_LAYOUT and "theme_sections_json" in PUBLIC_LAYOUT, "licensed theme editor configuration is not applied locally"
assert "LICENSE_PACKAGE_SIGNING_KEY" in THEME_PACKAGE_ADMIN and "hash_hmac" in THEME_PACKAGE_ADMIN, "theme package signing gate missing"
LICENSE_PLAN_ADMIN = (ROOT / "license-center" / "public" / "license_plan_admin.php").read_text(encoding="utf-8")
assert "license_plan_admin_route" in LICENSE_PLAN_ADMIN and "create_plan" in LICENSE_PLAN_ADMIN and "license_entitlements" in LICENSE_PLAN_ADMIN, "product plan and licence entitlement admin missing"
for admin_route in ["./releases", "./releases/new", "./installations", "./update-events", "./revocations", "./review-cases", "./product-quality"]:
    assert admin_route in INDEX, f"admin navigation missing: {admin_route}"
assert "Package SHA-256 does not match" in (ROOT / "license-center" / "public" / "release_admin.php").read_text(encoding="utf-8"), "release publish validation missing"
assert "UPDATE buyers SET name=?,locale=?" in INDEX, "buyer locale preference is not persisted"
assert "WHERE o.buyer_id=?" in INDEX and "WHERE buyer_id=?" in INDEX, "buyer-owned orders/downloads must be isolated"
assert "domain_limit" in INDEX and "theme_id" in INDEX, "licence theme/domain entitlements missing"
assert "buyer_entitlements_route" in ACCOUNT_ROUTES and "/account/entitlements" in ACCOUNT_ROUTES, "buyer entitlement panel missing"
assert "buyer_updates_route" in ACCOUNT_ROUTES and "/account/updates" in ACCOUNT_ROUTES, "buyer update panel missing"
assert "buyer_download_history_route" in ACCOUNT_ROUTES and "/account/download-history" in ACCOUNT_ROUTES, "buyer download history missing"
assert "download_events" in ACCOUNT_ROUTES and "INSERT INTO download_events" in ACCOUNT_ROUTES, "download audit event recording missing"
assert "updates_until" in (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8") and "update entitlement" in (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8"), "update entitlement enforcement missing"
PAYTR_CHECKOUT = (ROOT / "license-center" / "public" / "paytr_checkout.php").read_text(encoding="utf-8")
COMMERCE_ADMIN = (ROOT / "license-center" / "public" / "commerce_admin.php").read_text(encoding="utf-8")
assert "product_id" in PAYTR_CHECKOUT and "productSlug" in PAYTR_CHECKOUT and "product_prices" in PAYTR_CHECKOUT, "product checkout is not linked to catalogue products"
assert "requestedPlan" in PAYTR_CHECKOUT and "code=? AND status='published'" in PAYTR_CHECKOUT and "productPlan" in PAYTR_CHECKOUT, "checkout does not resolve the selected product-owned plan"
assert "order_items" in INDEX and "product_id,plan_id,product_name" in PAYTR_CHECKOUT, "orders must retain product and plan line-item identity"
assert "provider) VALUES(?,?,?,?,?,?,?)" not in INDEX, "legacy text-only test checkout still creates non-product orders"
assert "downloads_product" in INDEX and "release_kind" in INDEX and "release_id" in INDEX and "product_id INTEGER REFERENCES products" in INDEX and "downloads_product_identity" in INDEX, "download records must retain product and release identity"
REFUND_ADMIN = (ROOT / "license-center" / "public" / "refund_admin.php").read_text(encoding="utf-8")
assert "Full order refund" in REFUND_ADMIN and "order_adjustments" in REFUND_ADMIN, "refund audit entry missing"
assert "license_revocations" in REFUND_ADMIN and "licence.refunded" in REFUND_ADMIN, "refund must revoke linked licences"
assert "UPDATE downloads SET url=''" in REFUND_ADMIN and "UPDATE invoices SET status='void'" in REFUND_ADMIN, "refund must close downloads and void invoice"
assert "tax_profiles" in PAYTR_CHECKOUT and "billing_addresses" in PAYTR_CHECKOUT and "coupon_redemptions" in PAYTR_CHECKOUT and "order_adjustments" in PAYTR_CHECKOUT and "checkout_currency_rate" in PAYTR_CHECKOUT and "ORDER BY observed_at DESC" in PAYTR_CHECKOUT, "checkout commerce calculations or currency history missing"
assert "/compare" in CATALOG_PUBLIC and "product_metrics" in CATALOG_PUBLIC and "popular" in CATALOG_PUBLIC and "product_tag_links" in CATALOG_PUBLIC, "product comparison, tags and popularity discovery missing"
assert "catalog_display_currency" in CATALOG_PUBLIC and "catalog_display_amount" in CATALOG_PUBLIC and "catalog_term" in CATALOG_PUBLIC, "localized storefront currency and fallback copy missing"
assert "TofiDesk Starter Theme" in (ROOT / "src" / "Database.php").read_text(encoding="utf-8"), "generic install still seeds transport-only default theme"
assert "generic_public_text" in (ROOT / "src" / "I18n.php").read_text(encoding="utf-8"), "generic marketplace public copy overlay missing"
assert "Preview video URL" in CATALOG_ADMIN and "['video','Preview video URL','video_url']" in CATALOG_ADMIN, "theme preview video asset management missing"
assert "video asset" in (ROOT / "license-center" / "public" / "product_quality_admin.php").read_text(encoding="utf-8") and "<video controls" in CATALOG_PUBLIC, "theme video quality gate or rendering missing"
assert "loading=\"lazy\"" in CATALOG_PUBLIC and "in_array((string)$asset['kind'], ['screenshot','og_image','icon']" in CATALOG_PUBLIC, "theme screenshot preview rendering missing"
ACCOUNT_ROUTES = (ROOT / "license-center" / "public" / "account_routes.php").read_text(encoding="utf-8")
assert "buyer_download_route" in ACCOUNT_ROUTES and "X-TofiDesk-SHA256" in ACCOUNT_ROUTES and "update_events" in ACCOUNT_ROUTES, "licensed product download route missing"
assert "The theme release signature could not be verified" in ACCOUNT_ROUTES and "LICENSE_PACKAGE_SIGNING_KEY" in ACCOUNT_ROUTES, "buyer theme downloads must verify package signatures"
assert "commerce_admin_route" in COMMERCE_ADMIN and "tax_profiles" in COMMERCE_ADMIN and "coupons" in COMMERCE_ADMIN and "currency_rates" in COMMERCE_ADMIN and "manual_payments" in COMMERCE_ADMIN, "commerce admin controls missing"
assert "approve_revision" in PRODUCT_AI and "status='review'" in PRODUCT_AI, "editorial translation approval queue missing"
QUALITY_ADMIN = (ROOT / "license-center" / "public" / "product_quality_admin.php").read_text(encoding="utf-8")
assert "moderate_review" in QUALITY_ADMIN and "review_status" in QUALITY_ADMIN and "Review moderation" in QUALITY_ADMIN, "product review moderation missing"
assert "Only verified buyers can review this product." in CATALOG_PUBLIC and "o.status='paid'" in CATALOG_PUBLIC, "reviews must require a paid product order"
assert "license_entitlements" in (ROOT / "license-center" / "public" / "index.php").read_text(encoding="utf-8") and "paidPlan" in (ROOT / "license-center" / "public" / "index.php").read_text(encoding="utf-8"), "paid licence plan entitlements missing from webhook"
for currency in ["TL", "USD", "EUR", "GBP", "RUB"]:
    assert currency in PAYTR_CHECKOUT, f"PayTR currency missing: {currency}"
assert "PAYTR_RATE_USD" in ENV and "PAYTR_DEFAULT_CURRENCY" in ENV, "PayTR currency configuration missing"
UPDATES = (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8")
assert "application_releases" in UPDATES and "theme_packages" in UPDATES, "application and theme release registries missing"
assert "product_kind" in UPDATES and "Release integrity check failed" in UPDATES, "product-aware signed update path missing"
assert "storage_path" in UPDATES and "packagePath" in UPDATES and "release_kind" in UPDATES and "$releaseKind" in UPDATES and "Theme release signature verification failed" in UPDATES, "theme release downloads must use the theme package path and signature"
assert "grace_until" in (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8"), "grace period missing"
assert "license_revocations" in (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8"), "revocation registry missing"
assert "Installation seat limit reached" in (ROOT / "license-center" / "public" / "updates.php").read_text(encoding="utf-8"), "installation seat policy missing"
RELEASE_ADMIN = (ROOT / "license-center" / "public" / "release_admin.php").read_text(encoding="utf-8")
assert "Software and add-on releases" in RELEASE_ADMIN and "name=\"kind\"" in RELEASE_ADMIN and "addon" in RELEASE_ADMIN, "software/add-on release publishing surface missing"
MARKETPLACE_AUDIT = (ROOT / "license-center" / "bin" / "marketplace-audit.php").read_text(encoding="utf-8")
MARKETPLACE_AUDIT_SMOKE = (ROOT / "tests" / "marketplace_audit_smoke.php").read_text(encoding="utf-8")
assert "TOFIDESK_SQLITE_REEXEC" in MARKETPLACE_AUDIT_SMOKE and "php_pdo_sqlite.dll" in MARKETPLACE_AUDIT_SMOKE, "marketplace audit fixture must bootstrap SQLite on Windows CLI"
RUNTIME_LOCALE_COVERAGE = (ROOT / "tests" / "license_center_runtime_locale_coverage.py").read_text(encoding="utf-8")
assert "socket.socket()" in RUNTIME_LOCALE_COVERAGE and "probe.bind" in RUNTIME_LOCALE_COVERAGE, "runtime locale coverage must use an isolated local port"
assert "/review-cases" in RELEASE_ADMIN and "license_review_cases" in RELEASE_ADMIN, "lawful review queue missing"
assert "/anomalies" in RELEASE_ADMIN and "Activation anomalies" in RELEASE_ADMIN and "domain_count" in RELEASE_ADMIN, "licence anomaly review dashboard missing"
assert "--json" in MARKETPLACE_AUDIT and "sale_ready" in MARKETPLACE_AUDIT and "verified signed release package" in MARKETPLACE_AUDIT and "LICENSE_PACKAGE_SIGNING_KEY" in MARKETPLACE_AUDIT, "read-only marketplace release audit missing cryptographic theme verification"
assert "releaseCompatibilityValid" in MARKETPLACE_AUDIT and "application_release_compatibility_valid" in RELEASE_ADMIN and "product_release_compatibility_valid" in PRODUCT_QA, "release compatibility matrix gate missing"
assert "status=\\'published\\'" in MARKETPLACE_AUDIT and "status='published'" in PRODUCT_QA, "licence-plan publication state is inconsistent"
assert "unsigned fixture was not blocked" in MARKETPLACE_AUDIT_SMOKE and "complete product" in MARKETPLACE_AUDIT_SMOKE, "marketplace audit integration fixture missing"
assert "license-centre runtime coverage" in RUNTIME_LOCALE_COVERAGE and "routes" in RUNTIME_LOCALE_COVERAGE, "license-centre runtime locale coverage missing"
assert "installation.status" in RELEASE_ADMIN and "status=? WHERE id=?" in RELEASE_ADMIN, "installation status management and audit missing"
assert "name=\"status\"" in RELEASE_ADMIN and "require_csrf()" in RELEASE_ADMIN, "installation action must be CSRF protected"
UPDATE_CLIENT = (ROOT / "src" / "UpdateClient.php").read_text(encoding="utf-8")
assert "update-check.json" in UPDATE_CLIENT and "degraded" in (ROOT / "src" / "License.php").read_text(encoding="utf-8"), "client-side grace cache missing"
assert "create_backup()" in UPDATE_CLIENT and "maintenance.flag" in UPDATE_CLIENT, "safe update backup/maintenance flow missing"
assert "function seed_marketplace_demo_product" in INDEX and "setup();seed_marketplace_demo_product()" in INDEX, "catalog seed must run before public catalogue routing"
assert "function reconcile_order_product_links" in INDEX and "UPDATE orders SET product_id" in INDEX, "legacy orders must be reconciled to first-class product records"
PAYMENT_WEBHOOK = (ROOT / "license-center" / "public" / "payment_webhook_product.php").read_text(encoding="utf-8")
assert "payment_webhook_product" in INDEX and "fulfill_paid_order($order, $provider, $eventKey)" in PAYMENT_WEBHOOK and "beginTransaction" in PAYMENT_WEBHOOK and "rollBack" in PAYMENT_WEBHOOK, "payment callbacks must use transactional product-aware fulfillment"
assert "marketplace_seed_version" in INDEX and "if (($seed['value'] ?? '') === '4') return;" in INDEX, "marketplace demo seed must be versioned and idempotent"
assert "if(session_status()!==PHP_SESSION_ACTIVE)" in INDEX, "licence centre must not start an already active session twice"
assert "INSERT INTO product_translations" in INDEX and INDEX.count("'tofidesk-cms'") >= 2, "generic marketplace product translations are not seeded"
for locale in locales:
    assert "'" + locale + "'=>['TofiDesk CMS'" in INDEX, f"seed product translation missing for {locale}"
assert "nexa-business-theme" in INDEX and "'theme'" in INDEX, "clean marketplace install must seed the industry-neutral demo theme product"
assert "product_tag_links" in INDEX and "responsive" in INDEX and "business-software" in INDEX, "demo products must seed discoverable categories and tags"
assert "$faq = json_encode" in INDEX and "$breadcrumb = json_encode" in INDEX, "theme seed must include FAQ and breadcrumb schema"
assert "Live demo" in INDEX and "Documentation" in INDEX and "product_assets" in INDEX, "theme seed must include demo, documentation and preview assets"
for locale in locales:
    assert "'" + locale + "'=>[" in INDEX, f"theme seed translation missing for {locale}"

print("PASS license-center tokenless installer, locale, buyer security and route coverage")
