<#
deploy-frontend-to-xampp.ps1

Usage (PowerShell):
  .\deploy-frontend-to-xampp.ps1 [-Destination <path>] [-WhatIf]

Default behavior:
 - Source is the `frontend` folder next to this script (repo root)
 - Destination defaults to C:\xampp\htdocs\visual-editor-standalone
 - If destination exists it will be renamed with a timestamp to create a backup
 - Then the `frontend` folder will be copied into the destination

This script is safe to run locally. It creates backups and prints progress.
#>

param(
    [string]$Destination = "C:\xampp\htdocs\visual-editor-standalone",
    [switch]$WhatIf
)

function Write-Log { param($m) Write-Host "[deploy] $m" }

try {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $RepoRoot = Resolve-Path $ScriptDir
    $Source = Join-Path $RepoRoot 'frontend'

    Write-Log "Repo root: $RepoRoot"
    Write-Log "Source (frontend): $Source"
    Write-Log "Destination: $Destination"

    if (-not (Test-Path $Source)) {
        Write-Error "Source folder not found: $Source. Aborting."
        exit 2
    }

    if ($WhatIf) {
        Write-Log "WhatIf mode: no changes will be made. Preview follows."
    }

    # Ensure destination parent exists
    $dstParent = Split-Path $Destination -Parent
    if (-not (Test-Path $dstParent)) {
        if ($WhatIf) { Write-Log "Would create parent folder: $dstParent" } else { New-Item -ItemType Directory -Path $dstParent -Force | Out-Null; Write-Log "Created parent folder: $dstParent" }
    }

    # If destination exists, make a timestamped backup (rename)
    if (Test-Path $Destination) {
        $timestamp = Get-Date -Format yyyyMMddHHmmss
        $backupName = "$Destination.bak_$timestamp"
        if ($WhatIf) {
            Write-Log "Would move existing destination to $backupName"
        } else {
            Write-Log "Moving existing destination to $backupName"
            try {
                # Use Move-Item with full paths to avoid NewName parsing issues when paths contain spaces
                Move-Item -LiteralPath $Destination -Destination $backupName -Force -ErrorAction Stop
                Write-Log "Backup created: $backupName"
            } catch {
                Write-Error "Failed to move existing destination to backup: $_"
                throw
            }
        }
    }

    # Copy files from source to destination using robocopy for robustness if available
    $robocopy = Join-Path $env:windir 'System32\Robocopy.exe'
    if (Test-Path $robocopy -and -not $WhatIf) {
        # /E copy subfolders, including empty
        # /R:2 retry twice, /W:2 wait 2s
        # /NFL /NDL minimize logs, but keep basic
        Write-Log "Using robocopy to copy files (fast & robust)"
        $cmd = "`"$robocopy`" `"$Source`" `"$Destination`" /E /COPY:DAT /R:2 /W:2"
        Write-Log "Running: $cmd"
        $proc = Start-Process -FilePath $robocopy -ArgumentList "$Source","$Destination","/E","/COPY:DAT","/R:2","/W:2" -NoNewWindow -Wait -PassThru
        if ($proc.ExitCode -ge 8) {
            Write-Error "Robocopy return code $($proc.ExitCode) indicates a failure. See robocopy output above."
            exit 3
        }
    } else {
        if ($WhatIf) {
            Write-Log "Would recursively copy $Source -> $Destination (PowerShell Copy-Item)"
        } else {
            Write-Log "Copying files with Copy-Item (recursive)"
            Copy-Item -Path (Join-Path $Source '*') -Destination $Destination -Recurse -Force -ErrorAction Stop
        }
    }

    Write-Log "Deployment completed. Destination is: $Destination"
    Write-Log "If you served the site from XAMPP, open the page and hard-refresh (Ctrl+Shift+R) to pick up new scripts/styles."
    exit 0
} catch {
    Write-Error "Deployment failed: $_"
    exit 4
}
