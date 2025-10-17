# Inline Editor — Design Specification

**Дата:** 15 октября 2025  
**Задача:** Реализовать inline-редактирование контента прямо в preview (click-to-edit)  
**Статус:** 📋 Design — готов к реализации

---

## 1. Цель и scope

### Цель
Дать пользователю возможность **быстро редактировать текст и форматирование** прямо в preview (не открывая модальный редактор блоков), с сохранением изменений в draft и публикацией одним кликом.

### Scope MVP (богатое форматирование)
- **Текстовое форматирование:** bold, italic, underline, strikethrough
- **Ссылки:** добавление/редактирование гиперссылок (href + title)
- **Списки:** упорядоченные (ol) и неупорядоченные (ul)
- **Изображения:**
  - **Картинки в шаблоне блока** (например, hero image, about-me photo): замена src (выбор из медиатеки), alt text. Размер фиксирован шаблоном.
  - **Картинки как отдельный блок** (например, image-block): замена src, alt text, **редактирование размера** (width/height или preset: small/medium/large).
- **Заголовки:** H2, H3, H4 (inline-преобразование параграфа)
- **Undo/Redo:** откат и повтор изменений (browser native + custom stack)

### Out of scope (MVP)
- Таблицы (оставить для модального редактора)
- Встраивание видео/iframe (опционально позже)
- Markdown-режим (можно добавить как feature toggle)
- Collaborative editing (real-time multi-user)

---

## 2. UX flow: как пользователь будет работать

### 2.1 Вход в режим inline-редактирования

1. Пользователь открывает страницу в редакторе (`editor.html?id=...`)
2. Видит preview справа (или внизу в зависимости от layout)
3. **Наводит курсор на текстовый элемент** (h2, p, li, figcaption) → появляется **тонкий outline + иконка ✏️**
4. **Клик на элемент** → элемент становится **contenteditable**, появляется **floating toolbar** над элементом

### 2.2 Редактирование и форматирование

**Toolbar содержит кнопки:**
- **B** (bold) — `<strong>` → сохраняется как `**text**` в Markdown
- **I** (italic) — `<em>` → сохраняется как `*text*` в Markdown
- **U** (underline) — `<u>` → **НЕ поддерживается в Markdown**, конвертируется в HTML при сохранении
- **S** (strikethrough) — `<s>` → сохраняется как `~~text~~` в Markdown
- **🔗** (link) — открывает mini-popover с полями `href` и `title` → сохраняется как `[text](href "title")` в Markdown
- **• List** (unordered list) — превращает параграф в `<ul><li>` → сохраняется как `- item` в Markdown
- **1. List** (ordered list) — превращает параграф в `<ol><li>` → сохраняется как `1. item` in Markdown
- **H2 / H3 / H4** (heading level) — меняет тег элемента → сохраняется как `## / ### / ####` в Markdown
- **🖼️** (image) — поведение зависит от типа изображения:
  - **Картинка в шаблоне блока** (например, `<img class="hero-image">` в main-screen): открывает медиатеку для замены src и поле alt. Размер НЕ редактируется (фиксирован CSS шаблона). Сохраняется как `![alt](src)` в Markdown.
  - **Картинка как отдельный блок** (тип `image-block`): открывает медиатеку + редактор размера (preset: small/medium/large или width/height в px с **обязательной блокировкой пропорций**). Сохраняется с атрибутами width/height.
- **↩️** Undo / **↪️** Redo — откат/повтор изменений (уже реализовано в визуальном редакторе, расширяется для inline-режима)

**Keyboard shortcuts:**
- `Ctrl+B` → bold
- `Ctrl+I` → italic
- `Ctrl+K` → link
- `Ctrl+Z` → undo
- `Ctrl+Shift+Z` → redo
- `Esc` → отменить редактирование (revert)

### 2.3 Сохранение изменений

**Два режима:**

1. **Auto-save (debounced):**
   - После 2 секунд бездействия → отправляет PATCH `/api/pages/{id}/inline`
   - Показывает индикатор "💾 Saving..." → "✅ Saved"
   - Обновляет draft (не публикует автоматически)

2. **Manual save:**
   - Кнопка **"Save Draft"** (Ctrl+S) → сохраняет все изменения в draft
   - Кнопка **"Publish"** → сохраняет + вызывает `PUT /api/pages/{id}/publish`

