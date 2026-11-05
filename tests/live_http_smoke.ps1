$ErrorActionPreference = 'Stop'
$centerHealth = Invoke-WebRequest -Uri 'https://product.tofidesk.com/health.json' -UseBasicParsing -TimeoutSec 20
if ($centerHealth.StatusCode -ne 200) { throw 'Product Center health probe did not return HTTP 200' }
if ($centerHealth.Headers['X-TofiDesk-Product'] -ne 'product-center') { throw 'Product Center deployment marker is missing; an old or wrong package is serving the domain' }
$centerHealthJson = $centerHealth.Content | ConvertFrom-Json
if ($centerHealthJson.product -ne 'product-center') { throw 'Product Center health identity mismatch' }
Write-Host "PASS product-center health release=$($centerHealthJson.release)"
$urls = @(
    'https://product.tofidesk.com/',
    'https://product.tofidesk.com/login',
    'https://product.tofidesk.com/shop',
    'https://product.tofidesk.com/pricing',
    'https://kasnakliyat.com/'
)
foreach ($url in $urls) {
    $response = Invoke-WebRequest -Uri $url -Method Get -MaximumRedirection 5 -TimeoutSec 20
    if ([int]$response.StatusCode -ne 200) { throw "$url returned $($response.StatusCode)" }
    # PowerShell's proxy-backed BaseResponse may omit ResponseUri even when
    # the request was HTTPS. Validate the requested origin instead of treating
    # that adapter quirk as a deployment failure.
    if (-not $url.StartsWith('https://', [System.StringComparison]::OrdinalIgnoreCase)) { throw "$url is not an HTTPS test URL" }
    if ($response.Content -match 'HTTP ERROR 500|Internal Server Error|Uygulama kurulumu tamamlanmadı') {
        throw "$url returned an application error marker"
    }
    "PASS $url status=$($response.StatusCode) bytes=$($response.RawContentLength)"
}

$locales = @('tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko')
foreach ($locale in $locales) {
    $url = "https://product.tofidesk.com/shop?lang=$locale"
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 20
    if ($response.StatusCode -ne 200) { throw "Live locale failed: $url ($($response.StatusCode))" }
    if ($response.Content -match 'HTTP ERROR 5|Fatal error|Parse error|Application setup required') { throw "Live locale error marker: $url" }
    if ($response.Content -notmatch '<html[^>]+lang="' + $locale + '"') { throw "Locale attribute missing: $url" }
    if ($response.Content -notmatch 'Global software|Marketplace|software|Software|TofiDesk|Her sektör') { throw "Marketplace copy missing: $url" }
    if ($response.Content -match 'Taşımacılık işletme platformu|Taşımacılık sitelerini|Transport business platform|Sell transport websites|Transport CMS') { throw "Sector-specific marketplace copy leaked: $url" }
    Write-Host "PASS $url status=$($response.StatusCode) bytes=$($response.Content.Length)"
}

$theme = Invoke-WebRequest -Uri 'https://product.tofidesk.com/product?slug=nexa-business-theme&lang=tr' -UseBasicParsing -TimeoutSec 20
if ($theme.StatusCode -ne 200) { throw "Theme product detail failed: $($theme.StatusCode)" }
if ($theme.Content -notmatch 'Kas Nakliyat|Product|Offer|canonical') { throw 'Theme product detail is missing product/SEO data' }
if ($theme.Content -match 'HTTP ERROR 5|Fatal error|Parse error') { throw 'Theme product detail returned an application error' }
Write-Host "PASS https://product.tofidesk.com/product?slug=nexa-business-theme&lang=tr status=$($theme.StatusCode) bytes=$($theme.Content.Length)"
