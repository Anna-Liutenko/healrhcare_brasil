# Промт для реализации Inline Editor — Этап 1: Базовая инфраструктура

**Дата:** 15 октября 2025  
**Этап:** 1 из 10  
**Длительность:** 1-2 дня  
**Цель:** Создать фундамент для inline-редактирования: backend санитизация + Markdown конвертация + frontend скелет

---

## КОНТЕКСТ

У меня есть CMS с визуальным редактором (Vue.js) и backend на PHP (Clean Architecture). Сейчас пользователь редактирует страницы через модальные редакторы блоков. Я хочу добавить **inline-редактирование**: возможность кликнуть на текст прямо в preview и отредактировать его на месте (как в Notion, Medium).

**Текущая архитектура:**
- **Backend:** PHP 8.2, Clean Architecture (Domain/Application/Infrastructure/Presentation)
- **Database:** MySQL, таблицы `pages` и `blocks`
- **Frontend:** Vue.js 3, визуальный редактор (`frontend/editor.html`, `frontend/editor.js`)
- **Хранение данных:** Все текстовые поля в `Block->data` хранятся в **Markdown** (не HTML)
- **Рендеринг публичных страниц:** `RenderPageHtml` use-case конвертирует Markdown → HTML при генерации `rendered_html`

**Окружение разработки:**
- Windows с XAMPP (Apache + MySQL)
- Пути:
  - Backend: `C:\xampp\htdocs\healthcare-cms-backend\`
  - Frontend: `C:\xampp\htdocs\healthcare-cms-frontend\`
- Composer для PHP зависимостей
- CDN для frontend библиотек (не используем npm в этом проекте)

**Документация:**
- Полная спецификация inline editor: `docs/INLINE_EDITOR_SPEC.md`
- Уточнения и ответы на вопросы: `docs/INLINE_EDITOR_CLARIFICATIONS.md`
- Текущая архитектура публикации: `docs/PUBLISH_WORKFLOW_IMPLEMENTATION.md`

---

## ЗАДАЧА

Реализовать **Этап 1: Базовая инфраструктура** для inline-редактора. Этап включает:

### Backend (PHP)
1. Установить необходимые зависимости через Composer
2. Создать `Infrastructure\MarkdownConverter` — конвертация Markdown ↔ HTML
3. Создать `Infrastructure\HTMLSanitizer` — санитизация HTML (защита от XSS)
4. Создать `Application\UseCase\UpdatePageInline` — use-case для сохранения inline-изменений
5. Добавить endpoint `Presentation\Controller\PageController::patchInline()` — API для inline-редактирования
6. Написать unit-тесты для MarkdownConverter и HTMLSanitizer

### Frontend (JavaScript/Vue.js)
1. Подключить библиотеку Turndown.js (HTML → Markdown конвертация) через CDN
2. Создать `frontend/js/InlineEditorManager.js` — скелет класса для управления inline-редактированием
3. Добавить в `frontend/editor.html` кнопку "Enable Inline Editing" (toggle для включения/выключения режима)
4. Добавить CSS для hover outline (визуальный feedback при наведении на редактируемый элемент)
5. Реализовать undo/redo stack (или переиспользовать существующий из модального редактора)

### Критерии успеха (тесты)
- Запустить `editor.html`, включить inline mode, кликнуть на заголовок → элемент становится `contenteditable`
- Изменить текст → нажать Ctrl+Z → изменение откатилось (undo работает)
- Backend unit-тест: Markdown → HTML → Markdown (roundtrip) возвращает исходный текст
- Backend unit-тест: HTML с `<script>` тегом → санитизация удаляет `<script>`

---

## ТЕХНИЧЕСКИЕ ТРЕБОВАНИЯ

### Backend: Зависимости (Composer)

Установить следующие пакеты:

```bash
cd C:\xampp\htdocs\healthcare-cms-backend
composer require league/commonmark
composer require league/html-to-markdown
composer require ezyang/htmlpurifier
```

**Зачем нужны:**
- `league/commonmark` — парсинг Markdown → HTML
- `league/html-to-markdown` — конвертация HTML → Markdown (для roundtrip validation)
- `ezyang/htmlpurifier` — санитизация HTML (защита от XSS, удаление вредоносных тегов)

---

### Backend: Infrastructure\MarkdownConverter.php

**Путь:** `backend/src/Infrastructure/MarkdownConverter.php`

**Функционал:**
- Метод `toHTML(string $markdown): string` — конвертирует Markdown в HTML
- Метод `toMarkdown(string $html): string` — конвертирует HTML обратно в Markdown
- Конфигурация CommonMark:
  - `html_input => 'strip'` — удалять raw HTML из Markdown
  - `allow_unsafe_links => false` — блокировать `javascript:` и `data:` схемы

**Пример кода:**

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
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        
        $this->htmlConverter = new HtmlConverter([
            'strip_tags' => true,
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

**Unit-тест (создать файл `backend/tests/Unit/Infrastructure/MarkdownConverterTest.php`):**

```php
<?php
namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Infrastructure\MarkdownConverter;

