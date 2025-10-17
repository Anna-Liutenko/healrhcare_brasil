# Как запустить E2E-тесты

## Проблема с кириллицей в пути
Windows PowerShell и `proc_open()` не могут корректно обработать пути с кириллицей.  
Поэтому E2E-тесты **требуют ручного запуска сервера** в отдельном окне.

## Пошаговая инструкция

### Шаг 1: Откройте ПЕРВОЕ окно PowerShell
Запустите встроенный PHP-сервер с тестовой SQLite базой:

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

# Установите переменные окружения для SQLite
$env:DB_DEFAULT = 'sqlite'
$env:DB_DATABASE = (Resolve-Path '.\tests\tmp\e2e.sqlite').Path

# Запустите сервер с server_bootstrap.php
& 'C:\xampp\php\php.exe' -d auto_prepend_file=tests\E2E\server_bootstrap.php -S 127.0.0.1:8089 -t public
```

**Оставьте это окно открытым!** Сервер должен работать во время тестов.

### Шаг 2: Откройте ВТОРОЕ окно PowerShell
Запустите тесты:

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

# Запустите весь набор E2E-тестов
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests/_bootstrap.php tests/E2E

# Или запустите один конкретный тест
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests/_bootstrap.php --filter testPageEditWorkflow tests/E2E/HttpApiE2ETest.php
```

### Шаг 3: Остановка сервера
Когда закончите тестирование, нажмите `Ctrl+C` в первом окне чтобы остановить сервер.

## Частые проблемы

### Ошибка: "Connection refused" или "failed to open stream"
**Причина:** Сервер не запущен.  
**Решение:** Проверьте, что первое окно с сервером всё ещё работает. Вы должны видеть строку:
```
PHP 8.2.12 Development Server (http://127.0.0.1:8089) started
```

### Ошибка: "Address already in use"
**Причина:** Порт 8089 уже занят другим процессом.  
**Решение:** 
```powershell
# Найдите процесс, слушающий порт 8089
netstat -ano | findstr ":8089"

# Завершите процесс (замените 12345 на реальный PID)
taskkill /PID 12345 /F
```

### Тест пропущен (skipped)
**Причина:** E2E-тесты были отключены в `setUp()` из-за невозможности автозапуска сервера.  
**Решение:** Убедитесь, что вы запустили сервер вручную в первом окне.

## Альтернатива: переместить проект в путь без кириллицы
Если хотите автоматический запуск сервера в тестах:

```powershell
# Переместите проект в путь без кириллицы
Move-Item 'C:\Users\annal\Documents\Мои сайты\...' 'C:\projects\healthcare-cms'
```

После этого `proc_open()` будет работать корректно и сервер запустится автоматически.

---
**Документация обновлена:** 9 октября 2025
