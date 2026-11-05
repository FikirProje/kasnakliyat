# Windows local preview

XAMPP is only a local preview environment. It is not required for the production server and it must use PHP 8.2 or newer.

The project can be served directly from the working tree, so files changed by the developer become visible after a browser refresh. Do not copy the project into `htdocs`; this avoids mixing the Kas Nakliyat application and Product Center directories.

## Requirements

- XAMPP with PHP 8.2/8.3
- `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `zip` and `fileinfo` enabled in `C:\xampp\php\php.ini`
- Restarting Apache is not needed when using PHP's built-in preview server

## Kas Nakliyat application preview

Open PowerShell in the repository root:

```powershell
$env:APP_ENV = 'development'
$env:APP_URL = 'http://127.0.0.1:8080'
$env:DB_PATH = "$env:TEMP\tofidesk-local\app.sqlite"
$env:STORAGE_PATH = "$env:TEMP\tofidesk-local\storage"
New-Item -ItemType Directory -Force -Path "$env:TEMP\tofidesk-local\storage" | Out-Null
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080/install` once, create a local administrator, then use `/admin`.

## Product Center preview

Open a second PowerShell window in the repository root:

```powershell
$env:APP_ENV = 'development'
$env:APP_URL = 'http://127.0.0.1:8090'
$env:LICENSE_DB_PATH = "$env:TEMP\tofidesk-local\product-center.sqlite"
$env:LICENSE_STORAGE_PATH = "$env:TEMP\tofidesk-local\product-storage"
New-Item -ItemType Directory -Force -Path "$env:TEMP\tofidesk-local\product-storage" | Out-Null
C:\xampp\php\php.exe -S 127.0.0.1:8090 -t license-center\public license-center\public\index.php
```

Open `http://127.0.0.1:8090/install` to create the Product Center administrator. The marketplace is at `/shop`; administrator login is `/login`.

This local database is isolated under the Windows temporary directory. It never uses the live Plesk database and it is not included in deployment archives.