class MarkdownConverterTest extends TestCase
{
    private MarkdownConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new MarkdownConverter();
    }

    public function testMarkdownToHTML(): void
    {
        $markdown = "**bold** and *italic*";
        $html = $this->converter->toHTML($markdown);
        
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    public function testHTMLToMarkdown(): void
    {
        $html = "<p><strong>bold</strong> and <em>italic</em></p>";
        $markdown = $this->converter->toMarkdown($html);
        
        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('*italic*', $markdown);
    }

    public function testRoundtrip(): void
    {
        $originalMarkdown = "## Heading\n\n**Bold** text with [link](https://example.com)";
        $html = $this->converter->toHTML($originalMarkdown);
        $resultMarkdown = $this->converter->toMarkdown($html);
        
        // Roundtrip может немного изменить форматирование, но смысл должен сохраниться
        $this->assertStringContainsString('Heading', $resultMarkdown);
        $this->assertStringContainsString('**Bold**', $resultMarkdown);
        $this->assertStringContainsString('[link](https://example.com)', $resultMarkdown);
    }

    public function testBlocksUnsafeLinks(): void
    {
        $markdown = "[Click me](javascript:alert('XSS'))";
        $html = $this->converter->toHTML($markdown);
        
        // Не должно содержать javascript: схему
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
```

---

### Backend: Infrastructure\HTMLSanitizer.php

**Путь:** `backend/src/Infrastructure/HTMLSanitizer.php`

**Функционал:**
- Метод `sanitize(string $html, array $config): string` — очищает HTML от вредоносных тегов/атрибутов
- Использует HTMLPurifier с whitelist тегов и атрибутов
- Конфигурация:
  - Разрешённые теги: `<p>, <h2>, <h3>, <h4>, <strong>, <em>, <u>, <s>, <a>, <ul>, <ol>, <li>, <img>, <br>`
  - Разрешённые атрибуты:
    - `<a>`: `href`, `title`, `target` (только `_blank`)
    - `<img>`: `src`, `alt`, `width`, `height`, `class`
  - Разрешённые схемы URI: `http`, `https`, `mailto`

**Пример кода:**

```php
<?php
namespace Infrastructure;

use HTMLPurifier;
use HTMLPurifier_Config;

class HTMLSanitizer
{
    public function sanitize(string $html, array $config): string
    {
        $purifierConfig = HTMLPurifier_Config::createDefault();
        
        // Allowed tags
        $allowedTags = implode(',', $config['allowedTags']);
        $purifierConfig->set('HTML.Allowed', $allowedTags);
        
        // Allowed attributes per tag
        if (isset($config['allowedAttributes'])) {
            foreach ($config['allowedAttributes'] as $tag => $attrs) {
                $attrString = implode(',', $attrs);
                $purifierConfig->set("HTML.AllowedAttributes.$tag", $attrString);
            }
        }
        
        // Allowed target values for links
        $purifierConfig->set('Attr.AllowedFrameTargets', ['_blank']);
        
        // Allowed URI schemes
        $purifierConfig->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true
        ]);
        
        // Disable external resources in data: or other unsafe schemes
        $purifierConfig->set('URI.DisableExternalResources', false);
        
        $purifier = new HTMLPurifier($purifierConfig);
        return $purifier->purify($html);
    }
}
```

**Unit-тест (создать файл `backend/tests/Unit/Infrastructure/HTMLSanitizerTest.php`):**

```php
<?php
namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Infrastructure\HTMLSanitizer;

