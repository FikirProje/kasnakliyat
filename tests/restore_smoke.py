#!/usr/bin/env python3
"""Round-trip restore and tamper rejection against isolated, offline fixtures."""
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import sys
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN") or shutil.which("php") or "/workspace/.runtime/bin/php"


with tempfile.TemporaryDirectory(prefix="kas-restore-test-") as temporary:
    base = Path(temporary)
    storage, database = base / "storage", base / "database.sqlite"
    env = os.environ.copy()
    env.update(STORAGE_PATH=str(storage), DB_PATH=str(database))

    def run(args, expected=0):
        result = subprocess.run(args, env=env, cwd=ROOT, text=True, capture_output=True, timeout=30)
        assert result.returncode == expected, result.stderr
        return result.stdout

    run([PHP, "bin/console.php", "migrate"])
    with sqlite3.connect(database) as conn:
        conn.execute("INSERT INTO settings(key,value) VALUES('restore_marker','original')")
    (storage / "media/originals/marker.txt").write_text("original media")
    backup = Path(run([PHP, "bin/console.php", "backup"]).strip())
    with sqlite3.connect(database) as conn:
        conn.execute("UPDATE settings SET value='changed' WHERE key='restore_marker'")
    (storage / "media/originals/marker.txt").write_text("changed media")
    run([sys.executable, "bin/restore.py", "--archive=" + str(backup)], expected=2)
    run([sys.executable, "bin/restore.py", "--archive=" + str(backup), "--maintenance-confirmed"])
    with sqlite3.connect(database) as conn:
        assert conn.execute("SELECT value FROM settings WHERE key='restore_marker'").fetchone()[0] == "original"
    assert (storage / "media/originals/marker.txt").read_text() == "original media"
    recovery = next((storage / "backups").glob("pre-restore-*"))
    with sqlite3.connect(recovery / "app.sqlite") as conn:
        assert conn.execute("SELECT value FROM settings WHERE key='restore_marker'").fetchone()[0] == "changed"
    assert (recovery / "media/originals/marker.txt").read_text() == "changed media"
    print("PASS: data/media round-trip; pre-restore recovery copy; maintenance prerequisite")

    def altered_archive(name, alter):
        path = base / name
        with tarfile.open(backup, "r:gz") as source, tarfile.open(path, "w:gz") as target:
            for member in source.getmembers():
                if member.isfile():
                    data = source.extractfile(member).read()
                    data = alter(member.name, data)
                    member.size = len(data)
                    target.addfile(member, io.BytesIO(data))
                else:
                    target.addfile(member)
        return path

    tampered = altered_archive("tampered.tar.gz", lambda name, data: b"tampered" if name.endswith("marker.txt") else data)
    before = hashlib.sha256(database.read_bytes()).hexdigest()
    run([sys.executable, "bin/restore.py", "--archive=" + str(tampered), "--maintenance-confirmed"], expected=1)
    assert hashlib.sha256(database.read_bytes()).hexdigest() == before
    assert (storage / "media/originals/marker.txt").read_text() == "original media"
    unsafe = base / "unsafe.tar.gz"
    with tarfile.open(unsafe, "w:gz") as tar:
        member = tarfile.TarInfo("../escaped")
        member.size = 4
        tar.addfile(member, io.BytesIO(b"evil"))
    run([sys.executable, "bin/restore.py", "--archive=" + str(unsafe), "--maintenance-confirmed"], expected=1)
    assert not (base / "escaped").exists()
    print("PASS: tampered content and traversal rejected without modifying current data")
