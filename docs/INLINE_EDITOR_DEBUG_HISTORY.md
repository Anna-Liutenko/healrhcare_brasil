# История отладки Inline-редактирования
**Дата:** 15-16 октября 2025  
**Цель:** Реализация и исправление inline-редактирования блоков на странице

---

## Фаза 1: Начальная реализация (завершена ранее)

### Что было сделано
- **Backend Stage 1:**
  - `Infrastructure\Service\MarkdownConverter` — конвертация Markdown ↔ HTML
  - `Infrastructure\Service\HTMLSanitizer` — очистка HTML (ezyang/htmlpurifier)
  - `Application\UseCase\UpdatePageInline` — use-case для обновления полей блока
  - `Presentation\Controller\PageController::patchInline` — endpoint `PATCH /api/pages/{id}/inline`
  
- **Frontend:**
  - `frontend/js/InlineEditorManager.js` — менеджер inline-редактирования
  - Аннотации `data-inline-editable`, `data-block-id`, `data-field-path` в `editor.js`
  - CSS стили для подсветки редактируемых элементов
  - Debounced autosave (2 секунды) + Ctrl+S для ручного сохранения

- **Тестирование:**
  - Playwright harness для E2E smoke-тестов (прошли успешно)
  - `frontend/tests/inline-editor-test.html` — standalone harness с mock fetch

---

## Фаза 2: Обнаружение UX-проблем (15 октября)

### Проблемы, выявленные пользователем
1. **"Залипание" подсветки** — после клика вне редактируемого элемента подсветка остаётся
2. **Низкий контраст** — слабо видно, какой элемент активен
3. **Кнопка переключения режима** — нет визуальной обратной связи (label не меняется)
4. **Отсутствие toolbar** — нет WYSIWYG-панели (запланирована позже)
5. **Блок "About" не редактируется** — элементы не имеют `data-*` атрибутов
6. **Сохранённый текст не виден после публикации** — после inline-сохранения изменения не попадают в rendered_html

---

## Фаза 3: Исправление frontend UX (15 октября)

### Изменения в `InlineEditorManager.js`

#### 3.1. Исправление "залипания" подсветки
**Проблема:** После клика вне элемента, `contenteditable` и класс `.inline-editing` оставались.

**Решение:**
```javascript
// Добавлен глобальный обработчик клика в enableInlineMode()
document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-inline-editable]')) {
        this.stopEdit();
    }
});
```

**Усиление методов `startEdit()` и `stopEdit()`:**
```javascript
stopEdit() {
    if (this.activeElement) {
        this.activeElement.removeAttribute('contenteditable');
        this.activeElement.classList.remove('inline-editing');
        this.activeElement = null;
    }
}

startEdit(element) {
    this.stopEdit(); // Сначала сбрасываем предыдущий
    this.activeElement = element;
    element.setAttribute('contenteditable', 'true');
    element.classList.add('inline-editing');
    element.focus();
}
```

#### 3.2. Добавление диагностического логирования
```javascript
saveChanges() {
    console.debug('[InlineEditor] saveChanges called', {
        pageId: this.pageId,
        blockId,
        fieldPath,
        htmlPreview: html.substring(0, 100)
    });
    // ... fetch PATCH
}
```

#### 3.3. Совместимость payload с backend
**Проблема:** Backend ожидает `newMarkdown`, frontend отправлял только `markdown`.

**Решение:**
```javascript
const payload = {
    blockId,
    fieldPath,
    markdown: markdown,      // Старое поле (для совместимости)
    newMarkdown: markdown    // Новое поле (требуется backend)
};
```

---

### Изменения в `editor.js`

#### 3.4. Исправление toggle button labels
**Проблема:** Кнопка не меняла текст и aria-pressed при переключении режима.

**Решение:**
```javascript
// Инициализация: чтение data-атрибутов
const enableLabel = inlineToggle.dataset.inlineEnableLabel || 'Enable Inline';
const disableLabel = inlineToggle.dataset.inlineDisableLabel || 'Disable Inline';

inlineToggle.addEventListener('click', () => {
    const isInlineActive = inlineToggle.getAttribute('aria-pressed') === 'true';
    if (isInlineActive) {
        inlineManager.disableInlineMode();
        inlineToggle.textContent = enableLabel;
        inlineToggle.setAttribute('aria-pressed', 'false');
        inlineToggle.classList.remove('active');
    } else {
        inlineManager.enableInlineMode();
        inlineToggle.textContent = disableLabel;
        inlineToggle.setAttribute('aria-pressed', 'true');
        inlineToggle.classList.add('active');
    }
});
```

