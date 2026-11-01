#!/usr/bin/env python3
"""Offline, manifest-verified data restore. Never reads or restores credential values."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import sqlite3
import tarfile
import tempfile
import time


def restore(archive: Path, storage: Path, database: Path):
    storage.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=".restore-", dir=storage) as temporary:
        work = Path(temporary)
        with tarfile.open(archive, "r:gz") as tar:
            members = tar.getmembers()
            if len(members) > 100_000 or sum(m.size for m in members) > 4 * 1024**3:
                raise ValueError("Archive exceeds restore limits.")
            names = set()
            for member in members:
                path = PurePosixPath(member.name)
                if path.is_absolute() or ".." in path.parts or "\\" in member.name or member.name in names:
                    raise ValueError("Unsafe or duplicate archive member.")
                if not (member.isfile() or member.isdir()) or path.parts[0] not in ("app.sqlite", "manifest.json", "media"):
                    raise ValueError("Unexpected file or link in archive.")
                names.add(member.name)
            tar.extractall(work, members=members, filter="data")
        manifest = json.loads((work / "manifest.json").read_text())
        if manifest.get("format") != 1 or manifest.get("environment_secret_files_included") is not False:
            raise ValueError("Unsupported backup manifest.")
        actual = {str(p.relative_to(work)): hashlib.sha256(p.read_bytes()).hexdigest()
                  for p in work.rglob("*") if p.is_file() and p.name != "manifest.json"}
        if actual != manifest["sha256"] or "app.sqlite" not in actual:
            raise ValueError("Backup checksum or member-set mismatch.")
        with sqlite3.connect(f"file:{work / 'app.sqlite'}?mode=ro", uri=True) as connection:
            if connection.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
                raise ValueError("Backup database integrity check failed.")
            if not connection.execute("SELECT version FROM migrations WHERE version=3").fetchone():
                raise ValueError("Backup does not have the expected database schema.")
        # An additional recovery copy survives both success and failure.
        recovery = storage / "backups" / ("pre-restore-" + str(time.time_ns()))
        recovery.mkdir(parents=True, mode=0o700)
        if database.exists():
            with sqlite3.connect(database) as source, sqlite3.connect(recovery / "app.sqlite") as target:
                source.backup(target)
        if (storage / "media").exists():
            shutil.copytree(storage / "media", recovery / "media", symlinks=False)
        database.parent.mkdir(parents=True, exist_ok=True)
        staged_database = database.parent / (".restored-" + str(time.time_ns()) + ".sqlite")
        shutil.copy2(work / "app.sqlite", staged_database)
        os.chmod(staged_database, 0o600)
        old_media = work / "previous-media"
        try:
            # Processes must be stopped: replacing an active WAL database is unsafe.
            for suffix in ("-wal", "-shm"):
                database.with_name(database.name + suffix).unlink(missing_ok=True)
            if (storage / "media").exists():
                os.replace(storage / "media", old_media)
            os.replace(work / "media", storage / "media")
            os.replace(staged_database, database)
        except BaseException:
            if (storage / "media").exists():
                shutil.rmtree(storage / "media")
            if old_media.exists():
                os.replace(old_media, storage / "media")
            if (recovery / "app.sqlite").exists():
                shutil.copy2(recovery / "app.sqlite", database)
            raise
        finally:
            staged_database.unlink(missing_ok=True)
        print("Restore verified. Recovery copy: " + str(recovery))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--archive", required=True, type=Path)
    parser.add_argument("--storage", type=Path, default=Path(os.environ.get("STORAGE_PATH", Path(__file__).resolve().parents[1] / "storage")))
    parser.add_argument("--database", type=Path)
    parser.add_argument("--maintenance-confirmed", action="store_true", help="Confirm web and worker processes are stopped")
    args = parser.parse_args()
    if not args.maintenance_confirmed:
        parser.error("Stop web and worker processes, then use --maintenance-confirmed.")
    os.umask(0o077)
    database = args.database or Path(os.environ.get("DB_PATH") or args.storage / "app.sqlite")
    try:
        restore(args.archive.resolve(strict=True), args.storage.resolve(), database.resolve())
    except (OSError, ValueError, KeyError, sqlite3.Error, tarfile.TarError) as error:
        parser.exit(1, "Restore failed: " + str(error) + "\n")
