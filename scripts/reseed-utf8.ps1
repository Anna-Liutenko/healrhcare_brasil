<#
Reseed the CMS database with UTF-8 (utf8mb4) encoding.

Usage examples:
  # Run with defaults (xampp mysql, user root, no password)
  .\reseed-utf8.ps1

  # Specify DB creds (no interactive prompt)
  .\reseed-utf8.ps1 -MysqlExe "C:\xampp\mysql\bin\mysql.exe" -User root -Password "mysecret"

This script will execute these files (in order):
  database/seeds/users_seed.sql
  database/seeds/pages_seed.sql
  database/seeds/blocks_seed.sql
  database/seeds/SEED_DATA.sql

Notes:
- The seed files include a `SET NAMES utf8mb4` directive so MySQL client must use utf8mb4 too.
- By default this script assumes your database name is `healthcare_cms` and you have a local MySQL server from XAMPP.
- It is recommended to back up your database before running.
#>
param(
    [string]$MysqlExe = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$User = 'root',
    [string]$Password = '',
    [string]$Database = 'healthcare_cms',
    [switch]$Force,
    [switch]$ContinueOnError,
    [switch]$SkipSEEDData
)

function Run-SqlFile($mysql, $user, $password, $db, $file) {
    if (-not (Test-Path $file)) {
        Write-Host "SQL file not found: $file" -ForegroundColor Red
        return $false
    }

    if (-not (Test-Path $mysql)) {
        Write-Host "mysql client not found at: $mysql" -ForegroundColor Red
        return $false
    }

    Write-Host "Importing $file -> $db (using $mysql)" -ForegroundColor Cyan

    # Use mysql client with -e "source <file>" to avoid shell redirection and quoting issues
    # Read file content and pipe to mysql client (PowerShell handles Unicode paths)
    $pwPart = ''
    if ($password -ne '') { $pwPart = "--password=$password" }

    $cmdArgs = @('--default-character-set=utf8mb4', '-u', $user)
    if ($pwPart -ne '') { $cmdArgs += $pwPart }
    $cmdArgs += $db

    if ($ContinueOnError) {
        # --force tells mysql to continue even if SQL errors occur (useful for reseed)
        $cmdArgs = @('--force') + $cmdArgs
    }

    Write-Host "Running: $mysql $($cmdArgs -join ' ') (piping file content)" -ForegroundColor DarkCyan

    try {
        # Read SQL files as UTF-8 to preserve Cyrillic characters
        Get-Content -LiteralPath $file -Raw -Encoding UTF8 | & $mysql @cmdArgs
        $exit = $LASTEXITCODE
    } catch {
        Write-Host "Exception while running mysql: $_" -ForegroundColor Red
        return $false
    }

    if ($exit -ne 0) {
        Write-Host "Import failed for $file (exit code $exit)" -ForegroundColor Red
        return $false
    }

    Write-Host "Imported $file" -ForegroundColor Green
    return $true
}

# Confirm
Write-Host "=== Reseed UTF-8 helper ===" -ForegroundColor Yellow
Write-Host "MySQL client: $MysqlExe"
Write-Host "DB: $Database"
Write-Host "User: $User"
if ($Password -ne '') { Write-Host "Password: (provided)" } else { Write-Host "Password: (empty)" }

# Files (relative to repo root)
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path | Split-Path -Parent
$files = @()
$files += Join-Path $repoRoot 'database\seeds\users_seed.sql'
$files += Join-Path $repoRoot 'database\seeds\pages_seed.sql'
$files += Join-Path $repoRoot 'database\seeds\blocks_seed.sql'
if (-not $SkipSEEDData) {
    $files += Join-Path $repoRoot 'database\seeds\SEED_DATA.sql'
} else {
    Write-Host "Skipping SEED_DATA.sql as requested" -ForegroundColor Yellow
}

# Ask for confirmation unless forced
if (-not $Force) {
    Write-Host "This will import the seed SQL files into '$Database'. Make sure you have a backup." -ForegroundColor Yellow
    $ok = Read-Host "Proceed? (y/n)"
    if ($ok -ne 'y' -and $ok -ne 'Y') {
        Write-Host "Aborted by user." -ForegroundColor Cyan
        exit 1
    }
} else {
    Write-Host "Force flag provided: proceeding without interactive confirmation." -ForegroundColor Yellow
}

# Run files
$allOk = $true
foreach ($f in $files) {
    $ok = Run-SqlFile -mysql $MysqlExe -user $User -password $Password -db $Database -file $f
    if (-not $ok) { $allOk = $false; break }
}

if ($allOk) {
    Write-Host "\nAll seeds imported successfully." -ForegroundColor Green
    Write-Host "Next: open the editor and verify pages load and Cyrillic text renders correctly. See the checklist below." -ForegroundColor Cyan
    Write-Host "\nChecklist (manual):" -ForegroundColor White
    Write-Host "1) Start a static server in 'frontend' (example below) or open 'frontend/editor.html' in browser." -ForegroundColor White
    Write-Host "   Example: php -S localhost:8000 -t frontend" -ForegroundColor Gray
    Write-Host "2) Open editor as admin: username 'anna' / password 'admin123'" -ForegroundColor White
    Write-Host "3) Visit these preset pages by id (URLs):" -ForegroundColor White
    Write-Host "   Home: ?id=75f53538-dd6c-489a-9b20-d0004bb5086b" -ForegroundColor Gray
    Write-Host "   Guides: ?id=a1b2c3d4-e5f6-7890-abcd-ef1234567891" -ForegroundColor Gray
    Write-Host "   Blog: ?id=b2c3d4e5-f6g7-8901-bcde-f23456789012" -ForegroundColor Gray
    Write-Host "   All Materials: ?id=c3d4e5f6-g7h8-9012-cdef-345678901234" -ForegroundColor Gray
    Write-Host "   Bot: ?id=d4e5f6g7-h8i9-0123-def0-456789012345" -ForegroundColor Gray
    Write-Host "   Sample Article: ?id=e5f6g7h8-i9j0-1234-ef01-567890123456" -ForegroundColor Gray
    exit 0
} else {
    Write-Host "One or more imports failed. See messages above." -ForegroundColor Red
    exit 2
}
