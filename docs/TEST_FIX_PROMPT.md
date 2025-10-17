# Промпт: Минимальные тест-фиксы для PHPUnit

## Контекст и проблема

После запуска PHPUnit получили:
- **1 Error**: `Template 'simple-test' not found` в `ImportIntegrationTest::testImportCreatesPageAndBlocks`
- **1 Failure**: `testRequireAuthThrowsWhenInvalid` не бросает ожидаемый `UnauthorizedException`
- **6 Tests, 10 Assertions**: 4 теста прошли успешно

## Цель

Исправить оба падающих теста минимальными изменениями, используя только надёжные методы редактирования файлов.

---

## Исправление 1: Template Not Found (ImportIntegrationTest)

### Проблема
`FileSystemStaticTemplateRepository` использует жёстко закодированный путь `backend/templates/` и константу `TEMPLATE_MAP`. Тест создавал фикстуры в `tests/fixtures/templates`, а репозиторий их не видел.

### Решение
Обновить `backend/tests/Integration/ImportIntegrationTest.php` чтобы:
1. Записывать template-файл в `backend/templates/simple-test.html` (там, где репозиторий его ищет)
2. Создавать `.imported_templates.json` там же
3. **НО**: `FileSystemStaticTemplateRepository::TEMPLATE_MAP` жёстко закодирован и не содержит `'simple-test'`, поэтому `findBySlug('simple-test')` всегда вернёт `null`

### Два варианта решения

#### Вариант A: Использовать существующий шаблон из TEMPLATE_MAP
Изменить тест так, чтобы он использовал один из существующих шаблонов ('home', 'guides', 'blog', 'all-materials', 'bot', 'article'), например 'article'.

**Преимущества**:
- Не нужно менять production-код репозитория
- Минимальные изменения в тесте

**Недостатки**:
- Нужно чтобы файл `backend/templates/article.html` существовал или создать его в setUp()

#### Вариант B: Добавить 'simple-test' в TEMPLATE_MAP
Изменить `FileSystemStaticTemplateRepository::TEMPLATE_MAP` добавив ключ `'simple-test'`.

**Преимущества**:
- Тест остаётся логичным (использует свой slug)

**Недостатки**:
- Меняем production-код ради тестов (антипаттерн)
- Тестовый шаблон попадёт в продакшен-репозиторий

### Рекомендация: Вариант A

Изменить `ImportIntegrationTest.php`, чтобы:
1. Создать файл `backend/templates/article.html` с тестовым содержимым
2. Убедиться что `.imported_templates.json` существует
3. Вызвать `$useCase->execute('article', 'test-user-1', false)` вместо `'simple-test'`

---

## Исправление 2: AuthHelper UnauthorizedException не бросается

### Проблема
`AuthHelperTest::testRequireAuthThrowsWhenInvalid()`:
```php
$this->expectException(UnauthorizedException::class);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';
AuthHelper::requireAuth();
```

Тест ожидает исключение, но оно не бросается. Вероятная причина:
- `ApiLogger` кеширует заголовки в статическом свойстве `$requestHeaders`
- `ApiLogger::getRequestHeaders()` вызывает `captureHeaders()` только если кеш пустой
- Между тестами кеш не очищается → `AuthHelper::extractBearerToken()` читает старые заголовки
- Предыдущий тест (`testGetCurrentUserReturnsUserForValidToken`) установил валидный токен → он остаётся в кеше → текущий тест не бросает исключение

### Решение
В `AuthHelperTest::setUp()` очищать кеш заголовков `ApiLogger` через Reflection, чтобы каждый тест начинал с чистого состояния.

**Код для вставки в `setUp()` после `$stmt->execute([...])`:**

```php
        // Clear ApiLogger header cache to avoid cross-test leakage
        $apiLogRef = new \ReflectionClass(\Infrastructure\Middleware\ApiLogger::class);
        $reqHeadersProp = $apiLogRef->getProperty('requestHeaders');
        $reqHeadersProp->setAccessible(true);
        $reqHeadersProp->setValue(null, []);
```

---

## Детальный план выполнения (пошаговый)

### Шаг 1: Прочитать текущий ImportIntegrationTest
**Инструмент**: `run_in_terminal` + PowerShell `Get-Content`

**Команда**:
```powershell
Get-Content -Raw backend\tests\Integration\ImportIntegrationTest.php
```

**Цель**: Увидеть текущую структуру `setUp()` и `testImportCreatesPageAndBlocks()`.

---

### Шаг 2: Создать временный PHP-скрипт для патча ImportIntegrationTest
**Инструмент**: `create_file`

