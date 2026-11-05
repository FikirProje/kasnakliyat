# Global location provider contract

The core stores locations as a parent-linked tree. It does not hard-code one country's administrative dataset. Providers can ship a JSON file and import it with:

```text
php bin/location.php validate locations.json
php bin/location.php import locations.json
```

The payload can be an array or `{ "locations": [...] }`. Each record requires `country_code`, a supported `level` (`country`, `region`, `province`, `city`, `district`, `neighborhood`, or `postal_area`), `name`, and a lowercase URL-safe `slug`. Use `key` and `parent_key` to describe the hierarchy without knowing database IDs. Imports are idempotent on country, level, parent and slug.
