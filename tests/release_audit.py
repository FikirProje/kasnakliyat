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
PUBLIC_SITE = (ROOT / "src" / "PublicSite.php").read_text(encoding="utf-8")
assert "kullanim-sartlari" in PUBLIC_SITE and "destek-sla" in PUBLIC_SITE, "legal routes missing"
ADMIN = (ROOT / "src" / "Admin.php").read_text(encoding="utf-8")
assert "admin_legal_page" in ADMIN and "legal_terms_" in ADMIN and "legal_sla_" in ADMIN, "admin legal editor missing"
INBOUND = (ROOT / "src" / "InboundMail.php").read_text(encoding="utf-8")
WORKER = (ROOT / "bin" / "worker.php").read_text(encoding="utf-8")
assert "purge_inbound_mail" in INBOUND and "IMAP_RETENTION_DAYS" in INBOUND and "purge_inbound_mail" in WORKER, "IMAP retention policy missing"

assert ARCHIVE.is_file(), f"missing archive: {ARCHIVE}"
with zipfile.ZipFile(ARCHIVE) as archive:
    assert archive.testzip() is None, "archive CRC check failed"
    names = archive.namelist()
    assert len(names) == len(set(names)), "duplicate archive paths"
    assert all(not name.startswith(".git/") for name in names)
    assert all(not name.endswith((".sqlite", ".sqlite-wal", ".sqlite-shm")) for name in names)
    assert all("storage/sessions/" not in name and "storage/backups/" not in name for name in names)
    assert ".env" not in names

    text_extensions = {".php", ".md", ".txt", ".py", ".js", ".css", ".svg", ".json"}
    forbidden = ("Kaş Nakliyat", "kasnakliyat.com", "kasnakliyat.local", "Bayındır Mh.", "505 219", "bilgi@kas")
    for name in names:
        if Path(name).suffix.lower() in text_extensions:
            text = archive.read(name).decode("utf-8", "ignore")
            assert not any(value in text for value in forbidden), f"customer identifier remains in {name}"

print("PASS generic marketplace archive integrity, secret/runtime exclusion and customer-data scrub")
