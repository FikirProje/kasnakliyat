#!/usr/bin/env python3
"""Create an allowlisted deployment ZIP; never include runtime data or credentials."""
import argparse
import base64
from pathlib import Path
import stat
import zipfile

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--output", type=Path, required=True)
parser.add_argument("--profile", choices=("deployment", "marketplace"), default="deployment",
                    help="deployment keeps the configured demo; marketplace replaces customer brand assets")
args = parser.parse_args()
roots = ["src", "public", "templates", "config", "docs", "bin", "tests"]
files = [ROOT / name for name in ["README.md", "KURULUM.txt", "BILGI.md", "CHANGELOG.md", "SUPPORT.md", ".env.example", ".gitignore", ".htaccess", "index.php", "install.php", "storage/.gitkeep"]]
for name in roots:
    for path in (ROOT / name).rglob("*"):
        if path.is_symlink():
            raise SystemExit("Refusing symlink in release: " + str(path))
        if path.is_file() and "__pycache__" not in path.parts and path.suffix != ".pyc":
            files.append(path)

# These supplied/customer-facing assets must never be redistributed in a generic
# marketplace archive.  The app still receives neutral placeholders below so a
# buyer can complete the installer before replacing their brand media.
marketplace_excluded = {
    "public/assets/hero.webp", "public/assets/hero-mobile.webp",
    "public/assets/logo.svg", "public/assets/logo-light.svg", "public/assets/logo-monochrome.svg", "public/assets/logo-white.svg",
    "public/assets/brand.svg", "public/assets/favicon.svg", "public/assets/favicon-32.png",
    "public/assets/icon-192.png", "public/assets/apple-touch-icon.png", "public/assets/share-card.png",
    "public/assets/share-card.svg", "public/assets/social-square.png", "public/assets/social-story.png",
    "config/site.php", "bin/package.py",
}
if args.profile == "marketplace":
    files = [path for path in files if path.relative_to(ROOT).as_posix() not in marketplace_excluded]

GENERIC_LOGO = b'''<svg xmlns="http://www.w3.org/2000/svg" width="430" height="80" viewBox="0 0 430 80" role="img"><rect width="64" height="64" x="4" y="8" rx="17" fill="#0D2E42"/><path d="M16 46h40V31H25a9 9 0 0 0-9 9z" fill="#fff"/><circle cx="30" cy="50" r="6" fill="#FF9149"/><circle cx="52" cy="50" r="6" fill="#FF9149"/><text x="87" y="45" fill="#0D2E42" font-family="system-ui,sans-serif" font-size="30" font-weight="800">TRANSPORT CMS</text><text x="89" y="64" fill="#58716e" font-family="system-ui,sans-serif" font-size="10" font-weight="600" letter-spacing="3">YOUR BUSINESS NAME</text></svg>'''
GENERIC_LOGO_LIGHT = GENERIC_LOGO.replace(b'fill="#0D2E42" font-family', b'fill="#fff" font-family').replace(b'fill="#58716e"', b'fill="#c9d8d3"')
TRANSPARENT_PNG = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0bQAAAABJRU5ErkJggg==")
TEXT_SUFFIXES = {".php", ".md", ".txt", ".py", ".js", ".css", ".svg", ".json"}
GENERIC_REPLACEMENTS = (
    ("KAŞ NAKLİYAT", "TRANSPORT CMS"), ("Kaş Nakliyat", "Transport CMS"),
    ("kasnakliyat.com", "example-transport.test"), ("bilgi@kasnakliyat.com", "hello@example-transport.test"),
    ("info@kasnakliyat.com", "admin@example-transport.test"), ("+90 505 219 43 46", "+1 555 010 0100"),
    ("kasnakliyat.local", "transport-cms.local"),
    ("kasnakliyat", "transport-cms"),
    ("Bayındır", "Your district"), ("505 219", "555 010"),
    ("bilgi@kas", "contact@example-transport.test"),
    ("+905052194346", "15550100100"), ("Bayındır Mh. Ağalar Sk. No:25 Kaş / Antalya", "Your business address"),
    ("KAŞ / ANTALYA", "YOUR CITY / YOUR COUNTRY"), ("Kaş / Antalya", "Your City / Your Country"),
    ("Kaş", "Your City"), ("KAŞ", "YOUR CITY"),
)

def release_bytes(path: Path) -> bytes:
    data = path.read_bytes()
    if args.profile != "marketplace" or path.suffix.lower() not in TEXT_SUFFIXES:
        return data
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError:
        return data
    for source, replacement in GENERIC_REPLACEMENTS:
        text = text.replace(source, replacement)
    return text.encode("utf-8")
args.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(args.output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(files):
        archive_name = path.relative_to(ROOT).as_posix()
        info = zipfile.ZipInfo.from_file(path, archive_name)
        # Upload archives should be readable by the web server even if the
        # development workspace uses owner-only source-file permissions.
        mode = 0o755 if path.stat().st_mode & stat.S_IXUSR else 0o644
        info.external_attr = (stat.S_IFREG | mode) << 16
        archive.writestr(info, release_bytes(path), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    if args.profile == "marketplace":
        for name in ("public/assets/logo.svg", "public/assets/brand.svg", "public/assets/favicon.svg", "public/assets/logo-monochrome.svg", "public/assets/logo-white.svg"):
            archive.writestr(name, GENERIC_LOGO, compress_type=zipfile.ZIP_DEFLATED)
        archive.writestr("public/assets/logo-light.svg", GENERIC_LOGO_LIGHT, compress_type=zipfile.ZIP_DEFLATED)
        for name in ("public/assets/favicon-32.png", "public/assets/icon-192.png", "public/assets/apple-touch-icon.png", "public/assets/share-card.png", "public/assets/social-square.png", "public/assets/social-story.png"):
            archive.writestr(name, TRANSPARENT_PNG, compress_type=zipfile.ZIP_DEFLATED)
        archive.writestr("config/site.php", b"<?php\nreturn [];\n", compress_type=zipfile.ZIP_DEFLATED)
with zipfile.ZipFile(args.output) as archive:
    assert archive.testzip() is None
    names = archive.namelist()
    assert len(names) == len(set(names)), "release archive contains duplicate paths"
    for name in names:
        assert name != ".env" and not name.endswith((".sqlite", ".sqlite-wal", ".sqlite-shm"))
        assert not name.startswith((".git/", "storage/sessions/", "storage/backups/", "storage/media/"))
        if args.profile == "marketplace" and Path(name).suffix.lower() in TEXT_SUFFIXES:
            text = archive.read(name).decode("utf-8", "ignore")
            forbidden = ("Kaş Nakliyat", "kasnakliyat.com", "kasnakliyat.local", "Bayındır Mh.", "505 219", "bilgi@kas")
            assert not any(value in text for value in forbidden), f"customer identifier remains in {name}"
print(f"Deployment ZIP: {args.output} ({args.output.stat().st_size:,} bytes; {len(files)} files)")