**Путь**: `c:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\patch_import_test.php`

**Содержимое**:
```php
<?php
// Temporary script to patch ImportIntegrationTest.php

$filePath = 'backend/tests/Integration/ImportIntegrationTest.php';
$content = file_get_contents($filePath);

// Find and replace: change templatesDir path
$oldTemplatesDir = "\$templatesDir = __DIR__ . '/../fixtures/templates';";
$newTemplatesDir = "\$templatesDir = dirname(__DIR__, 2) . '/templates';";

$content = str_replace($oldTemplatesDir, $newTemplatesDir, $content);

// Find and replace: change slug from 'simple-test' to 'article'
$content = str_replace("'simple-test'", "'article'", $content);

// Find and replace: change filename
$content = str_replace("'/simple-test.html'", "'/article.html'", $content);

// Save
file_put_contents($filePath, $content);

echo "ImportIntegrationTest patched successfully\n";
```

---

### Шаг 3: Выполнить патч ImportIntegrationTest
**Инструмент**: `run_in_terminal`

**Команда**:
```powershell
& 'C:\xampp\php\php.exe' .\patch_import_test.php; Remove-Item .\patch_import_test.php
```

**Ожидаемый вывод**:
```
ImportIntegrationTest patched successfully
```

---

### Шаг 4: Прочитать текущий AuthHelperTest
**Инструмент**: `run_in_terminal`

**Команда**:
```powershell
Get-Content -Raw backend\tests\Unit\AuthHelperTest.php
```

**Цель**: Увидеть текущую структуру `setUp()` и проверить, не был ли патч уже применён.

---

### Шаг 5: Создать временный PHP-скрипт для патча AuthHelperTest
**Инструмент**: `create_file`

**Путь**: `c:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\patch_auth_test.php`

**Содержимое**:
```php
<?php
// Temporary script to patch AuthHelperTest.php

$filePath = 'backend/tests/Unit/AuthHelperTest.php';
$content = file_get_contents($filePath);

// Check if already patched
if (strpos($content, 'Clear ApiLogger header cache') !== false) {
    echo "AuthHelperTest already patched, skipping\n";
    exit(0);
}

// Find the needle: line where user is seeded
$needle = "\$stmt->execute(['id' => 'u1', 'username' => 'u', 'email' => 'u@example.com', 'password_hash' => 'h', 'role' => 'admin']);";

// Code to insert after needle
$insert = <<<'PHPCODE'

        // Clear ApiLogger header cache to avoid cross-test leakage
        $apiLogRef = new \ReflectionClass(\Infrastructure\Middleware\ApiLogger::class);
        $reqHeadersProp = $apiLogRef->getProperty('requestHeaders');
        $reqHeadersProp->setAccessible(true);
        $reqHeadersProp->setValue(null, []);
PHPCODE;

// Replace
$content = str_replace($needle, $needle . $insert, $content);

// Save
file_put_contents($filePath, $content);

echo "AuthHelperTest patched successfully\n";
```

---

### Шаг 6: Выполнить патч AuthHelperTest
**Инструмент**: `run_in_terminal`

**Команда**:
```powershell
& 'C:\xampp\php\php.exe' .\patch_auth_test.php; Remove-Item .\patch_auth_test.php
```

**Ожидаемый вывод**:
```
AuthHelperTest patched successfully
```
или
```
AuthHelperTest already patched, skipping
```

---

### Шаг 7: Проверить синтаксис изменённых файлов
**Инструмент**: `run_in_terminal`

**Команда**:
```powershell
& 'C:\xampp\php\php.exe' -l backend\tests\Integration\ImportIntegrationTest.php; & 'C:\xampp\php\php.exe' -l backend\tests\Unit\AuthHelperTest.php
```

**Ожидаемый вывод**:
```
No syntax errors detected in backend\tests\Integration\ImportIntegrationTest.php
No syntax errors detected in backend\tests\Unit\AuthHelperTest.php
```

---

### Шаг 8: Запустить PHPUnit повторно
**Инструмент**: `run_in_terminal`

**Команда**:
```powershell
Set-Location backend; & 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests; Set-Location ..
```

**Ожидаемый вывод** (успех):
```
PHPUnit 10.5.58 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12

......                                                              6 / 6 (100%)

Time: 00:00.XXX, Memory: 10.00 MB

OK (6 tests, X assertions)
```

**Возможные проблемы**:
- Если файл `backend/templates/article.html` не существует → создать его в `ImportIntegrationTest::setUp()`
- Если `FileSystemStaticTemplateRepository::TEMPLATE_MAP['article']` указывает на несуществующий файл → тест упадёт с той же ошибкой

