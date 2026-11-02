from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INDEX = (ROOT / "license-center" / "public" / "index.php").read_text(encoding="utf-8")
INSTALLER = (ROOT / "license-center" / "public" / "installer.php").read_text(encoding="utf-8")
ACCOUNT_ROUTES = (ROOT / "license-center" / "public" / "account_routes.php").read_text(encoding="utf-8")
ENV = (ROOT / "license-center" / ".env.example").read_text(encoding="utf-8")

locales = ["tr", "en", "de", "fr", "es", "it", "pt", "ru", "ar", "fa", "nl", "zh", "ja", "ko"]
assert all(f"'{locale}'" in INDEX for locale in locales), "locale coverage missing"
assert "LICENSE_INSTALL_TOKEN" not in ENV, "obsolete installer token remains"
assert "Tokenless first setup" in INSTALLER, "tokenless installer copy missing"
assert 'onchange="if(this.value)location.href=this.value"' in INSTALLER and 'class="sr-only"' in INSTALLER, "installer locale selector missing"
assert all(("'"+locale+"'=>'") in INSTALLER for locale in locales), "installer locale names missing"
assert "buyer_session_epoch" in INDEX, "buyer session revocation missing"
assert "lc_send_account_email" in ACCOUNT_ROUTES, "account mail transport missing"
assert "lc_smtp_send" in ACCOUNT_ROUTES, "SMTP transport missing"
assert "storage/outbox" in ACCOUNT_ROUTES, "test-mode outbox missing"
assert "error_log" not in ACCOUNT_ROUTES, "account tokens must never be written to logs"
assert "buyer-auth:" in ACCOUNT_ROUTES and "rate_limit" in ACCOUNT_ROUTES, "buyer auth rate limiting missing"
assert "license_plan_domain_limit" in ACCOUNT_ROUTES and "Agency" in ACCOUNT_ROUTES and "Studio" in ACCOUNT_ROUTES, "plan domain entitlements missing"
assert "locale-select" in ACCOUNT_ROUTES and "onchange=\"if(this.value)location.href=this.value\"" in ACCOUNT_ROUTES, "modern locale selector missing"
assert "Taşımacılık işletme platformu" in ACCOUNT_ROUTES and "Dahil edilen yazılım özellikleri" in ACCOUNT_ROUTES, "Turkish storefront copy missing"
assert "lc_cookie_consent" in ACCOUNT_ROUTES and "data-lc-cookie" in ACCOUNT_ROUTES, "sales cookie consent missing"
assert "googletagmanager" in INDEX and "lc_cookie_consent" in ACCOUNT_ROUTES, "sales analytics consent gate missing"
for metric in ["orders", "revenue", "downloads", "webhook_errors"]:
    assert metric in INDEX, f"sales dashboard metric missing: {metric}"
for feature in ["theme_versions", "changelog", "preview_url", "price_minor", "license_type", "/theme-preview", "/theme-versions"]:
    assert feature in INDEX, f"theme sales feature missing: {feature}"
for route in ["/shop", "/pricing", "/account/register", "/account/forgot-password", "/account/profile", "/account/security", "/account/support", "/orders", "/themes"]:
    assert f"$path==='{route}'" in INDEX, f"route missing: {route}"
assert "UPDATE buyers SET name=?,locale=?" in INDEX, "buyer locale preference is not persisted"

print("PASS license-center tokenless installer, locale, buyer security and route coverage")
