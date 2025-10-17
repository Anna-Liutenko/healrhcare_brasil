# 🚀 Start E2E Server (Windows-safe)
# Этот скрипт запускает сервер БЕЗ auto_prepend (обходит проблему с кириллицей)

$ErrorActionPreference = "Stop"

Write-Host "🔧 Preparing E2E environment..." -ForegroundColor Cyan

# 1. Setup environment
$env:DB_DEFAULT = 'sqlite'
$env:DB_DATABASE = "$PWD\tests\tmp\e2e.sqlite"

# 2. Ensure bootstrap runs (creates DB + schema)
Write-Host "📦 Running test bootstrap..." -ForegroundColor Yellow
php -r "require 'tests/_bootstrap.php'; echo 'Bootstrap OK' . PHP_EOL;"

# 3. Start server WITHOUT auto_prepend
Write-Host "🌐 Starting development server on http://127.0.0.1:8089" -ForegroundColor Green
Write-Host "   Press Ctrl+C to stop" -ForegroundColor Gray
Write-Host ""

php -S 127.0.0.1:8089 -t public
