#!/usr/bin/env python3
"""Tokenless first-admin installer smoke test."""
import tempfile
from smoke import Smoke, Browser


def main():
    with tempfile.TemporaryDirectory(prefix="tofidesk-installer-") as directory:
        runner = Smoke(directory)
        try:
            runner.start(provision_users=False)
            browser = Browser(runner.base)
            assert browser.get("/install")[0] == 200, "tokenless installer unavailable"
            fields = dict(
                app_url=runner.base,
                business_name="Demo Transport",
                admin_name="Installer test",
                admin_email="first-admin@example.test",
                admin_password=runner.password,
                admin_password_confirm=runner.password,
            )
            assert browser.request("/install", dict(fields, csrf="invalid"))[0] == 419
            assert not runner.sql("SELECT * FROM users"), "CSRF failure created user"
            status, _, _, url = browser.form("/install", fields)
            records = runner.sql("SELECT * FROM users")
            assert status == 200 and "/admin/login" in url and len(records) == 1
            assert records[0]["password_hash"] != runner.password
            assert runner.sql("SELECT value FROM settings WHERE key='business_name'")[0]["value"] == "Demo Transport"
            print("PASS tokenless first-admin installation creates one hashed account")
            assert browser.get("/install")[0] == 403
            assert browser.request("/install", fields)[0] == 403
            print("PASS installer locks after first account")
            status, _, _, url = browser.form("/admin/login", {"email": "first-admin@example.test", "password": runner.password, "totp": ""})
            assert status == 200 and "/admin/login" not in url
            print("PASS installed admin can authenticate")
            return 0
        finally:
            runner.stop()


if __name__ == "__main__":
    raise SystemExit(main())