**Cancel/Revert:**
- Кнопка **"Cancel"** (Esc) → откатывает изменения к последнему сохранённому состоянию
- При закрытии страницы с несохранёнными изменениями → показывает `beforeunload` prompt

---

## 3. Архитектура решения

### 3.0 Соответствие Clean Architecture + Markdown-first подход

**Принципы, которые мы соблюдаем:**

1. **Domain Layer (чистые бизнес-правила):**
   - `Domain\Entity\Page` — не зависит от способа редактирования (модальный или inline)
   - `Domain\Entity\Block` — хранит данные в `data` как ассоциативный массив, где текстовые поля хранятся в **Markdown**
   - `Domain\ValueObject\PageStatus` — остаётся неизменным

2. **Application Layer (use-cases):**
   - **NEW:** `Application\UseCase\UpdatePageInline` — новый use-case для inline-редактирования
   - **EXISTING:** `Application\UseCase\PublishPage` — переиспользуется без изменений
   - **EXISTING:** `Application\UseCase\RenderPageHtml` — переиспользуется, но добавляем Markdown → HTML конвертацию если её ещё нет

3. **Infrastructure Layer:**
   - **NEW:** `Infrastructure\MarkdownConverter` — конвертирует Markdown ↔ HTML (использует `league/commonmark`)
   - **EXISTING:** `Infrastructure\Repository\MySQLPageRepository` — без изменений
   - **UPDATED:** `Infrastructure\HTMLSanitizer` — теперь работает с Markdown: парсит → санитизирует HTML → конвертирует обратно в Markdown

4. **Presentation Layer:**
   - **UPDATED:** `Presentation\Controller\PageController` — добавляет endpoint `patchInline()`
   - **EXISTING:** `Presentation\Controller\PublicPageController` — без изменений

**Markdown-first подход:**
- Все текстовые поля в `Block->data` хранятся как **Markdown** (не HTML)
- Frontend (inline editor) конвертирует WYSIWYG изменения → Markdown перед отправкой на сервер
- Backend валидирует Markdown → конвертирует в HTML (для проверки безопасности) → конвертирует обратно в Markdown (roundtrip sanitization)
- При рендеринге публичной страницы: `RenderPageHtml` конвертирует Markdown → HTML через `MarkdownConverter`
- Преимущества:
  - Чистый, читаемый текст в БД (легко экспортировать, индексировать, делать поиск)
  - Нет лишних HTML-тегов (например `<span style="...">` из Word paste)
  - Совместимость с будущим Markdown-режимом редактора

**Новые зависимости:**
- `league/commonmark` (PHP) — для Markdown parsing/rendering
- `turndown.js` (JS, CDN) — для конвертации HTML → Markdown на frontend (при paste из Word, например)

---

### 3.1 Frontend (Vue.js + contenteditable)

