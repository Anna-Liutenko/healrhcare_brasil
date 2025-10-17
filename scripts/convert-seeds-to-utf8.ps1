<#
Convert seed SQL files to UTF-8 (no BOM) and save alongside originals as *.utf8.sql.
Default source encoding: Windows-1251 (CP1251). Adjust with -SourceEncoding if needed.

Usage examples:
  .\convert-seeds-to-utf8.ps1                  # uses CP1251 -> UTF-8
  .\convert-seeds-to-utf8.ps1 -SourceEncoding 'koi8-r'
#>
param(
    [string]$SourceEncoding = 'windows-1251'
)

Write-Host "=== Converting seed files to UTF-8 (from $SourceEncoding) ===" -ForegroundColor Yellow

# Determine repo root
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path | Split-Path -Parent
$seedsDir = Join-Path $repoRoot 'database\seeds'

if (-not (Test-Path $seedsDir)) {
    Write-Host "Seeds directory not found: $seedsDir" -ForegroundColor Red
    exit 1
}

# Files to convert (include SEED_DATA as well, copy-only; actual import may skip it)
$files = @()
$files += (Join-Path $seedsDir 'users_seed.sql')
$files += (Join-Path $seedsDir 'pages_seed.sql')
$files += (Join-Path $seedsDir 'blocks_seed.sql')
$files += (Join-Path $seedsDir 'SEED_DATA.sql')

# Prepare encodings
try {
    $srcEnc = [System.Text.Encoding]::GetEncoding($SourceEncoding)
} catch {
    Write-Host "Unknown source encoding: $SourceEncoding" -ForegroundColor Red
    exit 1
}
$utf8 = New-Object System.Text.UTF8Encoding($false)  # no BOM

$converted = @()
foreach ($src in $files) {
    if (-not (Test-Path $src)) {
        Write-Host "Skip (not found): $src" -ForegroundColor DarkGray
        continue
    }

    $dst = $src + '.utf8.sql'
    Write-Host "Converting: $src -> $dst" -ForegroundColor Cyan

    try {
        $text = [System.IO.File]::ReadAllText($src, $srcEnc)
        [System.IO.File]::WriteAllText($dst, $text, $utf8)
        $converted += $dst
        Write-Host "OK: $dst" -ForegroundColor Green
    } catch {
        Write-Host "Failed: $src -> $dst : $_" -ForegroundColor Red
    }
}

Write-Host "\nDone. Converted files:" -ForegroundColor Yellow
$converted | ForEach-Object { Write-Host " - $_" }
