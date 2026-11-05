"""Keep the Product Center package layout safe for direct and rewritten URLs."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CENTER = ROOT / "license-center"

for required in ("index.php", "installer.php", ".htaccess", "public/index.php", "public/installer.php", "public/account_routes.php"):
    assert (CENTER / required).is_file(), f"Product Center entrypoint missing: {required}"

htaccess = (CENTER / ".htaccess").read_text(encoding="utf-8")
assert "RewriteRule ^install$ public/installer.php" in htaccess, "clean /install rewrite missing"
assert "RewriteRule ^ index.php" in htaccess, "front-controller rewrite missing"

root_index = (CENTER / "index.php").read_text(encoding="utf-8")
assert "require __DIR__ . '/public/index.php'" in root_index, "root index does not delegate to Product Center public entrypoint"
assert "function lc_root_preflight" in root_index and "pdo_sqlite" in root_index, "root entrypoint lacks friendly extension preflight"

root_installer = (CENTER / "installer.php").read_text(encoding="utf-8")
assert "require __DIR__ . '/public/installer.php'" in root_installer, "root installer does not delegate to Product Center installer"
assert "function lc_installer_preflight" in root_installer and "pdo_sqlite" in root_installer, "direct installer lacks friendly extension preflight"

for path in (CENTER / "index.php", CENTER / "installer.php", CENTER / "public/index.php", CENTER / "public/installer.php"):
    source = path.read_text(encoding="utf-8")
    assert "src/bootstrap.php" not in source, f"legacy application bootstrap leaked into {path.name}"

print("PASS Product Center root/public entrypoints, /install rewrite and bootstrap separation")
