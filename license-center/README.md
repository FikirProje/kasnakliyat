# TofiDesk Product Center — sales, themes and licences

Deploy this directory as the document root for `https://product.tofidesk.com` with PHP 8.2+ and SQLite enabled. Copy `.env.example` outside the web root as `.env`, set `APP_URL=https://product.tofidesk.com` and `LICENSE_DB_PATH`, then visit `/install` once to create the first administrator. The installer is tokenless and locks automatically after the first administrator.

This is the single product centre: product sales, theme catalogue and sales, buyer accounts, orders, downloads, support, domain licences, installation records, update releases and revocation review. The API never exposes administrator credentials, Envato tokens or licence secrets.

Create a standalone deployment archive by compressing the `license-center` directory with the public directory as the document root. The repository's prepared archive is `outputs/product-tofidesk-license-center.zip`.

If URL rewriting is unavailable, open /installer.php instead of /install for the first administrator setup.

## Deployment map

- Upload `product-tofidesk-license-center.zip` to `product.tofidesk.com` as the complete product/sales/theme/licence centre.
- Upload `product-tofidesk-app-deployment.zip` to `kasnakliyat.com` as the branded demo/installed CMS.
- Deliver `product-tofidesk-marketplace.zip` to customers as the clean generic CMS package; it is not the sales centre.

Extract the selected archive directly into the domain's httpdocs document root. Keep .env, SQLite data and private storage outside the public web root where possible. Never upload the main CMS archive to the sales centre or the licence-centre archive to a customer's CMS domain.

## CodeCanyon / Envato flow

CodeCanyon does **not** automatically post a purchase code to a seller's server during a buyer's installation. A buyer receives the purchase code in their Envato download/licence certificate. The installer or the licence page asks for that code. The author server verifies it through Envato's author-sale API using the seller's `ENVATO_PERSONAL_TOKEN`, then creates or activates a TofiDesk licence key. Keep the Envato token only in this centre's server environment.

The centre provides manual key issuance, secure domain activation and a server-side `/api/v1/envato/verify` endpoint. The endpoint requires `ENVATO_PERSONAL_TOKEN`, applies rate limiting and must be verified on an author account before enabling automatic fulfilment. Do not claim automatic CodeCanyon licensing when that server secret is absent.

## Buyer portal

Buyers use `/account/register` and `/account/login` to view their products, licences, domains, downloads, orders, invoices and support tickets. Payment provider callbacks are intentionally separate from this portal; a paid order must be created only after a verified provider event.

## API contract

`POST /api/v1/licenses/activate` accepts `license_key`, `domain` and an optional `locale`. It attaches an unused allowed domain, or verifies an existing domain, and returns the active theme for that locale. `POST /api/v1/licenses/check` re-checks an existing installation. Both endpoints return only `active`, `license`, `theme` and a response timestamp.

Use HTTPS in production. Back up the SQLite database outside the web root.
