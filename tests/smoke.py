#!/usr/bin/env python3
"""Isolated HTTP, database and queue regression checks; Python standard library only.

Run PHP_BIN=/path/to/php python3 tests/smoke.py. The script creates a temporary
storage/database, starts its own loopback PHP server and removes all fixtures.
It never contacts an AI or SMTP provider and never touches application data.
"""
from __future__ import annotations

import base64
import datetime as dt
import http.cookiejar
import json
import hashlib
import tarfile
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
LOCALES = "tr en de fr es it pt nl ru ar zh ja ko fa".split()
PHP = os.environ.get("PHP_BIN") or shutil.which("php") or "/workspace/.runtime/bin/php"
if PHP and not Path(PHP).is_file():
    PHP = shutil.which(PHP)
PHP_FLAGS: list[str] = ["-d", "display_startup_errors=0", "-d", "display_errors=0"]
if os.name == "nt":
    sqlite_extension = Path(PHP).resolve().parent / "ext" / "php_sqlite3.dll"
    if sqlite_extension.is_file():
        PHP_FLAGS += ["-d", f"extension={sqlite_extension}"]
    pdo_sqlite_extension = Path(PHP).resolve().parent / "ext" / "php_pdo_sqlite.dll"
    if pdo_sqlite_extension.is_file():
        PHP_FLAGS += ["-d", f"extension={pdo_sqlite_extension}"]
    gd_extension = Path(PHP).resolve().parent / "ext" / "php_gd.dll"
    if gd_extension.is_file():
        PHP_FLAGS += ["-d", f"extension={gd_extension}"]


