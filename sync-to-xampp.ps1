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
Write-Host "СИНХРОНИЗАЦИЯ ПРОЕКТА В XAMPP" -ForegroundColor Cyan
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
    Write-Host "   Из: $Source" -ForegroundColor Gray
    Write-Host "   В:  $Destination" -ForegroundColor Gray

    if (-not (Test-Path $Source)) {
        Write-Host "   ❌ ОШИБКА: Исходная папка не найдена!" -ForegroundColor Red
        return $false
    }

    if (-not (Test-Path $Destination)) {
        Write-Host "   ⚠️  Целевая папка не существует, создаю..." -ForegroundColor Yellow
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
        Write-Host "   ❌ ИСКЛЮЧЕНИЕ: $_" -ForegroundColor Red
        return $false
    }
}

# Синхронизация backend
Write-Host ""
$backendOk = Sync-Files -Source $backendSource -Destination $backendTarget -Description "BACKEND (PHP)"

# Синхронизация frontend
Write-Host ""
$frontendOk = Sync-Files -Source $frontendSource -Destination $frontendTarget -Description "FRONTEND (JS/HTML/CSS)"

# Итоги
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "РЕЗУЛЬТАТ" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($backendOk -and $frontendOk) {
    Write-Host "✅ Все файлы синхронизированы успешно!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Теперь можно:" -ForegroundColor Yellow
    Write-Host "  • Открыть http://localhost/visual-editor-standalone/" -ForegroundColor Gray
    Write-Host "  • Обновить страницу в браузере (Ctrl+Shift+R)" -ForegroundColor Gray
} else {
    Write-Host "⚠️  Синхронизация завершена с ошибками" -ForegroundColor Yellow
    if (-not $backendOk) {
        Write-Host "  ❌ Backend не синхронизирован" -ForegroundColor Red
    }
    if (-not $frontendOk) {
        Write-Host "  ❌ Frontend не синхронизирован" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Нажмите любую клавишу для выхода..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
