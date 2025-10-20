# Скрипт синхронизации файлов проекта в XAMPP
# Запуск: powershell -ExecutionPolicy Bypass -File sync-to-xampp.ps1

$ErrorActionPreference = "Stop"

# Пути
$sourceRoot = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS"
$backendSource = "$sourceRoot\backend"
$frontendSource = "$sourceRoot\frontend"
$backendTarget = "C:\xampp\htdocs\healthcare-cms-backend"
$frontendTarget = "C:\xampp\htdocs\visual-editor-standalone"

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

    Write-Host "📁 $Description" -ForegroundColor Yellow
    Write-Host "   From: $Source" -ForegroundColor Gray
    Write-Host "   To:  $Destination" -ForegroundColor Gray

    if (-not (Test-Path $Source)) {
        Write-Host "   ❌ ERROR: Source folder not found!" -ForegroundColor Red
        return $false
    }

    if (-not (Test-Path $Destination)) {
        Write-Host "   ⚠️  Target folder does not exist, creating..." -ForegroundColor Yellow
        New-Item -Path $Destination -ItemType Directory -Force | Out-Null
    }

    try {
        # Копируем все файлы рекурсивно
        robocopy "$Source" "$Destination" /MIR /R:3 /W:1 /NP /NDL /NFL /NJH /NJS | Out-Null

        if ($LASTEXITCODE -le 7) {
            Write-Host "   ✅ Синхронизировано успешно" -ForegroundColor Green
            return $true
        } else {
            Write-Host "   ❌ ОШИБКА при копировании (код: $LASTEXITCODE)" -ForegroundColor Red
            return $false
        }
    } catch {
        Write-Host "   ❌ EXCEPTION: $_" -ForegroundColor Red
        return $false
    }
}

# Sync backend
Write-Host ""
$backendOk = Sync-Files -Source $backendSource -Destination $backendTarget -Description "BACKEND (PHP)"

# Sync frontend
Write-Host ""
$frontendOk = Sync-Files -Source $frontendSource -Destination $frontendTarget -Description "FRONTEND (JS/HTML/CSS)"

# Results
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESULT" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($backendOk -and $frontendOk) {
    Write-Host "✅ All files synchronized successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Now you can:" -ForegroundColor Yellow
    Write-Host "  • Open http://localhost/visual-editor-standalone/" -ForegroundColor Gray
    Write-Host "  • Refresh page in browser (Ctrl+Shift+R)" -ForegroundColor Gray
} else {
    Write-Host "⚠️  Synchronization completed with errors" -ForegroundColor Yellow
    if (-not $backendOk) {
        Write-Host "  ❌ Backend not synchronized" -ForegroundColor Red
    }
    if (-not $frontendOk) {
        Write-Host "  ❌ Frontend not synchronized" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
