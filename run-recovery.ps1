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

# Colors for output
$Green = 'Green'
$Red = 'Red'
$Yellow = 'Yellow'
$Blue = 'Cyan'

function Write-Success {
    Write-Host "✓ $args" -ForegroundColor $Green
}

function Write-Error_ {
    Write-Host "❌ $args" -ForegroundColor $Red
}

function Write-Warning_ {
    Write-Host "⚠️  $args" -ForegroundColor $Yellow
}

function Write-Info {
    Write-Host "ℹ️  $args" -ForegroundColor $Blue
}

function Write-Header {
    Write-Host "`n========================================" -ForegroundColor $Blue
    Write-Host "$args" -ForegroundColor $Blue
    Write-Host "========================================`n" -ForegroundColor $Blue
}

# Get backend directory
$backendDir = Join-Path $PSScriptRoot ".." -Resolve
$projectRoot = Split-Path $backendDir

Write-Host "`nHealthcare CMS Recovery Script" -ForegroundColor $Blue
Write-Host "Project: $projectRoot" -ForegroundColor $Blue

# Step 0: Pre-flight checks
Write-Header "STEP 0: Pre-flight Checks"

# Check if MySQL is accessible
Write-Info "Checking MySQL connection..."
try {
    $db = [System.Data.Odbc.OdbcConnection]::new("Driver={MySQL ODBC 8.0 ANSI Driver};Server=localhost;Database=healthcare_cms;Uid=root;Pwd=;")
    $db.Open()
    $db.Close()
    Write-Success "MySQL is accessible"
} catch {
    Write-Error_ "Cannot connect to MySQL"
    Write-Error_ "Make sure MySQL is running in XAMPP Control Panel"
    exit 1
}

# Step 1: Diagnostics
Write-Header "STEP 1: Running Diagnostics"

Write-Info "Checking database state..."
$output = & php "$backendDir\scripts\diagnose.php"
Write-Host $output

# Step 2: Prepare media
Write-Header "STEP 2: Preparing Media Files"

Write-Info "Running prepare_media.php..."
$output = & php "$backendDir\scripts\prepare_media.php"
Write-Host $output

if ($LASTEXITCODE -eq 0) {
    Write-Success "Media files prepared"
} else {
    Write-Error_ "Failed to prepare media files"
    exit 1
}

# Step 3: Restore media DB
Write-Header "STEP 3: Restoring Media Database Records"

Write-Info "Running restore_media_db.php..."
$output = & php "$backendDir\scripts\restore_media_db.php"
Write-Host $output

if ($LASTEXITCODE -eq 0) {
    Write-Success "Media records restored"
} else {
    Write-Error_ "Failed to restore media records"
}

# Step 4: Regenerate HTML
Write-Header "STEP 4: Regenerating Page HTML"

Write-Info "Running regenerate_html.php..."
$output = & php "$backendDir\scripts\regenerate_html.php"
Write-Host $output

if ($LASTEXITCODE -eq 0) {
    Write-Success "HTML regenerated"
} else {
    Write-Error_ "Failed to regenerate HTML"
}

# Step 5: Final diagnostics
Write-Header "STEP 5: Final Verification"

Write-Info "Running final diagnostics..."
$output = & php "$backendDir\scripts\diagnose.php"
Write-Host $output

Write-Success "Recovery process completed!"

Write-Header "Next Steps"

Write-Info "1. Verify XAMPP is running (Apache + MySQL)"
Write-Info "2. Run sync-to-xampp.ps1 to deploy to local XAMPP"
Write-Info "3. Open http://localhost/healthcare-cms to test"
Write-Info "4. Check:"
Write-Info "   - Media library shows files"
Write-Info "   - Public pages load with SERVED=pre-rendered"
Write-Info "   - Collection pages show all materials"

Write-Host "`nDone!`n" -ForegroundColor $Green
