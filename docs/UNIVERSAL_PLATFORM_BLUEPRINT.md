# TofiDesk Universal Platform blueprint

## Product boundary

`kasnakliyat.com` is the first branded demo and reference implementation.
The reusable product is a sector-neutral CMS runtime: a site owner chooses a
sector pack, content types, theme and plugins without receiving transport-only
tables or prompts.

## Runtime layers

1. Core runtime: authentication, roles, settings, media, content, migrations,
   cache, queue, cron, audit and secure HTTP/API boundaries. `bin/cron.php`
   is a bounded cPanel/Plesk entrypoint: it migrates safely, removes expired
   cache rows, recovers stale worker claims and processes the platform queue.
2. Registries: modules, hooks, content types, fields, taxonomies, locations,
   search documents, webhooks and roles/permissions. These are the extension
   contract, not a copy of the Kas Nakliyat application.
3. Sector packs: optional definitions for transport, real estate, healthcare,
   restaurant, ecommerce, agency, education, booking, business services and
   corporate. A pack adds
   schema and labels; it must not fork the runtime.
4. Presentation: themes and child themes consume content types through
   documented sections and hooks. Theme settings are data, so a theme can be
   changed without losing content.
5. Product centre: products, plans, releases, packages, licences, domains,
   downloads, updates, support and marketplace editorial approval.

## Module manifest contract

Every first-party or approved third-party module must declare:

```json
{
  "slug": "real-estate-pack",
  "name": "Real estate pack",
  "version": "1.0.0",
  "kind": "sector",
  "requires": {"tofidesk": ">=1.0.0", "php": ">=8.2"},
  "content_types": ["listing", "agent"],
  "taxonomies": ["property_type", "amenity"],
  "hooks": ["content.saved", "search.reindex"],
  "permissions": ["listing.manage"],
  "translations": ["tr", "en"],
  "license": "commercial"
}
```

The installer validates the manifest, dependencies, package hash and signed
release before enabling a module. Quarantined or incompatible modules cannot
register hooks or execute public requests.

The current admin shell exposes `/admin/modules` for first-party pack status
and safe enable/disable operations, and `/admin/locations` for browsing the
country tree and importing validated JSON location packs. `php bin/module.php validate modules` is a
portable pre-release check and never executes module code.

The versioned developer API is available under `/api/platform/v1`:
`modules` exposes approved public module metadata, including compatibility
requirements, content types, taxonomies, declared hooks, permissions and
non-secret settings schemas. `locations` exposes the country-to-neighborhood
tree, and `search` exposes localized text, type, taxonomy, location, radius,
sort and facet results. Public reads are cacheable;
the `POST /forms/submit` write endpoint is protected by a server-side
`PLATFORM_API_TOKEN` (sent as `X-TofiDesk-API-Key` or a Bearer token), limits
JSON payload size and persists submissions through the same validation path as
the web forms. No write token is shipped in a package; each deployment must
generate its own secret in the environment.

Approved modules may declare only the platform event names they consume. Runtime
registration uses `platform_register_hook()` and rejects undeclared events,
unknown handlers and priorities outside the bounded range. `platform_dispatch()`
ignores unknown events and isolates handler exceptions so one extension cannot
break the request. A module's configurable sector state is stored separately in
`platform_module_settings`, never mixed into core content tables.

Developer commands are intentionally small and portable: `php bin/module.php
scaffold <slug> <name> [sector|plugin]` creates a validated sector-pack under
`modules/` or a plugin manifest under `plugins/`, and
  `php bin/theme.php scaffold <slug> <name>` creates a 14-locale, dependency-aware
  data-only theme manifest. Child themes declare `parent` and override only
  selected sections/tokens; the bundled `nexa-business-child` is the reference
  inheritance example. Both commands refuse to overwrite an existing
directory; package code is still reviewed and enabled only after the release
quality gate.

## Location and discovery contract

Locations use a country → region/province → city → district → neighborhood /
postal-area tree. Every listing-like content type may reference a location.
Search accepts locale, type, taxonomy, location and text; an indexed search
adapter may replace the portable SQLite fallback without changing the public
contract. The portable location search is also exposed before a paid map
provider is configured; a maps adapter can later add geocoding, autocomplete,
distance and static-map capabilities without changing content or API
consumers. Favorites, comparisons, moderated reviews and locale-aware
popularity scores are separate discovery records, so products, themes,
plugins and sector content can share the same catalogue UX without coupling
their schemas.

The read-only discovery API includes `related`, `reviews` and `popular`.
Popularity is an explicit weighted score (views, favorites and downloads),
with bounded increments and no visitor-identifying data stored in the score
table.

## One-month delivery slices

- Slice A: registries, migration safety, module manifest validation and core
  admin screens.
- Slice B: sector pack SDK and three reference packs (transport, real estate,
  business services), plus location import contract.
- Slice C: theme sections, child overrides, plugin hooks, compatibility and
  developer documentation, including the data-only theme manifest and bounded
  hook registration contract.
- Slice D: public search/filter/location UX, localized SEO and marketplace
  packaging.
- Slice E: automated E2E, installer/upgrade/rollback rehearsal and release
  audit. PayTR, SMTP and external map/AI credentials remain deployment gates.

The goal is an extensible platform, not a hard-coded collection of every
industry. New industries should be installable as reviewed packs while the
same core, licence and update system remains intact.
