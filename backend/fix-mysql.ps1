# Fix XAMPP MySQL root privileges
# This script repairs the mysql.user table to restore root access

Write-Host "=== XAMPP MySQL Privileges Repair ===" -ForegroundColor Cyan
Write-Host "This will temporarily stop MySQL, fix root privileges, and restart it.`n" -ForegroundColor Yellow

$mysqlBin = "C:\xampp\mysql\bin"
$mysqlExe = Join-Path $mysqlBin "mysqld.exe"
$mysqladminExe = Join-Path $mysqlBin "mysqladmin.exe"
$mysqlCliExe = Join-Path $mysqlBin "mysql.exe"
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$fixSqlFile = Join-Path $scriptPath "fix-mysql-root.sql"

# Check if files exist
if (-not (Test-Path $mysqlExe)) {
    Write-Host "[✗] MySQL executable not found: $mysqlExe" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $fixSqlFile)) {
    Write-Host "[✗] SQL fix file not found: $fixSqlFile" -ForegroundColor Red
    exit 1
}

Write-Host "[→] Step 1: Stopping MySQL..." -ForegroundColor Cyan
try {
    & $mysqladminExe -u root shutdown 2>$null
    Start-Sleep -Seconds 2
    Write-Host "[✓] MySQL stopped`n" -ForegroundColor Green
} catch {
    Write-Host "[!] Could not stop MySQL gracefully (may already be stopped): $_`n" -ForegroundColor Yellow
}

Write-Host "[→] Step 2: Starting MySQL with --skip-grant-tables..." -ForegroundColor Cyan
try {
    # Start MySQL with skip-grant-tables in background
    $process = Start-Process -FilePath $mysqlExe `
        -ArgumentList '--skip-grant-tables' `
        -WindowStyle Hidden `
        -PassThru `
        -ErrorAction Stop
    
    Write-Host "[✓] MySQL started in recovery mode`n" -ForegroundColor Green
    Start-Sleep -Seconds 3
} catch {
    Write-Host "[✗] Failed to start MySQL: $_" -ForegroundColor Red
    exit 1
}

Write-Host "[→] Step 3: Applying privilege fixes..." -ForegroundColor Cyan
try {
    # Run the SQL fix script
    $output = & $mysqlCliExe -u root --skip-password < $fixSqlFile 2>&1
    Write-Host "[✓] SQL script executed`n" -ForegroundColor Green
    Write-Host $output
} catch {
    Write-Host "[✗] Failed to apply fixes: $_" -ForegroundColor Red
    exit 1
}

Write-Host "[→] Step 4: Stopping temporary MySQL instance..." -ForegroundColor Cyan
try {
    # Kill the process we started
    Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    
    Write-Host "[✓] MySQL stopped`n" -ForegroundColor Green
}
catch {
    Write-Host "[!] Could not stop MySQL: $_`n" -ForegroundColor Yellow
}

Write-Host "[✓✓✓] Repair Complete! ===" -ForegroundColor Green
Write-Host "`nPlease restart MySQL in XAMPP Control Panel and then test:" -ForegroundColor Cyan
Write-Host "    php test-login-flow.php`n" -ForegroundColor Gray
