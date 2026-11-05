#!/usr/bin/env python3
"""Exercise actual SMTP commands against a loopback-only test relay.

Uses isolated temporary application storage. Never contacts an external SMTP or
AI service and never reads or prints credentials. Requires PHP on PATH or PHP_BIN.
"""
import email.policy
import email.parser
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import threading


def main(disconnect_on_quit=False):
    root = Path(__file__).resolve().parents[1]
    php = os.environ.get("PHP_BIN") or shutil.which("php")
    if not php:
        raise RuntimeError("PHP is required; activate the documented PHP runtime first.")
    php_flags = ["-d", "display_startup_errors=0", "-d", "display_errors=0"]
    if os.name == "nt":
        for extension in ("php_sqlite3.dll", "php_pdo_sqlite.dll"):
            path = Path(php).resolve().parent / "ext" / extension
            if path.is_file(): php_flags += ["-d", f"extension={path}"]
    received = []
    errors = []
    listener = socket.socket()
    listener.bind(("127.0.0.1", 0))
    listener.listen(1)
    listener.settimeout(20)
    port = listener.getsockname()[1]

    def relay():
        try:
            with listener:
                connection, _ = listener.accept()
                with connection:
                    connection.settimeout(15)
                    stream = connection.makefile("rwb", buffering=0)
                    stream.write(b"220 loopback test relay\r\n")
                    data = False
                    while True:
                        line = stream.readline(8192)
                        if not line:
                            break
                        received.append((data, line))
                        if data:
                            if line == b".\r\n":
                                data = False
                                stream.write(b"250 queued\r\n")
                            continue
                        if line.startswith(b"EHLO "):
                            stream.write(b"250-localhost\r\n250 PIPELINING\r\n")
                        elif line.startswith((b"MAIL FROM:", b"RCPT TO:")):
                            stream.write(b"250 OK\r\n")
                        elif line == b"DATA\r\n":
                            data = True
                            stream.write(b"354 send message\r\n")
                        elif line == b"QUIT\r\n":
                            if not disconnect_on_quit:
                                stream.write(b"221 bye\r\n")
                            break
                        else:
                            stream.write(b"500 unexpected command\r\n")
        except Exception as error:
            errors.append(error)

    thread = threading.Thread(target=relay, daemon=True)
    thread.start()
    with tempfile.TemporaryDirectory(prefix="kasnakliyat-smtp-") as temporary:
        directory = Path(temporary)
        script = directory / "send.php"
        script.write_text("""<?php
require $argv[1] . '/src/bootstrap.php';
migrate();
$id=enqueue_mail('customer@example.test','Türkçe e-posta denemesi',"Merhaba\\n.Nokta satırı\\nKaş");
$counts=send_mail_queue(1);
$mail=row('SELECT status,attempts,sent_at,claimed_at FROM mail_queue WHERE id=?',[$id]);
echo json_encode(['counts'=>$counts,'mail'=>$mail],JSON_THROW_ON_ERROR);
""", encoding="utf-8")
        environment = dict(os.environ)
        environment.update(STORAGE_PATH=str(directory / "storage"), DB_PATH=str(directory / "app.sqlite"), APP_ENV="testing", AI_API_KEY="", SMTP_HOST="127.0.0.1", SMTP_PORT=str(port), SMTP_USER="", SMTP_PASSWORD="", SMTP_FROM="sender@example.test")
        result = subprocess.run([php, *php_flags, str(script), str(root)], env=environment, capture_output=True, text=True, timeout=30)
        if result.returncode:
            raise RuntimeError("PHP SMTP check failed; inspect the isolated application's error handling.")
        outcome = json.loads(result.stdout)
        assert outcome["counts"] == {"sent": 1, "failed": 0}, "queued mail was not delivered"
        assert outcome["mail"]["status"] == "sent" and outcome["mail"]["attempts"] == 1, "successful delivery was not persisted"
        assert outcome["mail"]["sent_at"] and outcome["mail"]["claimed_at"] is None, "successful delivery did not clear its lease"
    thread.join(timeout=5)
    assert not thread.is_alive() and not errors, "loopback SMTP relay did not finish"
    commands = b"".join(line for data, line in received if not data)
    assert b"MAIL FROM:<sender@example.test>\r\n" in commands
    assert b"RCPT TO:<customer@example.test>\r\n" in commands
    assert b"AUTH" not in commands, "loopback relay received an unexpected authentication attempt"
    message = b"".join(line for data, line in received if data and line != b".\r\n")
    parsed = email.parser.BytesParser(policy=email.policy.default).parsebytes(message)
    assert str(parsed["Subject"]) == "Türkçe e-posta denemesi", "UTF-8 subject was corrupted"
    assert parsed.get_content().strip() == "Merhaba\n.Nokta satırı\nKaş", "UTF-8 message body was corrupted"
    scenario = "QUIT disconnect after DATA acceptance" if disconnect_on_quit else "normal SMTP cleanup"
    print("PASS SMTP protocol delivery, UTF-8 subject/body, persisted sent state and cleared lease: " + scenario)


if __name__ == "__main__":
    main()
    main(disconnect_on_quit=True)
