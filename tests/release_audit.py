"""Static release-gate checks for the generic marketplace archive."""
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = Path(__file__).resolve().parents[3] / "outputs" / "product-tofidesk-marketplace.zip"
BOOTSTRAP = (ROOT / "src" / "bootstrap.php").read_text(encoding="utf-8")
PUBLIC_LAYOUT = (ROOT / "templates" / "public" / "layout.php").read_text(encoding="utf-8")
assert "style-src 'self' 'nonce-" in BOOTSTRAP and "unsafe-inline" not in BOOTSTRAP, "CSP policy is unsafe or missing nonce"
assert "csp_nonce()" in PUBLIC_LAYOUT, "custom theme CSS is not nonce-bound"
assert "social-links" in PUBLIC_LAYOUT and "noopener noreferrer" in PUBLIC_LAYOUT, "social profile links are not accessible and safe"
assert "setting('business_name'" in PUBLIC_LAYOUT, "public brand is not settings-driven"
assert "tofidesk_cookie_consent" in PUBLIC_LAYOUT and "data-cookie-accept" in (ROOT / "public" / "assets" / "site.js").read_text(encoding="utf-8"), "cookie consent flow missing"
assert "data-cookie-necessary" in PUBLIC_LAYOUT and "essential" in (ROOT / "public" / "assets" / "site.js").read_text(encoding="utf-8"), "necessary-only cookie choice missing"
assert "analyticsId" in PUBLIC_LAYOUT and "cookie_consent" in PUBLIC_LAYOUT, "analytics is not consent-gated"
PUBLIC_SITE = (ROOT / "src" / "PublicSite.php").read_text(encoding="utf-8")
assert "kullanim-sartlari" in PUBLIC_SITE and "destek-sla" in PUBLIC_SITE, "legal routes missing"
ADMIN = (ROOT / "src" / "Admin.php").read_text(encoding="utf-8")
assert "admin_legal_page" in ADMIN and "legal_terms_" in ADMIN and "legal_sla_" in ADMIN, "admin legal editor missing"
INBOUND = (ROOT / "src" / "InboundMail.php").read_text(encoding="utf-8")
WORKER = (ROOT / "bin" / "worker.php").read_text(encoding="utf-8")
assert "purge_inbound_mail" in INBOUND and "IMAP_RETENTION_DAYS" in INBOUND and "purge_inbound_mail" in WORKER, "IMAP retention policy missing"
ROOT_INDEX = (ROOT / "index.php").read_text(encoding="utf-8")
ROOT_INSTALL = (ROOT / "install.php").read_text(encoding="utf-8")
assert "PHP_VERSION_ID < 80200" in ROOT_INDEX and "PHP_VERSION_ID < 80200" in ROOT_INSTALL, "legacy PHP handler would fail before showing requirements"
assert "require __DIR__ . '/public/index.php';" in ROOT_INDEX and "$_SERVER['REQUEST_URI'] = '/install';" in ROOT_INSTALL, "root entrypoint fallback missing"

assert ARCHIVE.is_file(), f"missing archive: {ARCHIVE}"
with zipfile.ZipFile(ARCHIVE) as archive:
    assert archive.testzip() is None, "archive CRC check failed"
    names = archive.namelist()
    assert len(names) == len(set(names)), "duplicate archive paths"
    assert all(not name.startswith(".git/") for name in names)
    assert all(not name.endswith((".sqlite", ".sqlite-wal", ".sqlite-shm")) for name in names)
    assert all("storage/sessions/" not in name and "storage/backups/" not in name for name in names)
    assert ".env" not in names
    assert "index.php" in names and "install.php" in names, "httpdocs root entrypoints missing"
    packaged_index = archive.read("index.php").decode("utf-8", "ignore")
    packaged_install = archive.read("install.php").decode("utf-8", "ignore")
    assert "PHP_VERSION_ID < 80200" in packaged_index and "PHP_VERSION_ID < 80200" in packaged_install, "legacy PHP guard missing from archive"

    text_extensions = {".php", ".md", ".txt", ".py", ".js", ".css", ".svg", ".json"}
    forbidden = ("Kaş Nakliyat", "kasnakliyat.com", "kasnakliyat.local", "Bayındır Mh.", "505 219", "bilgi@kas")
    for name in names:
        if Path(name).suffix.lower() in text_extensions:
            text = archive.read(name).decode("utf-8", "ignore")
            assert not any(value in text for value in forbidden), f"customer identifier remains in {name}"

print("PASS generic marketplace archive integrity, secret/runtime exclusion and customer-data scrub")
