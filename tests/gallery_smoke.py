#!/usr/bin/env python3
"""Isolated visitor-facing gallery and AJAX regression check."""
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import http.cookiejar
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN") or shutil.which("php") or "/workspace/.runtime/bin/php"

with tempfile.TemporaryDirectory(prefix="kas-gallery-") as temporary:
    work = Path(temporary)
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    base = f"http://127.0.0.1:{port}"
    env = os.environ.copy()
    env.update(APP_ENV="testing", APP_URL=base, STORAGE_PATH=str(work / "storage"), DB_PATH=str(work / "app.sqlite"))
    seed = """<?php
require 'src/bootstrap.php'; migrate();
$source=storage_path('gallery-seed.png'); $image=imagecreatetruecolor(80,50);
$navy=imagecolorallocate($image,13,46,66); imagefill($image,0,0,$navy); imagepng($image,$source); imagedestroy($image);
$id=media_store_file($source,'real-office-photo.png','tr',false,'library');
query('UPDATE media_translations SET alt=?,caption=? WHERE media_id=? AND lang=?',['Gerçek ofis hazırlığı','Galeri testi',$id,'tr']);
query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Editor','editor@example.test',password_hash('gallery-test-password',PASSWORD_DEFAULT),'editor']);
echo $id;
"""
    seeded = subprocess.run([PHP], input=seed, text=True, cwd=ROOT, env=env, capture_output=True, timeout=30)
    assert seeded.returncode == 0, seeded.stderr
    media_id = int(seeded.stdout.strip())
    server = subprocess.Popen([PHP, "-S", f"127.0.0.1:{port}", "-t", "public", "public/index.php"], cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(60):
            try:
                page = urllib.request.urlopen(base + "/tr/galeri/", timeout=2).read().decode()
                break
            except OSError:
                time.sleep(.1)
        else:
            raise AssertionError("gallery server did not start")
        assert "gallery-grid" in page and f"/media/{media_id}?variant=webp" not in page
        cookies = http.cookiejar.CookieJar()
        browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies), urllib.request.HTTPRedirectHandler())
        def csrf(path):
            html = browser.open(base + path, timeout=5).read().decode()
            token = re.search(r'name="csrf" value="([a-f0-9]+)"', html)
            assert token, f"missing CSRF at {path}"
            return token.group(1)
        login = urllib.parse.urlencode({'csrf': csrf('/admin/login'), 'email': 'editor@example.test', 'password': 'gallery-test-password', 'totp': ''}).encode()
        browser.open(urllib.request.Request(base + '/admin/login', login), timeout=5).read()
        gallery_token = csrf('/admin/gallery')
        publish = urllib.parse.urlencode({'csrf': gallery_token, 'action': 'gallery', 'media_id': str(media_id), 'status': 'published', 'sort_order': '5'}).encode()
        browser.open(urllib.request.Request(base + '/admin/gallery', publish), timeout=5).read()
        page = urllib.request.urlopen(base + "/tr/galeri/", timeout=5).read().decode()
        assert f"/media/{media_id}?variant=webp" in page and "data-gallery-more" in page
        home = urllib.request.urlopen(base + "/tr/", timeout=5).read().decode()
        assert "gallery-preview" in home and f"/media/{media_id}?variant=webp" in home
        payload = json.loads(urllib.request.urlopen(base + "/api/gallery?lang=tr&offset=0", timeout=5).read())
        assert payload["items"][0]["alt"] == "Gerçek ofis hazırlığı"
        assert payload["items"][0]["caption"] == "Galeri testi"
        assert payload["items"][0]["src"] == f"/media/{media_id}?variant=webp"
        image = urllib.request.urlopen(base + payload["items"][0]["src"], timeout=5)
        assert image.status == 200 and image.headers["Content-Type"] == "image/webp"
        print("PASS: published real-media gallery card, AJAX payload and public WebP delivery")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
