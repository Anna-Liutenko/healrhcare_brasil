#!/usr/bin/env pwsh
# Refactoring Verification Checklist

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "    Refactoring Verification Check    " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`n[1] PHP Files Check..." -ForegroundColor Yellow
$phpFiles = @(
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\CollectionCardRenderer.php",
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\CollectionHtmlBuilder.php",
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php"
)

foreach ($file in $phpFiles) {
    if (Test-Path $file) {
        Write-Host "  OK: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
    } else {
        Write-Host "  MISSING: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Red
    }
}

Write-Host "`n[2] JavaScript Files Check..." -ForegroundColor Yellow
$jsFiles = @(
    "C:\xampp\htdocs\healthcare-cms-frontend\modules\card-templates.js",
    "C:\xampp\htdocs\healthcare-cms-frontend\collection-tabs.js"
)

foreach ($file in $jsFiles) {
    if (Test-Path $file) {
        Write-Host "  OK: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
    } else {
        Write-Host "  MISSING: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Red
    }
}

Write-Host "`n[3] PHP Syntax Check..." -ForegroundColor Yellow
foreach ($file in $phpFiles) {
    if (Test-Path $file) {
        $output = php -l $file 2>&1
        if ($output -like "*No syntax errors*") {
            Write-Host "  OK: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
        } else {
            Write-Host "  ERROR: $([System.IO.Path]::GetFileName($file))" -ForegroundColor Red
        }
    }
}

Write-Host "`n[4] Key Methods in PHP Classes..." -ForegroundColor Yellow
$methods = @(
    @{ File = "CollectionCardRenderer.php"; Method = "renderCard" },
    @{ File = "CollectionCardRenderer.php"; Method = "sanitizeImageUrl" },
    @{ File = "CollectionHtmlBuilder.php"; Method = "build" }
)

foreach ($check in $methods) {
    $filePath = "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\$($check.File)"
    if (Test-Path $filePath) {
        $content = Get-Content -Path $filePath -Raw
        if ($content -like "*$($check.Method)*") {
            Write-Host "  OK: $($check.File)::$($check.Method)()" -ForegroundColor Green
        } else {
            Write-Host "  MISSING: $($check.File)::$($check.Method)()" -ForegroundColor Red
        }
    }
}

Write-Host "`n[5] Key Functions in JS..." -ForegroundColor Yellow
$jsChecks = @(
    @{ File = "card-templates.js"; Func = "renderCard" },
    @{ File = "card-templates.js"; Func = "escapeHtml" },
    @{ File = "collection-tabs.js"; Func = "CardTemplates.renderCard" }
)

foreach ($check in $jsChecks) {
    if ($check.File -like "*card-templates*") {
        $filePath = "C:\xampp\htdocs\healthcare-cms-frontend\modules\card-templates.js"
    } else {
        $filePath = "C:\xampp\htdocs\healthcare-cms-frontend\collection-tabs.js"
    }
    
    if (Test-Path $filePath) {
        $content = Get-Content -Path $filePath -Raw
        if ($content -like "*$($check.Func)*") {
            Write-Host "  OK: $($check.File)::$($check.Func)" -ForegroundColor Green
        } else {
            Write-Host "  MISSING: $($check.File)::$($check.Func)" -ForegroundColor Red
        }
    }
}

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "   All checks completed! Ready to test" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "`nTest in browser:" -ForegroundColor Cyan
Write-Host "  1. http://localhost/p/all-materials?section=guides" -ForegroundColor Cyan
Write-Host "  2. Click 'Stat'i' tab - heading should change" -ForegroundColor Cyan
Write-Host "  3. Check F12 Console for debug messages" -ForegroundColor Cyan
