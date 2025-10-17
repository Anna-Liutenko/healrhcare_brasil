# Quick Sync to XAMPP
# Usage: .\quick-sync.ps1

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "QUICK SYNC TO XAMPP" -ForegroundColor Cyan
Write-Host "===================" -ForegroundColor Cyan
Write-Host ""

# Paths
$projectPath = $PSScriptRoot
$backendSource = Join-Path $projectPath "backend"
$frontendSource = Join-Path $projectPath "frontend"
$backendTarget = "C:\xampp\htdocs\healthcare-cms-backend"
$frontendTarget = "C:\xampp\htdocs\visual-editor-standalone"

# Check if frontend is symlink
$frontendItem = Get-Item $frontendTarget -ErrorAction SilentlyContinue
$frontendIsSymlink = $frontendItem -and $frontendItem.LinkType -eq "SymbolicLink"

# Check if backend is symlink
$backendItem = Get-Item $backendTarget -ErrorAction SilentlyContinue
$backendIsSymlink = $backendItem -and $backendItem.LinkType -eq "SymbolicLink"

# Frontend
Write-Host "Frontend:" -ForegroundColor Yellow
if ($frontendIsSymlink) {
    Write-Host "  OK Symlink active - no sync needed" -ForegroundColor Green
    Write-Host "  -> $($frontendItem.Target)" -ForegroundColor Gray
} else {
    Write-Host "  Copying files..." -ForegroundColor Yellow
    robocopy "$frontendSource" "$frontendTarget" /MIR /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
    if ($LASTEXITCODE -le 7) {
        Write-Host "  OK Frontend synced" -ForegroundColor Green
    } else {
        Write-Host "  ERROR Frontend sync failed (code: $LASTEXITCODE)" -ForegroundColor Red
    }
}

Write-Host ""

# Backend
Write-Host "Backend:" -ForegroundColor Yellow
if ($backendIsSymlink) {
    Write-Host "  OK Symlink active - no sync needed" -ForegroundColor Green
    Write-Host "  -> $($backendItem.Target)" -ForegroundColor Gray
} else {
    Write-Host "  Copying files..." -ForegroundColor Yellow
    robocopy "$backendSource" "$backendTarget" /MIR /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
    if ($LASTEXITCODE -le 7) {
        Write-Host "  OK Backend synced" -ForegroundColor Green
    } else {
        Write-Host "  ERROR Backend sync failed (code: $LASTEXITCODE)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "===================" -ForegroundColor Cyan
Write-Host "DONE!" -ForegroundColor Green
Write-Host "===================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Open in browser:" -ForegroundColor Yellow
Write-Host "  http://localhost/visual-editor-standalone/editor.html" -ForegroundColor White
Write-Host ""
Write-Host "TIP: Refresh page with Ctrl+Shift+R" -ForegroundColor Gray
Write-Host ""
