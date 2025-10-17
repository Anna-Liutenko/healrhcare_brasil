# Промт: Установка Optional Composer Packages для Inline Editor

## Цель
Установить недостающие пакеты `league/html-to-markdown` и `ezyang/htmlpurifier` в проект, обновить autoload, перезапустить сервер и выполнить интеграционный smoke test PATCH-эндпоинта для проверки работы с полными библиотеками (не fallback).

## Контекст

### Текущее состояние
- **Проект:** Healthcare CMS Backend (PHP 8.2, Clean Architecture)
- **Путь к проекту:** `backend/` (относительно workspace root)
- **Composer:** установлен локально как `backend/composer.phar` (версия 2.8.12)
- **PHP CLI:** работает, но **БЕЗ zip extension** → Composer будет клонировать пакеты из git (медленнее, но работает)
- **Уже установлено:** 
  - `league/commonmark:^2.7`
  - `ramsey/uuid:^4.7`
  - Базовые dev-пакеты и autoload
- **Толерантный код:** `MarkdownConverter` и `HTMLSanitizer` имеют fallback-реализации, если optional-пакеты отсутствуют
- **PHP-сервер:** запущен на `http://127.0.0.1:8089` (built-in server, public директория: `backend/public`)

### Что нужно установить
1. `league/html-to-markdown` (версия `^5.1`) — для конвертации HTML → Markdown в `MarkdownConverter`
2. `ezyang/htmlpurifier` (версия `^4.16`) — для санитизации HTML в `HTMLSanitizer`

### Потенциальные проблемы
- Composer может долго работать из-за отсутствия zip extension (будет клонировать репозитории через git)
- Возможны конфликты версий (маловероятно, но нужно проверять вывод Composer)
- После установки нужно обновить autoload и перезапустить PHP-сервер
- Нужно убедиться, что классы загружаются корректно

---

## Пошаговый план выполнения (с самопроверками)

### Этап 1: Подготовка окружения

**Шаг 1.1:** Убедиться, что рабочая директория — workspace root.

```powershell
# Выполнить:
Get-Location
```

**Самопроверка 1.1:**
- Вывод должен содержать путь вида: `...\Разработка сайта с CMS`
- Если нет — выполнить `Set-Location 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS'`

**Шаг 1.2:** Проверить наличие `backend/composer.phar`.

```powershell
# Выполнить:
Test-Path 'backend/composer.phar'
```

**Самопроверка 1.2:**
- Вывод должен быть `True`
- Если `False` — сообщить об ошибке: "composer.phar отсутствует в backend/, нужно установить Composer"

**Шаг 1.3:** Проверить текущее состояние composer.json (убедиться, что базовые пакеты установлены).

```powershell
# Выполнить:
Get-Content 'backend/composer.json' | ConvertFrom-Json | Select-Object -ExpandProperty require
```

**Самопроверка 1.3:**
- Должны присутствовать: `league/commonmark`, `ramsey/uuid`
- Если отсутствуют — это проблема, нужно сообщить об ошибке

---

### Этап 2: Установка пакетов (по одному с проверкой)

**Шаг 2.1:** Установить `league/html-to-markdown`.

```powershell
# Выполнить (из workspace root):
Push-Location 'backend'; & php composer.phar require 'league/html-to-markdown:^5.1' --no-interaction; Pop-Location
```

**Самопроверка 2.1.A:** Проверить вывод Composer:
- Вывод должен содержать строку вида: `Package operations: X installs, 0 updates, 0 removals`
- Вывод должен содержать: `Generating optimized autoload files`
- Если в выводе есть `Your requirements could not be resolved` или `conflicts` — записать полный вывод ошибки и остановиться (сообщить об ошибке конфликта версий)

**Самопроверка 2.1.B:** Убедиться, что пакет появился в `vendor/league/`.

```powershell
# Выполнить:
Test-Path 'backend/vendor/league/html-to-markdown'
```

**Ожидание:** `True`  
**Если False:** Пакет не установился. Проверить логи Composer и попробовать ручную установку с `--prefer-source`.

**Самопроверка 2.1.C:** Проверить, что autoload обновлён (наличие записи в `vendor/composer/installed.json`).

```powershell
# Выполнить:
Get-Content 'backend/vendor/composer/installed.json' | Select-String 'html-to-markdown'
```

