"""Static release-gate checks for the generic marketplace archive."""
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = Path(__file__).resolve().parents[3] / "outputs" / "product-tofidesk-marketplace.zip"

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
