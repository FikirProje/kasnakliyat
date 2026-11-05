from __future__ import annotations

import http.cookiejar
import os
import re
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request
import shutil
import socket
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN") or shutil.which("php") or r"C:\xampp\php\php.exe"
if not Path(PHP).is_file():
    raise SystemExit("PHP_BIN is required for license-centre runtime coverage")

locales = ["tr", "en", "de", "fr", "es", "it", "pt", "ru", "ar", "fa", "nl", "zh", "ja", "ko"]
localized_route_terms = {
    "/products": ["Product catalogue", "Create product", "Product name"],
    "/licenses": ["Create licence plan", "Issue licence", "Billing period"],
    "/product-quality": ["Product quality review", "Approve and publish", "Complete missing fields"],
    "/account/login": ["Buyer sign in", "Forgot password?", "Create buyer account"],
    "/account/register": ["Create buyer account", "Confirm password", "Create account"],
    "/account/forgot-password": ["Forgot password?", "Send reset link", "Back to sign in"],
    "/license-requests": ["Licence change requests", "Approve", "Reject"],
}
routes = [
    "/", "/shop", "/pricing", "/search", "/compare", "/products", "/themes",
    "/licenses", "/orders", "/requests", "/support", "/health", "/settings",
    "/releases", "/installations", "/update-events", "/revocations", "/review-cases",
    "/product-quality", "/product-ai", "/commerce", "/license-requests",
    "/product?slug=tofidesk-cms", "/product?slug=nexa-business-theme",
    "/theme-preview?slug=nexa-business-theme",
    "/account/login", "/account/register", "/account/forgot-password",
    "/blog", "/privacy", "/terms", "/support-policy",
]

with tempfile.TemporaryDirectory(prefix="tofidesk-license-centre-") as directory:
    temp = Path(directory)
    # Never collide with another local smoke test or a developer's PHP server.
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    storage = temp / "storage"
    storage.mkdir()
    router = temp / "router.php"
    public = (ROOT / "license-center" / "public").as_posix().replace("\\", "\\\\")
    router.write_text(
        "<?php\n"
        "$target = '" + public + "/index.php';\n"
        "$requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';\n"
        "if (PHP_SAPI === 'cli-server' && !str_ends_with($requested, '.php') && is_file('" + public + "'.$requested)) { return false; }\n"
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
    server_log = temp / "server.log"
    server_log_handle = server_log.open("wb")
    command = [PHP, "-d", "display_errors=0"]
    if os.name == "nt":
        extension_dir = Path(PHP).resolve().parent / "ext"
        for extension in ("php_sqlite3.dll", "php_pdo_sqlite.dll"):
            extension_path = extension_dir / extension
            if extension_path.is_file(): command += ["-d", f"extension={extension_path}"]
    command += ["-S", f"127.0.0.1:{port}", "-t", str(ROOT / "license-center" / "public"), str(router)]
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=server_log_handle)
    try:
        deadline = time.monotonic() + 8
        ready = False
        while time.monotonic() < deadline:
            if process.poll() is not None:
                server_log_handle.flush()
                raise RuntimeError(f"license-centre PHP server exited with {process.returncode}: {server_log.read_text(encoding='utf-8', errors='replace')[-1600:]}")
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.25):
                    ready = True
                    break
            except OSError:
                time.sleep(0.15)
        if not ready:
            server_log_handle.flush()
            raise RuntimeError(f"license-centre PHP server did not become ready: {server_log.read_text(encoding='utf-8', errors='replace')[-1600:]}")
        jar = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

        def request(path: str, data: dict[str, str] | None = None) -> tuple[int, str]:
            url = f"http://127.0.0.1:{port}" + path
            body = None if data is None else urllib.parse.urlencode(data).encode()
            req = urllib.request.Request(url, data=body, headers={"Accept-Language": "tr"})
            try:
                with opener.open(req, timeout=10) as response:
                    return response.status, response.read().decode("utf-8", "replace")
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode("utf-8", "replace")

        status, installer = request("/installer.php?lang=tr")
        if status != 200 or 'name="email"' not in installer:
            raise RuntimeError(f"license-centre installer did not render: {status}; snippet={installer[:500]}")
        status, _ = request("/installer.php?lang=tr", {"lang": "tr", "email": "admin@example.test", "password": "VeryStrongPassword!123"})
        if status != 200:
            raise RuntimeError(f"license-centre first-admin setup failed: {status}")
        status, login = request("/login?lang=tr")
        token = re.search(r'name="csrf" value="([^"]+)"', login)
        if status != 200 or not token:
            raise RuntimeError("license-centre login form did not render")
        status, _ = request("/login?lang=tr", {"csrf": token.group(1), "email": "admin@example.test", "password": "VeryStrongPassword!123"})
        if status != 200:
            raise RuntimeError(f"license-centre admin login failed: {status}")

        for locale in locales:
            direction = "rtl" if locale in {"ar", "fa"} else "ltr"
            for route in routes:
                status, html = request(route + ("?lang=" + locale if "?" not in route else "&lang=" + locale))
                if status != 200:
                    server_log_handle.flush()
                    raise RuntimeError(f"{locale}{route}: HTTP {status}; snippet={html[:700]}; server={server_log.read_text(encoding='utf-8', errors='replace')[-1200:]}")
                if f'<html lang="{locale}" dir="{direction}"' not in html:
                    raise RuntimeError(f"{locale}{route}: missing locale/direction")
                if locale != "tr" and "Taşımacılık işletme platformu" in html:
                    raise RuntimeError(f"{locale}{route}: transport-specific copy leaked")
                if locale != "en" and "We use optional analytics cookies to improve the sales experience." in html:
                    raise RuntimeError(f"{locale}{route}: English cookie banner leaked")
                route_path = route.split("?", 1)[0]
                if locale != "en" and route_path in localized_route_terms:
                    leaked = [term for term in localized_route_terms[route_path] if term in html]
                    if leaked:
                        raise RuntimeError(f"{locale}{route}: untranslated UI terms leaked: {leaked}")
    finally:
        process.terminate()
        try:
            process.wait(timeout=3)
        except subprocess.TimeoutExpired:
            process.kill()
        server_log_handle.close()

print(f"PASS license-centre runtime coverage: {len(locales)} locales × {len(routes)} routes")
