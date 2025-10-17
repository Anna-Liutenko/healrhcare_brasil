<#
Simple deploy script: safer copy to XAMPP without robocopy
Usage: .\deploy-frontend-to-xampp-simple.ps1 [-Destination "C:\xampp\htdocs\visual-editor-standalone"]
#>
param(
    [string]$Destination = "C:\xampp\htdocs\visual-editor-standalone"
)

function Log($m){ Write-Host "[deploy] $m" }

try {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $Source = Join-Path $ScriptDir 'frontend'

    Log "Script dir: $ScriptDir"
    Log "Source: $Source"
    Log "Destination: $Destination"

    if (-not (Test-Path $Source)) { throw "Source not found: $Source" }

    if (Test-Path $Destination) {
        $ts = Get-Date -Format yyyyMMddHHmmss
        $bak = "$Destination.bak_$ts"
        Log "Destination exists. Moving to backup: $bak"
        Move-Item -LiteralPath $Destination -Destination $bak -Force
    }

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    Log "Copying files..."
    Copy-Item -Path (Join-Path $Source '*') -Destination $Destination -Recurse -Force -ErrorAction Stop
    Log "Copy finished"
    Log "Deployment OK"
    exit 0
} catch {
    Write-Host "[deploy] ERROR: $_" -ForegroundColor Red
    exit 1
}
