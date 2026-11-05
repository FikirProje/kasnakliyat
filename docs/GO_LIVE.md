# Go-live checklist

## Before DNS cutover

1. Deploy the **deployment** profile for Kaş Nakliyat, or the **marketplace** profile for a buyer:
   `python3 bin/package.py --profile=deployment --output=site.zip`
2. Set the web server document root to `public/`; keep `.env` and `storage/` outside the web root.
3. Set `APP_ENV=production`, the final HTTPS-only `APP_URL`, and restrictive file permissions.
4. Open `/install`, select the installer language, and create the first administrator. The installer closes automatically after the first account.
5. Configure the business name, contact details, legal text, logo, services, gallery and social profiles in the panel.
6. In **Admin > Legal**, review Terms and SLA text for every enabled locale. Confirm the public legal links and the cookie banner; analytics must remain disabled until the visitor accepts analytics cookies.

## Search engines and social platforms

Add each supplied verification code to `.env`; leave unused entries blank:

```env
BING_SITE_VERIFICATION=
YANDEX_VERIFICATION=
FACEBOOK_DOMAIN_VERIFICATION=
```

Google, Bing, Yandex and Pinterest verification are available in the administration settings. After publishing, submit `https://your-domain.example/sitemap.xml` to Google Search Console, Bing Webmaster Tools and Yandex Webmaster. Verify Open Graph previews with each social platform's official debugger after DNS and HTTPS are live.

## Production checks

- Run `php bin/console.php check` and the worker periodically: `php bin/worker.php all`.
- Configure SMTP before testing password reset, account verification and license-center mail. Keep `LC_SMTP_*` secrets outside the ZIP; without SMTP, license-center messages are retained only in the private `storage/outbox/` test transport.
- If inbound mail is enabled, set `IMAP_RETENTION_DAYS` (1–3650, or `0` to disable automatic purge) and ensure the worker runs regularly. Confirm the retention policy matches the privacy policy.
- Send a real quote, reply by SMTP, and confirm the delivery and inbound IMAP collection where configured.
- Run PageSpeed Insights on the final public HTTPS URL for mobile and desktop. Address real field/lab findings before paid marketing.
- Confirm `robots.txt`, sitemap, canonical, all enabled language URLs, 404 handling and HTTPS redirects.
- Take an encrypted backup and verify a restore in a non-production directory.
- For Plesk/cPanel, verify the document root is `public/` (or `httpdocs/public/`), `storage/` is writable but not public, HTTPS is forced, and the cron command uses the selected PHP binary.
- Run `php bin/hosting-check.php` on the target PHP runtime (or upload it temporarily as `hosting-check.php`) and remove the diagnostic file after the check.
