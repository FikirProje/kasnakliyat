"""Contract checks for the full application administration surface."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
admin = (ROOT / "src" / "Admin.php").read_text(encoding="utf-8")
required = [
    "settings", "email-templates", "notification-rules", "integrations",
    "security", "anomalies", "users", "audit", "backup", "mail", "languages", "themes",
    "licenses", "updates", "modules", "plugins", "menus", "forms", "locations", "taxonomies", "legal",
    "seo", "jobs", "content-models",
]
for section in required:
    assert f"'{section}'" in admin, f"admin section missing: {section}"
assert "admin_integrations_page()" in admin, "integration dashboard missing"
assert "admin_settings_page()" in admin, "settings dashboard missing"
assert "admin_ai" in admin or "ai_settings" in admin, "AI settings route missing"
assert "map_settings" in admin, "map settings route missing"
assert "admin_anomalies_page()" in admin and "failedLogins" in admin and "securityAudit" in admin, "security anomaly dashboard missing"
assert "admin_modules_page('sector')" in admin and "admin_modules_page('plugin')" in admin, "module/plugin roots are not separated"
assert "ROOT.'/plugins'" in admin and "ROOT.'/modules'" in admin, "module/plugin manifest roots missing"
assert "admin_menus_page()" in admin and "admin_forms_page()" in admin, "menus/forms admin screens missing"
assert "menu_save" in admin and "form_save" in admin, "menus/forms write actions missing"
assert "overflow-y: auto" in (ROOT / "public" / "assets" / "admin-overrides.css").read_text(encoding="utf-8")
print("PASS admin surface contract: settings, integrations, content, SEO, locale, theme, licence and operations routes")
