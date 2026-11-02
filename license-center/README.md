# TofiDesk Transportation licence centre

Deploy this directory as the document root for `https://tofidesk.com/transportation` with PHP 8.2+ and SQLite enabled. Copy `.env.example` outside the web root as `.env`, set `APP_URL`, `LICENSE_DB_PATH` and a long `LICENSE_INSTALL_TOKEN`, then visit `/install?token=...` once to create the first administrator.

The centre issues licence keys, limits each key to configured domains, publishes a selected theme payload, accepts purchase/contact requests at `/contact`, and exposes a public product and theme page at `/shop`. The API never exposes administrator credentials, Envato tokens or licence secrets.

Create a standalone deployment archive with `python3 bin/package.py /tmp/tofi-transportation-license-center.zip`.

## CodeCanyon / Envato flow

CodeCanyon does **not** automatically post a purchase code to a seller's server during a buyer's installation. A buyer receives the purchase code in their Envato download/licence certificate. The installer or the licence page asks for that code. The author server verifies it through Envato's author-sale API using the seller's `ENVATO_PERSONAL_TOKEN`, then creates or activates a TofiDesk licence key. Keep the Envato token only in this centre's server environment.

The included first release provides manual key issuance and secure domain activation. Add the Envato seller token and enable the `/api/v1/envato/verify` endpoint only after testing it on an author account; do not claim automatic CodeCanyon licensing before that verification succeeds.

## API contract

`POST /api/v1/licenses/activate` accepts `license_key`, `domain` and an optional `locale`. It attaches an unused allowed domain, or verifies an existing domain, and returns the active theme for that locale. `POST /api/v1/licenses/check` re-checks an existing installation. Both endpoints return only `active`, `license`, `theme` and a response timestamp.

Use HTTPS in production. Back up the SQLite database outside the web root.
