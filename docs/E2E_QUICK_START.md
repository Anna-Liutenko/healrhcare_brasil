# E2E Tests Quick Start
**Дата:** 8 октября 2025

Быстрая шпаргалка для запуска E2E тестов. Полное описание см. [E2E_TESTING_IMPLEMENTATION_PROMPT.md](./E2E_TESTING_IMPLEMENTATION_PROMPT.md)

---

## 🚀 Задача A: PHP API E2E Tests

### Что тестируем
- ✅ CREATE страницу через `POST /api/pages`
- ✅ UPDATE страницу через `PUT /api/pages/{id}`
- ✅ PUBLISH через `PUT /api/pages/{id}/publish`
- ✅ Проверяем публичный URL `/p/{slug}`

### Команды (PowerShell)

```powershell
# Запустить только E2E тесты
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests\E2E
Set-Location ..

# Запустить все тесты (Unit + Integration + E2E)
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests
Set-Location ..
```

### Ожидаемый результат
```
OK (7 tests, 14 assertions)
```
или
```
OK, but some tests were skipped! (E2E может пропуститься если сервер не запустился)
```

---

## 🎭 Задача B: Playwright Browser UI Tests

### Что тестируем
- ✅ Авторизация в редакторе
- ✅ Создание новой страницы через UI
- ✅ Добавление блоков (текст, hero)
- ✅ Сохранение и публикация
- ✅ Проверка публичной страницы в браузере

### Установка (первый раз)

```powershell
# 1. Перейти в папку e2e
Set-Location frontend\e2e

# 2. Установить зависимости
npm install

# 3. Установить браузеры Playwright
npx playwright install --with-deps

# 4. Вернуться в корень
Set-Location ..\..
```

### Команды запуска

#### Вариант 1: Автоматический запуск сервера (рекомендуется)
Если в `playwright.config.js` раскомментирован `webServer`, Playwright сам запустит PHP сервер.

```powershell
Set-Location frontend\e2e
npm test
Set-Location ..\..
```

#### Вариант 2: Ручной запуск сервера
**В первой вкладке PowerShell:**
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
```

**Во второй вкладке PowerShell:**
```powershell
Set-Location frontend\e2e
npm test
Set-Location ..\..
```

### Режимы запуска

```powershell
# Headless (по умолчанию, без видимого браузера)
npm test

# Headed (видимый браузер — удобно для отладки)
npm run test:headed

# Debug (пошаговая отладка с Playwright Inspector)
npm run test:debug

# UI Mode (интерактивный режим с Playwright UI)
npm run test:ui

# Просмотр HTML отчёта
npm run show-report
```

### Ожидаемый результат
```
Running 1 test using 1 worker
  ✓  1 editor.spec.js:XX:XX › Page Editor Workflow › should login, create... (15s)

  1 passed (15s)
```

---

## 🔍 Диагностика проблем

### PHP E2E тесты не запускаются
**Проблема:** `Class 'PHPUnit\Framework\TestCase' not found`

**Решение:**
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' composer.phar install
Set-Location ..
```

---

### Playwright тесты падают с timeout
**Проблема:** `Timeout 30000ms exceeded waiting for selector...`

**Решение 1:** Убедитесь, что PHP сервер запущен
```powershell
# Проверить:
curl http://127.0.0.1:8000/api/health
```

**Решение 2:** Увеличьте timeout в `playwright.config.js`
```javascript
timeout: 60000, // 60 секунд
```

**Решение 3:** Запустите в headed режиме, чтобы увидеть что происходит
```powershell
npm run test:headed
```

---

### Браузер не находит элементы
**Проблема:** Селекторы в тесте не соответствуют реальному HTML

**Решение:** Используйте Playwright Inspector
```powershell
npm run test:debug
```
Затем кликните на элемент в браузере — Playwright покажет правильный селектор.

---

## 📊 CI/CD

### GitHub Actions автоматически запускает:
1. **PHP тесты** — `.github/workflows/phpunit.yml`
2. **Playwright тесты** — `.github/workflows/playwright.yml`

### Просмотр результатов:
- GitHub → Actions → выбрать workflow run
- Скачать артефакты (HTML отчёты Playwright)

---

## 🎯 Быстрый чеклист перед коммитом

```powershell
# 1. Запустить PHP линтер
& 'C:\xampp\php\php.exe' -l backend\src\**\*.php

# 2. Запустить PHP тесты
Set-Location backend; & 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests\_bootstrap.php tests; Set-Location ..

# 3. Запустить Playwright тесты
Set-Location frontend\e2e; npm test; Set-Location ..\..

# 4. Проверить, что все прошло ✅
```

---

## 📚 Дополнительные ресурсы

- Полное описание: [E2E_TESTING_IMPLEMENTATION_PROMPT.md](./E2E_TESTING_IMPLEMENTATION_PROMPT.md)
- Playwright Docs: https://playwright.dev/
- PHPUnit Docs: https://phpunit.de/documentation.html
- Шпаргалка разработчика: [DEVELOPER_CHEAT_SHEET.md](./DEVELOPER_CHEAT_SHEET.md)

---

**Последнее обновление:** 8 октября 2025