```
┌─────────────────────────────────────────────────────────────┐
│                   INLINE EDITOR ARCHITECTURE                │
└─────────────────────────────────────────────────────────────┘

FRONTEND (editor.html)
├── InlineEditorManager.js ← NEW
│   ├── enableInlineEdit(element, blockId, fieldPath)
│   ├── disableInlineEdit(element)
│   ├── getEditedHTML() → sanitized HTML
│   └── applyFormatting(command) → execCommand / custom logic
│
├── FloatingToolbar.vue ← NEW component
│   ├── props: position, visible, availableCommands
│   ├── methods: execBold(), execItalic(), openLinkPopover()
│   └── emits: format-applied, toolbar-closed
│
├── LinkPopover.vue ← NEW component
│   ├── fields: href (input), title (input)
│   └── emits: link-inserted(href, title)
│
├── MediaPickerPopover.vue ← NEW (reuse existing media library)
│   └── emits: image-selected(src, alt)
│
├── ImageSizeEditor.vue ← NEW (for standalone image blocks only)
│   ├── props: currentWidth, currentHeight, blockType
│   ├── presets: small (300px), medium (600px), large (1200px), custom
│   ├── **aspect ratio lock:** ВСЕГДА включён (width/height взаимосвязаны)
│   └── emits: size-changed(width, height)
│
└── editor.js (existing)
    ├── enableInlineMode() ← NEW method
    │   └── для каждого preview-элемента добавляет hover + click listeners
    ├── saveInlineChanges() ← NEW
    │   └── собирает изменения, отправляет PATCH /api/pages/{id}/inline
    └── publishWithInlineChanges() ← UPDATED
        └── вызывает saveInlineChanges() → PUT /publish

───────────────────────────────────────────────────────────────

BACKEND (PHP)
├── Domain/Entity/Page.php ← БЕЗ ИЗМЕНЕНИЙ
│   └── Block->data хранит текстовые поля как Markdown
│
├── Presentation/Controller/PageController.php
│   └── patchInline(Request) ← NEW endpoint
│       └── принимает: { blockId, fieldPath, newMarkdown }
│       └── валидирует, санитизирует, обновляет блок
│
├── Application/UseCase/UpdatePageInline.php ← NEW
│   ├── execute(pageId, blockId, fieldPath, newMarkdown)
│   ├── validateMarkdown(markdown) → использует MarkdownConverter + HTMLSanitizer (roundtrip)
│   ├── updateBlockField(block, fieldPath, sanitizedMarkdown)
│   └── pageRepo->save(page)
│
├── Application/UseCase/RenderPageHtml.php ← MINOR UPDATE
│   └── convertMarkdownToHTML(string) ← используется при рендеринге блоков
│
├── Infrastructure/MarkdownConverter.php ← NEW
│   ├── toHTML(markdown) → HTML (использует league/commonmark)
│   └── toMarkdown(html) → Markdown (для roundtrip validation)
│
└── Infrastructure/HTMLSanitizer.php ← UPDATED
    └── sanitize(html, allowedTags, allowedAttributes) — работает в связке с MarkdownConverter
```

### 3.2 API Contract

#### PATCH `/api/pages/{id}/inline`

**Request:**
```json
{
  "blockId": "block-uuid-123",
  "fieldPath": "data.title",
  "newMarkdown": "Обновлённый **заголовок**"
}
```

**Примечание:** Frontend отправляет **Markdown**, а не HTML. Backend конвертирует Markdown → HTML при рендеринге публичной страницы.

**Response (success):**
```json
{
  "success": true,
  "page": {
    "id": "page-uuid",
    "status": "draft",
    "updatedAt": "2025-10-15T14:30:00Z"
  },
  "block": {
    "id": "block-uuid-123",
    "type": "main-screen",
    "data": {
      "title": "Обновлённый **заголовок**"
    }
  }
}
```

**Response (error):**
```json
{
  "success": false,
  "error": "Invalid Markdown: disallowed syntax"
}
```

**Валидация и санитизация:**
- Разрешённый Markdown синтаксис:
  - Bold: `**text**` или `__text__`
  - Italic: `*text*` или `_text_`
  - Strikethrough: `~~text~~`
  - Links: `[text](href "title")`
  - Lists: `- item` (unordered), `1. item` (ordered)
  - Headings: `## / ### / ####`
  - Images: `![alt](src)` (с опциональными атрибутами width/height через HTML: `<img src="..." width="600" height="400">`)
- **Запрещены:** HTML-теги `<script>`, `<iframe>`, raw HTML вставки (кроме `<img>` с атрибутами размера)
- Backend парсит Markdown → конвертирует в HTML → прогоняет через HTMLPurifier → сохраняет обратно как Markdown (roundtrip для валидации)
- Используем **league/commonmark** (PHP) для Markdown parsing

---

## 4. Edge cases и обработка ошибок

### 4.1 Конфликт изменений (concurrent edits)

**Проблема:** Два пользователя редактируют одну страницу одновременно.

**Решение MVP:**
- При PATCH проверяем `updatedAt` (optimistic locking): если страница изменилась с момента загрузки → возвращаем `409 Conflict`
- Frontend показывает уведомление: "Page was updated by another user. Please reload."
- Пользователь может скопировать свои изменения, перезагрузить и применить заново

**Future:** WebSocket + operational transforms (out of scope MVP)

### 4.2 Невалидный HTML от пользователя

**Проблема:** Пользователь вставил HTML из Word / скопировал с сайта → куча `<span style="...">`, вложенные теги.

**Решение:**
- Frontend: перехватываем `paste` event → вызываем `document.execCommand('insertText', false, plainText)` или используем **clipboard API** с санитизацией
- Backend: всегда прогоняем через HTMLPurifier → strip недопустимые теги/атрибуты
- Логируем случаи санитизации для мониторинга