class Browser:
    def __init__(self, base: str):
        self.base = base
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path: str, data: dict | None = None, *, files: dict | None = None, json_data: dict | None = None, headers: dict | None = None):
        headers = dict(headers or {})
        payload = None
        if files:
            boundary = "----kas-smoke-" + secrets.token_hex(12)
            pieces = []
            for key, value in (data or {}).items():
                pieces += [f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n".encode()]
            for key, (filename, mime, contents) in files.items():
                pieces += [f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"; filename=\"{filename}\"\r\nContent-Type: {mime}\r\n\r\n".encode(), contents, b"\r\n"]
            pieces += [f"--{boundary}--\r\n".encode()]
            payload = b"".join(pieces)
            headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
        elif json_data is not None:
            payload = json.dumps(json_data, ensure_ascii=False).encode()
            headers["Content-Type"] = "application/json"
        elif data is not None:
            payload = urllib.parse.urlencode(data, doseq=True).encode()
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        req = urllib.request.Request(self.base + path, payload, headers)
        try:
            response = self.opener.open(req, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read(), dict(response.headers), response.url

    def get(self, path: str):
        return self.request(path)

    def form(self, path: str, fields: dict, **kwargs):
        status, body, _, _ = self.get(path)
        assert status == 200, f"form {path}: GET {status}"
        match = re.search(rb'name=["\'](?:csrf|_csrf)["\'][^>]*value=["\']([^"\']+)', body)
        assert match, f"form {path}: missing CSRF input"
        fields = dict(fields, csrf=match.group(1).decode())
        return self.request(path, fields, **kwargs)


class Smoke:
    def __init__(self, directory: str):
        self.temp = Path(directory)
        with socket.socket() as reserve:
            reserve.bind(("127.0.0.1", 0))
            self.port = reserve.getsockname()[1]
        self.base = f"http://127.0.0.1:{self.port}"
        self.env = os.environ.copy()
        self.env.update(APP_ENV="testing", APP_URL=self.base, STORAGE_PATH=str(self.temp / "storage"), DB_PATH=str(self.temp / "app.sqlite"), AI_API_KEY="", SMTP_HOST="", SMTP_USER="", SMTP_PASSWORD="", SMTP_FROM="", INSTALL_TOKEN="", PLATFORM_API_TOKEN="p" * 48)
        self.password = secrets.token_urlsafe(24)
        self.env["SMOKE_PASSWORD"] = self.password
        self.server = None
        self.passed = 0
        self.failures = []
        self.browser = Browser(self.base)
        self.admin = Browser(self.base)
        self.editor = Browser(self.base)
        self.operator = Browser(self.base)
        self.article_id = None

    def php(self, code: str) -> str:
        source = "<?php require " + json.dumps(str(ROOT / "src/bootstrap.php")) + ";\n" + code
        result = subprocess.run([PHP, *PHP_FLAGS], input=source, capture_output=True, text=True, env=self.env, cwd=ROOT, timeout=45)
        assert result.returncode == 0, f"PHP subprocess failed ({result.returncode}): {result.stderr[-1500:]}"
        return result.stdout

    def sql(self, query: str, args=()):
        with sqlite3.connect(self.env["DB_PATH"], timeout=10) as conn:
            conn.row_factory = sqlite3.Row
            result = conn.execute(query, args)
            return [dict(r) for r in result.fetchall()]

    def start(self, provision_users=True):
        self.php("migrate(); migrate();")
        if provision_users:
            self.php("foreach(['admin','editor','operator'] as $role) { query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)', [ucfirst($role), $role.'@example.test',password_hash(getenv('SMOKE_PASSWORD'),PASSWORD_DEFAULT),$role]); } platform_upsert_form('api-contact','tr','API contact'); platform_add_form_field((int)row(\"SELECT id FROM platform_forms WHERE slug='api-contact' AND locale='tr'\")['id'], ['slug'=>'email','label'=>'Email','field_type'=>'email','required'=>true]);")
        self.log = open(self.temp / "server.log", "wb")
        self.server = subprocess.Popen([PHP, *PHP_FLAGS, "-S", f"127.0.0.1:{self.port}", "-t", "public", "public/index.php"], cwd=ROOT, env=self.env, stdout=self.log, stderr=self.log)
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            if self.server.poll() is not None:
                raise AssertionError("PHP server exited: " + (self.temp / "server.log").read_text()[-2000:])
            try:
                if self.browser.get("/tr/")[0] == 200:
                    return
            except OSError:
                pass
            time.sleep(.1)
        raise AssertionError("PHP server did not become ready: " + (self.temp / "server.log").read_text()[-2000:])

    def stop(self):
        if self.server:
            if os.name == "nt":
                subprocess.run(["taskkill", "/PID", str(self.server.pid), "/T", "/F"], capture_output=True)
            else:
                self.server.terminate()
            try:
                self.server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.server.kill()
                self.server.wait()
        if hasattr(self, "log"):
            self.log.close()

    def check(self, name: str, callback):
        try:
            callback()
            self.passed += 1
            print("PASS " + name, flush=True)
        except Exception as error:
            self.failures.append((name, str(error)))
            print("FAIL " + name + ": " + str(error), flush=True)

    def locale_pages(self):
        for locale in LOCALES:
            status, body, headers, _ = self.browser.get(f"/{locale}/")
            assert status == 200, f"{locale} home: {status}"
            html = body.decode()
            assert re.search(rf'<html\b[^>]*lang=["\']{locale}["\']', html), f"{locale}: html lang"
            direction = "rtl" if locale in ("ar", "fa") else "ltr"
            assert re.search(rf'<html\b[^>]*dir=["\']{direction}["\']', html), f"{locale}: direction"
            assert "name=\"viewport\"" in html or "name='viewport'" in html
            assert '<h1' in html, f"{locale}: heading missing"
            assert "X-Content-Type-Options" in headers
            for route in ("hizmetler/yuk-tasimaciligi/", "blog/", "teklif-al/", "randevu/", "gizlilik/"):
                code, content, _, _ = self.browser.get(f"/{locale}/{route}")
                assert code == 200 and f'lang="{locale}"' in content.decode(), f"{locale}/{route}: {code}"
        parity = json.loads(self.php("$catalog=translation_catalog(); $counts=[]; foreach($catalog as $lang=>$items) {$counts[$lang]=count($items);} echo json_encode($counts);"))
        assert set(parity) == set(LOCALES), "translation catalog locale parity"
        assert len(set(parity.values())) == 1, f"translation key counts differ: {parity}"

    def sitemap(self):
        status, body, _, _ = self.browser.get("/sitemap.xml")
        assert status == 200, f"sitemap: {status}"
        tree = ET.fromstring(body)
        urls = tree.findall("{http://www.sitemaps.org/schemas/sitemap/0.9}url")
        assert len(urls) >= 14, "core sitemap pages missing"
        locs = [u.findtext("{http://www.sitemaps.org/schemas/sitemap/0.9}loc") for u in urls]
        assert len(locs) == len(set(locs)), "duplicate sitemap URLs"
        assert all(url.startswith(self.base + "/") for url in locs), "wrong canonical origin"
        assert not any("/admin" in url for url in locs), "admin in sitemap"
        status, robots, _, _ = self.browser.get("/robots.txt")
        assert status == 200 and (b"Disallow: /admin" in robots or b"Disallow: /\n" in robots), "robots development/admin exclusion"

    def platform_api(self):
        status, body, headers, _ = self.browser.get("/api/platform/v1/modules")
        assert status == 200 and headers.get("X-TofiDesk-API-Version") == "1", f"platform modules API: {status}"
        modules = json.loads(body)
        assert any(item["slug"] == "transport-pack" for item in modules["items"]), "transport module missing from API"
        transport = next(item for item in modules["items"] if item["slug"] == "transport-pack")
        assert "quote.manage" in transport["permissions"] and "content.saved" in transport["hooks"] and transport["requires"]["php"] == ">=8.2", "module capability metadata missing"
        assert "quote_workflow" in transport["settings"], "module settings schema missing"
        status, body, _, _ = self.browser.get("/api/platform/v1/locations?country=TR&level=country")
        assert status == 200 and any(item["country_code"] == "TR" for item in json.loads(body)["items"]), "location API missing TR"
        status, body, _, _ = self.browser.get("/api/platform/v1/search?lang=tr&limit=1")
        assert status == 200 and "facets" in json.loads(body), "search API facets missing"
        status, body, _, _ = self.browser.get("/api/platform/v1/schema?type=listing")
        assert status == 200 and json.loads(body)["type"] == "listing", "content schema API missing"
        status, body, _, _ = self.browser.get("/api/platform/v1/menus?slug=primary&lang=tr")
        assert status == 200 and json.loads(body)["total"] >= 0, "menu API missing"
        status, body, _, _ = self.browser.get("/api/platform/v1/discovery/related?type=listing&id=42&lang=tr")
        assert status == 200 and "items" in json.loads(body), "related discovery API missing"
        status, body, _, _ = self.browser.get("/api/platform/v1/discovery/reviews?type=listing&id=42")
        assert status == 200 and json.loads(body)["total"] == 0, "review moderation API leaked pending data"
        status, _, _, _ = self.browser.request("/api/platform/v1/modules", {"x": "1"})
        assert status == 401, f"public API write method was not rejected: {status}"

    def platform_api_write(self):
        status, body, _, _ = self.browser.request(
            "/api/platform/v1/forms/submit",
            json_data={"slug": "api-contact", "lang": "tr", "values": {"email": "api@example.test"}},
            headers={"X-TofiDesk-API-Key": "wrong-token"},
        )
        assert status == 401 and json.loads(body)["error"] == "invalid_api_token", "invalid REST token was accepted"
        status, body, _, _ = self.browser.request(
            "/api/platform/v1/forms/submit",
            json_data={"slug": "api-contact", "lang": "tr", "values": {"email": "api@example.test"}},
            headers={"X-TofiDesk-API-Key": "p" * 48},
        )
        payload = json.loads(body)
        assert status == 201 and payload["status"] == "received" and int(payload["submission_id"]) > 0, "authenticated REST form submission failed"
        assert self.sql("SELECT count(*) n FROM platform_form_submissions WHERE id=?", (payload["submission_id"],))[0]["n"] == 1, "REST submission was not persisted"

    def theme_preview(self):
        for locale in LOCALES:
            status, body, _, _ = self.browser.get(f"/{locale}/tema-onizleme/")
            html = body.decode()
            assert status == 200, f"{locale} theme preview: {status}"
            assert f'<html lang="{locale}"' in html, f"{locale} preview language missing"
            assert 'td-section-hero' in html and 'td-section-feature_grid' in html, f"{locale} preview sections missing"
            assert 'td-theme-nexa-business' in html, f"{locale} preview manifest not rendered"

    def protected_storage(self):
        for path in ("/.env", "/src/bootstrap.php", "/storage/app.sqlite", "/.git/config", "/bin/console.php"):
            status, body, _, _ = self.browser.get(path)
            assert status in (403, 404), f"private path exposed: {path} => {status}"
            assert b"<?php" not in body and b"SQLite format" not in body, f"private bytes leaked: {path}"
        status, _, _, url = self.browser.get("/admin/requests")
        assert status == 200 and "/admin/login" in url, "anonymous admin access"

    def invalid_requests(self):
        before = self.sql("SELECT count(*) AS n FROM requests")[0]["n"]
        status, _, _, _ = self.browser.request("/tr/teklif-al/", {"csrf": "invalid"})
        assert status == 419, f"invalid CSRF: {status}"
        fields = self.request_fields()
        fields["preferred_date"] = "2020-02-30"
        status, _, _, _ = self.browser.form("/tr/teklif-al/", fields)
        assert status == 422, f"invalid date: {status}"
        fields = self.request_fields()
        fields["consent"] = "0"
        status, _, _, _ = self.browser.form("/tr/teklif-al/", fields)
        assert status == 422, f"missing consent: {status}"
        fields = self.request_fields()
        status, _, _, _ = self.browser.form("/tr/teklif-al/", fields, files={"photo": ("cargo.jpg", "image/jpeg", b"<?php echo 'not an image';")})
        assert status == 422, f"MIME-spoofed image: {status}"
        after = self.sql("SELECT count(*) AS n FROM requests")[0]["n"]
        assert before == after, "rejected forms persisted requests"

    def request_fields(self):
        return dict(name="Smoke Test Customer", email="customer@example.test", phone="+90 555 000 00 00", origin="Kaş", destination="Kalkan", cargo="Test cargo", preferred_date=(dt.date.today() + dt.timedelta(days=7)).isoformat(), slot="10:30", notes="<script>test</script>", consent="1", website="")

    def pending_requests(self):
        for lang, route, kind in (("tr", "teklif-al", "quote"), ("en", "randevu", "appointment"), ("ar", "randevu", "appointment")):
            status, body, _, _ = self.browser.form(f"/{lang}/{route}/", self.request_fields())
            assert status == 200, f"valid {kind}: {status}"
            assert b"KN-" in body, f"missing request reference: {kind}"
        records = self.sql("SELECT * FROM requests")
        assert len(records) == 3 and all(r["status"] == "new" for r in records), "requests auto-confirmed or lost"
        assert len({r["reference"] for r in records}) == 3, "reference collision"

    def private_upload(self):
        # Generate a valid image through installed GD, not a misleading MIME fixture.
        image = self.php("$im=imagecreatetruecolor(8,8); ob_start(); imagepng($im); $bytes=ob_get_clean(); echo base64_encode($bytes); imagedestroy($im);")
        status, _, _, _ = self.browser.form("/tr/teklif-al/", self.request_fields(), files={"photo": ("cargo.png", "image/png", base64.b64decode(image))})
        assert status == 200, f"valid upload: {status}"
        media = self.sql("SELECT * FROM media ORDER BY id DESC LIMIT 1")
        assert media and self.sql("SELECT * FROM request_media WHERE media_id=?", (media[0]["id"],)), "attachment relation missing"
        assert Path(self.env["STORAGE_PATH"], "media/originals", media[0]["filename"]).is_file(), "original not retained"
        for variant in ("original", "webp", "avif"):
            status, body, headers, final_url = self.browser.get(f"/media/{media[0]['id']}?variant={variant}")
            blocked = status in (403, 404) or (status == 200 and "/admin/login" in final_url and "text/html" in headers.get("Content-Type", ""))
            assert blocked and not body.startswith((b"RIFF", b"\x89PNG", b"\xff\xd8")), f"private {variant} accessible anonymously: {status}"

    def login(self, browser: Browser, role: str):
        status, body, _, url = browser.form("/admin/login", {"email": f"{role}@example.test", "password": self.password, "totp": ""})
        assert status == 200 and "/admin/login" not in url, f"{role} login failed: {status} {url}"

    def auth_roles(self):
        for browser, role in ((self.admin, "admin"), (self.editor, "editor"), (self.operator, "operator")):
            self.login(browser, role)
        # Render a real authenticated admin screen in every supported locale.
        # This catches translations that exist in a dictionary but are bypassed
        # by a route/layout branch.
        admin_id = self.sql("SELECT id FROM users WHERE email='admin@example.test'")[0]["id"]
        for locale in LOCALES:
            # Do not open a second PHP process while the single-threaded
            # built-in server is serving requests. On Windows that can leave
            # SQLite waiting on the server's connection indefinitely.
            self.sql("INSERT INTO user_preferences(user_id,preference_key,preference_value,updated_at) VALUES(?,?,?,datetime('now')) ON CONFLICT(user_id,preference_key) DO UPDATE SET preference_value=excluded.preference_value,updated_at=datetime('now')", (admin_id, "admin_locale", locale))
            status, body, _, _ = self.admin.get("/admin/settings")
            html = body.decode()
            assert status == 200 and f'<html lang="{locale}"' in html, f"admin {locale} render: {status}"
            if locale != "tr":
                assert "İşletme ve Entegrasyonlar" not in html and "Marka ve iletişim" not in html, f"Turkish admin copy leaked in {locale}"
            for route in ("/admin", "/admin/requests", "/admin/appointments", "/admin/content", "/admin/media", "/admin/seo", "/admin/settings", "/admin/security", "/admin/email-templates", "/admin/notification-rules", "/admin/integrations", "/admin/users", "/admin/jobs", "/admin/audit", "/admin/backup", "/admin/mail", "/admin/languages", "/admin/themes", "/admin/licenses", "/admin/updates", "/admin/modules", "/admin/plugins", "/admin/menus", "/admin/forms", "/admin/legal"):
                code, page, _, _ = self.admin.get(route)
                rendered = page.decode("utf-8", "replace")
                assert code == 200 and f'<html lang="{locale}"' in rendered, f"admin {locale}{route}: {code}"
                if locale != "tr":
                    for leaked in ("İşletme ve Entegrasyonlar", "Marka ve iletişim", "Yeni içerik oluştur", "Güvenli güncelleme", "Yasal metinler", "Sunucu ön kontrolü", "Gerçek görsellerden galeri oluşturun", "Marka ve Ana Görsel", "Yasal metinleri kaydet", "Public bağlantılar", "Lisans anahtarı", "Yapay zekâ sağlayıcısı", "SMTP testini kuyruğa al", "SMS/WhatsApp testini kuyruğa al", "PWA uygulama adı", "Harita Görünümü", "İçerik stüdyosu", "Tema Kütüphanesi"):
                        assert leaked not in rendered, f"Turkish admin copy leaked in {locale}{route}: {leaked}"
        for route in ("/admin/requests", "/admin/appointments", "/admin/content", "/admin/media", "/admin/seo", "/admin/settings", "/admin/users", "/admin/jobs", "/admin/audit", "/admin/backup"):
            status, _, _, _ = self.admin.get(route)
            assert status == 200, f"admin {route}: {status}"
        for route in ("/admin/settings", "/admin/users", "/admin/backup", "/admin/content", "/admin/jobs"):
            status, _, _, _ = self.operator.get(route)
            assert status == 403, f"operator reached {route}: {status}"
        for route in ("/admin/settings", "/admin/users", "/admin/requests"):
            status, _, _, _ = self.editor.get(route)
            assert status == 403, f"editor reached {route}: {status}"
        assert self.editor.get("/admin/content")[0] == 200
        private = self.sql("SELECT media_id FROM request_media LIMIT 1")
        if private:
            status, image, _, _ = self.admin.get(f"/media/{private[0]['media_id']}?variant=webp")
            assert status == 200 and image.startswith(b"RIFF"), "admin cannot read private derivative"

    def missing_integration_credentials(self):
        self.php("query(\"INSERT INTO articles(kind,source_lang,category) VALUES('article','tr','Test')\"); $id=(int)db()->lastInsertId(); query(\"INSERT INTO translations(article_id,lang,title,slug,body,status) VALUES(?,'tr','Source','source','Original editorial text','draft')\",[$id]); enqueue_ai_job($id,'en','localize','Test'); enqueue_mail('recipient@example.test','Test message','Retain this failed message.'); echo json_encode(['ai'=>process_ai_jobs(), 'mail'=>send_mail_queue()]);")
        job = self.sql("SELECT * FROM jobs ORDER BY id DESC LIMIT 1")[0]
        assert job["status"] == "failed" and "AI_API_KEY" in job["error"], "missing API key faked successful generation"
        source = self.sql("SELECT * FROM translations WHERE slug='source'")[0]
        assert source["body"] == "Original editorial text" and source["status"] == "draft", "failed AI overwrote source"
        mail = self.sql("SELECT * FROM mail_queue ORDER BY id DESC LIMIT 1")[0]
        assert mail["status"] == "failed" and mail["body"] == "Retain this failed message." and mail["last_error"], "SMTP failure discarded email"

    def publication_rules(self):
        self.php("query(\"INSERT INTO articles(kind,source_lang,category) VALUES('article','tr','Test')\"); $id=(int)db()->lastInsertId(); foreach([['tr','published','published-smoke',null],['en','draft','draft-smoke',null],['de','published','future-smoke','2999-01-01 00:00:00']] as $d) {query('INSERT INTO translations(article_id,lang,title,slug,excerpt,body,status,scheduled_at) VALUES(?,?,?,?,?,?,?,?)',[$id,$d[0],'Editorial test',$d[2],'Useful summary','Text with <script>alert(1)</script> as plain text.',$d[1],$d[3]]);}")
        status, body, _, _ = self.browser.get("/tr/blog/published-smoke/")
        assert status == 200, f"published article: {status}"
        text = body.decode()
        assert "&lt;script&gt;alert(1)&lt;/script&gt;" in text and "<script>alert(1)</script>" not in text, "plain text content became executable"
        assert 'hreflang="en"' not in text and 'hreflang="de"' not in text, "unpublished alternate advertised"
        assert self.browser.get("/en/blog/draft-smoke/")[0] == 404
        assert self.browser.get("/de/blog/future-smoke/")[0] == 404
        status, sitemap, _, _ = self.browser.get("/sitemap.xml")
        assert b"published-smoke" in sitemap and b"draft-smoke" not in sitemap and b"future-smoke" not in sitemap, "sitemap publication filtering"



    def editorial_workflow(self):
        status, _, _, url = self.editor.form("/admin/content", {"action": "create", "kind": "article", "lang": "tr", "category": "Smoke editorial"})
        assert status == 200 and "/admin/editor?" in url, f"content create {status} {url}"
        article_id = int(urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)["id"][0])
        self.article_id = article_id
        def save(lang, revision, slug, status="draft", body="Editorial text edited by a human."):
            return self.editor.form(f"/admin/editor?id={article_id}&lang={lang}", dict(action="save", id=article_id, lang=lang, revision=revision, title="Editorial smoke title", slug=slug, body=body, excerpt="A useful summary.", seo_title="Editorial SEO title", seo_description="An independent SEO description.", keywords="cargo, transport", status=status, category="Smoke editorial", scheduled_at=""))
        assert save("tr", 0, "editorial-old")[0] == 200
        tr = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='tr'", (article_id,))
        assert tr and tr[0]["revision"] == 1 and tr[0]["status"] == "draft", "draft save failed"
        assert self.browser.get("/tr/blog/editorial-old/")[0] == 404, "draft visible"
        assert save("en", 0, "editorial-english", body="Manually reviewed English text.")[0] == 200
        assert save("tr", 1, "editorial-old", status="published")[0] == 200
        tr = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='tr'", (article_id,))[0]
        en = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]
        assert tr["revision"] == 2 and tr["status"] == "published", "explicit publication failed"
        assert en["body"] == "Manually reviewed English text." and en["status"] == "draft", "source update overwrote other language"
        assert self.browser.get("/tr/blog/editorial-old/")[0] == 200
        assert save("tr", 1, "stale-slug", body="Stale browser overwrite.")[0] == 200
        after_stale = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='tr'", (article_id,))[0]
        assert after_stale["revision"] == 2 and after_stale["slug"] == "editorial-old" and after_stale["body"] == tr["body"], "stale revision overwrote current content"
        assert save("tr", 2, "editorial-new", status="published")[0] == 200
        moved = self.sql("SELECT * FROM redirects WHERE lang='tr' AND old_slug='editorial-old'")
        assert moved and moved[0]["new_slug"] == "editorial-new", "slug redirect not saved"
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, headers, newurl):
                return None
        opener = urllib.request.build_opener(NoRedirect)
        try:
            response = opener.open(self.base + "/tr/blog/editorial-old/", timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        assert response.status == 301 and response.headers["Location"].endswith("/tr/blog/editorial-new/"), "old slug did not301 redirect"

    def appointment_conflicts(self):
        appointments = self.sql("SELECT * FROM requests WHERE type='appointment' ORDER BY id")
        assert len(appointments) >= 2, "appointment fixtures missing"
        first, second = appointments[:2]
        initial_first = len(self.sql("SELECT * FROM mail_queue WHERE request_id=?", (first["id"],)))
        initial_second = len(self.sql("SELECT * FROM mail_queue WHERE request_id=?", (second["id"],)))
        def confirm(record):
            return self.operator.form("/admin/appointments", dict(action="request", id=record["id"], status="confirmed", preferred_date=first["preferred_date"], slot=first["slot"], price="1500", admin_note="Confirmed by authorized operation user."))
        assert confirm(first)[0] == 200
        assert self.sql("SELECT status FROM requests WHERE id=?", (first["id"],))[0]["status"] == "confirmed", "operator could not confirm appointment"
        assert confirm(second)[0] == 200
        states = self.sql("SELECT id,status FROM requests WHERE id IN (?,?) ORDER BY id", (first["id"], second["id"]))
        assert states[0]["status"] == "confirmed" and states[1]["status"] == "new", "conflicting appointment confirmed"
        assert len(self.sql("SELECT * FROM mail_queue WHERE request_id=?", (first["id"],))) == initial_first + 1, "confirmation notification not persisted"
        assert len(self.sql("SELECT * FROM mail_queue WHERE request_id=?", (second["id"],))) == initial_second, "rejected conflict sent false confirmation"

    def ai_editorial_approval(self):
        assert self.article_id, "editorial fixture missing"
        article_id = self.article_id
        current_en = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]
        status, _, _, _ = self.editor.form(f"/admin/editor?id={article_id}&lang=en", dict(action="save", id=article_id, lang="en", revision=current_en["revision"], title=current_en["title"], slug=current_en["slug"], body=current_en["body"], excerpt=current_en["excerpt"], seo_title=current_en["seo_title"], seo_description=current_en["seo_description"], keywords=current_en["keywords"], status="published", category="Smoke editorial", scheduled_at=""))
        assert status == 200 and self.browser.get("/en/blog/editorial-english/")[0] == 200, "English pre-approval publication failed"
        existing = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]
        source = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='tr'", (article_id,))[0]
        fixture = json.dumps(dict(title="Reviewed proposal", slug="approved-proposal", excerpt="An editorial proposal.", body="A generated proposal awaiting human review.", seo_title="Proposed SEO", seo_description="Proposed independent SEO.", keywords="cargo"))
        # An explicit completed provider-result fixture tests review behavior; no API call is mocked as a live success.
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            cursor = conn.execute("INSERT INTO jobs(article_id,lang,mode,status,prompt,result,attempts) VALUES(?,'en','localize','completed','Fixture for editorial approval',?,1)", (article_id, fixture))
            job_id = cursor.lastrowid
        assert self.sql("SELECT body FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]["body"] == existing["body"], "completed AI job auto-overwrote translation"
        assert self.editor.form("/admin/jobs", {"action": "apply", "id": job_id, "revision": existing["revision"] - 1})[0] == 200
        assert self.sql("SELECT body FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]["body"] == existing["body"], "stale AI approval overwrote translation"
        assert self.editor.form("/admin/jobs", {"action": "apply", "id": job_id, "revision": existing["revision"]})[0] == 200
        applied = self.sql("SELECT * FROM translations WHERE article_id=? AND lang='en'", (article_id,))[0]
        assert applied["body"] == "A generated proposal awaiting human review." and applied["status"] == "draft", "approved AI result was not a separate draft"
        assert self.sql("SELECT body FROM translations WHERE article_id=? AND lang='tr'", (article_id,))[0]["body"] == source["body"], "AI result overwrote source language"
        assert self.browser.get("/en/blog/approved-proposal/")[0] == 404, "AI approval auto-published draft"
        assert self.browser.get("/en/blog/editorial-english/")[0] == 404, "old AI-replaced slug redirected to unpublished draft"
        assert self.sql("SELECT * FROM redirects WHERE lang='en' AND old_slug='editorial-english'"), "AI apply did not preserve old URL"
        status, _, _, _ = self.editor.form(f"/admin/editor?id={article_id}&lang=en", dict(action="save", id=article_id, lang="en", revision=applied["revision"], title=applied["title"], slug=applied["slug"], body=applied["body"], excerpt=applied["excerpt"], seo_title=applied["seo_title"], seo_description=applied["seo_description"], keywords=applied["keywords"], status="published", category="Smoke editorial", scheduled_at=""))
        assert status == 200
        status, _, _, final_url = self.browser.get("/en/blog/editorial-english/")
        assert status == 200 and final_url.endswith("/en/blog/approved-proposal/"), "old AI-replaced URL not redirected after explicit publication"


    def media_purpose_isolation(self):
        orphan = int(self.php("$tmp=tempnam(storage_path(),'orphan-'); $im=imagecreatetruecolor(8,8); imagepng($im,$tmp); imagedestroy($im); try {echo media_store_file($tmp,'orphan-customer.png','tr',false);} finally {if(is_file($tmp)) unlink($tmp);}"))
        media = self.sql("SELECT * FROM media WHERE id=?", (orphan,))[0]
        assert media["purpose"] == "customer" and not self.sql("SELECT * FROM request_media WHERE media_id=?", (orphan,)), "orphan fixture not default-private"
        status, data, headers, url = self.browser.get(f"/media/{orphan}")
        assert status in (403, 404) or ("/admin/login" in url and "text/html" in headers.get("Content-Type", "")), "orphan customer media leaked before request relation"
        assert self.editor.get(f"/media/{orphan}")[0] == 403, "editor read orphan customer photo"
        status, photo, headers, _ = self.admin.get(f"/media/{orphan}")
        assert status == 200 and photo.startswith(b"RIFF") and headers.get("Cache-Control") == "no-store", "customer photo cache/control failed"
        status, page, _, _ = self.editor.get("/admin/media")
        assert status == 200 and b"orphan-customer.png" not in page, "customer photo listed in editorial library"
        before = self.sql("SELECT * FROM media_translations WHERE media_id=? AND lang='tr'", (orphan,))[0]
        self.editor.form("/admin/media", dict(action="metadata", id=orphan, lang="tr", title="Forbidden edit", alt="Forbidden alt", caption="Forbidden caption"))
        assert self.sql("SELECT * FROM media_translations WHERE media_id=? AND lang='tr'", (orphan,))[0] == before, "editor changed customer metadata"
        image = self.php("$im=imagecreatetruecolor(8,8); ob_start(); imagepng($im); echo base64_encode(ob_get_clean()); imagedestroy($im);")
        status, _, _, _ = self.editor.form("/admin/media", dict(action="upload", lang="tr"), files={"photo": ("editorial-library.png", "image/png", base64.b64decode(image))})
        assert status == 200
        library = self.sql("SELECT * FROM media WHERE original_name='editorial-library.png' ORDER BY id DESC LIMIT 1")[0]
        assert library["purpose"] == "library", "admin upload not explicitly library"
        status, _, headers, url = self.browser.get(f"/media/{library['id']}")
        assert status in (403,404) or ("/admin/login" in url and "text/html" in headers.get("Content-Type", "")), "unpublished library media public"
        assert self.editor.get(f"/media/{library['id']}")[0] == 200
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            conn.execute("UPDATE translations SET cover_media_id=? WHERE article_id=? AND lang='tr'", (library["id"], self.article_id))
        status, photo, headers, _ = self.browser.get(f"/media/{library['id']}")
        assert status == 200 and photo.startswith(b"RIFF"), "published cover inaccessible"

    def redirects_are_bounded(self):
        fixtures = [("tr", "cycle-a", "cycle-b"), ("tr", "cycle-b", "cycle-a"), ("tr", "chain-a", "chain-b"), ("tr", "chain-b", "editorial-new")]
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            conn.executemany("INSERT INTO redirects(lang,old_slug,new_slug) VALUES(?,?,?)", fixtures)
        assert self.browser.get("/tr/blog/cycle-a/")[0] == 404, "redirect cycle did not terminate404"
        status, _, _, url = self.browser.get("/tr/blog/chain-a/")
        assert status == 200 and url.endswith("/tr/blog/editorial-new/"), "redirect chain did not resolve published final target"
        assert self.browser.get("/tr/hizmetler/chain-a/")[0] == 404, "wrong content-kind redirect allowed"
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            conn.execute("UPDATE translations SET status='draft' WHERE article_id=? AND lang='tr'", (self.article_id,))
        assert self.browser.get("/tr/blog/editorial-old/")[0] == 404, "renamed draft leaked through redirect"
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            conn.execute("UPDATE translations SET status='published' WHERE article_id=? AND lang='tr'", (self.article_id,))
        assert self.browser.get("/tr/blog/editorial-old/")[0] == 200, "renamed published target unavailable"

    def bulk_queue_is_atomic(self):
        assert self.article_id
        with sqlite3.connect(self.env["DB_PATH"]) as conn:
            initial = conn.execute("SELECT count(*) FROM jobs WHERE status IN ('queued','running')").fetchone()[0]
            conn.executemany("INSERT INTO jobs(article_id,lang,mode,status,prompt) VALUES(?,'en','write','queued','Atomic bulk fixture')", [(self.article_id,)] * (95-initial))
        try:
            before = self.sql("SELECT count(*) n FROM jobs")[0]["n"]
            fields = dict(action="generate", id=self.article_id, lang="tr", mode="localize", prompt="Atomic batch test", **{"langs[]": LOCALES})
            status, body, _, _ = self.editor.form(f"/admin/editor?id={self.article_id}&lang=tr", fields)
            assert status == 200 and "Hiçbir yeni iş eklenmedi" in body.decode(), "capacity error not visible"
            assert self.sql("SELECT count(*) n FROM jobs")[0]["n"] == before, "over-cap batch partially enqueued"
            fields["langs[]"] = ["en", "invalid"]
            status, body, _, _ = self.editor.form(f"/admin/editor?id={self.article_id}&lang=tr", fields)
            assert status == 200 and "Yayın dili geçersiz" in body.decode(), "invalid publishing-language error not visible"
            assert self.sql("SELECT count(*) n FROM jobs")[0]["n"] == before, "invalid target batch partially enqueued"
        finally:
            self.sql("DELETE FROM jobs WHERE prompt='Atomic bulk fixture'")

    def backup_integrity(self):
        filename = self.php("echo create_backup();").strip()
        assert Path(filename).is_file(), "backup not created"
        with tarfile.open(filename, "r:gz") as archive:
            names = archive.getnames()
            assert "app.sqlite" in names and "manifest.json" in names, "backup database/manifest missing"
            assert not any(".env" in name or "session" in name for name in names), "backup contains secrets/sessions"
            manifest = json.load(archive.extractfile("manifest.json"))
            assert manifest["environment_secret_files_included"] is False
            assert manifest["application_auth_data_included"] is True
            for name, expected in manifest["sha256"].items():
                assert name in names, f"backup missing {name}"
                assert hashlib.sha256(archive.extractfile(name).read()).hexdigest() == expected, f"backup checksum {name}"
            snapshot = self.temp / "backup-snapshot.sqlite"
            snapshot.write_bytes(archive.extractfile("app.sqlite").read())
        with sqlite3.connect(snapshot) as conn:
            assert conn.execute("PRAGMA integrity_check").fetchone()[0] == "ok", "backup snapshot corrupt"
            snapshot_count = conn.execute("SELECT count(*) FROM requests").fetchone()[0]
        assert snapshot_count == self.sql("SELECT count(*) n FROM requests")[0]["n"], "backup lost requests"
        assert not self.sql("SELECT * FROM translations WHERE status='published' AND slug='source'"), "backup operation changed editorial state"

    def security_primitives(self):
        result = json.loads(self.php("echo json_encode(['totp'=>totp_code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',59),'invalid'=>totp_valid('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ','not-a-code'),'limits'=>[rate_limit('smoke-limit',2,60),rate_limit('smoke-limit',2,60),rate_limit('smoke-limit',2,60)]]);"))
        assert result["totp"] == "287082", "TOTP RFC6238 vector mismatch"
        assert result["invalid"] is False and result["limits"] == [True, True, False], "TOTP/rate-limit rejection failed"

    def run(self):
        self.check("14 locales, RTL, core pages and translation parity", self.locale_pages)
        self.check("XML sitemap and robots policy", self.sitemap)
        self.check("versioned platform API modules, locations and search", self.platform_api)
        self.check("authenticated platform REST form submission", self.platform_api_write)
        self.check("SDK theme preview renders section manifest in all locales", self.theme_preview)
        self.check("private files and anonymous admin access", self.protected_storage)
        self.check("CSRF, invalid date, consent and MIME rejection", self.invalid_requests)
        self.check("quote and appointments persist pending with unique references", self.pending_requests)
        self.check("photo originals/derivatives retained and anonymous access denied", self.private_upload)
        self.check("login, role authorization and protected media", self.auth_roles)
        self.check("editor create/save/publish, locale preservation, stale revisions and301 redirect", self.editorial_workflow)
        self.check("appointment confirmation rejects same-slot conflicts and false emails", self.appointment_conflicts)
        self.check("completed AI results need explicit approval and remain draft", self.ai_editorial_approval)
        self.check("private orphan customer media, editorial isolation and published covers", self.media_purpose_isolation)
        self.check("bounded redirects require a published target of the same kind", self.redirects_are_bounded)
        self.check("AI/SMTP missing credentials retain data and record failures", self.missing_integration_credentials)
        self.check("bulk AI capacity and invalid-language failures are atomic", self.bulk_queue_is_atomic)
        self.check("draft/scheduled exclusion, published article escaping and alternates", self.publication_rules)
        self.check("backup snapshot integrity, media checksums and secret exclusion", self.backup_integrity)
        self.check("RFC6238 TOTP and bounded rate limits", self.security_primitives)
        print(f"\n{self.passed} groups passed; {len(self.failures)} groups failed.")
        if self.failures:
            log = self.temp / "storage/logs/php.log"
            if log.exists():
                print("\nPHP error evidence:\n" + log.read_text()[-5000:])
        return 1 if self.failures else 0


def main():
    if not PHP or not Path(PHP).is_file():
        print("PHP executable missing. Set PHP_BIN.", file=sys.stderr)
        return 2
    with tempfile.TemporaryDirectory(prefix="kasnakliyat-smoke-", ignore_cleanup_errors=True) as directory:
        runner = Smoke(directory)
        try:
            runner.start()
            return runner.run()
        except Exception as error:
            print("SETUP FAILED: " + str(error), file=sys.stderr)
            for path in (runner.temp / "server.log", runner.temp / "storage/logs/php.log"):
                if path.exists():
                    print(path.read_text()[-4000:], file=sys.stderr)
            return 2
        finally:
            runner.stop()


if __name__ == "__main__":
    raise SystemExit(main())
