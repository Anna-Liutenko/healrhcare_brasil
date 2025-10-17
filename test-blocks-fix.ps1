# Скрипт для тестирования исправления сохранения блоков
# Дата: 06.10.2025

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "ТЕСТИРОВАНИЕ ИСПРАВЛЕНИЯ БЛОКОВ" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ===== ТЕСТ 1: PHP Синтаксис =====
Write-Host "📋 Тест 1: Проверка синтаксиса UpdatePage.php" -ForegroundColor Yellow
$phpPath = "C:\xampp\php\php.exe"
$updatePagePath = "C:\xampp\htdocs\healthcare-cms-backend\src\Application\UseCase\UpdatePage.php"

try {
    $syntaxCheck = & $phpPath -l $updatePagePath 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Синтаксис корректен" -ForegroundColor Green
        Write-Host "   $syntaxCheck" -ForegroundColor Gray
    } else {
        Write-Host "   ❌ Ошибка синтаксиса!" -ForegroundColor Red
        Write-Host "   $syntaxCheck" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "   ❌ Не удалось запустить проверку: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""

# ===== ТЕСТ 2: Проверка синхронизации файлов =====
Write-Host "📋 Тест 2: Проверка синхронизации workspace ↔ XAMPP" -ForegroundColor Yellow
$workspacePath = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend\src\Application\UseCase\UpdatePage.php"
$xamppPath = "C:\xampp\htdocs\healthcare-cms-backend\src\Application\UseCase\UpdatePage.php"

$workspaceHash = (Get-FileHash -Path $workspacePath -Algorithm SHA256).Hash
$xamppHash = (Get-FileHash -Path $xamppPath -Algorithm SHA256).Hash

if ($workspaceHash -eq $xamppHash) {
    Write-Host "   ✅ Файлы синхронизированы" -ForegroundColor Green
    Write-Host "   Hash: $workspaceHash" -ForegroundColor Gray
} else {
    Write-Host "   ⚠️  ФАЙЛЫ НЕ СИНХРОНИЗИРОВАНЫ!" -ForegroundColor Yellow
    Write-Host "   Workspace: $workspaceHash" -ForegroundColor Gray
    Write-Host "   XAMPP:     $xamppHash" -ForegroundColor Gray
    
    $sync = Read-Host "   Синхронизировать сейчас? (y/n)"
    if ($sync -eq 'y') {
        Copy-Item -Path $workspacePath -Destination $xamppPath -Force
        Write-Host "   ✅ Файл синхронизирован" -ForegroundColor Green
    }
}

Write-Host ""

# ===== ТЕСТ 3: Текущее состояние БД =====
Write-Host "📋 Тест 3: Текущее состояние блоков в БД" -ForegroundColor Yellow
$checkBlocksScript = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend\scripts\check_blocks.php"

try {
    Write-Host "   Запускаем check_blocks.php..." -ForegroundColor Gray
    & $phpPath $checkBlocksScript
} catch {
    Write-Host "   ❌ Ошибка при проверке блоков: $_" -ForegroundColor Red
}

Write-Host ""

# ===== ИНСТРУКЦИИ ДЛЯ РУЧНОГО ТЕСТИРОВАНИЯ =====
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "СЛЕДУЮЩИЕ ШАГИ (РУЧНОЕ ТЕСТИРОВАНИЕ)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "🎯 ТЕСТ 4: Обновление существующей страницы" -ForegroundColor Yellow
Write-Host "   1. Откройте:" -ForegroundColor White
Write-Host "      http://localhost/visual-editor-standalone/editor.html?id=c933586d-58ac-438d-bee6-2aeca6f07f9e" -ForegroundColor Cyan
Write-Host "   2. Войдите: admin / admin123" -ForegroundColor White
Write-Host "   3. Добавьте 2-3 блока из библиотеки" -ForegroundColor White
Write-Host "   4. Нажмите 'Сохранить'" -ForegroundColor White
Write-Host "   5. Проверьте debug-панель: [API 4] ← 200" -ForegroundColor White
Write-Host ""
Write-Host "🆕 ТЕСТ 5: Создание новой страницы" -ForegroundColor Yellow
Write-Host "   1. Откройте:" -ForegroundColor White
Write-Host "      http://localhost/visual-editor-standalone/editor.html" -ForegroundColor Cyan
Write-Host "   2. Создайте страницу с блоками" -ForegroundColor White
Write-Host "   3. Сохраните и проверьте, что URL изменился" -ForegroundColor White
Write-Host "   4. Нажмите F5 — блоки должны остаться" -ForegroundColor White
Write-Host ""
Write-Host "✅ После тестирования запустите снова этот скрипт" -ForegroundColor Green
Write-Host "   для проверки изменений в БД" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Нажмите любую клавишу для выхода..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