### 4.3 XSS атаки

**Проблема:** Злоумышленник пытается вставить `<img src=x onerror="alert('XSS')">`.

**Решение:**
- **Frontend sanitization:** используем **DOMPurify** (JS) перед отправкой на сервер
- **Backend sanitization:** HTMLPurifier с whitelist тегов/атрибутов
- **CSP headers:** Content-Security-Policy запрещает inline scripts
- **Escaped output:** при рендеринге публичной страницы — экранируем, если не доверяем БД (но мы доверяем после санитизации)

### 4.4 Потеря несохранённых изменений

**Проблема:** Пользователь случайно закрыл вкладку / браузер упал.

**Решение:**
- **Auto-save** каждые 2 секунды → минимизирует потери
- **localStorage backup:** при каждом изменении сохраняем snapshot в `localStorage` → при перезагрузке предлагаем восстановить
- **beforeunload warning:** если есть несохранённые изменения → браузер показывает предупреждение

### 4.5 Изображения: broken links после inline-редактирования

**Проблема:** Пользователь заменил `src` на несуществующий файл.

**Решение:**
- При выборе изображения через медиатеку → всегда используем существующие файлы из `media` table
- Backend валидирует `src`: проверяет, что файл существует в `uploads/` или в `media` table
- Если файл не найден → возвращаем ошибку `400 Bad Request: Image not found`

---

## 5. Технические детали реализации

### 5.1 Frontend: InlineEditorManager.js

**Основные методы:**

```javascript
class InlineEditorManager {
  constructor(previewElement, pageId) {
    this.preview = previewElement;
    this.pageId = pageId;
    this.activeElement = null;
    this.toolbar = null;
    this.undoStack = [];
    this.redoStack = [];
  }

  enableInlineMode() {
    // Для каждого editable-элемента (h2, p, li, figcaption) добавляем:
    const editables = this.preview.querySelectorAll('[data-inline-editable]');
    editables.forEach(el => {
      el.addEventListener('mouseenter', this.showEditHint);
      el.addEventListener('click', this.startEdit);
    });
  }

  startEdit(element) {
    this.activeElement = element;
    element.setAttribute('contenteditable', 'true');
    element.focus();
    
    // Сохранить snapshot для undo
    this.pushUndoState(element.innerHTML);
    
    // Показать toolbar
    this.toolbar = new FloatingToolbar({
      position: this.getToolbarPosition(element),
      commands: this.getAvailableCommands(element)
    });
    this.toolbar.show();
    
    // Навесить listeners для auto-save
    element.addEventListener('input', this.onInput);
    element.addEventListener('blur', this.onBlur);
  }

  applyFormatting(command, value = null) {
    // Используем execCommand для простых команд
    if (['bold', 'italic', 'underline', 'strikethrough'].includes(command)) {
      document.execCommand(command, false, null);
    } else if (command === 'createLink') {
      document.execCommand('createLink', false, value);
    } else if (command === 'insertUnorderedList') {
      document.execCommand('insertUnorderedList', false, null);
    }
    // custom logic для сложных команд (heading level, image replacement)
    else if (command === 'replaceImage') {
      // value = { src, alt, width?, height? }
      const img = this.activeElement.querySelector('img') || this.activeElement;
      if (img.tagName === 'IMG') {
        img.src = value.src;
        img.alt = value.alt || '';
        
        // Размер редактируется только для standalone image blocks
        const blockType = this.activeElement.dataset.blockType;
        if (blockType === 'image-block' && value.width && value.height) {
          img.width = value.width;
          img.height = value.height;
        }
        // Для картинок в шаблоне блока (hero, about-me) — размер НЕ меняем
      }
    }
    
    this.pushUndoState(this.activeElement.innerHTML);
  }

  saveChanges() {
    const blockId = this.activeElement.dataset.blockId;
    const fieldPath = this.activeElement.dataset.fieldPath;
    
    // Конвертируем HTML → Markdown перед отправкой
    const turndownService = new TurndownService();
    const markdown = turndownService.turndown(this.activeElement.innerHTML);
    
    return fetch(`/api/pages/${this.pageId}/inline`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ blockId, fieldPath, newMarkdown: markdown })
    });
  }

  undo() {
    if (this.undoStack.length > 0) {
      const prevState = this.undoStack.pop();
      this.redoStack.push(this.activeElement.innerHTML);
      this.activeElement.innerHTML = prevState;
    }
  }

  redo() {
    if (this.redoStack.length > 0) {
      const nextState = this.redoStack.pop();
      this.undoStack.push(this.activeElement.innerHTML);
      this.activeElement.innerHTML = nextState;
    }
  }
}
```

