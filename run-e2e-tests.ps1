# E2E Test Runner for Windows
# Запуск: .\run-e2e-tests.ps1

$ErrorActionPreference = "Stop"

Write-Host "🧪 Starting E2E Tests for Healthcare CMS" -ForegroundColor Cyan
Write-Host ""

# 1. Check if server is already running
Write-Host "1️⃣  Checking if server is running on port 8089..." -ForegroundColor Yellow
$serverRunning = Test-NetConnection -ComputerName 127.0.0.1 -Port 8089 -InformationLevel Quiet -WarningAction SilentlyContinue

if (-not $serverRunning) {
    Write-Host "   ❌ Server not running!" -ForegroundColor Red
    Write-Host ""
    Write-Host "📝 Please start the server in a separate window:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "cd backend" -ForegroundColor White
    Write-Host "`$env:DB_DEFAULT='sqlite'" -ForegroundColor White
    Write-Host "`$env:DB_DATABASE=`"`$PWD\tests\tmp\e2e.sqlite`"" -ForegroundColor White
    Write-Host "php -S 127.0.0.1:8089 -t public" -ForegroundColor White
    Write-Host ""
    exit 1
}

Write-Host "   ✅ Server is running" -ForegroundColor Green
Write-Host ""

# 2. Run PHPUnit tests
Write-Host "2️⃣  Running PHPUnit E2E tests..." -ForegroundColor Yellow
cd backend

& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --filter testPageEditWorkflow tests\E2E\HttpApiE2ETest.php

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ All E2E tests passed!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "❌ Some tests failed. Check output above." -ForegroundColor Red
    exit 1
}
