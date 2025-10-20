# Deploy to staging wrapper
# This script calls existing sync-to-xampp.ps1 and then runs smoke tests
param(
    [string]$SyncScript = "sync-to-xampp.ps1",
    [string]$SmokeScript = "deploy/SMOKE_TESTS.ps1",
    [string]$BaseDir = "$(Split-Path -Parent $MyInvocation.MyCommand.Path)\.."
)

Write-Host "Starting staging deploy..."

# Run sync script (assumes it's present in repo root)
$syncPath = Join-Path $BaseDir $SyncScript
if (Test-Path $syncPath) {
    Write-Host "Running sync script: $syncPath"
    & powershell -NoProfile -ExecutionPolicy Bypass -File $syncPath
} else {
    Write-Host "Sync script not found at $syncPath" -ForegroundColor Yellow
}

# Run smoke tests
$smokePath = Join-Path $BaseDir $SmokeScript
if (Test-Path $smokePath) {
    Write-Host "Running smoke tests: $smokePath"
    & powershell -NoProfile -ExecutionPolicy Bypass -File $smokePath
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) { Write-Host "Smoke tests failed (exit $exitCode)" -ForegroundColor Red; exit $exitCode }
} else {
    Write-Host "Smoke tests script not found at $smokePath" -ForegroundColor Yellow
}

Write-Host "Staging deploy complete." -ForegroundColor Green
