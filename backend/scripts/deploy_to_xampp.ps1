<#
Deploy backend/frontend to XAMPP with backup, copy, composer install, migration and smoke checks.
Usage: .\deploy_to_xampp.ps1 [-Force] [-XamppDir 'C:\xampp']
#>

param(
    [switch]$Force,
    [string]$XamppDir = 'C:\xampp',
    [string]$DestBackend = 'C:\xampp\htdocs\healthcare-cms-backend',
    [string[]]$DestFrontends = @('C:\xampp\htdocs\visual-editor-standalone','C:\xampp\htdocs\healthcare-cms-frontend'),
    [string]$DbUser = 'root',
    [System.Security.SecureString]$DbPassword = $null,
    [System.Management.Automation.PSCredential]$DbCredential = $null,
    [string]$DbName = 'healthcare_cms',
    [string]$PublicBaseUrl = 'http://localhost'
)

Set-StrictMode -Version Latest

Function Write-Log([string]$m){ Write-Host "[deploy] $m" }

$ScriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$BackendRoot = Split-Path -Parent $ScriptRoot
$RepoRoot = Split-Path -Parent $BackendRoot

$PhpExe = Join-Path $XamppDir 'php\php.exe'
$MysqlDump = Join-Path $XamppDir 'mysql\bin\mysqldump.exe'
$MysqlExe = Join-Path $XamppDir 'mysql\bin\mysql.exe'

if (-not $Force) {
    Write-Host "This will deploy code to XAMPP and run DB migration on database '$DbName'." -ForegroundColor Yellow
    $confirmation = Read-Host 'Type YES to continue'
    if ($confirmation -ne 'YES') { Write-Log 'Aborted by user.'; exit 1 }
}

if (-not (Test-Path $PhpExe)) { Write-Error "PHP not found at $PhpExe"; exit 2 }
if (-not (Test-Path $MysqlExe) -or -not (Test-Path $MysqlDump)) { Write-Error "MySQL client not found under $XamppDir\mysql\bin"; exit 2 }

$BackupsDir = 'C:\xampp\backups'
if (-not (Test-Path $BackupsDir)) { New-Item -Path $BackupsDir -ItemType Directory | Out-Null }

$timestamp = Get-Date -Format yyyyMMdd_HHmmss
$backupFile = Join-Path $BackupsDir "backup_preprod_$timestamp.sql"

Write-Log "Creating DB backup to $backupFile"
function Convert-SecureStringToPlain([System.Security.SecureString]$s) {
    if ($null -eq $s) { return '' }
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
    try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

if ($null -ne $DbCredential) {
    $DbUser = $DbCredential.UserName
    $plainDbPassword = $DbCredential.GetNetworkCredential().Password
} else {
    $plainDbPassword = Convert-SecureStringToPlain $DbPassword
}

$mysqldumpArgs = @("-u$DbUser")
if ($plainDbPassword -ne '') { $mysqldumpArgs += "-p$plainDbPassword" }
$mysqldumpArgs += @("--result-file=$backupFile", $DbName)

& $MysqlDump @mysqldumpArgs
if ($LASTEXITCODE -ne 0) { Write-Error "mysqldump failed with code $LASTEXITCODE"; exit 3 }
Write-Log "DB backup created."

Write-Log "Copying backend files to $DestBackend"
if (-not (Test-Path $DestBackend)) { New-Item -Path $DestBackend -ItemType Directory -Force | Out-Null }
robocopy (Join-Path $RepoRoot 'backend') $DestBackend * /MIR /Z /NP /R:3 /W:5 | ForEach-Object { Write-Host $_ }
$rc = $LASTEXITCODE
if ($rc -ge 8) { Write-Error "Robocopy backend failed with exit code $rc"; exit 4 }

foreach ($dest in $DestFrontends) {
    Write-Log "Copying frontend to $dest"
    if (-not (Test-Path $dest)) { New-Item -Path $dest -ItemType Directory -Force | Out-Null }
    robocopy (Join-Path $RepoRoot 'frontend') $dest * /MIR /Z /NP /R:3 /W:5 | ForEach-Object { Write-Host $_ }
    $rc = $LASTEXITCODE
    if ($rc -ge 8) { Write-Error "Robocopy frontend failed with code $rc"; exit 4 }
}

Write-Log "Running composer install in backend (if available)"
Push-Location $DestBackend
try {
    $composerPhar = Join-Path $RepoRoot 'composer.phar'
    if (Test-Path (Join-Path $DestBackend 'composer.phar')) { $composerPhar = Join-Path $DestBackend 'composer.phar' }
    if (Test-Path $composerPhar) {
        & $PhpExe $composerPhar 'install' '--no-dev' '--optimize-autoloader'
    } elseif (Get-Command composer -ErrorAction SilentlyContinue) {
        composer install --no-dev --optimize-autoloader
    } else {
        Write-Warning 'composer.phar not found and composer not in PATH - skipping composer install.'
    }
} finally { Pop-Location }

$migrationScript = Join-Path $DestBackend 'scripts\apply_migration_stage1.php'
if (Test-Path $migrationScript) {
    Write-Log "Running migration script: $migrationScript"
    & $PhpExe $migrationScript
    if ($LASTEXITCODE -ne 0) { Write-Error "Migration script failed with code $LASTEXITCODE"; exit 5 }
} else {
    Write-Warning "Migration script not found at $migrationScript. You may run SQL manually."
}

Write-Log "Verifying DB schema via mysql client"
$mysqlArgs = @("-u", $DbUser)
if ($plainDbPassword -ne '') { $mysqlArgs += "--password=$plainDbPassword" }
$mysqlArgs += @('--batch','--skip-column-names','-e',"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$DbName' AND TABLE_NAME='pages' AND COLUMN_NAME IN ('rendered_html','menu_title','source_template_slug');")
$output = & $MysqlExe @mysqlArgs 2>&1
Write-Host $output
if ($output -match 'rendered_html' -and $output -match 'menu_title') {
    Write-Log "Required columns present."
} else {
    Write-Error "Schema verification failed - required columns missing."; exit 6
}

Write-Log "Performing HTTP smoke check on $PublicBaseUrl"
try {
    $resp = Invoke-WebRequest -Uri $PublicBaseUrl -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    if ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 400) { Write-Log "HTTP check OK: $($resp.StatusCode)" } else { Write-Error "HTTP check returned status $($resp.StatusCode)"; exit 7 }
} catch {
    Write-Error "HTTP check failed: $($_.Exception.Message)"; exit 7
}

Write-Log "Deploy completed successfully."
exit 0