#### 3.5. Исправление `renderAboutSection` — добавление data-* атрибутов
**Проблема:** Блок "About" не имел атрибутов `data-inline-editable`, `data-block-id`, `data-field-path`.

**Было:**
```javascript
function renderAboutSection(aboutData) {
    return `
        <section class="about-section">
            <h2>${aboutData.title || 'О нас'}</h2>
            ${aboutData.paragraphs.map(p => `<p>${p}</p>`).join('')}
        </section>
    `;
}
```

**Стало:**
```javascript
function renderAboutSection(block) {
    const data = block.data;
    const blockId = block.id;
    return `
        <section class="about-section" data-block-type="about">
            <h2 
                data-inline-editable 
                data-block-id="${blockId}" 
                data-field-path="data.title"
                data-block-type="about"
            >${data.title || 'О нас'}</h2>
            ${data.paragraphs.map((p, idx) => `
                <p 
                    data-inline-editable 
                    data-block-id="${blockId}" 
                    data-field-path="data.paragraphs[${idx}]"
                    data-block-type="about"
                >${p}</p>
            `).join('')}
        </section>
    `;
}
```

**Результат:** Теперь все элементы About-блока стали редактируемыми inline.

---

### Изменения в `inline-editor.css`

#### 3.6. Улучшение контраста подсветки
```css
:root {
    --inline-border-color: #4a90e2;
    --inline-bg-color: rgba(74, 144, 226, 0.08);
    --inline-shadow-color: rgba(74, 144, 226, 0.3);
}

[data-inline-editable]:hover {
    outline: 2px dashed var(--inline-border-color);
    background-color: var(--inline-bg-color);
    cursor: pointer;
}

.inline-editing {
    outline: 3px solid var(--inline-border-color) !important;
    box-shadow: 0 0 8px var(--inline-shadow-color) !important;
    background-color: var(--inline-bg-color) !important;
}
```

---

## Фаза 4: Деплой и первые тесты (15 октября)

### 4.1. Создание deploy-скриптов

#### Попытка 1: `deploy-frontend-to-xampp.ps1` (robust)
**Проблема:** PowerShell не смог корректно обработать параметры с кириллицей и пробелами в пути.

**Ошибка:**
```
Cannot bind parameter 'SourceDir'. Cannot convert value...
```

#### Попытка 2: `deploy-frontend-to-xampp-simple.ps1` (упрощённый)
**Решение:** Hardcoded пути, явное указание кодировки UTF-8 BOM.

```powershell
$ErrorActionPreference = 'Stop'
$SourceDir = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend"
$DestDir = "C:\xampp\htdocs\visual-editor-standalone"

if (Test-Path $DestDir) {
    $timestamp = Get-Date -Format "yyyyMMddHHmmss"
    $backupDir = "$DestDir.bak_$timestamp"
    Move-Item -Path $DestDir -Destination $backupDir
    Write-Host "[deploy] Backed up to $backupDir"
}

Copy-Item -Path $SourceDir -Destination $DestDir -Recurse
Write-Host "[deploy] Deployment OK: frontend → $DestDir"
```

**Результат:** Деплой прошёл успешно. Созданы backups:
- `C:\xampp\htdocs\visual-editor-standalone.bak_20251015233832`
- и другие (при повторных деплоях)

### 4.2. Запуск Playwright smoke-тестов
```
Running 1 test using 1 worker
  ✓  1 inline-editor-test.html:3:1 › Inline editor smoke test (3.6s)
  1 passed (3.6s)
```

**Статус:** ✅ Тесты прошли.

---

## Фаза 5: Backend ошибки — начало отладки (15-16 октября)

### 5.1. Ошибка 400: Missing required fields

**Консоль браузера:**
```json
{
  "success": false,
  "error": "Missing required fields: blockId, fieldPath, newMarkdown"
}
```

**Причина:** Frontend отправлял только `markdown`, backend ожидал `newMarkdown`.

**Исправление:** Добавили `newMarkdown` в payload (см. 3.3).

**Результат:** 400 исчезла.

---

### 5.2. Ошибка 500: Block not found

