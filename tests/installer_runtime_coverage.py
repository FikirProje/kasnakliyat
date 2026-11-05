from __future__ import annotations

import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN') or shutil.which('php')
if not PHP or not Path(PHP).is_file():
    raise SystemExit('PHP_BIN is required for installer runtime coverage')

locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko']
with tempfile.TemporaryDirectory(prefix='tofidesk-installer-') as directory:
    temp = Path(directory)
    storage = temp / 'storage'
    storage.mkdir()
    env = os.environ.copy()
    env.update({'LICENSE_DB_PATH': str(temp / 'licenses.sqlite'), 'LICENSE_STORAGE_PATH': str(storage)})
    php_flags = [PHP, '-d', 'display_errors=0']
    if os.name == 'nt':
        extension_dir = Path(PHP).resolve().parent / 'ext'
        for extension in ('php_sqlite3.dll', 'php_pdo_sqlite.dll'):
            path = extension_dir / extension
            if path.is_file():
                php_flags += ['-d', f'extension={path}']
    with socket.socket() as reserve:
        reserve.bind(('127.0.0.1', 0))
        port = reserve.getsockname()[1]
    command = php_flags + ['-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'license-center' / 'public')]
    server_log = temp / 'server.log'
    with server_log.open('wb') as log:
        process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=log, stderr=log)
    try:
        base_url = f'http://127.0.0.1:{port}'
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            if process.poll() is not None:
                raise RuntimeError(f'installer runtime server exited: {server_log.read_text(errors="replace")[-2000:]}')
            try:
                with urllib.request.urlopen(f'{base_url}/installer.php?lang=tr', timeout=2) as response:
                    if response.status == 200:
                        break
            except (OSError, urllib.error.URLError):
                time.sleep(0.1)
        else:
            raise RuntimeError(f'installer runtime server did not become ready: {server_log.read_text(errors="replace")[-2000:]}')
        for locale in locales:
            with urllib.request.urlopen(f'{base_url}/installer.php?lang={locale}', timeout=10) as response:
                html = response.read().decode('utf-8', 'replace')
            if response.status != 200 or f'<html lang="{locale}"' not in html or '<form method="post">' not in html:
                raise RuntimeError(f'installer runtime failed for {locale}: {response.status}')
            if locale != 'tr' and 'Sunucu gereksinimleri' in html:
                raise RuntimeError(f'Turkish installer leakage for {locale}')
    finally:
        process.terminate()
        try:
            process.wait(timeout=3)
        except subprocess.TimeoutExpired:
            process.kill()
print(f'{len(locales)} installer locales rendered successfully.')
