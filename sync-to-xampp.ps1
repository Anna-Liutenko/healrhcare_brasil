# Скрипт синхронизации файлов проекта в XAMPP
# Запуск: powershell -ExecutionPolicy Bypass -File sync-to-xampp.ps1

param(
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

# Log file for sync operations
$sourceRoot = $PSScriptRoot
$logFile = Join-Path $sourceRoot 'sync-to-xampp.log'

function Write-Log {
    param(
        [string]$Level,
        [string]$Message
    )
    $ts = (Get-Date).ToString('o')
    $line = "$ts [$Level] $Message"
    try {
        Add-Content -Path $logFile -Value $line
    } catch {
        # best-effort logging — don't fail the script if log write fails
    }
    switch ($Level) {
        'ERROR' { Write-Host $line -ForegroundColor Red }
        'WARN'  { Write-Host $line -ForegroundColor Yellow }
        default { Write-Host $line -ForegroundColor Gray }
    }
}

Write-Log 'INFO' "Starting sync-to-xampp.ps1. DryRun=$($DryRun.IsPresent)"

# Пути - используем текущую директорию скрипта (PSScriptRoot) для совместимости с кириллицей
$sourceRoot = $PSScriptRoot
$backendSource = "$sourceRoot\backend"
$frontendSource = "$sourceRoot\frontend"
$backendTarget = "C:\xampp\htdocs\healthcare-cms-backend"
$frontendTarget = "C:\xampp\htdocs\healthcare-cms-frontend"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PROJECT SYNC TO XAMPP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Функция для копирования с проверкой
function Sync-Files {
    param(
        [string]$Source,
        [string]$Destination,
        [string]$Description
    )

    Write-Host "[SYNC] $Description" -ForegroundColor Yellow
    Write-Host "   From: $Source" -ForegroundColor Gray
    Write-Host "   To:   $Destination" -ForegroundColor Gray

    if (-not (Test-Path $Source)) {
        Write-Host "   [ERROR] Source folder not found!" -ForegroundColor Red
        return $false
    }

    if (-not (Test-Path $Destination)) {
        Write-Host "   [WARN] Target folder does not exist, creating..." -ForegroundColor Yellow
        New-Item -Path $Destination -ItemType Directory -Force | Out-Null
    }

    try {
        # Копируем все файлы рекурсивно
        # ВАЖНО: исключаем папку uploads (user-generated content) чтобы /MIR не удалял загруженные изображения
        # /XD принимает имя директории или путь — указываем просто "uploads" чтобы исключить все папок с таким именем
        $robocopyArgs = @()
        if ($DryRun.IsPresent) { $robocopyArgs += '/L' }
        $robocopyArgs += '/MIR','/XD','uploads','/R:3','/W:1','/NP','/NDL','/NFL','/NJH','/NJS'

        Write-Log 'INFO' "Running robocopy from '$Source' to '$Destination' with args: $($robocopyArgs -join ' ')"

        & robocopy $Source $Destination $robocopyArgs | Out-Null

        $exit = $LASTEXITCODE
        Write-Log 'INFO' "robocopy exit code: $exit"

        if ($exit -le 7) {
            Write-Log 'INFO' "Synchronized successfully: $Description"
            return $true
        } else {
            Write-Log 'ERROR' "Copy failed (code: $exit) for $Description"
            return $false
        }
    } catch {
        Write-Log 'ERROR' "EXCEPTION during sync: $_"
        return $false
    }
}

# Sync backend
Write-Host ""
$backendOk = Sync-Files -Source $backendSource -Destination $backendTarget -Description "BACKEND (PHP)"

# Sync frontend
Write-Host ""
$frontendOk = Sync-Files -Source $frontendSource -Destination $frontendTarget -Description "FRONTEND (JS/HTML/CSS)"

# Ensure uploads folders exist on target (prevent 404s if uploads were never created)
$backendUploadsTarget = Join-Path $backendTarget 'public\uploads'
if (-not (Test-Path $backendUploadsTarget)) {
    Write-Host "[INFO] Creating missing backend uploads folder: $backendUploadsTarget" -ForegroundColor Yellow
    New-Item -Path $backendUploadsTarget -ItemType Directory -Force | Out-Null
}

$frontendUploadsTarget = Join-Path $frontendTarget 'uploads'
if (-not (Test-Path $frontendUploadsTarget)) {
    Write-Host "[INFO] Creating missing frontend uploads folder: $frontendUploadsTarget" -ForegroundColor Yellow
    New-Item -Path $frontendUploadsTarget -ItemType Directory -Force | Out-Null
}

# Post-sync: log counts of upload files and optionally run cleanup script in dry-run mode to compare DB vs FS
try {
    $backendUploadsPath = Join-Path $backendTarget 'public\uploads'
    $backendFilesCount = 0
    if (Test-Path $backendUploadsPath) {
        $backendFilesCount = (Get-ChildItem -Path $backendUploadsPath -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    }
    Write-Log 'INFO' "Backend uploads files count: $backendFilesCount (path: $backendUploadsPath)"

    # If cleanup script exists, attempt to run it in dry-run and capture output for the log
    $cleanupScript = Join-Path $sourceRoot 'backend\scripts\cleanup-missing-media.php'
    if (Test-Path $cleanupScript) {
        # Try to find php executable (compatible with PowerShell 5.1)
        $phpExe = $null
        $phpCmd = Get-Command php -ErrorAction SilentlyContinue
        if ($phpCmd) {
            $phpExe = $phpCmd.Source
        }
        if (-not $phpExe) {
            # Common XAMPP path
            $possible = 'C:\xampp\php\php.exe'
            if (Test-Path $possible) { $phpExe = $possible }
        }

        if ($phpExe) {
            Write-Log 'INFO' "Running cleanup script (dry-run) with PHP: $phpExe"
            try {
                $procOutput = & $phpExe $cleanupScript '--dry-run' 2>&1 | Out-String
                Write-Log 'INFO' "Cleanup script output:\n$procOutput"
            } catch {
                Write-Log 'ERROR' "Failed to execute cleanup script: $_"
            }
        } else {
            Write-Log 'WARN' "PHP executable not found; skipping cleanup script run. Please ensure 'php' is in PATH or XAMPP installed."
        }
    } else {
        Write-Log 'WARN' "Cleanup script not found at: $cleanupScript"
    }
} catch {
    Write-Log 'ERROR' "Post-sync checks failed: $_"
}

# Results
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESULT" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($backendOk -and $frontendOk) {
    Write-Host "[OK] All files synchronized successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Now you can:" -ForegroundColor Yellow
    Write-Host "  - Open http://localhost/visual-editor-standalone/" -ForegroundColor Gray
    Write-Host "  - Refresh page in browser (Ctrl+Shift+R)" -ForegroundColor Gray
} else {
    Write-Host "[WARN] Synchronization completed with errors" -ForegroundColor Yellow
    if (-not $backendOk) {
        Write-Host "  [ERROR] Backend not synchronized" -ForegroundColor Red
    }
    if (-not $frontendOk) {
        Write-Host "  [ERROR] Frontend not synchronized" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
