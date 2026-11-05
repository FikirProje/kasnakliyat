# Marketplace archive scan

Run this scan against the exact ZIP intended for a marketplace submission:

```sh
python3 bin/package.py --profile marketplace --output=/tmp/transport-cms-marketplace.zip
rm -rf /tmp/transport-cms-scan && mkdir /tmp/transport-cms-scan
unzip -q /tmp/transport-cms-marketplace.zip -d /tmp/transport-cms-scan
rg -n -i 'Kaş Nakliyat|kasnakliyat|Bayındır|505 219|bilgi@kas' /tmp/transport-cms-scan
```

## Current result

The asset and runtime-data exclusions work: the archive excludes `.env`, SQLite files, media, sessions, backups, customer config, supplied hero images and supplied logo files. Neutral logo and favicon placeholders are injected.

The generic archive now passes the customer-identifier scan. Customer branding is removed or replaced in the marketplace profile, supplied customer media and runtime data are excluded, and neutral TofiDesk assets are injected. The Kaş Nakliyat deployment remains a separate branded profile.

Keep this scan as a release gate: run it against the exact ZIP before each marketplace publication.
