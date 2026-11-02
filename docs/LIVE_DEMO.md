# Live demo deployment

The repository does not include a hosted public domain. To publish a neutral demo, deploy the marketplace ZIP to an isolated PHP 8.1+ host with SQLite, GD and the required writable storage permissions.

## Safe demo setup

1. Create a fresh subdomain such as `demo.example.com` and an empty database/storage location.
2. Upload the marketplace ZIP and point the document root to the extracted public directory.
3. Copy `.env.example` to `.env`; set a long random install token and a demo-only `APP_URL`.
4. Open `/install?token=...`, create a demo administrator and keep the install token private.
5. Add neutral sample content only; never import customer backups, real email credentials or payment keys.
6. Verify one LTR locale, one RTL locale, the public shop, `/pricing`, buyer registration, `/theme-preview` and license activate/check.

## Isolation and expiry

Use a separate database and storage volume for the demo. Disable outbound SMTP and payment webhooks unless they are explicitly needed for a test. Rotate or remove the demo after review; a demo must never be used as production data storage.

## Acceptance URLs

- `/install?token=...`
- `/shop`
- `/pricing`
- `/account/register`
- `/theme-versions`
- `/theme-preview?id=...`