**Ожидание:** Вывод должен содержать строку с `"name": "league/html-to-markdown"`  
**Если нет:** Запустить `php backend/composer.phar dump-autoload -d backend` для принудительной регенерации autoload.

---

**Шаг 2.2:** Установить `ezyang/htmlpurifier`.

```powershell
# Выполнить (из workspace root):
Push-Location 'backend'; & php composer.phar require 'ezyang/htmlpurifier:^4.16' --no-interaction; Pop-Location
```

**Самопроверка 2.2.A:** Проверить вывод Composer:
- Вывод должен содержать: `Package operations: X installs, 0 updates, 0 removals`
- Вывод должен содержать: `Generating optimized autoload files`
- Если в выводе есть `conflicts` или ошибки — записать полный вывод и остановиться

**Самопроверка 2.2.B:** Убедиться, что пакет появился в `vendor/ezyang/`.

```powershell
# Выполнить:
Test-Path 'backend/vendor/ezyang/htmlpurifier'
```

**Ожидание:** `True`  
**Если False:** Пакет не установился. Проверить логи Composer и попробовать ручную установку с `--prefer-source`.

**Самопроверка 2.2.C:** Проверить наличие класса `HTMLPurifier` в autoload.

```powershell
# Выполнить:
Get-Content 'backend/vendor/composer/installed.json' | Select-String 'htmlpurifier'
```

**Ожидание:** Вывод должен содержать `"name": "ezyang/htmlpurifier"`  
**Если нет:** Запустить `php backend/composer.phar dump-autoload -d backend`.

---

### Этап 3: Проверка наличия классов в runtime

**Шаг 3.1:** Создать временный PHP-скрипт для проверки загрузки классов.

```powershell
# Выполнить:
@"
<?php
require __DIR__ . '/backend/vendor/autoload.php';

echo "Проверка загрузки классов:\n";

if (class_exists('League\HTMLToMarkdown\HtmlConverter')) {
    echo "✓ League\HTMLToMarkdown\HtmlConverter — найден\n";
} else {
    echo "✗ League\HTMLToMarkdown\HtmlConverter — НЕ найден\n";
    exit(1);
}

if (class_exists('HTMLPurifier')) {
    echo "✓ HTMLPurifier — найден\n";
} else {
    echo "✗ HTMLPurifier — НЕ найден\n";
    exit(1);
}

if (class_exists('HTMLPurifier_Config')) {
    echo "✓ HTMLPurifier_Config — найден\n";
} else {
    echo "✗ HTMLPurifier_Config — НЕ найден\n";
    exit(1);
}

echo "\nВсе классы загружаются корректно.\n";
exit(0);
"@ | Out-File -Encoding UTF8 -FilePath 'backend/check_classes.php'
```

**Шаг 3.2:** Запустить скрипт проверки.

```powershell
# Выполнить:
php backend/check_classes.php
```

**Самопроверка 3.2:**
- Вывод должен содержать три строки с `✓` (галочками)
- Вывод должен завершаться: `Все классы загружаются корректно.`
- Exit code должен быть `0`
- **Если Exit code = 1 или есть `✗`:** Классы не загружаются. Проверить:
  1. Наличие пакетов в `vendor/`
  2. Содержимое `vendor/autoload.php`
  3. Запустить `php backend/composer.phar dump-autoload -o -d backend`

**Шаг 3.3:** Удалить временный скрипт (опционально).

```powershell
# Выполнить:
Remove-Item 'backend/check_classes.php' -ErrorAction SilentlyContinue
```

---

### Этап 4: Перезапуск PHP-сервера

**Важно:** PHP кэширует загруженные классы. После установки новых пакетов нужно перезапустить сервер.

**Шаг 4.1:** Найти и остановить запущенный PHP-процесс (built-in server).

```powershell
# Выполнить:
Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*127.0.0.1:8089*' } | Stop-Process -Force
```

**Самопроверка 4.1:**
- Команда должна выполниться без ошибок
- Если процесс не найден (нормально, если сервер не запущен) — продолжить

**Шаг 4.2:** Запустить новый PHP-сервер.

```powershell
# Выполнить:
Start-Process -NoNewWindow -FilePath php -ArgumentList '-S','127.0.0.1:8089','-t','"backend/public"' -WorkingDirectory $PWD
```

**Шаг 4.3:** Подождать 2 секунды, чтобы сервер стартовал.

```powershell
# Выполнить:
Start-Sleep -Seconds 2
```

