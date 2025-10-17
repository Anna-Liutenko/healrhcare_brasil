# ========================================
# Setup workspace mirror links to XAMPP
# ========================================
# Purpose:
#   Create junctions inside the workspace that point to XAMPP folders
#   so tooling (including AI assistants) can read them as part of the repo.
#
# Usage:
#   1) Open Windows PowerShell as Administrator (recommended)
#   2) Run:  ./scripts/setup-xampp-mirror.ps1
#
# Notes:
#   - We prefer directory Junctions (mklink /J) to avoid symlink restrictions
#     and to not require Developer Mode. Admin rights are recommended.
#   - If a link already exists, it will be removed and recreated.
# ========================================

$ErrorActionPreference = 'Stop'

Write-Host "🔧 Creating XAMPP mirror links inside the workspace..." -ForegroundColor Cyan

# Resolve workspace root from script location
$workspaceRoot = Split-Path -Parent $PSScriptRoot
$mirrorRoot = Join-Path $workspaceRoot 'xampp-mirror'

# Targets (adjust if your XAMPP is installed elsewhere)
$xamppHtdocs = 'C:\xampp\htdocs'
$backendHtdocsTarget = Join-Path $xamppHtdocs 'healthcare-cms-backend'
$apacheLogsTarget = 'C:\xampp\apache\logs'

# Mirror link paths
$mirrorHtdocs = Join-Path $mirrorRoot 'htdocs'
$mirrorBackend = Join-Path $mirrorHtdocs 'healthcare-cms-backend'
$mirrorApacheLogs = Join-Path $mirrorRoot 'apache-logs'

# Helper: (re)create junction using mklink /J for compatibility across PS versions
function New-Junction($LinkPath, $TargetPath) {
    if (Test-Path $LinkPath) {
        Write-Host "  Removing existing: $LinkPath" -ForegroundColor DarkGray
        try {
            Remove-Item -LiteralPath $LinkPath -Recurse -Force -ErrorAction Stop
        } catch {
            # If path is a junction, -Recurse may fail; try rmdir
            cmd /c rmdir /s /q "$LinkPath" | Out-Null
        }
    }
    if (-not (Test-Path $TargetPath)) {
        throw "Target path does not exist: $TargetPath"
    }
    $parent = Split-Path -Parent $LinkPath
    if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent | Out-Null }

    Write-Host "  ➕ Linking" -NoNewline; Write-Host " $LinkPath " -ForegroundColor Yellow -NoNewline; Write-Host "→ $TargetPath" -ForegroundColor Gray
    cmd /c mklink /J "$LinkPath" "$TargetPath" | Out-Null
    if (-not $?) { throw "Failed to create junction: $LinkPath" }
}

# Create mirror root structure
if (-not (Test-Path $mirrorRoot)) { New-Item -ItemType Directory -Path $mirrorRoot | Out-Null }
if (-not (Test-Path $mirrorHtdocs)) { New-Item -ItemType Directory -Path $mirrorHtdocs | Out-Null }

# Create junctions
Write-Host "\n📁 XAMPP htdocs → workspace mirror"
New-Junction -LinkPath $mirrorBackend -TargetPath $backendHtdocsTarget

Write-Host "\n🧾 Apache logs → workspace mirror"
New-Junction -LinkPath $mirrorApacheLogs -TargetPath $apacheLogsTarget

Write-Host "\n✅ Done. You can now browse these paths inside the workspace:" -ForegroundColor Green
Write-Host "   - $mirrorBackend  (mirrors C:\xampp\htdocs\healthcare-cms-backend)"
Write-Host "   - $mirrorApacheLogs (mirrors C:\xampp\apache\logs)"

Write-Host "\nℹ️ If access still fails, run PowerShell as Administrator and try again." -ForegroundColor Yellow
