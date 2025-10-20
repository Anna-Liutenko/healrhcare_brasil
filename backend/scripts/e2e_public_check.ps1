# Quick E2E public page check
# Usage: .\e2e_public_check.ps1 -BaseUrl http://127.0.0.1:8089 -Slug test-page
param(
    [string]$BaseUrl = 'http://127.0.0.1:8089',
    [string]$Slug = 'testovaya'
)

$ErrorActionPreference = 'Stop'

Write-Host "Checking public page at $BaseUrl/public/page/$Slug ..."

try {
    $resp = Invoke-WebRequest -Uri "$BaseUrl/public/page/$Slug" -UseBasicParsing -TimeoutSec 10
    Write-Host "Status: $($resp.StatusCode)"
    if ($resp.StatusCode -eq 200) {
        Write-Host "OK: Public page returned 200"
        if ($resp.Content -match '/uploads/') { Write-Host "Uploads links present (ok)" }
    } else {
        Write-Host "Non-200 status: $($resp.StatusCode)" -ForegroundColor Red
        exit 2
    }
} catch {
    Write-Host "Request failed: $_" -ForegroundColor Red
    exit 1
}
