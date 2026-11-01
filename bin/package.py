#!/usr/bin/env python3
"""Create an allowlisted deployment ZIP; never include runtime data or credentials."""
import argparse
from pathlib import Path
import stat
import zipfile

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--output", type=Path, required=True)
args = parser.parse_args()
roots = ["src", "public", "templates", "config", "docs", "bin", "tests"]
files = [ROOT / name for name in ["README.md", "KURULUM.txt", ".env.example", ".gitignore", "storage/.gitkeep"]]
for name in roots:
    for path in (ROOT / name).rglob("*"):
        if path.is_symlink():
            raise SystemExit("Refusing symlink in release: " + str(path))
        if path.is_file() and "__pycache__" not in path.parts and path.suffix != ".pyc":
            files.append(path)
args.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(args.output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(files):
        info = zipfile.ZipInfo.from_file(path, str(path.relative_to(ROOT)))
        # Upload archives should be readable by the web server even if the
        # development workspace uses owner-only source-file permissions.
        mode = 0o755 if path.stat().st_mode & stat.S_IXUSR else 0o644
        info.external_attr = (stat.S_IFREG | mode) << 16
        archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
with zipfile.ZipFile(args.output) as archive:
    assert archive.testzip() is None
    for name in archive.namelist():
        assert name != ".env" and not name.endswith((".sqlite", ".sqlite-wal", ".sqlite-shm"))
        assert not name.startswith((".git/", "storage/sessions/", "storage/backups/", "storage/media/"))
print(f"Deployment ZIP: {args.output} ({args.output.stat().st_size:,} bytes; {len(files)} files)")