**Шаг 4.4:** Проверить, что сервер отвечает (health check).

```powershell
# Выполнить:
Invoke-RestMethod -Method Get -Uri 'http://127.0.0.1:8089/api/health' -ErrorAction Stop | ConvertTo-Json -Compress
```

**Самопроверка 4.4:**
- Вывод должен содержать: `"status":"ok"`
- Если ошибка подключения — сервер не запустился. Проверить:
  1. Запущен ли процесс php: `Get-Process -Name php`
  2. Занят ли порт 8089: `netstat -an | Select-String ':8089'`

---

### Этап 5: Интеграционный smoke test (PATCH + GET)

**Шаг 5.1:** Подготовить PATCH-запрос с известным pageId и blockId.

**Известные данные (из предыдущих проверок):**
- `pageId`: `a1b2c3d4-e5f6-7890-abcd-ef1234567891` (страница "Гайды")
- `blockId`: `42def4c1-2da4-41ca-b9af-230eeb326865` (блок page-header)
- `fieldPath`: `data.title`

```powershell
# Выполнить:
$timestamp = (Get-Date).ToString('o')
$payload = @{
    blockId = '42def4c1-2da4-41ca-b9af-230eeb326865'
    fieldPath = 'data.title'
    newMarkdown = "✅ SMOKE TEST PASSED - $timestamp"
} | ConvertTo-Json -Compress

Invoke-RestMethod -Method Patch -Uri 'http://127.0.0.1:8089/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891/inline' -ContentType 'application/json' -Body $payload -ErrorAction Stop | ConvertTo-Json -Depth 5
```

**Самопроверка 5.1.A:** Проверить HTTP-ответ:
- Ответ должен содержать: `"success":true`
- Ответ должен содержать: `"page":{"id":"a1b2c3d4-e5f6-7890-abcd-ef1234567891"`
- Ответ должен содержать обновлённый `block` с `"id":"42def4c1-2da4-41ca-b9af-230eeb326865"`
- **Если ответ содержит `"success":false` или `"error"`:** Записать полный текст ошибки и проверить логи:
  - Прочитать последние 20 строк из `backend/logs/errors.log`
  - Проверить, загружаются ли классы `HtmlConverter` и `HTMLPurifier` (вернуться к Этапу 3)

**Самопроверка 5.1.B:** Убедиться, что в ответе НЕТ признаков fallback-реализации:
- Если в логах (`backend/logs/api-requests.log` или `backend/logs/api-responses.log`) появляется слово `fallback` — это значит, что классы не загрузились
- Прочитать последние 50 строк логов:
  ```powershell
  Get-Content 'backend/logs/api-responses.log' -Tail 50
  ```

**Шаг 5.2:** Выполнить GET-запрос для проверки сохранённых данных.

```powershell
# Выполнить:
Invoke-RestMethod -Method Get -Uri 'http://127.0.0.1:8089/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891' -ErrorAction Stop | ConvertTo-Json -Depth 5
```

**Самопроверка 5.2:**
- В ответе должен быть блок с `"id":"42def4c1-2da4-41ca-b9af-230eeb326865"`
- В поле `data.title` блока должен быть текст: `✅ SMOKE TEST PASSED - <timestamp>`
- **Если текст НЕ обновился:** Данные не сохранились в БД. Проверить:
  1. Логи ошибок: `Get-Content 'backend/logs/errors.log' -Tail 30`
  2. Запрос-тела логи: `Get-Content 'backend/logs/api-requests.log' -Tail 30`
  3. Убедиться, что MySQL запущена и доступна

---

### Этап 6: Финальная проверка использования полных библиотек

**Шаг 6.1:** Добавить временное логирование в код для подтверждения использования библиотек.

**Цель:** Убедиться, что `MarkdownConverter` и `HTMLSanitizer` используют реальные библиотеки, а не fallback.

