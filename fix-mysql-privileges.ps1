#!/usr/bin/env powershell
# Fix XAMPP MySQL root privileges
# This script stops MySQL, starts it in safe mode, fixes privileges, and restarts normally

# Check if running as administrator
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Please right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Red
    exit 1
}

$xamppPath = "C:\xampp"
$mysqlBinPath = "$xamppPath\mysql\bin"
$mysqld = "$mysqlBinPath\mysqld.exe"
$mysql = "$mysqlBinPath\mysql.exe"

if (-not (Test-Path $mysqld)) {
    Write-Host "ERROR: mysqld.exe not found at $mysqld" -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "XAMPP MySQL Privilege Fix Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Stop MySQL service
Write-Host "[Step 1] Stopping MySQL..." -ForegroundColor Yellow
Stop-Service -Name "MySQL" -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2
Write-Host "[✓] MySQL stopped" -ForegroundColor Green
Write-Host ""

# Step 2: Start MySQL in safe mode (skip-grant-tables)
Write-Host "[Step 2] Starting MySQL in safe mode (--skip-grant-tables)..." -ForegroundColor Yellow
$mysqlProcess = Start-Process -FilePath $mysqld -ArgumentList "--skip-grant-tables" -PassThru -NoNewWindow
Start-Sleep -Seconds 3

if (-not $mysqlProcess.HasExited) {
    Write-Host "[✓] MySQL started in safe mode" -ForegroundColor Green
} else {
    Write-Host "[✗] MySQL failed to start" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Step 3: Run SQL fix script
Write-Host "[Step 3] Applying privilege fixes..." -ForegroundColor Yellow

$sqlCommands = @"
USE mysql;

-- Clear existing root users
DELETE FROM user WHERE User='root';

-- Create root@localhost without password
INSERT INTO user (Host, User, authentication_string, ssl_type, ssl_cipher, x509_issuer, x509_subject, is_role)
VALUES ('localhost', 'root', '', '', '', '', '', 'N');

-- Create root@127.0.0.1 without password  
INSERT INTO user (Host, User, authentication_string, ssl_type, ssl_cipher, x509_issuer, x509_subject, is_role)
VALUES ('127.0.0.1', 'root', '', '', '', '', '', 'N');

-- Create root@% (any host) without password
INSERT INTO user (Host, User, authentication_string, ssl_type, ssl_cipher, x509_issuer, x509_subject, is_role)
VALUES ('%', 'root', '', '', '', '', '', 'N');

-- Grant full privileges
UPDATE user SET 
    Select_priv='Y',
    Insert_priv='Y',
    Update_priv='Y',
    Delete_priv='Y',
    Create_priv='Y',
    Drop_priv='Y',
    Grant_priv='Y',
    References_priv='Y',
    Index_priv='Y',
    Alter_priv='Y',
    Create_tmp_table_priv='Y',
    Lock_tables_priv='Y',
    Execute_priv='Y',
    Create_view_priv='Y',
    Show_view_priv='Y',
    Create_routine_priv='Y',
    Alter_routine_priv='Y',
    Trigger_priv='Y',
    Create_user_priv='Y',
    Event_priv='Y',
    Create_tablespace_priv='Y'
WHERE User='root';

-- Flush privileges
FLUSH PRIVILEGES;

-- Verify
SELECT Host, User FROM user WHERE User='root';
"@

# Save SQL to temp file
$tempSql = "$xamppPath\temp_fix_privileges.sql"
$sqlCommands | Out-File -FilePath $tempSql -Encoding UTF8 -Force

# Execute SQL
Write-Host "Running SQL commands..."
& $mysql -u root --skip-password < $tempSql 2>&1 | ForEach-Object { Write-Host "  $_" }

Remove-Item -Path $tempSql -Force -ErrorAction SilentlyContinue

Write-Host "[✓] Privileges updated" -ForegroundColor Green
Write-Host ""

# Step 4: Kill safe mode MySQL and start normally
Write-Host "[Step 4] Restarting MySQL in normal mode..." -ForegroundColor Yellow
Stop-Process -Id $mysqlProcess.Id -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

# Start MySQL service normally
Start-Service -Name "MySQL" -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3

Write-Host "[✓] MySQL restarted in normal mode" -ForegroundColor Green
Write-Host ""

# Step 5: Verify connection
Write-Host "[Step 5] Verifying connection..." -ForegroundColor Yellow
try {
    $output = & $mysql -u root --skip-password -e "SELECT @@version;" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[✓] Connection successful!" -ForegroundColor Green
        Write-Host "MySQL version: $output" -ForegroundColor Green
    } else {
        Write-Host "[✗] Connection failed: $output" -ForegroundColor Red
    }
} catch {
    Write-Host "[✗] Error verifying connection: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "MySQL privilege fix completed!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
