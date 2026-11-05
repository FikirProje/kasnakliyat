$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) { throw "XAMPP PHP bulunamadı: $php" }
$previewRoot = Join-Path $env:TEMP 'tofidesk-preview'
New-Item -ItemType Directory -Force -Path $previewRoot, (Join-Path $previewRoot 'app-storage'), (Join-Path $previewRoot 'product-storage') | Out-Null

function Start-Preview([string]$name, [string]$url, [string]$database, [string]$storage, [int]$port, [string]$documentRoot, [string]$router) {
    $env:APP_ENV = 'development'
    $env:APP_URL = $url
    $env:DB_PATH = $database
    $env:STORAGE_PATH = $storage
    $env:LICENSE_DB_PATH = $database
    $env:LICENSE_STORAGE_PATH = $storage
    $stdout = Join-Path $previewRoot ($name + '.log')
    $stderr = Join-Path $previewRoot ($name + '-error.log')
    $existing = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($existing) { return }
    Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$port", '-t', $documentRoot, $router) -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $stdout -RedirectStandardError $stderr | Out-Null
}

Start-Preview 'kasnakliyat' 'http://127.0.0.1:8080' (Join-Path $previewRoot 'app.sqlite') (Join-Path $previewRoot 'app-storage') 8080 'public' 'public/index.php'
Start-Preview 'product-center' 'http://127.0.0.1:8090' (Join-Path $previewRoot 'product-center.sqlite') (Join-Path $previewRoot 'product-storage') 8090 'license-center\public' 'license-center\public\index.php'
Write-Output 'Kas Nakliyat:  http://127.0.0.1:8080/install'
Write-Output 'Product Center: http://127.0.0.1:8090/install.php'
Write-Output "Preview verileri: $previewRoot"