```powershell
# Создать временный тестовый скрипт:
@"
<?php
require __DIR__ . '/backend/vendor/autoload.php';

\$converter = new \Infrastructure\MarkdownConverter();
\$sanitizer = new \Infrastructure\HTMLSanitizer();

echo "=== Тест конвертера ===\n";
\$html = \$converter->toHTML('**Test** markdown');
echo "HTML: \$html\n";

\$markdown = \$converter->toMarkdown('<p><strong>Test</strong> HTML</p>');
echo "Markdown: \$markdown\n";

echo "\n=== Тест санитайзера ===\n";
\$dirty = '<script>alert(1)</script><p onclick=\"bad()\">Clean <strong>text</strong></p>';
\$clean = \$sanitizer->sanitize(\$dirty, [
    'allowedTags' => ['p', 'strong'],
    'allowedAttributes' => [],
    'allowedSchemes' => ['http' => true, 'https' => true]
]);
echo "Cleaned HTML: \$clean\n";

echo "\nВсе тесты пройдены.\n";
"@ | Out-File -Encoding UTF8 -FilePath 'backend/test_libraries.php'
```

**Шаг 6.2:** Запустить тест.

```powershell
# Выполнить:
php backend/test_libraries.php
```

**Самопроверка 6.2:**
- Вывод должен содержать корректный HTML (например, `<p><strong>Test</strong> markdown</p>`)
- Вывод должен содержать корректный Markdown (например, `**Test** HTML`)
- Вывод должен содержать очищенный HTML БЕЗ тегов `<script>` и атрибутов `onclick`
- **Если скрипт выдаёт ошибку "Class not found":** Классы не загружаются — вернуться к Этапу 3
- **Если вывод выглядит как простой текст (stripped tags, без форматирования):** Используются fallback-реализации — проверить наличие пакетов в vendor/

**Шаг 6.3:** Удалить тестовый скрипт.

```powershell
# Выполнить:
Remove-Item 'backend/test_libraries.php' -ErrorAction SilentlyContinue
```

---

## Финальный чеклист (самопроверка всех этапов)

Пройти по списку и убедиться, что все пункты выполнены:

- [ ] **Этап 1:** Проверена рабочая директория и наличие composer.phar
- [ ] **Этап 2:** Установлены оба пакета (`league/html-to-markdown` и `ezyang/htmlpurifier`)
- [ ] **Этап 2:** Autoload обновлён (проверено наличие пакетов в `vendor/composer/installed.json`)
- [ ] **Этап 3:** Классы загружаются в PHP (проверено через `check_classes.php`)
- [ ] **Этап 4:** PHP-сервер перезапущен и отвечает на health check
- [ ] **Этап 5:** PATCH-запрос вернул `"success":true`
- [ ] **Этап 5:** GET-запрос показал обновлённые данные в блоке
- [ ] **Этап 6:** Тестовый скрипт подтвердил использование полных библиотек (не fallback)

---

## Ожидаемый результат

После выполнения всех этапов:

1. Пакеты `league/html-to-markdown` и `ezyang/htmlpurifier` установлены в `backend/vendor/`
2. Autoload обновлён, классы загружаются корректно
3. PHP-сервер работает на `http://127.0.0.1:8089`
4. PATCH-запрос к `/api/pages/{id}/inline` успешно обновляет данные блока
5. `MarkdownConverter` использует `League\HTMLToMarkdown\HtmlConverter` (не fallback)
6. `HTMLSanitizer` использует `HTMLPurifier` (не fallback)
7. Roundtrip validation работает с полными библиотеками: Markdown → HTML → Sanitize → Markdown → сохранение в БД

---

## Troubleshooting (если что-то пошло не так)

### Проблема 1: Composer долго работает или зависает

**Причина:** Отсутствие zip extension, Composer клонирует репозитории через git  
**Решение:**
- Подождать (клонирование может занять 2-5 минут на пакет)
- Если зависло на >10 минут — прервать (Ctrl+C) и запустить с `--prefer-source` явно:
  ```powershell
  php backend/composer.phar require league/html-to-markdown:^5.1 --prefer-source --no-interaction
  ```

### Проблема 2: Конфликт версий при установке пакета

**Симптом:** Composer выводит `Your requirements could not be resolved`  
**Решение:**
1. Прочитать вывод Composer — он покажет конфликтующие пакеты
2. Попробовать более гибкую версию (например, `^5.0` вместо `^5.1`)
3. Если конфликт с `league/commonmark` — обновить commonmark: `php backend/composer.phar update league/commonmark --with-dependencies`

### Проблема 3: Классы не загружаются после установки

**Симптом:** `check_classes.php` выводит `✗ Class not found`  
**Решение:**
1. Проверить наличие папок в `vendor/`:
   ```powershell
   Get-ChildItem 'backend/vendor/league' -Directory
   Get-ChildItem 'backend/vendor/ezyang' -Directory
   ```
