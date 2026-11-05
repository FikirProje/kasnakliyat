"""Verify that customer application and Product Center archives cannot be mixed."""
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
OUTPUTS = ROOT / "outputs"

def names(filename: str) -> set[str]:
    path = OUTPUTS / filename
    assert path.is_file(), f"missing archive: {path}"
    with ZipFile(path) as archive:
        assert archive.testzip() is None, f"CRC failure: {filename}"
        listed = archive.namelist()
        result = set(listed)
        assert len(result) == len(listed), f"duplicate paths: {filename}"
        assert listed == sorted(listed), f"archive order is not deterministic: {filename}"
        assert all(item.date_time == (2020, 1, 1, 0, 0, 0) for item in archive.infolist()), f"variable timestamps: {filename}"
        return result

app = names("product-tofidesk-app-deployment.zip")
marketplace = names("product-tofidesk-marketplace.zip")
center = names("product-tofidesk-license-center.zip")

for label, archive in (("application", app), ("marketplace", marketplace)):
    assert {"index.php", "install.php", ".htaccess", "public/index.php"} <= archive, f"{label} httpdocs entrypoints missing"
    assert "src/bootstrap.php" in archive and "config/site.php" in archive, f"{label} application runtime missing"
    assert "license-center/public/index.php" not in archive and "installer.php" not in archive, f"{label} contains Product Center files"

assert {"index.php", "installer.php", ".htaccess", "public/index.php", "public/installer.php"} <= center, "Product Center httpdocs entrypoints missing"
assert "license-center/public/index.php" not in center and "src/bootstrap.php" not in center, "Product Center contains customer app files"
checksums = (OUTPUTS / "SHA256SUMS.txt").read_text(encoding="utf-8")
assert "product-tofidesk-license-center.zip" in checksums and "product-tofidesk-marketplace.zip" in checksums

print("PASS deployment archive separation: application, marketplace and Product Center roots are distinct")