### 5.2 Backend: UpdatePageInline use-case

```php
<?php
namespace Application\UseCase;

use Domain\Entity\Page;
use Domain\Repository\PageRepositoryInterface;
use Infrastructure\MarkdownConverter;
use Infrastructure\HTMLSanitizer;

class UpdatePageInline
{
    private PageRepositoryInterface $pageRepo;
    private MarkdownConverter $markdownConverter;
    private HTMLSanitizer $sanitizer;

    public function execute(string $pageId, string $blockId, string $fieldPath, string $newMarkdown): array
    {
        $page = $this->pageRepo->findById($pageId);
        if (!$page) {
            throw new \Exception('Page not found');
        }

        // Найти блок
        $block = null;
        foreach ($page->getBlocks() as $b) {
            if ($b->getId() === $blockId) {
                $block = $b;
                break;
            }
        }
        if (!$block) {
            throw new \Exception('Block not found');
        }

        // Валидация Markdown (roundtrip: Markdown → HTML → sanitize → Markdown)
        $html = $this->markdownConverter->toHTML($newMarkdown);
        $sanitizedHTML = $this->sanitizer->sanitize($html, [
            'allowedTags' => ['p', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'img', 'br'],
            'allowedAttributes' => [
                'a' => ['href', 'title', 'target'],
                'img' => ['src', 'alt', 'width', 'height', 'class']
            ]
        ]);
        $sanitizedMarkdown = $this->markdownConverter->toMarkdown($sanitizedHTML);

        // Обновить поле в data блока
        // fieldPath = "data.title" → разбить на части и обновить вложенное поле
        $data = $block->getData();
        $pathParts = explode('.', $fieldPath);
        $ref = &$data;
        foreach ($pathParts as $i => $key) {
            if ($i === count($pathParts) - 1) {
                $ref[$key] = $sanitizedMarkdown; // Сохраняем Markdown, НЕ HTML
            } else {
                if (!isset($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
        $block->setData($data);

        // Обновить updatedAt
        $page->setUpdatedAt(new \DateTimeImmutable());

        // Сохранить
        $this->pageRepo->save($page);

        return [
            'success' => true,
            'page' => [
                'id' => $page->getId(),
                'status' => $page->getStatus()->getValue(),
                'updatedAt' => $page->getUpdatedAt()->format(DATE_ATOM)
            ],
            'block' => [
                'id' => $block->getId(),
                'type' => $block->getType(),
                'data' => $block->getData() // Возвращаем Markdown
            ]
        ];
    }
}
```

### 5.3 MarkdownConverter с league/commonmark

**Установка:**
```bash
composer require league/commonmark
```

**Infrastructure/MarkdownConverter.php:**
```php
<?php
namespace Infrastructure;

use League\CommonMark\CommonMarkConverter;
use League\HTMLToMarkdown\HtmlConverter;

class MarkdownConverter
{
    private CommonMarkConverter $markdownParser;
    private HtmlConverter $htmlConverter;

    public function __construct()
    {
        $this->markdownParser = new CommonMarkConverter([
            'html_input' => 'strip', // Удалить raw HTML
            'allow_unsafe_links' => false, // Блокировать javascript: и data:
        ]);
        
        $this->htmlConverter = new HtmlConverter([
            'strip_tags' => true, // Удалить неподдерживаемые теги
        ]);
    }

    public function toHTML(string $markdown): string
    {
        return $this->markdownParser->convert($markdown)->getContent();
    }

    public function toMarkdown(string $html): string
    {
        return $this->htmlConverter->convert($html);
    }
}
```

---

### 5.4 Sanitization с HTMLPurifier (для roundtrip validation)

**Установка:**
```bash
composer require ezyang/htmlpurifier
```

