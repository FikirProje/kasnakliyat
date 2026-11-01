# Marketplace release checklist

This repository can be deployed as a branded Kaş Nakliyat site. Before offering a generic copy for sale, create a clean release branch and complete every item below.

## Do not ship customer material

- Remove the Kaş Nakliyat name, supplied truck icon, address, phone number, email address, hero image, gallery images and database from the generic archive.
- Include only assets created for resale or assets with a licence that explicitly permits redistribution in a marketplace product.
- Do not include `.env`, `storage/`, backups, sessions, API keys, SMTP credentials or a pre-created administrator.

## Buyer installation

1. Upload the archive with `public/` as the web root.
2. Copy `.env.example` to `.env`, set `APP_URL`, `APP_ENV=production` and a 32+ character `INSTALL_TOKEN`.
3. Open `/install`, enter the business name and create the first administrator. The installer is English and closes after the first account.
4. In the administration panel, enter contact details, logo assets, social links, legal text, services, galleries and SMTP settings.
5. Add `AI_API_KEY` only when AI features are wanted. Configure the worker with `php bin/worker.php all` every minute.

## Release acceptance checks

- Run `PHP_BIN=php python3 tests/smoke.py`, `tests/installer_smoke.py`, `tests/gallery_smoke.py` and the browser test.
- Test a real SMTP delivery and reply with the buyer's credentials.
- Verify Open Graph, X cards, schema JSON-LD, sitemap and Pinterest/Google verification output with the buyer's domain.
- Test all published languages, including RTL, with content reviewed by a native speaker.
- Provide a changelog, support policy, PHP extension requirements and privacy notice template.

## Current product boundary

The public site has 14 built-in interface languages. The installer is English. The administration interface is currently Turkish, and IMAP inbox synchronization is not included. These need completion before marketing the product as a fully multilingual administration suite with inbound email management.
