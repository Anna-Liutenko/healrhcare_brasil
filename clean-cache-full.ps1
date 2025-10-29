#!/usr/bin/env powershell
# Cache clearing script for Quill migration testing
# Usage: .\clean-cache-full.ps1

param(
    [switch]$NoRestart = $false
)

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "CACHE CLEANUP FOR QUILL MIGRATION TESTING" -ForegroundColor Yellow
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# Check for admin rights
$isAdmin = ([System.Security.Principal.WindowsIdentity]::GetCurrent()).groups -match "S-1-5-32-544"
if (-not $isAdmin) {
    Write-Host "ERROR: This script requires administrator privileges!" -ForegroundColor Red
    Write-Host "Please run PowerShell as Administrator" -ForegroundColor Red
    exit 1
}

Write-Host "Admin privileges confirmed" -ForegroundColor Green
Write-Host ""

# ============================================
# Step 1: Clear PHP sessions and temp files
# ============================================
Write-Host "STEP 1: Clearing PHP sessions and temp files" -ForegroundColor Cyan
Write-Host "───────────────────────────────────────────────" -ForegroundColor Cyan

$phpTmpPath = "C:\xampp\tmp"
$phpSessionsPath = "C:\xampp\tmp\php_sessions"

if (Test-Path $phpSessionsPath) {
    Write-Host "Clearing: $phpSessionsPath"
    Get-ChildItem -Path $phpSessionsPath -Force -ErrorAction SilentlyContinue | 
        Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
    Write-Host "PHP sessions cleared" -ForegroundColor Green
} else {
    Write-Host "INFO: $phpSessionsPath not found (already clean)" -ForegroundColor Gray
}

if (Test-Path $phpTmpPath) {
    Write-Host "Clearing: $phpTmpPath"
    Get-ChildItem -Path $phpTmpPath -Filter "php*" -Force -ErrorAction SilentlyContinue | 
        Where-Object { -not $_.PSIsContainer } |
        Remove-Item -Force -ErrorAction SilentlyContinue
    Write-Host "Temp files cleared" -ForegroundColor Green
}

Write-Host ""

# ============================================
# Step 2: Apache restart
# ============================================
if (-not $NoRestart) {
    Write-Host "STEP 2: Restarting Apache" -ForegroundColor Cyan
    Write-Host "───────────────────────────" -ForegroundColor Cyan
    
    Write-Host "Stopping Apache..." -ForegroundColor Yellow
    Stop-Service Apache2.4 -Force -ErrorAction SilentlyContinue
    
    if ($?) {
        Write-Host "Apache stopped" -ForegroundColor Green
    } else {
        Write-Host "Apache was not running (OK)" -ForegroundColor Gray
    }
    
    Start-Sleep -Seconds 2
    
    Write-Host "Starting Apache..." -ForegroundColor Yellow
    Start-Service Apache2.4 -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
    
    $apacheStatus = Get-Service Apache2.4 -ErrorAction SilentlyContinue
    if ($apacheStatus.Status -eq "Running") {
        Write-Host "Apache started successfully" -ForegroundColor Green
    } else {
        Write-Host "ERROR: Apache failed to start!" -ForegroundColor Red
        Write-Host "Status: $($apacheStatus.Status)" -ForegroundColor Red
        exit 1
    }
    
    Write-Host ""
}

# ============================================
# Step 3: Check XAMPP files
# ============================================
Write-Host "STEP 3: Verifying XAMPP synchronization" -ForegroundColor Cyan
Write-Host "──────────────────────────────────────────" -ForegroundColor Cyan

$xamppFrontendPath = "C:\xampp\htdocs\healthcare-cms-frontend"
$filesToCheck = @(
    @{ path = "$xamppFrontendPath\editor.js"; check = "insertImageFromFile"; name = "editor.js" }
    @{ path = "$xamppFrontendPath\upload.php"; check = "410"; name = "upload.php (deprecated)" }
    @{ path = "$xamppFrontendPath\api-client.js"; check = "uploadMedia"; name = "api-client.js" }
)

$allFilesOk = $true

foreach ($file in $filesToCheck) {
    if (Test-Path $file.path) {
        Write-Host "Found: $($file.name)" -ForegroundColor Green
        
        # Check content
        $content = Get-Content $file.path -Raw
        if ($content -match $file.check) {
            Write-Host "  Contains '$($file.check)': OK" -ForegroundColor Green
        } else {
            Write-Host "  WARNING: Missing '$($file.check)'" -ForegroundColor Yellow
            $allFilesOk = $false
        }
    } else {
        Write-Host "ERROR: $($file.name) NOT FOUND" -ForegroundColor Red
        $allFilesOk = $false
    }
}

Write-Host ""

# ============================================
# Step 4: Check localhost availability
# ============================================
Write-Host "STEP 4: Checking local server availability" -ForegroundColor Cyan
Write-Host "───────────────────────────────────────────" -ForegroundColor Cyan

try {
    $response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    if ($response.StatusCode -eq 200) {
        Write-Host "http://localhost is available (HTTP 200)" -ForegroundColor Green
    } else {
        Write-Host "http://localhost responded with code $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "ERROR: http://localhost is not available. Check Apache." -ForegroundColor Red
}

Write-Host ""

# ============================================
# Final report
# ============================================
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "CLEANUP COMPLETE" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "  1. Open browser"
Write-Host "  2. Press F12 (open DevTools)"
Write-Host "  3. F12 Settings - Enable 'Disable cache (while DevTools is open)'"
Write-Host "  4. Press Ctrl + Shift + R (hard refresh)"
Write-Host "  5. Go to http://localhost/healthcare-cms-frontend/"
Write-Host "  6. Open console (F12 - Console tab)"
Write-Host "  7. Type: typeof app.insertImageFromFile"
Write-Host "     Expected result: 'function' [OK]"
Write-Host ""

Write-Host "READY FOR TESTING!" -ForegroundColor Green
Write-Host ""

# Additional info
if (-not $allFilesOk) {
    Write-Host "WARNING: Some files may not be synchronized!" -ForegroundColor Yellow
    Write-Host "Run: .\sync-to-xampp.ps1" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host "Pro Tips:" -ForegroundColor Cyan
Write-Host "  * If old code appears: Ctrl + Shift + Delete (clear browser cache)" -ForegroundColor Gray
Write-Host "  * If 404 errors: Check Apache is running (green indicator in XAMPP)" -ForegroundColor Gray
Write-Host "  * If cache still interferes: Close browser completely and reopen" -ForegroundColor Gray
Write-Host ""

Write-Host "See CACHE_CLEARING_GUIDE.md for more details" -ForegroundColor Gray