**Infrastructure/HTMLSanitizer.php:**
```php
<?php
namespace Infrastructure;

class HTMLSanitizer
{
    public function sanitize(string $html, array $config): string
    {
        $purifierConfig = \HTMLPurifier_Config::createDefault();
        
        // Allowed tags
        $purifierConfig->set('HTML.Allowed', implode(',', $config['allowedTags']));
        
        // Allowed attributes per tag
        foreach ($config['allowedAttributes'] as $tag => $attrs) {
            $purifierConfig->set("HTML.AllowedAttributes.$tag", implode(',', $attrs));
        }
        
        // Запретить target кроме _blank
        $purifierConfig->set('Attr.AllowedFrameTargets', ['_blank']);
        
        // Включить автоматическую очистку вредоносных схем (javascript:, data:)
        $purifierConfig->set('URI.DisableExternalResources', false);
        $purifierConfig->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        
        $purifier = new \HTMLPurifier($purifierConfig);
        return $purifier->purify($html);
    }
}
```

---

## 6. Пошаговый план реализации

### Этап 1: Базовая инфраструктура (1-2 дня)

**Backend:**
- [ ] Установить зависимости:
  - `composer require league/commonmark` — Markdown → HTML parsing
  - `composer require league/html-to-markdown` — HTML → Markdown conversion (для roundtrip)
  - `composer require ezyang/htmlpurifier` — HTML sanitization
- [ ] Создать `Infrastructure/MarkdownConverter.php` с методами `toHTML()` и `toMarkdown()`
- [ ] Создать `Infrastructure/HTMLSanitizer.php` с методом `sanitize()`
- [ ] Создать `Application/UseCase/UpdatePageInline.php`
- [ ] Добавить endpoint `PageController::patchInline()`
- [ ] Написать unit-тест для MarkdownConverter (проверить roundtrip: Markdown → HTML → Markdown)
- [ ] Написать unit-тест для sanitizer (проверить что `<script>` удаляется)

**Frontend:**
- [ ] Подключить библиотеки (CDN или npm):
  - Turndown.js — HTML → Markdown conversion
  - (DOMPurify опционально, т.к. санитизация на backend)
- [ ] Создать `frontend/js/InlineEditorManager.js` (скелет класса)
- [ ] Добавить в `editor.html` кнопку "Enable Inline Editing" (toggle)
- [ ] Добавить CSS для hover outline (`.inline-editable-hover`)
- [ ] **Реализовать undo/redo:** переиспользовать существующий undo/redo stack из визуального редактора или создать новый для inline-режима

**Тест:**
- [ ] Запустить `editor.html`, включить inline mode, кликнуть на заголовок → элемент должен стать contenteditable
- [ ] Изменить текст → нажать Ctrl+Z → изменение откатилось (undo работает)

---

### Этап 2: Floating Toolbar (2-3 дня)

**Frontend:**
- [ ] Создать `frontend/components/FloatingToolbar.vue`
  - Кнопки: B, I, U, S, Link, UL, OL
  - Позиционирование: absolute, рассчитать координаты над активным элементом
- [ ] Реализовать `applyFormatting()` с использованием `document.execCommand()`
- [ ] Добавить keyboard shortcuts (Ctrl+B, Ctrl+I, Ctrl+K)
- [ ] Добавить visual feedback: активные кнопки подсвечиваются (bold активен если курсор в `<strong>`)

**Тест:**
- [ ] Выделить текст → нажать B → текст должен стать `<strong>`
- [ ] Ctrl+K → открывается popover для ссылки

---

### Этап 3: Link & Image поповеры (1-2 дня)

**Frontend:**
- [ ] Создать `LinkPopover.vue`: два input (href, title) + кнопка Insert
- [ ] Создать `MediaPickerPopover.vue`: переиспользовать существующий media library modal
- [ ] Создать `ImageSizeEditor.vue`: показывается только для standalone image blocks (type='image-block')
  - Presets: Small (300px), Medium (600px), Large (1200px), Custom (ввод width/height)
  - **Aspect ratio lock ВСЕГДА включён:** при изменении width автоматически пересчитывается height (и наоборот)
  - Формула: `newHeight = (newWidth / originalWidth) * originalHeight`
  - UI: два input (width, height) + иконка 🔗 (показывает, что пропорции заблокированы)
