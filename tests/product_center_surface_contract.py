"""Contract checks for the Product Center sales and licence administration surface."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
public = ROOT / "license-center" / "public"
index = (public / "index.php").read_text(encoding="utf-8")
account_routes = (public / "account_routes.php").read_text(encoding="utf-8")
routes = {
    "catalog_public.php": ["catalog_public_route", "product_translations", "hreflang"],
    "catalog_admin.php": ["/product-edit", "/product-bundles", "product_bundles", "product_bundle_items", "product_quality"],
    "commerce_admin.php": ["orders", "license_plans", "refund"],
    "order_fulfillment.php": ["fulfillment_release", "release_kind"],
    "license_requests.php": ["license_requests", "domain"],
    "release_admin.php": ["/releases", "application_releases"],
    "updates.php": ["application_releases", "theme_packages", "update_events"],
    "support_admin.php": ["support"],
    "product_quality_admin.php": ["product_quality", "'ready'", "Approve and publish", "product_bundles", "product_bundle_items", "bundle components", "bundle component licence"],
    "product_ai_admin.php": ["product_translation_jobs", "draft", "editorial"],
}
for filename, needles in routes.items():
    text = index + ((public / filename).read_text(encoding="utf-8") if (public / filename).is_file() else "")
    for needle in needles:
        assert needle in text, f"Product Center surface missing {needle}: {filename}"
for needle in ["/themes", "/licenses", "/orders", "/support", "/health", "/settings", "/releases", "/product-quality", "/product-ai"]:
    assert needle in index, f"Product Center navigation/route missing: {needle}"
assert "X-TofiDesk-Product: product-center" in index, "Product Center deployment identity marker missing"
assert "product_bundles" in account_routes and "bundle_component_" in (public / "order_fulfillment.php").read_text(encoding="utf-8"), "bundle fulfilment entitlement surface missing"
print("PASS Product Center surface contract: catalogue, commerce, licence, release, support, QA and operations routes")