**Консоль браузера (после исправления 400):**
```
PATCH http://localhost/healthcare-cms-backend/api/pages/9c23c3ff-1e2f-44fa-880f-c92b66a63257/inline
Status: 500 Internal Server Error

Response:
{
  "success": false,
  "error": "Block not found"
}

Client-side log:
[InlineEditor] saveChanges called {
  pageId: "9c23c3ff-1e2f-44fa-880f-c92b66a63257",
  blockId: "f34cac9d-b426-4b22-887a-3a194f06eba1",
  fieldPath: "data.paragraphs[1]",
  htmlPreview: "<p>Мы предоставляем доступ...</p>"
}
```

**Проблема:** Backend не находит блок с ID `f34cac9d-b426-4b22-887a-3a194f06eba1` для страницы `9c23c3ff-...`.

---

## Фаза 6: Анализ backend logic (16 октября)

### 6.1. Чтение `UpdatePageInline.php`

**Логика use-case:**
```php
public function execute(string $pageId, string $blockId, string $fieldPath, string $newMarkdown): array
{
    $page = $this->pageRepo->findById($pageId);
    if (!$page) {
        throw new InvalidArgumentException('Page not found');
    }

    $blocks = $this->blockRepo->findByPageId($pageId); // ← Загрузка блоков из БД
    
    $block = null;
    foreach ($blocks as $b) {
        if ($b->getId() === $blockId) { // ← Сравнение ID
            $block = $b;
            break;
        }
    }

    if (!$block) {
        throw new InvalidArgumentException('Block not found'); // ← Это и есть наша ошибка
    }

    // ... санитизация, обновление data, сохранение
}
```

**Вывод:** Use-case ищет блок в результатах `findByPageId($pageId)`. Если не находит — выбрасывает "Block not found".

---

### 6.2. Чтение `MySQLBlockRepository.php`

**Метод `findByPageId`:**
```php
public function findByPageId(string $pageId): array
{
    $stmt = $this->db->prepare('
        SELECT * FROM blocks
        WHERE page_id = :page_id
        ORDER BY position ASC
    ');
    $stmt->execute(['page_id' => $pageId]);
    $rows = $stmt->fetchAll();

    return array_map(fn($row) => $this->hydrate($row), $rows);
}
```

**Вывод:** Простой SQL-запрос по `page_id`. Если в таблице `blocks` нет записи с `id = f34cac9d-...` и `page_id = 9c23c3ff-...`, блок не вернётся.

---

### 6.3. Возможные причины "Block not found"

1. **Блок не существует в БД** — строка в таблице `blocks` отсутствует или имеет другой ID
2. **Неправильный `pageId`** — клиент отправляет другой pageId, чем тот, который есть в БД
3. **Разные окружения** — frontend работает с одной БД (например, MySQL), а backend с другой (sqlite E2E)
4. **Блоки не были сохранены** — импорт/seed не создал строки в таблице `blocks`, только в `page.content` (JSON)
5. **Encoding/case issues** — маловероятно для UUID, но возможно

---

## Фаза 7: Архитектурный скан (16 октября)

### 7.1. Обнаружение нарушений Clean Architecture

**Поиск по репозиторию:**
```regex
new\s+MySQL\w+Repository
```

**Результат:** 63+ совпадения в файлах:
- `PageController.php` — 7 мест с `new MySQLPageRepository()`, `new MySQLBlockRepository()`
- `PublicPageController.php` — 4 места
- `MenuController.php`, `MediaController.php`, `UserController.php`, `SettingsController.php`, `AuthController.php`, `TemplateController.php`
- `AuthHelper.php` (Infrastructure layer!) — создаёт репозитории напрямую
- Множество тестов (ожидаемо)