2. Принудительно перегенерировать autoload:
   ```powershell
   php backend/composer.phar dump-autoload -o -d backend
   ```
3. Проверить, что в `vendor/composer/installed.json` есть записи о пакетах

### Проблема 4: PATCH возвращает ошибку 500

**Симптом:** `Invoke-RestMethod` выдаёт ошибку или `"success":false`  
**Решение:**
1. Прочитать логи ошибок:
   ```powershell
   Get-Content 'backend/logs/errors.log' -Tail 50
   ```
2. Проверить, что MySQL запущена и доступна
3. Проверить, что blockId существует в БД (выполнить GET-запрос перед PATCH)
4. Убедиться, что сервер использует обновлённый autoload (перезапустить сервер)

### Проблема 5: Fallback-реализации всё ещё используются

**Симптом:** Логи или тесты показывают, что используется fallback (stripped tags вместо правильного HTML)  
**Решение:**
1. Убедиться, что классы загружаются (запустить `check_classes.php`)
2. Проверить, что autoload включает новые пакеты:
   ```powershell
   Get-Content 'backend/vendor/composer/autoload_classmap.php' | Select-String 'HtmlConverter'
   ```
3. Перезапустить PHP-сервер (обязательно!)
4. Проверить версии PHP в CLI и в сервере (должны совпадать):
   ```powershell
   php -v
   ```

---

## Дополнительные команды для отладки

### Проверить установленные пакеты

```powershell
php backend/composer.phar show -d backend | Select-String -Pattern 'league|ezyang'
```

### Проверить версии установленных пакетов

```powershell
php backend/composer.phar show league/html-to-markdown -d backend
php backend/composer.phar show ezyang/htmlpurifier -d backend
```

### Проверить autoload вручную

```powershell
php -r "require 'backend/vendor/autoload.php'; var_dump(class_exists('League\\HTMLToMarkdown\\HtmlConverter'));"
```

### Перегенерировать autoload с оптимизацией

```powershell
php backend/composer.phar dump-autoload -o -d backend
```

### Просмотреть последние логи API

```powershell
Get-Content 'backend/logs/api-responses.log' -Tail 30
Get-Content 'backend/logs/errors.log' -Tail 30
```

---

## Финальная команда (всё в одном, для быстрого запуска)

После того как убедились, что все пакеты установлены и классы загружаются, можно запустить всё одной командой:

```powershell
# Перезапуск сервера + PATCH + GET
Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*8089*' } | Stop-Process -Force; Start-Sleep 1; Start-Process -NoNewWindow -FilePath php -ArgumentList '-S','127.0.0.1:8089','-t','"backend/public"' -WorkingDirectory $PWD; Start-Sleep 2; $ts = (Get-Date).ToString('o'); $p = @{blockId='42def4c1-2da4-41ca-b9af-230eeb326865';fieldPath='data.title';newMarkdown="✅ FINAL TEST - $ts"} | ConvertTo-Json -Compress; Write-Host "PATCH Response:"; Invoke-RestMethod -Method Patch -Uri 'http://127.0.0.1:8089/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891/inline' -ContentType 'application/json' -Body $p | ConvertTo-Json -Depth 3; Start-Sleep 1; Write-Host "`nGET Response:"; Invoke-RestMethod -Method Get -Uri 'http://127.0.0.1:8089/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891' | Select-Object -ExpandProperty blocks | Where-Object { $_.id -eq '42def4c1-2da4-41ca-b9af-230eeb326865' } | ConvertTo-Json -Depth 3
```

**Ожидаемый результат:**
- PATCH Response: `"success":true`
- GET Response: блок с обновлённым `data.title` = `"✅ FINAL TEST - <timestamp>"`

---

## Итог

Этот промт содержит:
- ✅ Пошаговые инструкции с самопроверками после каждого шага
- ✅ Явные критерии успеха/неудачи для каждого этапа
- ✅ Команды PowerShell ready-to-copy
- ✅ Troubleshooting секцию для частых проблем
- ✅ Проверки наличия классов в runtime (не полагается на предположения)
- ✅ Финальный интеграционный тест (PATCH + GET)
- ✅ Проверку использования полных библиотек (не fallback)

LLM, выполняющая этот промт, будет последовательно проверять себя на каждом этапе и сможет обнаружить проблемы до перехода к следующему шагу.