class HTMLSanitizerTest extends TestCase
{
    private HTMLSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HTMLSanitizer();
    }

    public function testRemovesScriptTags(): void
    {
        $html = '<p>Hello</p><script>alert("XSS")</script>';
        $config = [
            'allowedTags' => ['p', 'strong', 'em'],
            'allowedAttributes' => []
        ];
        
        $cleaned = $this->sanitizer->sanitize($html, $config);
        
        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringContainsString('<p>Hello</p>', $cleaned);
    }

    public function testRemovesOnEventHandlers(): void
    {
        $html = '<p onclick="alert(\'XSS\')">Click me</p>';
        $config = [
            'allowedTags' => ['p'],
            'allowedAttributes' => []
        ];
        
        $cleaned = $this->sanitizer->sanitize($html, $config);
        
        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringContainsString('Click me', $cleaned);
    }

    public function testAllowsSafeHTML(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $config = [
            'allowedTags' => ['p', 'strong', 'em'],
            'allowedAttributes' => []
        ];
        
        $cleaned = $this->sanitizer->sanitize($html, $config);
        
        $this->assertStringContainsString('<strong>Bold</strong>', $cleaned);
        $this->assertStringContainsString('<em>italic</em>', $cleaned);
    }

    public function testAllowsLinksWithAttributes(): void
    {
        $html = '<a href="https://example.com" title="Example" target="_blank">Link</a>';
        $config = [
            'allowedTags' => ['a'],
            'allowedAttributes' => [
                'a' => ['href', 'title', 'target']
            ]
        ];
        
        $cleaned = $this->sanitizer->sanitize($html, $config);
        
        $this->assertStringContainsString('href="https://example.com"', $cleaned);
        $this->assertStringContainsString('title="Example"', $cleaned);
        $this->assertStringContainsString('target="_blank"', $cleaned);
    }

    public function testBlocksJavascriptScheme(): void
    {
        $html = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $config = [
            'allowedTags' => ['a'],
            'allowedAttributes' => [
                'a' => ['href']
            ]
        ];
        
        $cleaned = $this->sanitizer->sanitize($html, $config);
        
        $this->assertStringNotContainsString('javascript:', $cleaned);
    }
}
```

---

### Backend: Application\UseCase\UpdatePageInline.php

**Путь:** `backend/src/Application/UseCase/UpdatePageInline.php`

**Функционал:**
- Принимает: `pageId`, `blockId`, `fieldPath` (например `"data.title"`), `newMarkdown`
- Находит страницу и блок по ID
- Выполняет roundtrip validation:
  1. Markdown → HTML (через MarkdownConverter)
  2. HTML → sanitized HTML (через HTMLSanitizer)
  3. Sanitized HTML → Markdown (обратно через MarkdownConverter)
- Обновляет поле в `Block->data` (по fieldPath)
- Сохраняет страницу (обновляет `updatedAt`)
- Возвращает результат: `{ success: true, page: {...}, block: {...} }`

**Пример кода:**

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

    public function __construct(
        PageRepositoryInterface $pageRepo,
        MarkdownConverter $markdownConverter,
        HTMLSanitizer $sanitizer
    ) {
        $this->pageRepo = $pageRepo;
        $this->markdownConverter = $markdownConverter;
        $this->sanitizer = $sanitizer;
    }

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
        
        // Пропустить первую часть если это "data"
        if ($pathParts[0] === 'data') {
            array_shift($pathParts);
        }
        
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

**Примечание:** Если у вас есть DI-контейнер, зарегистрируйте зависимости. Если нет — создайте их вручную в контроллере.

---

### Backend: Presentation\Controller\PageController::patchInline()

**Путь:** `backend/src/Presentation/Controller/PageController.php`

**Функционал:**
- Endpoint: `PATCH /api/pages/{id}/inline`
- Принимает JSON: `{ "blockId": "...", "fieldPath": "...", "newMarkdown": "..." }`
- Вызывает use-case `UpdatePageInline`
- Возвращает JSON response

**Пример кода (добавить в существующий PageController.php):**

```php
public function patchInline(string $id): void
{
    header('Content-Type: application/json');
    
    try {
        // Прочитать JSON из request body
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['blockId']) || !isset($input['fieldPath']) || !isset($input['newMarkdown'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // Создать use-case (или получить из DI-контейнера)
        $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
        $markdownConverter = new \Infrastructure\MarkdownConverter();
        $sanitizer = new \Infrastructure\HTMLSanitizer();
        $useCase = new \Application\UseCase\UpdatePageInline($pageRepo, $markdownConverter, $sanitizer);
        
        // Выполнить
        $result = $useCase->execute(
            $id,
            $input['blockId'],
            $input['fieldPath'],
            $input['newMarkdown']
        );
        
        http_response_code(200);
        echo json_encode($result);
        
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

**Не забудьте добавить роут в `backend/public/index.php`:**

```php
// Существующие роуты...

// Inline editing
if ($method === 'PATCH' && preg_match('#^/api/pages/([a-f0-9-]+)/inline$#', $path, $matches)) {
    $pageController->patchInline($matches[1]);
    exit;
}
```

---

### Frontend: Подключить Turndown.js через CDN

**Файл:** `frontend/editor.html`

**Добавить в `<head>` секцию:**

```html
<!-- Turndown.js для конвертации HTML → Markdown -->
<script src="https://cdn.jsdelivr.net/npm/turndown@7.1.2/dist/turndown.min.js"></script>
```

---

### Frontend: InlineEditorManager.js

**Путь:** `frontend/js/InlineEditorManager.js`

**Функционал (скелет для Этапа 1):**
- Класс для управления inline-редактированием
- Методы:
  - `enableInlineMode()` — включить режим (добавить listeners на preview элементы)
  - `disableInlineMode()` — выключить режим
  - `startEdit(element)` — сделать элемент contenteditable
  - `saveChanges()` — конвертировать HTML → Markdown и отправить PATCH запрос
  - `undo()`, `redo()` — управление undo/redo stack

**Пример кода:**

```javascript
class InlineEditorManager {
  constructor(previewElement, pageId) {
    this.preview = previewElement;
    this.pageId = pageId;
    this.activeElement = null;
    this.undoStack = [];
    this.redoStack = [];
    this.isInlineMode = false;
  }

  enableInlineMode() {
    this.isInlineMode = true;
    
    // Найти все редактируемые элементы (с data-inline-editable)
    const editables = this.preview.querySelectorAll('[data-inline-editable]');
    
    editables.forEach(el => {
      el.addEventListener('mouseenter', this.onMouseEnter.bind(this));
      el.addEventListener('mouseleave', this.onMouseLeave.bind(this));
      el.addEventListener('click', this.onClickElement.bind(this));
    });
    
    console.log(`Inline mode enabled for ${editables.length} elements`);
  }

  disableInlineMode() {
    this.isInlineMode = false;
    
    const editables = this.preview.querySelectorAll('[data-inline-editable]');
    editables.forEach(el => {
      el.removeEventListener('mouseenter', this.onMouseEnter.bind(this));
      el.removeEventListener('mouseleave', this.onMouseLeave.bind(this));
      el.removeEventListener('click', this.onClickElement.bind(this));
      el.classList.remove('inline-editable-hover');
      el.removeAttribute('contenteditable');
    });
    
    console.log('Inline mode disabled');
  }

  onMouseEnter(event) {
    if (!this.isInlineMode) return;
    event.currentTarget.classList.add('inline-editable-hover');
  }

  onMouseLeave(event) {
    if (!this.isInlineMode) return;
    event.currentTarget.classList.remove('inline-editable-hover');
  }

  onClickElement(event) {
    if (!this.isInlineMode) return;
    
    event.preventDefault();
    event.stopPropagation();
    
    this.startEdit(event.currentTarget);
  }

  startEdit(element) {
    this.activeElement = element;
    element.setAttribute('contenteditable', 'true');
    element.focus();
    
    // Сохранить snapshot для undo
    this.pushUndoState(element.innerHTML);
    
    console.log('Started editing:', element);
    
    // TODO (Этап 2): показать floating toolbar
  }

  async saveChanges() {
    if (!this.activeElement) return;
    
    const blockId = this.activeElement.dataset.blockId;
    const fieldPath = this.activeElement.dataset.fieldPath;
    
    // Конвертируем HTML → Markdown
    const turndownService = new TurndownService();
    const markdown = turndownService.turndown(this.activeElement.innerHTML);
    
    console.log('Saving changes:', { blockId, fieldPath, markdown });
    
    try {
      const response = await fetch(`/healthcare-cms-backend/api/pages/${this.pageId}/inline`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ blockId, fieldPath, newMarkdown: markdown })
      });
      
      const result = await response.json();
      
      if (result.success) {
        console.log('Saved successfully:', result);
        // TODO (Этап 4): показать индикатор "✅ Saved"
      } else {
        console.error('Save failed:', result.error);
        alert('Error saving: ' + result.error);
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Network error: ' + error.message);
    }
  }

  pushUndoState(html) {
    this.undoStack.push(html);
    this.redoStack = []; // Очистить redo при новом изменении
  }

  undo() {
    if (!this.activeElement || this.undoStack.length === 0) return;
    
    const prevState = this.undoStack.pop();
    this.redoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = prevState;
    
    console.log('Undo applied');
  }

  redo() {
    if (!this.activeElement || this.redoStack.length === 0) return;
    
    const nextState = this.redoStack.pop();
    this.undoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = nextState;
    
    console.log('Redo applied');
  }
}

// Экспортировать для использования в editor.js
window.InlineEditorManager = InlineEditorManager;
```

---

### Frontend: Обновить editor.html

**Файл:** `frontend/editor.html`

**Добавить кнопку "Enable Inline Editing" в интерфейс редактора:**

Найдите секцию с кнопками (например, рядом с "Save" и "Publish") и добавьте:

```html
<button id="toggleInlineMode" class="btn btn-secondary">
  📝 Enable Inline Editing
</button>
```

**Добавить в конец `<body>` (перед закрывающим тегом) подключение скрипта:**

```html
<script src="js/InlineEditorManager.js"></script>
```

---

### Frontend: Обновить editor.js

**Файл:** `frontend/editor.js`

**Добавить инициализацию InlineEditorManager и обработчик кнопки:**

```javascript
// В конец файла (после существующего кода)

let inlineEditorManager = null;
let inlineModeEnabled = false;

function initInlineEditor() {
  const previewElement = document.querySelector('.preview-container'); // Замените на ваш селектор preview
  const pageId = getCurrentPageId(); // Функция получения ID текущей страницы
  
  if (!previewElement) {
    console.warn('Preview container not found');
    return;
  }
  
  inlineEditorManager = new InlineEditorManager(previewElement, pageId);
  
  // Обработчик кнопки toggle
  const toggleBtn = document.getElementById('toggleInlineMode');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      inlineModeEnabled = !inlineModeEnabled;
      
      if (inlineModeEnabled) {
        inlineEditorManager.enableInlineMode();
        toggleBtn.textContent = '🚫 Disable Inline Editing';
        toggleBtn.classList.add('btn-danger');
        toggleBtn.classList.remove('btn-secondary');
      } else {
        inlineEditorManager.disableInlineMode();
        toggleBtn.textContent = '📝 Enable Inline Editing';
        toggleBtn.classList.add('btn-secondary');
        toggleBtn.classList.remove('btn-danger');
      }
    });
  }
  
  // Keyboard shortcuts (Ctrl+Z для undo, Ctrl+Shift+Z для redo)
  document.addEventListener('keydown', (e) => {
    if (!inlineModeEnabled || !inlineEditorManager.activeElement) return;
    
    if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
      e.preventDefault();
      inlineEditorManager.undo();
    } else if (e.ctrlKey && e.shiftKey && e.key === 'Z') {
      e.preventDefault();
      inlineEditorManager.redo();
    }
  });
}

// Вызвать при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
  // Ваш существующий код инициализации...
  
  initInlineEditor();
});
```

**Примечание:** Замените `getCurrentPageId()` на вашу функцию получения ID страницы (возможно, из URL query параметра `?id=...`).

---

### Frontend: CSS для hover outline

**Файл:** `frontend/styles.css` (или создать отдельный `frontend/inline-editor.css`)

**Добавить стили:**

```css
/* Inline Editor Styles */
.inline-editable-hover {
  outline: 2px dashed #4CAF50;
  outline-offset: 2px;
  cursor: pointer;
  transition: outline 0.2s ease;
}

.inline-editable-hover::after {
  content: '✏️';
  position: absolute;
  top: -10px;
  right: -10px;
  background: #4CAF50;
  color: white;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  pointer-events: none;
}

[contenteditable="true"] {
  outline: 2px solid #2196F3;
  outline-offset: 2px;
  background-color: #E3F2FD;
  padding: 4px;
  min-height: 20px;
}

[contenteditable="true"]:focus {
  outline: 2px solid #1976D2;
  background-color: #BBDEFB;
}
```

---

### Обновить preview rendering: добавить data-атрибуты

**Проблема:** Чтобы InlineEditorManager знал, какой блок и поле редактируется, нужно добавить `data-block-id`, `data-field-path`, и `data-inline-editable` атрибуты к HTML элементам в preview.

**Решение:** Обновить код генерации preview (в `editor.js` или в серверном рендере).

**Пример (для client-side preview в editor.js):**

Найдите функцию, которая рендерит блоки в preview (например `renderBlock()`) и добавьте data-атрибуты:

```javascript
function renderBlock(block) {
  const blockDiv = document.createElement('div');
  blockDiv.className = `block block-${block.type}`;
  blockDiv.dataset.blockId = block.id;
  
  if (block.type === 'main-screen') {
    const titleEl = document.createElement('h1');
    titleEl.textContent = block.data.title;
    
    // Добавить data-атрибуты для inline editing
    titleEl.dataset.inlineEditable = 'true';
    titleEl.dataset.blockId = block.id;
    titleEl.dataset.fieldPath = 'data.title';
    titleEl.dataset.blockType = block.type;
    
    blockDiv.appendChild(titleEl);
    
    // ... остальная вёрстка блока
  }
  
  // ... другие типы блоков
  
  return blockDiv;
}
```

**Для каждого редактируемого поля добавьте:**
- `data-inline-editable="true"` — маркер что элемент редактируемый
- `data-block-id="<block-id>"` — ID блока
- `data-field-path="data.title"` — путь к полю в `Block->data`
- `data-block-type="main-screen"` — тип блока (для conditional logic, например image size editor)

---

## КРИТЕРИИ ПРИЁМКИ (Checklist для проверки)

### Backend
- [ ] Установлены зависимости: `league/commonmark`, `league/html-to-markdown`, `ezyang/htmlpurifier`
- [ ] Создан `Infrastructure\MarkdownConverter.php` с методами `toHTML()` и `toMarkdown()`
- [ ] Создан `Infrastructure\HTMLSanitizer.php` с методом `sanitize()`
- [ ] Создан `Application\UseCase\UpdatePageInline.php`
- [ ] Добавлен endpoint `PageController::patchInline()`
- [ ] Добавлен роут `PATCH /api/pages/{id}/inline` в `index.php`
- [ ] Unit-тесты для MarkdownConverter проходят (roundtrip, блокировка unsafe links)
- [ ] Unit-тесты для HTMLSanitizer проходят (удаление `<script>`, `onclick`, разрешение safe tags)

### Frontend
- [ ] Подключён Turndown.js через CDN в `editor.html`
- [ ] Создан `frontend/js/InlineEditorManager.js`
- [ ] Добавлена кнопка "Enable Inline Editing" в `editor.html`
- [ ] Добавлены CSS стили для `.inline-editable-hover` и `[contenteditable]`
- [ ] Инициализация `InlineEditorManager` в `editor.js`
- [ ] Keyboard shortcuts работают: Ctrl+Z (undo), Ctrl+Shift+Z (redo)
- [ ] Preview элементы имеют data-атрибуты: `data-inline-editable`, `data-block-id`, `data-field-path`

### Интеграционные тесты (ручные)
- [ ] **Тест 1:** Открыть `editor.html?id=<page-id>`, нажать "Enable Inline Editing" → элементы preview получают hover outline при наведении
- [ ] **Тест 2:** Кликнуть на заголовок → элемент становится `contenteditable`, фон меняется
- [ ] **Тест 3:** Изменить текст, нажать Ctrl+S (или вызвать `saveChanges()` вручную из console) → запрос PATCH отправлен, ответ `success: true`
- [ ] **Тест 4:** Проверить БД: `SELECT data FROM blocks WHERE id='...'` → поле обновлено, текст в Markdown формате
- [ ] **Тест 5:** Изменить текст, нажать Ctrl+Z → изменение откатилось (undo работает)
- [ ] **Тест 6:** Нажать Ctrl+Shift+Z → изменение вернулось (redo работает)

---

## ОЖИДАЕМЫЙ РЕЗУЛЬТАТ

После завершения Этапа 1:
- ✅ Backend готов принимать PATCH запросы с Markdown и безопасно их обрабатывать
- ✅ Frontend может включить inline-режим, пользователь видит hover feedback
- ✅ Клик на элемент делает его редактируемым (contenteditable)
- ✅ Undo/Redo работают через Ctrl+Z/Shift+Z
- ✅ Изменения сохраняются в БД как Markdown (не HTML)
- ✅ Unit-тесты проходят, sanitization защищает от XSS

**Что НЕ реализовано на Этапе 1 (будет в следующих этапах):**
- Floating toolbar с кнопками форматирования (Этап 2)
- Link и Image поповеры (Этап 3)
- Auto-save (Этап 4)
- Полноценная интеграция с publish workflow (Этап 5)

---

## TROUBLESHOOTING

### Проблема: Composer не находит пакеты
**Решение:** Проверьте `composer.json` в корне backend:
```json
{
  "require": {
    "league/commonmark": "^2.4",
    "league/html-to-markdown": "^5.1",
    "ezyang/htmlpurifier": "^4.16"
  }
}
```
Затем запустите `composer update`.

### Проблема: HTMLPurifier кидает ошибку "Class not found"
**Решение:** Убедитесь что autoloader подключён в `index.php`:
```php
require __DIR__ . '/../vendor/autoload.php';
```

### Проблема: Turndown.js не определён в console
**Решение:** Проверьте что CDN script загружен:
```html
<script src="https://cdn.jsdelivr.net/npm/turndown@7.1.2/dist/turndown.min.js"></script>
```
Откройте DevTools → Network → проверьте что скрипт загрузился (200 OK).

### Проблема: PATCH запрос возвращает 404
**Решение:** Проверьте роут в `index.php`:
```php
if ($method === 'PATCH' && preg_match('#^/api/pages/([a-f0-9-]+)/inline$#', $path, $matches)) {
    $pageController->patchInline($matches[1]);
    exit;
}
```
Также проверьте что `$method` корректно определён (например через `$_SERVER['REQUEST_METHOD']`).

### Проблема: Preview элементы не получают data-атрибуты
**Решение:** Обновите функцию рендеринга preview — добавьте `data-inline-editable`, `data-block-id`, `data-field-path` к каждому редактируемому элементу.

---

## СЛЕДУЮЩИЙ ЭТАП

После завершения Этапа 1 переходите к **Этапу 2: Floating Toolbar** — реализация кнопок форматирования (B, I, U, S, Link, Lists) и их интеграция с inline-редактором.

**Длительность Этапа 2:** 2-3 дня  
**Документация:** `docs/INLINE_EDITOR_SPEC.md` → раздел 6 → Этап 2

---

**Автор:** Анна Лютенко + GitHub Copilot  
**Дата:** 15 октября 2025  
**Версия:** 1.0
