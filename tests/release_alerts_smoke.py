"""Exercise buyer release-alert preferences through a real local HTTP session."""
from __future__ import annotations

import http.cookiejar
import os
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN") or shutil.which("php")
if not PHP or not Path(PHP).is_file():
    raise SystemExit("PHP_BIN or php is required")

with tempfile.TemporaryDirectory(prefix="tofidesk-release-alerts-") as directory:
    temp = Path(directory)
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    storage = temp / "storage"
    storage.mkdir()
    router = temp / "router.php"
    public = (ROOT / "license-center" / "public").as_posix()
    router.write_text(
        "<?php\n"
        f"$target = {public!r} . '/index.php';\n"
        "$_SERVER['SCRIPT_NAME'] = '/index.php';\n"
        "require $target;\n",
        encoding="utf-8",
    )
    env = os.environ.copy()
    env.update({
        "LICENSE_DB_PATH": str(temp / "licenses.sqlite"),
        "LICENSE_STORAGE_PATH": str(storage),
        "LICENSE_PACKAGE_PATH": str(temp / "packages"),
        "APP_URL": f"http://127.0.0.1:{port}",
    })
    command = [PHP, "-d", "display_errors=0"]
    if os.name == "nt":
        extension_dir = Path(PHP).resolve().parent / "ext"
        for extension in ("php_sqlite3.dll", "php_pdo_sqlite.dll"):
            path = extension_dir / extension
            if path.is_file(): command += ["-d", f"extension={path}"]
    command += ["-S", f"127.0.0.1:{port}", "-t", str(ROOT / "license-center" / "public"), str(router)]
    server_log = temp / "server.log"
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=server_log.open("wb"))
    try:
        time.sleep(0.8)
        jar = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

        def request(path: str, data: dict[str, str] | None = None) -> tuple[int, str]:
            body = None if data is None else urllib.parse.urlencode(data).encode()
            req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", data=body, headers={"Accept-Language": "tr"})
            try:
                with opener.open(req, timeout=10) as response:
                    return response.status, response.read().decode("utf-8", "replace")
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode("utf-8", "replace")

        status, installer = request("/installer.php?lang=tr")
        token = re.search(r'name="csrf" value="([^"]+)"', installer)
        if status != 200 or not token:
            raise RuntimeError("installer did not render")
        # Admin setup uses the same cookie jar, so use a separate jar for the buyer below.
        request("/installer.php?lang=tr", {"lang": "tr", "email": "admin@example.test", "password": "VeryStrongPassword!123"})
        buyer_jar = http.cookiejar.CookieJar()
        buyer = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(buyer_jar))

        def buyer_request(path: str, data: dict[str, str] | None = None) -> tuple[int, str]:
            body = None if data is None else urllib.parse.urlencode(data).encode()
            req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", data=body, headers={"Accept-Language": "tr"})
            try:
                with buyer.open(req, timeout=10) as response:
                    return response.status, response.read().decode("utf-8", "replace")
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode("utf-8", "replace")

        status, register = buyer_request("/account/register?lang=tr")
        register_token = re.search(r'name="csrf" value="([^"]+)"', register)
        if status != 200 or not register_token:
            raise RuntimeError("buyer registration form did not render")
        status, _ = buyer_request("/account/register?lang=tr", {"csrf": register_token.group(1), "name": "Test Buyer", "email": "buyer@example.test", "password": "BuyerStrongPassword!123"})
        if status != 200:
            raise RuntimeError(f"buyer registration failed: {status}; server={server_log.read_text(encoding='utf-8', errors='replace')[-2000:]}")

        db_path = temp / "licenses.sqlite"
        db = sqlite3.connect(db_path)
        buyer_id = db.execute("SELECT id FROM buyers WHERE email=?", ("buyer@example.test",)).fetchone()[0]
        db.execute("INSERT INTO products(slug,name,kind,status) VALUES(?,?,?,?)", ("smoke-product", "Smoke Product", "application", "published"))
        product_id = db.execute("SELECT id FROM products WHERE slug=?", ("smoke-product",)).fetchone()[0]
        db.execute("INSERT INTO licenses(product,product_id,status,key_hash,key_hint,domain_limit,buyer_id) VALUES(?,?,?,?,?,?,?)", ("smoke-product", product_id, "active", "smoke", "SMOKE", 1, buyer_id))
        db.commit(); db.close()

        status, page = buyer_request("/account/release-alerts?lang=tr")
        alert_token = re.search(r'name="csrf" value="([^"]+)"', page)
        if status != 200 or not alert_token or "Smoke" not in page:
            raise RuntimeError(f"release-alert preference page did not render: {status}; page_tail={page[-1800:]}; server={server_log.read_text(encoding='utf-8', errors='replace')[-2000:]}")
        status, _ = buyer_request("/account/release-alerts?lang=tr", {"csrf": alert_token.group(1), "product[" + str(product_id) + "]": "1"})
        if status != 200:
            raise RuntimeError(f"release-alert preference save failed: {status}")
        db = sqlite3.connect(db_path)
        saved = db.execute("SELECT enabled,locale FROM release_subscriptions WHERE buyer_id=? AND product_id=?", (buyer_id, product_id)).fetchone()
        db.close()
        if saved != (1, "tr"):
            raise RuntimeError(f"release-alert preference was not persisted: {saved!r}")
    finally:
        process.terminate()
        try:
            process.wait(timeout=3)
        except subprocess.TimeoutExpired:
            process.kill()

print("PASS buyer release-alert preference flow and persistence")
