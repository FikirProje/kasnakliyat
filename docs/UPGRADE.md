# Upgrade and migration guide

## Before upgrading

1. Take a native backup from Admin → Backup and keep it outside the web root.
2. Copy `.env` and the storage directory to a protected location.
3. Confirm the PHP version and required extensions in `docs/GO_LIVE.md`.
4. Put the site into maintenance mode at the web-server level if the database is busy.

## Upgrade procedure

1. Upload the new release beside the current release.
2. Copy only the deployment `.env` and customer-owned storage into the new release.
3. Run the installer/upgrade entry point once. Database migrations are transactional and recorded in the `migrations` table.
4. Sign in to Admin and verify the selected locale, theme, media, SMTP queue and scheduled content.
5. Open the public site in one LTR locale and one RTL locale, then run the integration and smoke tests.

## Rollback

Stop the worker, restore the previous release directory and restore the database backup. Do not run a newer application against a restored older database without checking the migration table. Theme payloads and user preferences are preserved by their migrations.

## License centre

The license centre uses its own SQLite database. Back up `license-center/storage/licenses.sqlite` before upgrading. Theme metadata and `theme_versions` are additive migrations; published theme payloads remain data-only and are not executed as PHP.

## Release checklist

- [ ] No `.env`, customer media, credentials or database is in the marketplace ZIP.
- [ ] `/install` is protected by `INSTALL_TOKEN` and locks after the first administrator.
- [ ] Admin locale and RTL smoke checks pass.
- [ ] License activate/check rate-limit and domain-limit checks pass.
- [ ] Payment and Envato secrets are configured only on the server, never in the buyer package.
