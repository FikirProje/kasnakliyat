# Final FileZilla upload map

The prepared folders are under `outputs/filezilla-final-20261105/`. Upload the
contents of exactly one folder into the matching domain document root; do not
combine the folders.

| Folder | Destination | Purpose |
|---|---|---|
| `product-center/` | `product.tofidesk.com` `httpdocs/` | TofiDesk global product, theme, licence and sales centre |
| `kasnakliyat/` | `kasnakliyat.com` `httpdocs/` | Kas Nakliyat customer/demo application |
| `marketplace/` | A customer’s new site `httpdocs/` | Generic customer installation package |

The folder contents are already at the document-root level: `index.php`,
`.htaccess`, `public/` or `src/` are directly inside the selected folder.
There is no extra nested project directory to move.

Before a live upload, take a server backup. Product Center and Kas Nakliyat
are separate deployments and must not be merged. The current read-only live
check still sees the old transport-only Product Center deployment, so the
Product Center folder must replace that old mixed deployment before testing
the live URLs.
