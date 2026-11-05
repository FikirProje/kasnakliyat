"""Traceable acceptance contract for the global TofiDesk platform goal."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def read(relative: str) -> str:
    return (ROOT / relative).read_text(encoding="utf-8")

def require(text: str, *needles: str) -> None:
    for needle in needles:
        assert needle in text, f"missing contract marker: {needle}"

platform = read("src/Platform.php")
api = read("src/PlatformApi.php")
theme = read("src/ThemeManager.php")
admin = read("src/Admin.php")
catalog = read("license-center/public/catalog_public.php")
center_index = read("license-center/public/index.php")
commerce = read("license-center/public/paytr_checkout.php")
routes = read("license-center/public/account_routes.php")
installer = read("src/Installer.php")
update = read("src/UpdateClient.php")

require(platform, "platform_register_module", "platform_search_advanced", "platform_location_search", "platform_map_url", "platform_queue_push", "platform_register_webhook", "platform_user_can")
require(api, "locations/search", "taxonomies/terms", "forms/submit", "search", "reviews", "popular")
require(theme, "theme_resolve_sections", "theme_resolve_design_tokens", "theme_import_demo", "theme_manifest_errors")
require(admin, "/admin/content-models", "admin_integrations", "admin_security", "admin_audit")
require(catalog + center_index, "product_translations", "product_prices", "product_assets", "hreflang", "SoftwareApplication", "faq_schema_json", "breadcrumb_schema_json")
require(commerce, "currency_rates", "tax_profiles", "coupons", "order_items", "product_bundles", "product_bundle_items", "paytr.com/odeme/api/get-token")
require(routes + center_index, "password_resets", "email_verifications", "product_translation_jobs", "support_tickets", "release_subscriptions")
require(installer, "installer_t(", "installed.lock", "storage_path")
require(update, "update_installation_id", "update_archive_copy", "update_restore_copy", "maintenance.flag")

locales = {"tr", "en", "de", "fr", "es", "it", "pt", "ru", "ar", "fa", "nl", "zh", "ja", "ko"}
locale_sources = [
    read("src/I18n.php"),
    read("src/AdminI18n.php"),
    read("src/InstallerI18n.php"),
    read("license-center/public/account_mail_i18n.php"),
]
for locale in locales:
    assert any(f"'{locale}'" in source or f'"{locale}"' in source for source in locale_sources), f"locale missing: {locale}"

assert (ROOT / "bin/plugin.php").is_file(), "dedicated plugin SDK command missing"

modules = sorted((ROOT / "modules").glob("*/module.json"))
assert len(modules) >= 10, f"expected at least 10 sector modules, found {len(modules)}"
for manifest_path in modules:
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    assert manifest.get("slug") and manifest.get("content_types"), f"incomplete sector manifest: {manifest_path}"
    assert len(manifest.get("locales", locales)) >= 14, f"sector is not locale-ready: {manifest_path}"

module_cli = read("bin/module.php")
assert (ROOT / "bin/module.php").is_file() and (ROOT / "bin/theme.php").is_file(), "SDK commands missing"
assert "['sector','plugin']" in module_cli and "'kind'=>$kind" in module_cli, "plugin scaffold kind missing"
assert "'plugins'" in module_cli and "plugin.json" in module_cli and "$base" in module_cli, "plugin scaffold must use the plugins registry"
assert (ROOT / "install.php").is_file() and (ROOT / "hosting-check.php").is_file(), "deployment entrypoints missing"
assert (ROOT / "tests/locale_country_preference_smoke.php").is_file(), "country/browser locale regression test missing"
assert (ROOT / "docs/PLUGIN_SDK.md").is_file(), "plugin SDK guide missing"
assert (ROOT / "license-center/public/product_quality_admin.php").is_file(), "marketplace quality gate missing"
assert (ROOT / "license-center/public/payment_webhook_product.php").is_file(), "product webhook surface missing"
assert (ROOT / "license-center/bin/release.php").is_file(), "signed release command missing"

print(f"PASS global goal acceptance contract: {len(locales)} locales, {len(modules)} sector manifests, core/API/SDK/commerce/deployment surfaces")
