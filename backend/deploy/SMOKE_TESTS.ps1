# Simple smoke tests for staging after deploy
# Usage: .\SMOKE_TESTS.ps1 -BaseUrl "http://localhost/healthcare-cms-frontend"
param(
    [string]$BaseUrl = "http://localhost/healthcare-cms-frontend"
)

Write-Host "Running smoke tests against $BaseUrl"

$results = @()

function Check-Url($path, $expectSubstring) {
    $url = "$BaseUrl/$path".TrimEnd('/')
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
        $body = $resp.Content
        if ($body -like "*$expectSubstring*") { return @{ok=$true; url=$url} }
        return @{ok=$false; url=$url; msg="substring not found"}
    } catch {
        return @{ok=$false; url=$url; msg=$_.Exception.Message}
    }
}

$checks = @(
    @{path="index.php"; expect="Test Page"},
    @{path="/"; expect="Test Page"},
    @{path="editor/"; expect="editor"}
)

foreach ($c in $checks) {
    $r = Check-Url $c.path $c.expect
    if ($r.ok) { Write-Host "OK: $($r.url)" -ForegroundColor Green } else { Write-Host "FAIL: $($r.url) => $($r.msg)" -ForegroundColor Red }
    $results += $r
}

$failed = $results | Where-Object { $_.ok -eq $false }
if ($failed) {
    Write-Host "Smoke tests failed." -ForegroundColor Red
    exit 1
}

Write-Host "All smoke tests passed." -ForegroundColor Green
exit 0
