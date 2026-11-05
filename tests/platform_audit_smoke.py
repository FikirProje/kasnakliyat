from __future__ import annotations

import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
result = subprocess.run(['php', str(ROOT/'bin/platform-audit.php'), '--json'], cwd=ROOT, capture_output=True, text=True)
assert result.returncode == 0, result.stdout + result.stderr
report = json.loads(result.stdout)
assert report['status'] == 'pass' and report['failed'] == 0
assert len(report['checks']) >= 30
print(f"PASS platform audit: {len(report['checks'])} checks")
