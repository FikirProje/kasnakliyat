# Product Center upload contract

## Target mapping

| Archive | Host | Purpose |
|---|---|---|
| `outputs/product-tofidesk-license-center.zip` | `product.tofidesk.com` | Global product/theme marketplace, customer accounts, orders, licences, releases and support |
| `outputs/product-tofidesk-app-deployment.zip` | `kasnakliyat.com` | Kas Nakliyat branded demo application |
| `outputs/product-tofidesk-marketplace.zip` | Customer installation | Generic TofiDesk CMS customer package |

## Product Center root

Extract the contents of `product-tofidesk-license-center.zip` directly into the
document root of `product.tofidesk.com` (the same directory that serves
`index.php`). The root must contain these entries:

```text
index.php
installer.php
.htaccess
public/
bin/
storage/
```

Do not create an extra `product-tofidesk-license-center/` directory under the
document root, and do not copy this archive into `kasnakliyat.com`.

## Post-upload checks

1. Open `https://product.tofidesk.com/health.json`.
2. Confirm JSON `product` is `product-center` and the response header
   `X-TofiDesk-Product` is `product-center`.
3. Open `/shop?lang=tr` and confirm the heading is about software, themes and
   tools for every industry, not transport-only copy.
4. Open `/shop?lang=en`, `/shop?lang=ar` and `/shop?lang=ja`; confirm the HTML
   language and RTL direction are correct.
5. Open `/login` for the administrator sign-in. `/install` is available only
   before the first administrator is created; after setup it must remain locked.

The package is source-only and does not include `.env`, SQLite data, customer
uploads, sessions or secrets. Configure the Product Center environment from
`.env.example` and keep `LICENSE_DB_PATH` inside the writable `storage/`
directory.
