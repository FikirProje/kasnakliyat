from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
api = (ROOT / "src" / "PlatformApi.php").read_text(encoding="utf-8")
bootstrap = (ROOT / "src" / "bootstrap.php").read_text(encoding="utf-8")
public = (ROOT / "public" / "index.php").read_text(encoding="utf-8")
for resource in ("modules", "themes", "themes/detail", "locations", "locations/search", "taxonomies", "taxonomies/terms", "entries", "schema", "menus", "forms", "search", "related", "reviews", "popular"):
    assert resource in api, f"missing API resource: {resource}"
assert "/api/platform/v1" in public and "PlatformApi" in bootstrap
assert "method_not_allowed" in api and "write_api_not_configured" in api
assert "PLATFORM_API_TOKEN" in api and "forms/submit" in api and "invalid_api_token" in api
assert "X-TofiDesk-API-Version" in api
assert "platform_api_theme_public" in api and "theme_resolve_design_tokens" in api
print("PASS versioned platform API contract")
