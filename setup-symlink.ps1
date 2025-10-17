# ========================================
# Скрипт настройки Symlink для XAMPP
# ========================================
# 
# ЗАПУСКАТЬ ОТ ИМЕНИ АДМИНИСТРАТОРА!
# 
# Правый клик на PowerShell → "Запуск от имени администратора"
# Затем выполнить: .\setup-symlink.ps1
#
# ========================================

Write-Host "🔧 Настройка Symlink для Healthcare CMS" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Проверка прав администратора
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "❌ ОШИБКА: Скрипт должен быть запущен от имени Администратора!" -ForegroundColor Red
    Write-Host "`nПравый клик на PowerShell → 'Запуск от имени администратора'`n" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "✅ Права администратора подтверждены`n" -ForegroundColor Green

# Пути
$projectPath = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS"
$backendSource = Join-Path $projectPath "backend"
$frontendSource = Join-Path $projectPath "frontend"
$backendTarget = "c:\xampp\htdocs\healthcare-cms-backend"
$frontendTarget = "c:\xampp\htdocs\visual-editor-standalone"

# ========================================
# ШАГ 1: Удаление старых папок
# ========================================
Write-Host "📂 Шаг 1: Удаление старых папок из XAMPP..." -ForegroundColor Yellow

$foldersToRemove = @(
    "c:\xampp\htdocs\healthcare-cms-backend",
    "c:\xampp\htdocs\visual-editor-standalone"
)

foreach ($folder in $foldersToRemove) {
    if (Test-Path $folder) {
        Write-Host "  Удаляю: $folder" -ForegroundColor Gray
        Remove-Item $folder -Recurse -Force -ErrorAction SilentlyContinue
        if (Test-Path $folder) {
            Write-Host "  ⚠️  Не удалось удалить: $folder" -ForegroundColor Red
        } else {
            Write-Host "  ✅ Удалено: $folder" -ForegroundColor Green
        }
    } else {
        Write-Host "  ℹ️  Не найдено: $folder" -ForegroundColor Gray
    }
}

Write-Host ""

# ========================================
# ШАГ 2: Создание Symlink
# ========================================
Write-Host "🔗 Шаг 2: Создание Symlink..." -ForegroundColor Yellow

# Backend Symlink
Write-Host "`n  Backend:" -ForegroundColor Cyan
Write-Host "    От:  $backendTarget" -ForegroundColor Gray
Write-Host "    К:   $backendSource" -ForegroundColor Gray

try {
    New-Item -ItemType SymbolicLink -Path $backendTarget -Target $backendSource -Force -ErrorAction Stop | Out-Null
    Write-Host "  ✅ Backend symlink создан" -ForegroundColor Green
} catch {
    Write-Host "  ❌ Ошибка создания backend symlink: $_" -ForegroundColor Red
}

# Frontend Symlink
Write-Host "`n  Frontend:" -ForegroundColor Cyan
Write-Host "    От:  $frontendTarget" -ForegroundColor Gray
Write-Host "    К:   $frontendSource" -ForegroundColor Gray

try {
    New-Item -ItemType SymbolicLink -Path $frontendTarget -Target $frontendSource -Force -ErrorAction Stop | Out-Null
    Write-Host "  ✅ Frontend symlink создан`n" -ForegroundColor Green
} catch {
    Write-Host "  ❌ Ошибка создания frontend symlink: $_`n" -ForegroundColor Red
}

# ========================================
# ШАГ 3: Проверка
# ========================================
Write-Host "🔍 Шаг 3: Проверка symlink..." -ForegroundColor Yellow

$backendCheck = Get-Item $backendTarget -ErrorAction SilentlyContinue
$frontendCheck = Get-Item $frontendTarget -ErrorAction SilentlyContinue

if ($backendCheck.LinkType -eq "SymbolicLink") {
    Write-Host "  ✅ Backend symlink работает" -ForegroundColor Green
    Write-Host "     Ссылка на: $($backendCheck.Target)" -ForegroundColor Gray
} else {
    Write-Host "  ❌ Backend symlink НЕ работает" -ForegroundColor Red
}

if ($frontendCheck.LinkType -eq "SymbolicLink") {
    Write-Host "  ✅ Frontend symlink работает" -ForegroundColor Green
    Write-Host "     Ссылка на: $($frontendCheck.Target)`n" -ForegroundColor Gray
} else {
    Write-Host "  ❌ Frontend symlink НЕ работает`n" -ForegroundColor Red
}

# ========================================
# ФИНАЛ
# ========================================
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "🎉 Настройка завершена!" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "📝 Следующие шаги:" -ForegroundColor Yellow
Write-Host "  1. Убедись, что Apache и MySQL запущены в XAMPP" -ForegroundColor White
Write-Host "  2. Открой: http://localhost/visual-editor-standalone/" -ForegroundColor White
Write-Host "  3. Логин: admin@example.com / password123" -ForegroundColor White
Write-Host "  4. Попробуй создать тестовую страницу`n" -ForegroundColor White

Write-Host "🆘 Если что-то не работает:" -ForegroundColor Yellow
Write-Host "  - Проверь: http://localhost/healthcare-cms-backend/public/" -ForegroundColor White
Write-Host "  - Посмотри логи в backend/logs/ и c:\xampp\apache\logs\error.log" -ForegroundColor White
Write-Host "  - Прочитай CHECKLIST.md и SETUP_XAMPP.md`n" -ForegroundColor White

pause
