#!/usr/bin/env python3
"""Create a deployable, standalone TofiDesk licence-centre archive."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
import sys

root = Path(__file__).resolve().parents[1]
output = Path(sys.argv[1]) if len(sys.argv) > 1 else root / 'tofi-transportation-license-center.zip'
skip = {'.env', 'storage/licenses.sqlite', 'storage/sessions'}
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for path in root.rglob('*'):
        if not path.is_file() or path.relative_to(root).as_posix() in skip or path.suffix in {'.sqlite', '.log'}:
            continue
        archive.write(path, path.relative_to(root))
print(output)