- [ ] Интегрировать с toolbar: клик на 🔗 → показывает LinkPopover
- [ ] Интегрировать с toolbar: клик на 🖼️ → проверяет `data-block-type`:
  - Если `image-block` → показывает MediaPicker + ImageSizeEditor
  - Если другой тип (main-screen, about-section) → показывает только MediaPicker (без редактора размера)
- [ ] Обработать вставку ссылки: создать Markdown `[text](href "title")` и заменить выделенный текст

**Backend:**
- [ ] Валидация `src` в sanitizer: проверять что файл существует в `uploads/` или `media` table
- [ ] Валидация `width`/`height`: должны быть положительные числа < 5000px (предотвратить DoS)

**Тест:**
- [ ] Выбрать текст → клик Link → ввести href → текст становится `<a href="...">`
- [ ] Клик на img в hero-блоке → выбрать новое изображение → src обновляется, размер остаётся прежним (контролируется CSS)
- [ ] Клик на img в image-block → выбрать изображение + preset Medium → src и width обновляются

---

### Этап 4: Auto-save и Manual save (2 дня)

**Frontend:**
- [ ] Реализовать debounced auto-save (2 секунды после последнего изменения)
- [ ] Показывать индикатор сохранения: "💾 Saving..." → "✅ Saved" → "❌ Error"
- [ ] Кнопка "Save Draft" (Ctrl+S) → вызывает `saveInlineChanges()`
- [ ] Кнопка "Publish" → `saveInlineChanges()` + `PUT /api/pages/{id}/publish`

**Backend:**
- [ ] Обновить `PublishPage` use-case: убедиться что inline-изменения сохранены перед публикацией

**Тест:**
- [ ] Изменить текст → подождать 2 сек → проверить что PATCH отправлен
- [ ] Перезагрузить страницу → изменения сохранены
- [ ] Нажать Publish → публичная страница обновилась

---

### Этап 5: Undo/Redo (1 день)

**Frontend:**
- [ ] Реализовать undo/redo stack в `InlineEditorManager`
- [ ] Навесить listeners на Ctrl+Z / Ctrl+Shift+Z
- [ ] Кнопки ↩️ Undo / ↪️ Redo в toolbar
- [ ] Сохранять snapshot после каждого `applyFormatting()` и каждые N символов при вводе

**Тест:**
- [ ] Сделать изменение → Ctrl+Z → изменение откатилось
- [ ] Ctrl+Shift+Z → изменение вернулось

---

### Этап 6: Edge cases и error handling (2 дня)

**Frontend:**
- [ ] `beforeunload` prompt если есть несохранённые изменения
- [ ] localStorage backup: сохранять snapshot при каждом изменении
- [ ] При перезагрузке: если есть backup → показать notification "Restore unsaved changes?"
- [ ] Обработка 409 Conflict: показать уведомление + предложить reload

**Backend:**
- [ ] Добавить optimistic locking: проверять `updatedAt` при PATCH
- [ ] Логировать случаи санитизации (удалённые теги) → мониторинг XSS попыток

**Тест:**
- [ ] Два пользователя редактируют одну страницу → второй получает 409
- [ ] Вставить HTML с `<script>` → backend очищает, frontend показывает warning

---

### Этап 7: Интеграция с существующим редактором (1 день)

**Frontend:**
- [ ] Добавить toggle "Inline Editing Mode" в editor.html (переключатель режимов)
- [ ] В режиме inline: скрыть модальные редакторы блоков, показать preview во весь экран
- [ ] При выходе из inline mode: сохранить изменения и вернуться к обычному редактору

**Backend:**
- [ ] Убедиться что inline-изменения корректно интегрируются с блоками
- [ ] При публикации: `RenderPageHtml` должен использовать обновлённые `data` из блоков

---

### Этап 8: Тесты (2-3 дня)

**Unit tests (Frontend):**
- [ ] Тест `InlineEditorManager::sanitize()` с DOMPurify
- [ ] Тест undo/redo stack

**Unit tests (Backend):**
- [ ] Тест `HTMLSanitizer::sanitize()` — удаляет `<script>`, оставляет `<strong>`
- [ ] Тест `UpdatePageInline::execute()` — обновляет блок и сохраняет

