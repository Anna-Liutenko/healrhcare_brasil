# E2E Testing: Implementation Summary
**Дата:** 8 октября 2025  
**Статус:** Готово к реализации

---

## 📋 Что создано

### Документация
1. **E2E_TESTING_IMPLEMENTATION_PROMPT.md** — детальный промпт с пошаговыми инструкциями для обеих задач (API + UI)
2. **E2E_QUICK_START.md** — краткая шпаргалка с командами для быстрого запуска
3. **DOCUMENTATION_INDEX.md** — обновлен с новой секцией тестирования
4. **DEVELOPER_CHEAT_SHEET.md** — добавлена секция "Running Tests"

### Что уже есть
- ✅ PHP HTTP E2E test (`backend/tests/E2E/HttpImportE2ETest.php`) — тестирует импорт шаблона
- ✅ PHPUnit установлен и работает
- ✅ Test bootstrap с sqlite in-memory (`backend/tests/_bootstrap.php`)
- ✅ GitHub Actions workflow для PHPUnit (`.github/workflows/phpunit.yml`)

### Что нужно сделать

#### Задача A: Расширить PHP E2E (15–30 минут)
- [ ] Переименовать `HttpImportE2ETest.php` → `HttpApiE2ETest.php`
- [ ] Добавить метод `testPageEditWorkflow()` с шагами:
  - CREATE страницу через API
  - UPDATE страницу через API
  - PUBLISH через API
  - VERIFY публичный URL (`/p/{slug}`)
- [ ] Запустить локально и убедиться, что проходит
- [ ] Убедиться, что проходит в CI

#### Задача B: Playwright UI E2E (2 часа)
- [ ] Создать структуру `frontend/e2e/`
- [ ] Создать `package.json` с Playwright
- [ ] Создать `playwright.config.js`
- [ ] Создать тест `tests/editor.spec.js` с шагами:
  - LOGIN в редактор
  - CREATE новую страницу через UI
  - ADD блоки (текст, hero)
  - SAVE страницу
  - PUBLISH страницу
  - VERIFY публичный URL
- [ ] Установить Playwright локально
- [ ] Запустить тест в headless режиме
- [ ] Запустить тест в headed режиме (визуальная проверка)
- [ ] Создать CI workflow `.github/workflows/playwright.yml`
- [ ] Убедиться, что проходит в GitHub Actions

---

## 🎯 Критерии успеха

### Задача A (PHP API E2E)
```powershell
# Команда
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests\_bootstrap.php tests\E2E

# Ожидаемый результат
OK (2 tests, 10+ assertions)
```

**Тесты должны проверять:**
- ✅ CREATE возвращает `page_id`
- ✅ UPDATE успешно меняет данные
- ✅ PUBLISH меняет статус на `published`
- ✅ Публичный URL отдаёт HTML с обновлённым контентом

### Задача B (Playwright UI E2E)
```powershell
# Команда
Set-Location frontend\e2e
npm test

# Ожидаемый результат
✓ 1 editor.spec.js:XX:XX › Page Editor Workflow › should login... (15s)
1 passed (15s)
```

**Тест должен проверять:**
- ✅ Модальное окно логина открывается
- ✅ Авторизация проходит успешно
- ✅ Блоки можно добавлять и заполнять
- ✅ Кнопка "Сохранить" работает и показывает уведомление
- ✅ Кнопка "Опубликовать" работает
- ✅ Публичная страница отображает контент

---

## 📦 Файлы для создания/изменения

### Задача A (PHP)
```
backend/tests/E2E/
  HttpApiE2ETest.php  ← переименовать из HttpImportE2ETest.php
                      ← добавить testPageEditWorkflow()
```

### Задача B (Playwright)
```
frontend/e2e/
  package.json                  ← создать
  playwright.config.js          ← создать
  README.md                     ← создать
  tests/
    editor.spec.js              ← создать

.github/workflows/
  playwright.yml                ← создать
```

---

## 🛠️ Команды для выполнения задач

### Задача A: Реализация

```powershell
# 1. Переименовать файл (в VS Code или через PowerShell)
Rename-Item `
  -Path "backend\tests\E2E\HttpImportE2ETest.php" `
  -NewName "HttpApiE2ETest.php"

# 2. Открыть файл и добавить метод testPageEditWorkflow()
# См. детали в E2E_TESTING_IMPLEMENTATION_PROMPT.md, раздел "Задача A"

# 3. Запустить тесты
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests\_bootstrap.php tests\E2E
Set-Location ..
```

### Задача B: Реализация

```powershell
# 1. Создать структуру папок
New-Item -Path "frontend\e2e\tests" -ItemType Directory -Force

# 2. Создать файлы (см. содержимое в E2E_TESTING_IMPLEMENTATION_PROMPT.md)
# - frontend/e2e/package.json
# - frontend/e2e/playwright.config.js
# - frontend/e2e/README.md
# - frontend/e2e/tests/editor.spec.js

# 3. Установить зависимости
Set-Location frontend\e2e
npm install
npx playwright install --with-deps

# 4. Запустить тесты (в отдельных вкладках)
# Вкладка 1: PHP server
Set-Location backend
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public

# Вкладка 2: Playwright
Set-Location frontend\e2e
npm test

# 5. Создать CI workflow
# См. .github/workflows/playwright.yml в промпте
```

---

## 🔍 Траблшутинг

### PHP тесты: "Class not found"
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' composer.phar install
Set-Location ..
```

### Playwright: "Timeout waiting for selector"
```powershell
# Проверить, что сервер запущен
curl http://127.0.0.1:8000/api/health

# Запустить в headed режиме, чтобы увидеть проблему
npm run test:headed

# Использовать debug mode
npm run test:debug
```

### E2E пропускается (skipped)
- Это нормально в некоторых окружениях (Windows agent)
- В CI на Ubuntu должно работать
- Проверьте, запущен ли built-in PHP server

---

## 📚 Ресурсы

### Документация проекта
- **Детальный промпт:** [E2E_TESTING_IMPLEMENTATION_PROMPT.md](./E2E_TESTING_IMPLEMENTATION_PROMPT.md)
- **Быстрый старт:** [E2E_QUICK_START.md](./E2E_QUICK_START.md)
- **Индекс документации:** [DOCUMENTATION_INDEX.md](./DOCUMENTATION_INDEX.md)
- **Шпаргалка разработчика:** [DEVELOPER_CHEAT_SHEET.md](./DEVELOPER_CHEAT_SHEET.md)

### Внешние ресурсы
- [Playwright Documentation](https://playwright.dev/)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [GitHub Actions for Playwright](https://playwright.dev/docs/ci-intro)

---

## ⏱️ Оценка времени

| Задача | Оценка | Приоритет |
|--------|--------|-----------|
| A. PHP API E2E | 15–30 мин | Высокий |
| B. Playwright UI E2E | 2 часа | Высокий |
| CI настройка | 30 мин | Средний |
| Документация | ✅ Готово | — |

**Общее время:** ~3 часа

---

## ✅ Следующие шаги

1. **Прочитать детальный промпт:** [E2E_TESTING_IMPLEMENTATION_PROMPT.md](./E2E_TESTING_IMPLEMENTATION_PROMPT.md)
2. **Начать с задачи A** (быстрая, даёт обратную связь)
3. **Перейти к задаче B** (полное покрытие UI)
4. **Настроить CI** (автоматический запуск)
5. **Проверить, что всё работает** в GitHub Actions

---

**Создано:** 8 октября 2025  
**Автор:** GitHub Copilot  
**Статус:** Готово к реализации ✅
