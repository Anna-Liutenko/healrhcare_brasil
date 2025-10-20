param(
    [string]$BaseUrl = 'http://127.0.0.1:8089',
    [string]$Slug = 'testovaya-e2e-1'
)

$ErrorActionPreference = 'Stop'
$reportPath = Join-Path -Path ..\logs\deploy_verify -ChildPath ("e2e_publish_{0}.md" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))

function Log($msg) { "$(Get-Date -Format 's') | $msg" | Out-File -FilePath $reportPath -Append -Encoding utf8 }

Log "Starting E2E publish+check for slug=$Slug base=$BaseUrl"

# 1) Create page
$payload = @{ title = 'E2E Test Page'; slug = $Slug; createdBy = 'e2e-script'; status = 'published' } | ConvertTo-Json -Depth 10
Log "Creating page via POST /api/pages"
$resp = Invoke-WebRequest -Uri "$BaseUrl/api/pages" -Method POST -Body $payload -ContentType 'application/json' -UseBasicParsing -TimeoutSec 20
$body = $resp.Content | ConvertFrom-Json
Log "Create response: $($resp.StatusCode) -> $($resp.Content)"

$pageId = $body.pageId
if (-not $pageId) { Log "FAILED: no pageId returned"; exit 2 }

# 2) Update page with blocks to ensure meaningful content
Log "Updating page $pageId with blocks"
$updateBody = @{
    title = 'E2E Test Page UPDATED'
    seoDescription = 'Updated SEO Description'
    blocks = @(
        @{ type = 'hero'; position = 0; content = @{ heading = 'E2E Hero Block'; subheading = 'Test subheading' } },
        @{ type = 'article-cards'; position = 1; content = @{ title = 'Latest Articles'; columns = 2; cards = @(
            @{ title = 'Card One'; text = 'First card text'; image = 'https://via.placeholder.com/400x250' },
            @{ title = 'Card Two'; text = 'Second card text'; image = 'https://via.placeholder.com/400x250' }
        ) } }
    )
} | ConvertTo-Json -Depth 10

$upd = Invoke-WebRequest -Uri "$BaseUrl/api/pages/$pageId" -Method PUT -Body $updateBody -ContentType 'application/json' -UseBasicParsing -TimeoutSec 20
Log "Update response: $($upd.StatusCode) -> $($upd.Content)"

# 3) Publish page (ensure renderedHtml created)
Log "Publishing page $pageId"
$pub = Invoke-WebRequest -Uri "$BaseUrl/api/pages/$pageId/publish" -Method PUT -UseBasicParsing -TimeoutSec 20
Log "Publish response: $($pub.StatusCode) -> $($pub.Content)"

# 3) GET public page (full path)
Start-Sleep -Seconds 1
Log "Requesting public page /page/$Slug"
try {
    $public = Invoke-WebRequest -Uri "$BaseUrl/page/$Slug" -UseBasicParsing -TimeoutSec 10
    Log "Public page status: $($public.StatusCode)"
    if ($public.StatusCode -ne 200) { Log "FAILED: public page returned $($public.StatusCode)"; exit 3 }
} catch {
    Log "FAILED: exception when getting public page: $_"; exit 3
}

# 4) GET short URL /p/{slug}
Log "Requesting short URL /p/$Slug"
try {
    $short = Invoke-WebRequest -Uri "$BaseUrl/p/$Slug" -UseBasicParsing -TimeoutSec 10
    Log "Short URL status: $($short.StatusCode)"
    if ($short.StatusCode -ne 200) { Log "FAILED: short URL returned $($short.StatusCode)"; exit 4 }
} catch {
    Log "FAILED: exception when getting short URL: $_"; exit 4
}

# 5) Verify renderedHtml exists via API
$pages = Invoke-WebRequest -Uri "$BaseUrl/api/pages" -UseBasicParsing -TimeoutSec 10 | ConvertFrom-Json
$found = $pages | Where-Object { $_.slug -eq $Slug }
if (-not $found) { Log "FAILED: page not found in /api/pages"; exit 5 }

if ([string]::IsNullOrEmpty($found.renderedHtml)) {
    Log "FAILED: renderedHtml is empty after publish"
    exit 6
}

# Content-level checks: look for hero and cards markers in renderedHtml and public HTML
$apiHtml = $found.renderedHtml
$hasHero = $apiHtml -match '<section class="hero"' -or $apiHtml -match 'block-hero'
$hasCards = $apiHtml -match 'articles-grid' -or $apiHtml -match 'article-card'
if (-not ($hasHero -and $hasCards)) {
    Log "FAILED: renderedHtml missing expected content markers (hero=$hasHero cards=$hasCards)"
    Log "RenderedHtml head:\n$($apiHtml.Substring(0, [Math]::Min(400, $apiHtml.Length)))"
    exit 7
}

$pubHtml = $public.Content
$pubHasHero = $pubHtml -match '<section class="hero"' -or $pubHtml -match 'block-hero'
$pubHasCards = $pubHtml -match 'articles-grid' -or $pubHtml -match 'article-card'
if (-not ($pubHasHero -and $pubHasCards)) {
    Log "FAILED: public HTML missing expected content markers (hero=$pubHasHero cards=$pubHasCards)"
    Log "Public page head:\n$($pubHtml.Substring(0, [Math]::Min(400, $pubHtml.Length)))"
    exit 8
}

Log "SUCCESS: renderedHtml exists, contains hero/cards, and public endpoints returned 200 with expected content"
Log "Public page snippet:\n$($public.Content.Substring(0, [Math]::Min(400, $public.Content.Length)))"

Write-Host "E2E completed. Report written to $reportPath" -ForegroundColor Green