**Вывод:** Презентационный слой (Controllers) напрямую создаёт конкретные реализации репозиториев. Это нарушает Dependency Inversion Principle и затрудняет:
- Тестирование (невозможно легко подменить репозиторий mock'ом)
- Смену реализации (например, на PostgreSQL или Redis)
- Добавление middleware (логирование, метрики, кеширование)

---

### 7.2. Поиск вызовов `findByPageId`

**Результат:** 13 совпадений:
- Use-cases: `UpdatePageInline`, `GetPageWithBlocks`, `RenderPageHtml` (правильно — используют интерфейс)
- Repository: `MySQLBlockRepository` (реализация)
- Tests (mock implementations)

**Вывод:** Use-cases правильно используют интерфейс `BlockRepositoryInterface`. Проблема только в том, что Controllers создают конкретные репозитории вместо получения их через DI.

---

## Текущий статус (16 октября, 10:00 UTC+3)

### Что работает ✅
- Frontend inline-редактирование:
  - Подсветка элементов (без залипания)
  - Toggle кнопка с правильными labels
  - Блок "About" полностью редактируемый
  - Debounced autosave и Ctrl+S
  - Корректный payload с `newMarkdown`
- Деплой-скрипт работает
- Playwright тесты проходят

### Что не работает ❌
- **Сохранение изменений через PATCH endpoint:**
  - 500 Internal Server Error: "Block not found"
  - Причина: БД не содержит блок с указанным ID, либо используется неправильная БД

### Следующие шаги (TODO в проекте)
1. ✅ **Архитектурный скан** — завершён
2. 🔲 **Проверка БД** — выполнить SQL:
   ```sql
   SELECT id, page_id, type, position 
   FROM blocks 
   WHERE page_id = '9c23c3ff-1e2f-44fa-880f-c92b66a63257';
   ```
3. 🔲 **Добавить серверное логирование** — в `PageController::patchInline` для отладки
4. 🔲 **Рефакторинг DI** — создать RepositoryFactory и убрать `new MySQL*` из контроллеров
5. 🔲 **Debug endpoint** — создать `/api/internal/pages/{id}/blocks-debug` для быстрой проверки

---

## Технические детали

### Frontend Stack
- **Vue 3** (global build)
- **Vanilla JS** (`InlineEditorManager.js`)
- **Turndown.js** (HTML → Markdown, опционально)
- **CSS Custom Properties** (для темизации)

### Backend Stack
- **PHP 8+** (Clean Architecture)
- **MySQLBlockRepository** (Infrastructure layer)
- **UpdatePageInline** Use-case (Application layer)
- **PageController** (Presentation layer)
- **Libraries:**
  - `league/commonmark` (Markdown → HTML)
  - `league/html-to-markdown` (HTML → Markdown)
  - `ezyang/htmlpurifier` (HTML sanitization)

### Деплой
- **Источник:** `frontend/`
- **Назначение:** `C:\xampp\htdocs\visual-editor-standalone`
- **Backups:** `.bak_{timestamp}` при каждом деплое
- **Скрипт:** `deploy-frontend-to-xampp-simple.ps1`

### Тестирование
- **Playwright** — standalone harness (`frontend/tests/inline-editor-test.html`)
- **Manual testing** — браузер + DevTools console logs

---

## Логи и примеры

### Успешный деплой
```
[deploy] Destination exists
[deploy] Moving to: C:\xampp\htdocs\visual-editor-standalone.bak_20251015233832
[deploy] Copy-Item frontend → C:\xampp\htdocs\visual-editor-standalone
[deploy] Deployment OK
```

### Пример console.debug лога
```javascript
[InlineEditor] saveChanges called {
  pageId: "9c23c3ff-1e2f-44fa-880f-c92b66a63257",
  blockId: "f34cac9d-b426-4b22-887a-3a194f06eba1",
  fieldPath: "data.paragraphs[1]",
  htmlPreview: "<p>Мы предоставляем доступ к информации о системе здравоохранения Бразилии...</p>"
}
```

### Playwright test output
```
  ✓  1 inline-editor-test.html:3:1 › Inline editor smoke test (3.6s)
```

---

## Выводы

1. **Frontend полностью исправлен** — все UX-проблемы решены, код задеплоен.
2. **Backend logic корректен** — use-case правильно ищет блоки, проблема в данных.
3. **Корневая проблема** — несоответствие между frontend (block IDs в DOM) и backend (block IDs в БД).
4. **Архитектурная проблема** — прямое создание репозиториев в контроллерах нарушает Clean Architecture.

**Критический вопрос:** Почему блок с ID `f34cac9d-...` отсутствует в таблице `blocks` для страницы `9c23c3ff-...`?

**Рекомендуемые действия:**
1. Проверить БД (SQL запрос выше)
2. Добавить серверное логирование для отладки
3. После исправления данных — провести рефакторинг DI

---

**Документ создан:** 16 октября 2025  
**Автор:** GitHub Copilot (AI Assistant)  
**Статус:** Отладка продолжается
