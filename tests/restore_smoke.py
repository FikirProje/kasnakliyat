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
from contextlib import closing

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN") or shutil.which("php") or "/workspace/.runtime/bin/php"
PHP_FLAGS = ["-d", "display_startup_errors=0", "-d", "display_errors=0"]
if os.name == "nt":
    for extension in ("php_sqlite3.dll", "php_pdo_sqlite.dll"):
        path = Path(PHP).resolve().parent / "ext" / extension
        if path.is_file(): PHP_FLAGS += ["-d", f"extension={path}"]


with tempfile.TemporaryDirectory(prefix="kas-restore-test-") as temporary:
    base = Path(temporary)
    storage, database = base / "storage", base / "database.sqlite"
    env = os.environ.copy()
    env.update(STORAGE_PATH=str(storage), DB_PATH=str(database))

    def run(args, expected=0):
        command = [args[0], *PHP_FLAGS, *args[1:]] if args and args[0] == PHP else args
        result = subprocess.run(command, env=env, cwd=ROOT, text=True, capture_output=True, timeout=30)
        assert result.returncode == expected, result.stderr
        return result.stdout

    run([PHP, "bin/console.php", "migrate"])
    with closing(sqlite3.connect(database)) as conn:
        conn.execute("INSERT INTO settings(key,value) VALUES('restore_marker','original')")
        conn.commit()
    (storage / "media/originals/marker.txt").write_text("original media")
    backup = Path(run([PHP, "bin/console.php", "backup"]).strip())
    with closing(sqlite3.connect(database)) as conn:
        conn.execute("UPDATE settings SET value='changed' WHERE key='restore_marker'")
        conn.commit()
    (storage / "media/originals/marker.txt").write_text("changed media")
    run([sys.executable, "bin/restore.py", "--archive=" + str(backup)], expected=2)
    run([sys.executable, "bin/restore.py", "--archive=" + str(backup), "--maintenance-confirmed"])
    with closing(sqlite3.connect(database)) as conn:
        assert conn.execute("SELECT value FROM settings WHERE key='restore_marker'").fetchone()[0] == "original"
    assert (storage / "media/originals/marker.txt").read_text() == "original media"
    recovery = next((storage / "backups").glob("pre-restore-*"))
    with closing(sqlite3.connect(recovery / "app.sqlite")) as conn:
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
