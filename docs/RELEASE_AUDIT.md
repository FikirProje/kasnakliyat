# Release audit — global marketplace readiness

Audit date: 2026-11-02. This is an evidence-based release gate, not a marketing checklist.

## Validation run

- PHP syntax checks: `src/`, `public/index.php`, and public templates passed.
- Installer security flow: 8 groups passed.
- Gallery publication/AJAX/media flow: passed.
- Backup/restore integrity, recovery copy and traversal rejection: passed.
- The broader smoke suite had previously passed 17 groups. It must be run again after every merge to `main`.

## Release blockers

### P0 — must resolve before a marketplace release

1. **Do not use a chat-shared API key.** A key supplied in conversation must be revoked and replaced through the deployment secret store. PHP exception traces can include function arguments in server logs unless `zend.exception_ignore_args=1` is set; no secret should reach application logs.
2. **Generic archive is not clean.** The repository contains Kaş name, address, contact details, logos, supplied hero image and share cards. Build a separate generic distribution profile that excludes all customer assets, configuration and database content.
3. **Resolved — theme custom CSS and CSP.** The public layout emits the validated custom-theme block with the per-request `csp_nonce()` and the bootstrap policy allows that nonce; the policy does not use `unsafe-inline`. The admin rejects closing tags and oversized CSS values.
4. **The product cannot yet claim a 14-language admin/installer.** Public UI has fourteen built-in locales, but installer and admin are primarily Turkish/English and their language dictionaries are incomplete.

### P1 — required before the claimed feature set

1. Replace all visible hard-coded Kaş strings in public/admin templates, JSON-LD, email subjects, error pages and AI prompts with brand/profile settings.
2. Add a country profile to installation: locale, locale fallback, time zone, currency, date format, phone prefix, address conventions and RTL direction.
3. Build versioned theme packages: logo/favicon/media upload, colour tokens, font selection, section visibility/order and child-theme template overrides. Browser-based arbitrary PHP editing must remain unavailable.
4. Render validated social links as accessible icon/link controls in public footer/header. Current settings fields alone are insufficient.
5. Implement inbound IMAP message retrieval, encrypted credential handling, attachment limits, message threading and retention controls before advertising inbox management.
6. Add a production deployment acceptance test for HTTPS, SMTP delivery, AI egress and external verification meta tags. Local tests intentionally do not prove these integrations.

### P2 — release quality work

1. Supply a generic demonstration dataset in all supported languages, reviewed by native speakers; current seeded content is mainly Turkish/English.
2. Add a changelog, upgrade/migration guide, support policy, licence inventory and third-party attribution bundle.
3. Add screenshot/demo fixtures that do not show a real customer, supplied logo, address or contact data.
4. Test PHP 8.2–8.4 and documented shared-hosting/web-server combinations, including SQLite permissions and cron/worker operation.

## Existing strengths

- CSRF, rate limiting, upload MIME/decode checks, role boundaries, private customer media, scheduled publication, optimistic content revisions and backup integrity are covered by automated tests.
- SMTP is queued rather than sent in a request; failed work is retained for review.
- Public SEO foundations include canonical URLs, sitemap, hreflang, Open Graph, X card metadata and JSON-LD.

## Marketplace release decision

**Do not submit the current checkout as a generic marketplace archive.** It is suitable as the Kaş Nakliyat branded deployment baseline. Close the P0 gates, then create and validate a separate generic release artifact before submission.
