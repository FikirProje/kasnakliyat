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
assert "buyer_session_epoch" in INDEX, "buyer session revocation missing"
assert "lc_send_account_email" in ACCOUNT_ROUTES, "account mail transport missing"
assert "lc_smtp_send" in ACCOUNT_ROUTES, "SMTP transport missing"
assert "storage/outbox" in ACCOUNT_ROUTES, "test-mode outbox missing"
for feature in ["theme_versions", "changelog", "preview_url", "price_minor", "license_type", "/theme-preview", "/theme-versions"]:
    assert feature in INDEX, f"theme sales feature missing: {feature}"
for route in ["/shop", "/pricing", "/account/register", "/account/forgot-password", "/orders", "/themes"]:
    assert f"$path==='{route}'" in INDEX, f"route missing: {route}"

print("PASS license-center tokenless installer, locale, buyer security and route coverage")