**Integration tests (Backend):**
- [ ] PATCH `/api/pages/{id}/inline` → проверить что блок обновился
- [ ] PATCH с невалидным HTML → возвращает 400
- [ ] PATCH с устаревшим `updatedAt` → возвращает 409

**E2E tests (Playwright / Puppeteer):**
- [ ] Открыть редактор → включить inline mode → кликнуть на заголовок → ввести текст → Ctrl+S → перезагрузить → текст сохранён
- [ ] Inline edit → выделить текст → Ctrl+B → публиковать → публичная страница показывает bold

---

### Этап 9: Документация (1 день)

- [ ] Обновить `docs/INLINE_EDITOR_SPEC.md` (этот файл) с финальными изменениями
- [ ] Создать `docs/INLINE_EDITOR_USER_GUIDE.md` — как пользоваться inline-редактором
- [ ] Обновить `docs/PUBLISH_WORKFLOW_IMPLEMENTATION.md` — добавить секцию про inline editing
- [ ] Добавить в `README.md` краткое описание inline editing feature

---

### Этап 10: Rollout и мониторинг (1-2 дня)

**Staging:**
- [ ] Деплой на staging сервер
- [ ] Прогнать E2E тесты на staging
- [ ] Ручное тестирование: редактировать несколько страниц, проверить публикацию

**Production (canary):**
- [ ] Включить inline editing для 10% пользователей (feature flag)
- [ ] Мониторить логи: количество PATCH запросов, ошибки санитизации, 409 конфликты
- [ ] Собрать feedback от пользователей

**Full rollout:**
- [ ] Если всё OK → включить для 100% пользователей
- [ ] Откатить временный controller fallback `fixUploadsUrls()` (см. Removal plan в `PUBLISH_WORKFLOW_IMPLEMENTATION.md`)

---

## 7. Метрики успеха

**Функциональность:**
- ✅ Пользователь может отредактировать заголовок/параграф прямо в preview
- ✅ Форматирование (bold, italic, link, list) работает корректно
- ✅ Изменения сохраняются в draft автоматически (auto-save)
- ✅ Публикация обновляет публичную страницу с inline-изменениями
- ✅ Undo/Redo работают, нет потери данных при случайном закрытии вкладки

**Безопасность:**
- ✅ XSS атаки блокируются (санитизация на frontend + backend)
- ✅ Невалидный HTML очищается, но не ломает страницу
- ✅ Логируются попытки вставки вредоносного кода

**Performance:**
- ✅ Auto-save не создаёт избыточную нагрузку (debounce 2 сек)
- ✅ Публичная страница рендерится так же быстро (pre-rendering не сломался)

**UX:**
- ✅ Пользователи оценивают inline editing как "быстрее чем модальный редактор" (опрос)
- ✅ Количество багов/жалоб на потерю данных < 1% от числа редактирований

---

## 8. Известные ограничения и future enhancements

### Ограничения MVP
1. **Нет collaborative editing:** два пользователя не могут одновременно редактировать (conflict → reload)
2. **Нет версионирования inline-изменений:** можно добавить историю изменений в будущем
3. **Inline editing только для текстовых полей:** структурные изменения (добавить блок, удалить блок) — только через модальный редактор

### Future enhancements
- **Markdown mode:** переключатель между WYSIWYG и Markdown (для продвинутых пользователей)
- **Table support:** inline-редактирование таблиц (сложно, оставить на v2)
- **Real-time collaboration:** WebSocket + operational transforms (как в Google Docs)
- **AI-ассистент:** предложения по улучшению текста, SEO-оптимизация (интеграция с LLM)
- **Comment threads:** возможность оставлять комментарии к выделенному тексту (review workflow)

---

## Заключение

Inline editor с богатым форматированием — это **мощный инструмент**, который значительно ускорит редактирование контента и улучшит UX. План реализации рассчитан на **~12-15 рабочих дней** (с учётом тестирования и документации).

**Следующий шаг:** начать с **Этапа 1** (базовая инфраструктура) и двигаться последовательно по этапам. После каждого этапа — тестировать и коммитить инкрементально.

Если нужно, я могу сразу начать реализацию — скажи с какого этапа начинаем (рекомендую с Этапа 1: backend sanitizer + frontend скелет).

---

**Автор:** Анна Лютенко + GitHub Copilot  
**Дата:** 15 октября 2025  
**Версия документа:** 1.0 (Design spec)
