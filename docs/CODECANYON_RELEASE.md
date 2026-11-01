# CodeCanyon release package

Build the buyer archive with:

```sh
python3 bin/package.py --profile marketplace --output=/tmp/transport-cms-marketplace.zip
```

The marketplace profile deliberately removes Kaş Nakliyat customer assets, supplied truck icon, contact configuration and hero photographs. It inserts neutral placeholders and an empty `config/site.php`; buyers enter their own identity through `/install` and the administration panel.

Before submission, verify the archive contains no `.env`, SQLite database, media, backups, credentials, customer email/phone/address, or Kaş-branded asset. Run the release tests in `tests/README.md`, attach server requirements and include the marketplace support policy.
