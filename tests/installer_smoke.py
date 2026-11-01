#!/usr/bin/env python3
"""First-admin installer gates against isolated database and loopback HTTP only."""
import secrets
import tempfile
from smoke import Smoke, Browser


def main():
    with tempfile.TemporaryDirectory(prefix="kasnakliyat-installer-") as directory:
        runner = Smoke(directory)
        try:
            runner.start(provision_users=False)
            assert runner.browser.get("/install")[0] == 404, "installer enabled without token"
            print("PASS installer disabled by default")
            runner.stop()
            runner.env["INSTALL_TOKEN"] = "short-token"
            runner.start(provision_users=False)
            assert runner.browser.get("/install")[0] == 404, "installer accepted weak token"
            print("PASS short token cannot enable installer")
            runner.stop()
            token = secrets.token_urlsafe(32)
            runner.env["INSTALL_TOKEN"] = token
            runner.start(provision_users=False)
            browser = Browser(runner.base)
            fields = dict(setup_token=token, app_url=runner.base, business_name="Demo Transport", admin_name="Installer test", admin_email="first-admin@example.test", admin_password=runner.password, admin_password_confirm=runner.password)
            assert browser.get("/install")[0] == 200, "configured installer unavailable"
            assert browser.request("/install", dict(fields, csrf="invalid"))[0] == 419, "installer bypassed CSRF"
            assert not runner.sql("SELECT * FROM users"), "CSRF failure created user"
            print("PASS installer rejects CSRF without creating account")
            status, body, _, _ = browser.form("/install", dict(fields, setup_token="wrong-token"))
            assert status in (200, 400, 403, 422) and not runner.sql("SELECT * FROM users"), "wrong token created account"
            assert runner.password.encode() not in body and token.encode() not in body, "installer echoed secret"
            print("PASS installer rejects wrong token and never echoes passwords/token")
            status, _, _, _ = browser.form("/install", dict(fields, app_url="https://unrelated.example.test"))
            assert status in (200, 400, 422) and not runner.sql("SELECT * FROM users"), "wrong deployment origin created account"
            print("PASS installer validates configured application origin")
            status, _, _, url = browser.form("/install", fields)
            records = runner.sql("SELECT * FROM users")
            assert status == 200 and "/admin/login" in url and len(records) == 1 and records[0]["role"] == "admin", "first-admin installation failed"
            assert records[0]["password_hash"] != runner.password, "installer stored plaintext password"
            assert runner.sql("SELECT value FROM settings WHERE key='business_name'")[0]["value"] == "Demo Transport", "installer did not save business identity"
            print("PASS explicit setup creates exactly one hashed first-admin account")
            assert browser.get("/install")[0] == 403, "installer stays enabled after first account"
            assert browser.request("/install", fields)[0] == 403 and len(runner.sql("SELECT * FROM users")) == 1, "installer permitted second account"
            print("PASS installer refuses all reuse after account creation")
            status, _, _, url = browser.form("/admin/login", {"email": "first-admin@example.test", "password": runner.password, "totp": ""})
            assert status == 200 and "/admin/login" not in url, "created first-admin cannot log in"
            print("PASS installed admin can authenticate\n8 groups passed;0 failed.")
            return 0
        finally:
            runner.stop()


if __name__ == "__main__":
    raise SystemExit(main())
