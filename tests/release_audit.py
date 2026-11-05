"""Static release-gate checks for the generic marketplace archive."""
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / "outputs" / "product-tofidesk-marketplace.zip"
if not ARCHIVE.is_file():
    # Backwards-compatible path for the historical nested workspace layout.
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
MANIFEST = (ROOT / "public" / "manifest.php").read_text(encoding="utf-8")
assert "setting('business_name', 'TofiDesk')" in MANIFEST, "generic PWA fallback still exposes a customer brand"
assert "Professional multilingual software and website platform." in MANIFEST, "generic PWA fallback is not sector-neutral"
ADMIN = (ROOT / "src" / "Admin.php").read_text(encoding="utf-8")
assert "admin_legal_page" in ADMIN and "legal_terms_" in ADMIN and "legal_sla_" in ADMIN, "admin legal editor missing"
ADMIN_CSS = (ROOT / "public" / "assets" / "admin-overrides.css").read_text(encoding="utf-8")
assert ".sidebar nav" in ADMIN_CSS and "overflow-y: auto" in ADMIN_CSS and "min-height: 0" in ADMIN_CSS, "long admin navigation is not independently scrollable"
PACKAGER = (ROOT / "bin" / "package.py").read_text(encoding="utf-8")
for phrase in ["Transport business platform", "Sell transport websites with confidence.", "Themes made for transport brands", "Coastal transport and local moving", "Taşımacılık", "Nakliyat", "nakliye"]:
    assert phrase in PACKAGER, f"generic package replacement missing: {phrase}"
INBOUND = (ROOT / "src" / "InboundMail.php").read_text(encoding="utf-8")
WORKER = (ROOT / "bin" / "worker.php").read_text(encoding="utf-8")
assert "purge_inbound_mail" in INBOUND and "IMAP_RETENTION_DAYS" in INBOUND and "purge_inbound_mail" in WORKER, "IMAP retention policy missing"
assert "inbound_attachments" in INBOUND and "inbound_mail_attachments" in INBOUND and "in_reply_to" in INBOUND and "references_header" in INBOUND, "IMAP attachment/thread support missing"
assert "inbound_mail_thread_key" in INBOUND and "maxTotal" in INBOUND and "storage_path" in INBOUND, "IMAP attachment bounds or threading missing"
ROOT_INDEX = (ROOT / "index.php").read_text(encoding="utf-8")
ROOT_INSTALL = (ROOT / "install.php").read_text(encoding="utf-8")
RELEASE_CLI = (ROOT / "license-center" / "bin" / "release.php").read_text(encoding="utf-8")
assert "PHP_VERSION_ID < 80200" in ROOT_INDEX and "PHP_VERSION_ID < 80200" in ROOT_INSTALL, "legacy PHP handler would fail before showing requirements"
assert "require __DIR__ . '/public/index.php';" in ROOT_INDEX and "$_SERVER['REQUEST_URI'] = '/install';" in ROOT_INSTALL, "root entrypoint fallback missing"
assert "'compatibility'=>['php'=>'>='.$minPhp" in RELEASE_CLI and "arg('extensions'" in RELEASE_CLI, "application release compatibility manifest missing"

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
    assert "public/assets/admin-overrides.css" in names, "admin navigation accessibility override missing"
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
