#!/usr/bin/env pwsh
# Checklist для проверки рефакторинга коллекции

Write-Host "
╔════════════════════════════════════════════════════════════════════╗
║           CHECKLIST: Рефакторинг DRY для Коллекции               ║
╚════════════════════════════════════════════════════════════════════╝
" -ForegroundColor Cyan

# 1. Проверка PHP файлов
Write-Host "`n[1] Проверка PHP файлов..." -ForegroundColor Yellow
$phpFiles = @(
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\CollectionCardRenderer.php",
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\CollectionHtmlBuilder.php",
    "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php"
)

foreach ($file in $phpFiles) {
    if (Test-Path $file) {
        Write-Host "  ✓ $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $([System.IO.Path]::GetFileName($file)) НЕ НАЙДЕН!" -ForegroundColor Red
    }
}

# 2. Проверка JS файлов
Write-Host "`n[2] Проверка JS файлов..." -ForegroundColor Yellow
$jsFiles = @(
    "C:\xampp\htdocs\healthcare-cms-frontend\modules\card-templates.js",
    "C:\xampp\htdocs\healthcare-cms-frontend\collection-tabs.js"
)

foreach ($file in $jsFiles) {
    if (Test-Path $file) {
        Write-Host "  ✓ $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $([System.IO.Path]::GetFileName($file)) НЕ НАЙДЕН!" -ForegroundColor Red
    }
}

# 3. Проверка синтаксиса PHP
Write-Host "`n[3] Проверка синтаксиса PHP..." -ForegroundColor Yellow
foreach ($file in $phpFiles) {
    if (Test-Path $file) {
        $output = php -l $file 2>&1
        if ($output -like "*No syntax errors*") {
            Write-Host "  ✓ $([System.IO.Path]::GetFileName($file))" -ForegroundColor Green
        } else {
            Write-Host "  ✗ $([System.IO.Path]::GetFileName($file)) - ОШИБКА СИНТАКСИСА!" -ForegroundColor Red
            Write-Host "    $output" -ForegroundColor Red
        }
    }
}

# 4. Проверка содержимого PHP классов
Write-Host "`n[4] Проверка содержимого PHP классов..." -ForegroundColor Yellow

$checks = @(
    @{ File = "CollectionCardRenderer.php"; Pattern = "renderCard"; Description = "Метод renderCard()" },
    @{ File = "CollectionCardRenderer.php"; Pattern = "renderGrid"; Description = "Метод renderGrid()" },
    @{ File = "CollectionCardRenderer.php"; Pattern = "renderSection"; Description = "Метод renderSection()" },
    @{ File = "CollectionCardRenderer.php"; Pattern = "sanitizeImageUrl"; Description = "Метод sanitizeImageUrl()" },
    @{ File = "CollectionHtmlBuilder.php"; Pattern = "public function build"; Description = "Публичный метод build()" },
    @{ File = "CollectionHtmlBuilder.php"; Pattern = "CollectionCardRenderer"; Description = "Использование CollectionCardRenderer" },
    @{ File = "PublicPageController.php"; Pattern = "CollectionHtmlBuilder"; Description = "Импорт CollectionHtmlBuilder" },
    @{ File = "PublicPageController.php"; Pattern = "new CollectionHtmlBuilder"; Description = "Создание экземпляра CollectionHtmlBuilder" }
)

foreach ($check in $checks) {
    $filePath = "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Helper\$($check.File)"
    if ($check.File -like "*PublicPageController*") {
        $filePath = "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php"
    }
    
    if (Test-Path $filePath) {
        $content = Get-Content -Path $filePath -Raw
        if ($content -like "*$($check.Pattern)*") {
            Write-Host "  ✓ $($check.Description)" -ForegroundColor Green
        } else {
            Write-Host "  ✗ $($check.Description) НЕ НАЙДЕН!" -ForegroundColor Red
        }
    }
}

# 5. Проверка JS файлов
Write-Host "`n[5] Проверка содержимого JS файлов..." -ForegroundColor Yellow

$jsChecks = @(
    @{ File = "card-templates.js"; Pattern = "renderCard"; Description = "Функция renderCard()" },
    @{ File = "card-templates.js"; Pattern = "renderGrid"; Description = "Функция renderGrid()" },
    @{ File = "card-templates.js"; Pattern = "escapeHtml"; Description = "Функция escapeHtml()" },
    @{ File = "card-templates.js"; Pattern = "sanitizeImageUrl"; Description = "Функция sanitizeImageUrl()" },
    @{ File = "card-templates.js"; Pattern = "window.CardTemplates"; Description = "Экспорт window.CardTemplates" },
    @{ File = "collection-tabs.js"; Pattern = "CardTemplates.renderCard"; Description = "Использование CardTemplates.renderCard()" },
    @{ File = "collection-tabs.js"; Pattern = "section h2"; Description = "Селектор 'section h2' (FIX)" }
)

foreach ($check in $jsChecks) {
    $filePath = $null
    if ($check.File -like "*card-templates*") {
        $filePath = "C:\xampp\htdocs\healthcare-cms-frontend\modules\card-templates.js"
    } else {
        $filePath = "C:\xampp\htdocs\healthcare-cms-frontend\collection-tabs.js"
    }
    
    if (Test-Path $filePath) {
        $content = Get-Content -Path $filePath -Raw
        if ($content -like "*$($check.Pattern)*") {
            Write-Host "  ✓ $($check.Description)" -ForegroundColor Green
        } else {
            Write-Host "  ✗ $($check.Description) НЕ НАЙДЕН!" -ForegroundColor Red
        }
    }
}

# 6. Проверка документации
Write-Host "`n[6] Проверка документации..." -ForegroundColor Yellow
$docs = @(
    "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\ARCHITECTURE_REFACTORING_DRY.md",
    "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\REFACTORING_SUMMARY.md"
)

foreach ($doc in $docs) {
    if (Test-Path $doc) {
        Write-Host "  ✓ $([System.IO.Path]::GetFileName($doc))" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $([System.IO.Path]::GetFileName($doc)) НЕ НАЙДЕН!" -ForegroundColor Red
    }
}

# 7. Итоговый результат
Write-Host "`n" -ForegroundColor Cyan
Write-Host "╔════════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                        РЕФАКТОРИНГ ЗАВЕРШЁН!                      ║" -ForegroundColor Cyan
Write-Host "║                                                                    ║" -ForegroundColor Cyan
Write-Host "║  Теперь проверьте работу в браузере:                              ║" -ForegroundColor Cyan
Write-Host "║  1. Откройте http://localhost/p/all-materials?section=guides      ║" -ForegroundColor Cyan
Write-Host "║  2. Кликните на 'Статьи' - заголовок должен измениться            ║" -ForegroundColor Cyan
Write-Host "║  3. Кликните на 'Гайды' - заголовок должен вернуться              ║" -ForegroundColor Cyan
Write-Host "║  4. Откройте F12 (Developer Tools) → Console                     ║" -ForegroundColor Cyan
Write-Host "║  5. Проверьте что нет ошибок и видны console.debug() сообщения   ║" -ForegroundColor Cyan
Write-Host "║                                                                    ║" -ForegroundColor Cyan
Write-Host "║  Документация: ARCHITECTURE_REFACTORING_DRY.md                    ║" -ForegroundColor Cyan
Write-Host "║               REFACTORING_SUMMARY.md                              ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
