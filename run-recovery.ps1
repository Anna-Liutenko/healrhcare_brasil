#!/usr/bin/env powershell
<#
.SYNOPSIS
    Complete Recovery Execution Script
    
.DESCRIPTION
    Runs all recovery steps in sequence with proper error handling
    and validation checks after each step
    
.NOTES
    Make sure MySQL and Apache are running in XAMPP before executing
#>

# Get backend directory (using current location instead of PSScriptRoot for better compatibility)
$projectRoot = Get-Location
$backendDir = Join-Path $projectRoot "backend"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Healthcare CMS Recovery Script" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan
Write-Host "Project: $projectRoot" -ForegroundColor Cyan
Write-Host "Backend: $backendDir`n" -ForegroundColor Cyan

# Step 0: Pre-flight checks
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 0: Pre-flight Checks" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Checking if backend directory exists..." -ForegroundColor Yellow
if (-not (Test-Path $backendDir)) {
    Write-Host "[X] Backend directory not found: $backendDir" -ForegroundColor Red
    exit 1
}
Write-Host "[+] Backend directory found" -ForegroundColor Green

Write-Host "[*] Checking if PHP is available..." -ForegroundColor Yellow
$phpCheck = php -v 2>$null
if ($LASTEXITCODE -eq 0) {
    Write-Host "[+] PHP is available" -ForegroundColor Green
    Write-Host "    $($phpCheck[0])" -ForegroundColor Gray
} else {
    Write-Host "[X] PHP not found - make sure XAMPP is running" -ForegroundColor Red
    exit 1
}

# Step 1: Diagnostics
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 1: Running Diagnostics" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Checking database state..." -ForegroundColor Yellow
$diagScript = Join-Path $backendDir "scripts" "diagnose.php"
if (Test-Path $diagScript) {
    & php "$diagScript"
} else {
    Write-Host "[X] Diagnose script not found: $diagScript" -ForegroundColor Red
    exit 1
}

# Step 2: Prepare media
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 2: Preparing Media Files" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Running prepare_media.php..." -ForegroundColor Yellow
$prepareScript = Join-Path $backendDir "scripts" "prepare_media.php"
if (Test-Path $prepareScript) {
    & php "$prepareScript"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[+] Media files prepared successfully" -ForegroundColor Green
    } else {
        Write-Host "[X] Failed to prepare media files" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[X] Prepare media script not found: $prepareScript" -ForegroundColor Red
    exit 1
}

# Step 3: Restore media DB
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 3: Restoring Media Database Records" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Running restore_media_db.php..." -ForegroundColor Yellow
$restoreScript = Join-Path $backendDir "scripts" "restore_media_db.php"
if (Test-Path $restoreScript) {
    & php "$restoreScript"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[+] Media records restored successfully" -ForegroundColor Green
    } else {
        Write-Host "[!] Some media records may have failed (check above)" -ForegroundColor Yellow
    }
} else {
    Write-Host "[X] Restore media script not found: $restoreScript" -ForegroundColor Red
    exit 1
}

# Step 4: Regenerate HTML
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 4: Regenerating Page HTML" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Running regenerate_html.php..." -ForegroundColor Yellow
$regenerateScript = Join-Path $backendDir "scripts" "regenerate_html.php"
if (Test-Path $regenerateScript) {
    & php "$regenerateScript"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[+] HTML regenerated successfully" -ForegroundColor Green
    } else {
        Write-Host "[!] Some HTML pages may have failed (check above)" -ForegroundColor Yellow
    }
} else {
    Write-Host "[X] Regenerate HTML script not found: $regenerateScript" -ForegroundColor Red
    exit 1
}

# Step 5: Final diagnostics
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "STEP 5: Final Verification" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[*] Running final diagnostics..." -ForegroundColor Yellow
& php "$diagScript"

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "RECOVERY COMPLETE" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Green

Write-Host "NEXT STEPS:`n" -ForegroundColor Cyan
Write-Host "1. Fix two code files:" -ForegroundColor Yellow
Write-Host "   - backend/src/Presentation/Controller/PublicPageController.php (line 609, 611-613)" -ForegroundColor Gray
Write-Host "   - backend/src/Application/UseCase/GetCollectionItems.php (after line 135)" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Sync to XAMPP:" -ForegroundColor Yellow
Write-Host "   .\sync-to-xampp.ps1" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Test in browser:" -ForegroundColor Yellow
Write-Host "   - Media library: all files visible" -ForegroundColor Gray
Write-Host "   - Public pages: check SERVED=pre-rendered" -ForegroundColor Gray
Write-Host "   - Collections: all materials visible" -ForegroundColor Gray
Write-Host ""
Write-Host "Done!" -ForegroundColor Green
Write-Host ""
