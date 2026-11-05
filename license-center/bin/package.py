#!/usr/bin/env python3
"""Create a deployable, standalone TofiDesk licence-centre archive."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo
import stat
import sys

root = Path(__file__).resolve().parents[1]
output = Path(sys.argv[1]) if len(sys.argv) > 1 else root / 'tofi-product-marketplace-license-center.zip'
def include(path: Path) -> bool:
    """Keep the archive source-only; never ship local runtime/test state."""
    relative = path.relative_to(root).as_posix()
    if relative == '.env' or path.suffix.lower() in {'.sqlite', '.sqlite-wal', '.sqlite-shm', '.log'}:
        return False
    if '__pycache__' in path.parts or path.suffix.lower() == '.pyc':
        return False
    if relative.startswith(('storage/sessions/', 'storage/backups/', 'storage/media/', 'storage/outbox/')):
        return False
    # Preserve directory placeholders only; the installer creates the rest.
    return relative == 'storage/.gitkeep' or not relative.startswith('storage/')

with ZipFile(output, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(root.rglob('*'), key=lambda item: item.relative_to(root).as_posix()):
        if not path.is_file() or not include(path):
            continue
        name = path.relative_to(root).as_posix()
        info = ZipInfo(name, date_time=(2020, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        mode = 0o755 if path.stat().st_mode & stat.S_IXUSR else 0o644
        info.external_attr = (stat.S_IFREG | mode) << 16
        archive.writestr(info, path.read_bytes())
with ZipFile(output) as archive:
    names = archive.namelist()
    assert all(not name.startswith(('storage/sessions/', 'storage/backups/', 'storage/media/', 'storage/outbox/')) for name in names)
    assert '.env' not in names and not any(name.endswith(('.sqlite', '.sqlite-wal', '.sqlite-shm', '.log')) for name in names)
print(output)