---

## Запасной вариант: Если 'article' тоже не найдётся

Если репозиторий не найдёт 'article' (файл не существует), добавить в `ImportIntegrationTest::setUp()` создание файла:

```php
// Ensure backend/templates/article.html exists for test
$articlePath = dirname(__DIR__, 2) . '/templates/article.html';
if (!file_exists($articlePath)) {
    file_put_contents($articlePath, "<html><head><title>Test Article</title></head><body><div data-block='{\"type\":\"text-block\"}'>Test content</div></body></html>");
}
```

**Временный PHP-скрипт для этого**:
```php
<?php
$filePath = 'backend/tests/Integration/ImportIntegrationTest.php';
$content = file_get_contents($filePath);

$needle = "// create a simple template file";
$insert = <<<'PHPCODE'
// Ensure backend/templates/article.html exists for test
        $articlePath = dirname(__DIR__, 2) . '/templates/article.html';
        if (!file_exists(dirname($articlePath))) {
            mkdir(dirname($articlePath), 0777, true);
        }
        if (!file_exists($articlePath)) {
            file_put_contents($articlePath, "<html><head><title>Test Article</title></head><body><div data-block='{\"type\":\"text-block\"}'>Test content</div></body></html>");
        }

        
PHPCODE;

$content = str_replace($needle, $insert . $needle, $content);
file_put_contents($filePath, $content);
echo "Added article.html creation\n";
```

---

## Критические замечания для избежания ошибок PowerShell

### ❌ НЕ делать:
1. **НЕ использовать многострочные строки прямо в PowerShell команде** — всегда будут проблемы с экранированием `\n`, `$`, кавычек
2. **НЕ использовать apply_patch/replace_string_in_file для файлов в путях с кириллицей** — инструменты имеют проблемы с unicode путями
3. **НЕ вставлять PHP-код с `$` переменными в PowerShell строку без экранирования** — PowerShell интерпретирует как свои переменные
4. **НЕ использовать одинарные кавычки внутри одинарных кавычек** — PowerShell не умеет их экранировать

### ✅ ДЕЛАТЬ:
1. **Создавать временные PHP-скрипты через `create_file`** — чистый PHP без проблем с экранированием
2. **Запускать эти скрипты через `& 'C:\xampp\php\php.exe' .\script.php`** — надёжно и безопасно
3. **Удалять временные скрипты после выполнения** — `Remove-Item .\script.php`
4. **Использовать heredoc `<<<'PHPCODE'` в PHP-скриптах** — не нужно экранировать `$`, `"`, `\n`
5. **Проверять существование изменений перед патчем** — `strpos($content, 'marker')` чтобы избежать дублирования
6. **Всегда проверять синтаксис после патча** — `php -l file.php`

---

## Чек-лист выполнения

- [ ] Шаг 1: Прочитать `ImportIntegrationTest.php`
- [ ] Шаг 2: Создать `patch_import_test.php`
- [ ] Шаг 3: Выполнить патч ImportIntegrationTest
- [ ] Шаг 4: Прочитать `AuthHelperTest.php`
- [ ] Шаг 5: Создать `patch_auth_test.php`
- [ ] Шаг 6: Выполнить патч AuthHelperTest
- [ ] Шаг 7: Проверить синтаксис PHP (lint)
- [ ] Шаг 8: Запустить PHPUnit
- [ ] Если тест упал на "article not found" → применить запасной вариант (создание article.html в setUp)
- [ ] Если тесты прошли → зафиксировать изменения, удалить временные файлы

---

## Ожидаемый результат

```
PHPUnit 10.5.58 by Sebastian Bergmann and contributors.

......                                                              6 / 6 (100%)

OK (6 tests, 12 assertions)
```

Все 6 тестов должны пройти:
- `AuthHelperTest::testGetCurrentUserReturnsNullWhenNoHeader` ✅
- `AuthHelperTest::testGetCurrentUserReturnsUserForValidToken` ✅
- `AuthHelperTest::testRequireAuthThrowsWhenInvalid` ✅ (исправлен)
- `ImportIntegrationTest::testImportCreatesPageAndBlocks` ✅ (исправлен)
- Остальные 2 теста (если есть) ✅

---

## Примечания

- Все изменения **только в тестовых файлах** (`backend/tests/`), production-код не трогаем
- Если `backend/templates/` не существует — создать вручную или в тесте
- Проверить что `.imported_templates.json` пустой или корректный JSON
- После успешного прогона можно очистить `backend/templates/article.html` если он был создан только для теста (или оставить для будущих запусков)
