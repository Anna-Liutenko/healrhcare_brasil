# Install Composer Dependencies
# Run this script to set up vendor folder with all dependencies

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "COMPOSER DEPENDENCIES SETUP" -ForegroundColor Cyan
Write-Host "===========================" -ForegroundColor Cyan
Write-Host ""

$backendPath = Join-Path $PSScriptRoot "backend"

# Check if composer is installed
Write-Host "Checking for Composer..." -ForegroundColor Yellow

$composerExists = $false
try {
    $composerVersion = php -r "if (file_exists('composer.phar')) echo 'local'; else echo 'none';"
    if ($composerVersion -eq "none") {
        # Try global composer
        $null = composer --version 2>&1
        $composerExists = $true
        $composerCmd = "composer"
        Write-Host "  OK Global composer found" -ForegroundColor Green
    }