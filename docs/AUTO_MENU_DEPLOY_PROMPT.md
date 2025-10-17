# Prompt: Deploy Automatic Menu Changes to XAMPP

## Goal
Перенести локально внесённые изменения (backend + frontend, включая автоматическое меню) в рабочую копию CMS под XAMPP (`C:\xampp\htdocs`) и убедиться, что всё работает под Apache.

## Контекст
- Проект разрабатывается локально в директории `C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS`.
- Рабочий сервер (XAMPP) обслуживает файлы из `C:\xampp\htdocs\healthcare-cms-backend` и `C:\xampp\htdocs\healthcare-cms-frontend`.
- Apache на Windows может держать файлы открытыми, поэтому иногда копирование не удаётся до перезапуска сервиса.
- Был обновлён backend (Entity `Page`, use cases `CreatePage`/`UpdatePage`, репозиторий `MySQLPageRepository`, контроллер `MenuController` если изменялся) и frontend (`editor.js`, `api-client.js`, `styles.css`), плюс новые скрипты `scripts/smoke_menu_test_v2.ps1` и `scripts/menu_result.json` (появится после запуска).

## Обязательные шаги

### 1. Подготовка
1. Убедиться, что локальные тесты пройдены (смоук-скрипт `scripts/smoke_menu_test_v2.ps1` завершился на `MENU_OK`).
2. Открыть PowerShell с правами, достаточными для остановки Apache (либо использовать XAMPP Control Panel вручную).
3. Опционально: сделать резервную копию текущих файлов в `htdocs` (например, папка `_backup_2025-10-05`).

### 2. Остановка Apache (если нужны права на запись)
1. Через PowerShell:
   ```powershell
   Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force
   ```
   Или использовать XAMPP Control Panel → Stop для Apache (и MySQL, если требуется).
2. Убедиться, что процессы `httpd.exe` больше не висят (иначе файлы останутся заблокированными).

### 3. Копирование backend-файлов
1. Скопировать следующие файлы/каталоги:
   - `backend\src\Domain\Entity\Page.php`
   - `backend\src\Application\UseCase\CreatePage.php`
   - `backend\src\Application\UseCase\UpdatePage.php`
   - `backend\src\Infrastructure\Repository\MySQLPageRepository.php`
   - `backend\src\Presentation\Controllers\MenuController.php` (если обновлялся)
   - `backend\public\index.php` (если менялись маршруты)
   - Любые другие изменённые файлы (проверить через Git или список правок)
2. Команда PowerShell для копирования (пример):
   ```powershell
   $backendSrc = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend"
   $backendDst = "C:\xampp\htdocs\healthcare-cms-backend"
   Copy-Item "$backendSrc\src\Domain\Entity\Page.php" "$backendDst\src\Domain\Entity\" -Force
   Copy-Item "$backendSrc\src\Application\UseCase\CreatePage.php" "$backendDst\src\Application\UseCase\" -Force
   Copy-Item "$backendSrc\src\Application\UseCase\UpdatePage.php" "$backendDst\src\Application\UseCase\" -Force
   Copy-Item "$backendSrc\src\Infrastructure\Repository\MySQLPageRepository.php" "$backendDst\src\Infrastructure\Repository\" -Force
   Copy-Item "$backendSrc\src\Presentation\Controllers\MenuController.php" "$backendDst\src\Presentation\Controllers\" -Force
   Copy-Item "$backendSrc\public\index.php" "$backendDst\public\" -Force
   ```

### 4. Копирование frontend-файлов (по необходимости)
1. Если были изменения во фронте:
   ```powershell
   $frontSrc = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend"
   $frontDst = "C:\xampp\htdocs\healthcare-cms-frontend"
   Copy-Item "$frontSrc\editor.js" "$frontDst\" -Force
   Copy-Item "$frontSrc\api-client.js" "$frontDst\" -Force
   Copy-Item "$frontSrc\styles.css" "$frontDst\" -Force
   Copy-Item "$frontSrc\editor.html" "$frontDst\" -Force
   ```
2. При необходимости обновить другие assets (templates.js, blocks.js и т.д.)

### 5. Перезапуск Apache/MySQL
1. Через XAMPP Control Panel → Start (Apache, MySQL).
   - Если запуск через команду: `& "C:\xampp\apache\bin\httpd.exe" -k start`
2. Проверить, что Apache запущен и отдаёт backend/фронтенд.

### 6. Проверка после деплоя
1. Выполнить смоук-тест уже по файлам в `htdocs` (конфигурация та же — `http://localhost/healthcare-cms-backend/public`).
2. Убедиться, что результат `MENU_OK`.
3. Открыть фронтенд `http://localhost/healthcare-cms-frontend/` и убедиться, что редактор/меню работают (опционально — проверить публичный сайт).

### 7. Завершение
1. Зафиксировать результат (например, сохранить лог смоук-теста в `scripts/menu_result.json`).
2. Если делали резервную копию — можно удалить, когда убедитесь в стабильности.
3. Задокументировать (примечание в `PROJECT_STATUS.md` или другом отчёте).

## Разрешение проблем
- Если `Copy-Item` жалуется на «file in use»: убедиться, что Apache остановлен. Иногда помогает переименовать файл в `htdocs` перед копированием.
- Если Apache не стартует после копирования, посмотреть лог `C:\xampp\apache\logs\error.log` (часто проблема в синтаксисе PHP; убедиться, что все файлы скопированы полностью).
- Если смоук-тест возвращает `MENU_FAIL`, перепроверить, что все backend-изменения попали в `htdocs` и база содержит нужные столбцы (`menu_label`).

## Итог
После выполнения шагов локальная и рабочая копии будут синхронизированы, автоматическое меню заработает под XAMPP, а смоук-тест подтвердит, что `GET /api/menu/public` возвращает опубликованную страницу с `show_in_menu=1`.
