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

The release is **not yet suitable for marketplace submission** because customer branding remains in source code and documentation, including the readme, installation guide, management interface, email subjects, seed content, brand generator and backup naming. A passing scan must return no customer identifiers outside an explicitly separated Kaş-only profile.

Do not remove this scan gate or market the generic ZIP as customer-neutral until it passes.
