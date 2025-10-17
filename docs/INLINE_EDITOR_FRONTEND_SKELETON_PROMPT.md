# Промт: Реализация Frontend Skeleton для Inline Editor

**Дата:** 15 октября 2025  
**Этап:** Frontend Skeleton (Этап 1 из плана)  
**Цель:** Создать минимальный рабочий прототип inline-редактора: кнопка toggle, hover на элементы, contenteditable при клике, skeleton класса InlineEditorManager с методами enable/disable/startEdit/saveChanges/undo/redo

---

## Контекст

### Что уже сделано (Backend)
- ✅ Backend endpoint `PATCH /api/pages/{id}/inline` работает
- ✅ `Infrastructure\MarkdownConverter` и `Infrastructure\HTMLSanitizer` реализованы
- ✅ `Application\UseCase\UpdatePageInline` создан и протестирован
- ✅ Composer пакеты установлены: `league/commonmark`, `league/html-to-markdown`, `ezyang/htmlpurifier`
- ✅ Integration smoke test прошёл успешно (PATCH обновил блок в БД)

### Что нужно сделать (Frontend)
Реализовать frontend skeleton для inline-редактора:

1. **Подключить Turndown.js** (CDN) — для конвертации HTML → Markdown перед отправкой на сервер
2. **Создать `frontend/js/InlineEditorManager.js`** — класс для управления inline-редактированием
3. **Добавить кнопку toggle** в `frontend/editor.html` — "Enable Inline Editing"
4. **Добавить CSS** — стили для hover (outline) и contenteditable (подсветка)
5. **Подключить и инициализировать** InlineEditorManager в `frontend/editor.js`
6. **Аннотировать preview-элементы** data-атрибутами (`data-inline-editable`, `data-block-id`, `data-field-path`)

### Окружение разработки
- **Проект:** Healthcare CMS Frontend (vanilla JS + небольшое количество Vue.js)
- **Путь к frontend:** `C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend\`
- **Backend API:** `http://localhost/healthcare-cms-backend/api/`
- **Основной файл редактора:** `frontend/editor.html`
- **Основной скрипт редактора:** `frontend/editor.js`
- **Существующие CSS:** `frontend/styles.css` или `frontend/editor-ui.css`

### Архитектурные принципы (из спецификации)
- **Markdown-first:** Frontend конвертирует HTML → Markdown перед отправкой на сервер (используем Turndown.js)
- **Progressive enhancement:** Inline-режим — опциональная фича, основной редактор (модальные формы) продолжает работать
- **No breaking changes:** Не трогаем существующий код редактора, только добавляем новый функционал

---

## Задача

Реализовать **Frontend Skeleton** для inline-редактора. Skeleton должен:

1. Позволить пользователю **включить inline-режим** (toggle button)
2. Показывать **hover outline** при наведении на редактируемые элементы
3. Делать элемент **contenteditable** при клике
4. Сохранять изменения через **PATCH /api/pages/{id}/inline** (с конвертацией HTML → Markdown)
5. Поддерживать **undo/redo** (базовый stack)

**Важно:** На этом этапе НЕ реализуем:
- Floating toolbar (будет в следующем этапе)
- Link/image поповеры (будет позднее)
- Auto-save debouncing (добавим после базовой интеграции)

Фокус: **минимальный рабочий прототип**, который можно быстро протестировать вручную.

---

## Пошаговый план выполнения

### Этап 1: Подключить Turndown.js (CDN)

**Цель:** Добавить библиотеку для конвертации HTML → Markdown на frontend.

**Шаг 1.1:** Открыть `frontend/editor.html`.

**Шаг 1.2:** Найти секцию `<head>` и добавить CDN-ссылку на Turndown.js **перед закрывающим тегом `</head>`**:

```html
<!-- Turndown.js для конвертации HTML → Markdown -->
<script src="https://cdn.jsdelivr.net/npm/turndown@7.1.2/dist/turndown.min.js"></script>
```

**Самопроверка 1.2:**
- Сохранить файл `frontend/editor.html`
- Открыть `frontend/editor.html` в браузере
- Открыть DevTools → Console
- Выполнить: `typeof TurndownService`
- **Ожидаемый результат:** `"function"`
- **Если `undefined`:** Проверить URL CDN, проверить что скрипт загрузился (DevTools → Network)

---

### Этап 2: Создать `frontend/js/InlineEditorManager.js`

**Цель:** Создать класс для управления inline-редактированием с методами: `enableInlineMode`, `disableInlineMode`, `startEdit`, `saveChanges`, `undo`, `redo`.

**Шаг 2.1:** Создать файл `frontend/js/InlineEditorManager.js`.

**Шаг 2.2:** Скопировать следующий код в файл:

```javascript
/**
 * InlineEditorManager — управляет inline-редактированием preview
 * 
 * Основные методы:
 * - enableInlineMode() — включить режим (добавить listeners на preview-элементы)
 * - disableInlineMode() — выключить режим
 * - startEdit(element) — сделать элемент contenteditable
 * - saveChanges() — конвертировать HTML → Markdown и отправить PATCH
 * - undo() / redo() — управление undo/redo stack
 */
class InlineEditorManager {
  constructor(previewElement, pageId) {
    this.preview = previewElement; // DOM-элемент preview контейнера
    this.pageId = pageId; // ID текущей страницы (для PATCH запроса)
    this.activeElement = null; // Текущий редактируемый элемент
    this.isInlineMode = false; // Флаг: включён ли inline-режим
    this.undoStack = []; // Stack для undo
    this.redoStack = []; // Stack для redo
    this.autoSaveTimeout = null; // Таймаут для debounced auto-save (на будущее)
  }

  /**
   * Включить inline-режим
   * Добавляет hover + click listeners ко всем элементам с [data-inline-editable]
   */
  enableInlineMode() {
    if (this.isInlineMode) {
      console.warn('Inline mode уже включён');
      return;
    }

    this.isInlineMode = true;
    console.log('Inline mode: enabled');

    // Найти все редактируемые элементы (с data-inline-editable="true")
    const editables = this.preview.querySelectorAll('[data-inline-editable="true"]');
    
    if (editables.length === 0) {
      console.warn('Не найдено элементов с data-inline-editable="true". Убедитесь, что preview рендерится с data-атрибутами.');
    }

    editables.forEach(el => {
      el.addEventListener('mouseenter', this.onMouseEnter.bind(this));
      el.addEventListener('mouseleave', this.onMouseLeave.bind(this));
      el.addEventListener('click', this.onClickElement.bind(this));
    });

    console.log(`Inline mode: добавлены listeners к ${editables.length} элементам`);
  }

  /**
   * Выключить inline-режим
   * Удаляет listeners, очищает contenteditable
   */
  disableInlineMode() {
    if (!this.isInlineMode) {
      console.warn('Inline mode уже выключен');
      return;
    }

    this.isInlineMode = false;
    console.log('Inline mode: disabled');

    const editables = this.preview.querySelectorAll('[data-inline-editable="true"]');
    editables.forEach(el => {
      el.removeEventListener('mouseenter', this.onMouseEnter.bind(this));
      el.removeEventListener('mouseleave', this.onMouseLeave.bind(this));
      el.removeEventListener('click', this.onClickElement.bind(this));
      el.classList.remove('inline-editable-hover');
      el.removeAttribute('contenteditable');
    });

    // Очистить activeElement
    if (this.activeElement) {
      this.activeElement.removeAttribute('contenteditable');
      this.activeElement = null;
    }
  }

  /**
   * Hover handler: добавить класс для outline
   */
  onMouseEnter(event) {
    if (!this.isInlineMode) return;
    event.currentTarget.classList.add('inline-editable-hover');
  }

  /**
   * Mouse leave handler: убрать класс outline
   */
  onMouseLeave(event) {
    if (!this.isInlineMode) return;
    event.currentTarget.classList.remove('inline-editable-hover');
  }

  /**
   * Click handler: начать редактирование
   */
  onClickElement(event) {
    if (!this.isInlineMode) return;
    
    event.preventDefault();
    event.stopPropagation();
    
    this.startEdit(event.currentTarget);
  }

  /**
   * Начать редактирование элемента
   * @param {HTMLElement} element — элемент для редактирования
   */
  startEdit(element) {
    console.log('startEdit:', element);

    // Если редактируется другой элемент — завершить его редактирование
    if (this.activeElement && this.activeElement !== element) {
      this.activeElement.removeAttribute('contenteditable');
      this.activeElement.blur();
    }

    this.activeElement = element;
    element.setAttribute('contenteditable', 'true');
    element.focus();

    // Сохранить snapshot для undo
    this.pushUndoState(element.innerHTML);

    // Навесить listener на input для будущего auto-save
    element.addEventListener('input', this.onInput.bind(this));
    element.addEventListener('blur', this.onBlur.bind(this));

    console.log('Element contenteditable: true, snapshot сохранён для undo');
  }

  /**
   * Input handler (для будущего auto-save)
   */
  onInput(event) {
    // TODO (Этап 4): добавить debounced auto-save
    console.log('Input detected, содержимое изменено');
  }

  /**
   * Blur handler: снять contenteditable (опционально)
   */
  onBlur(event) {
    console.log('Element потерял фокус');
    // Опционально: снять contenteditable при потере фокуса
    // event.currentTarget.removeAttribute('contenteditable');
  }

  /**
   * Сохранить изменения (PATCH запрос)
   * Конвертирует HTML → Markdown и отправляет на сервер
   * @returns {Promise} — результат PATCH запроса
   */
  async saveChanges() {
    if (!this.activeElement) {
      console.warn('Нет активного элемента для сохранения');
      return;
    }

    const blockId = this.activeElement.dataset.blockId;
    const fieldPath = this.activeElement.dataset.fieldPath;

    if (!blockId || !fieldPath) {
      console.error('Элемент не имеет data-block-id или data-field-path. Невозможно сохранить.', this.activeElement);
      return;
    }

    // Конвертировать HTML → Markdown
    const turndownService = new TurndownService();
    const markdown = turndownService.turndown(this.activeElement.innerHTML);

    console.log('Сохранение изменений:', { blockId, fieldPath, markdown });

    const apiUrl = `/healthcare-cms-backend/api/pages/${this.pageId}/inline`;
    const payload = {
      blockId,
      fieldPath,
      newMarkdown: markdown
    };

    try {
      const response = await fetch(apiUrl, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.success) {
        console.log('✅ Сохранено успешно:', result);
        // TODO (Этап 4): показать UI индикатор "Saved"
      } else {
        console.error('❌ Ошибка сохранения:', result.error);
        alert('Ошибка сохранения: ' + result.error);
      }

      return result;
    } catch (error) {
      console.error('❌ Network error:', error);
      alert('Network error: ' + error.message);
      throw error;
    }
  }

  /**
   * Сохранить snapshot для undo
   * @param {string} html — HTML-содержимое элемента
   */
  pushUndoState(html) {
    this.undoStack.push(html);
    this.redoStack = []; // Очистить redo при новом изменении
    console.log('Undo stack size:', this.undoStack.length);
  }

  /**
   * Откатить изменение (undo)
   */
  undo() {
    if (!this.activeElement) {
      console.warn('Нет активного элемента для undo');
      return;
    }

    if (this.undoStack.length === 0) {
      console.warn('Undo stack пуст');
      return;
    }

    const prevState = this.undoStack.pop();
    this.redoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = prevState;

    console.log('✅ Undo применён, redo stack size:', this.redoStack.length);
  }

  /**
   * Повторить изменение (redo)
   */
  redo() {
    if (!this.activeElement) {
      console.warn('Нет активного элемента для redo');
      return;
    }

    if (this.redoStack.length === 0) {
      console.warn('Redo stack пуст');
      return;
    }

    const nextState = this.redoStack.pop();
    this.undoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = nextState;

    console.log('✅ Redo применён, undo stack size:', this.undoStack.length);
  }
}

// Экспортировать класс в глобальную область видимости (для использования в editor.js)
window.InlineEditorManager = InlineEditorManager;
```

**Самопроверка 2.2:**
- Файл `frontend/js/InlineEditorManager.js` создан
- Код скопирован без ошибок
- Открыть файл в редакторе — проверить синтаксис (не должно быть красных подчёркиваний)

---

### Этап 3: Добавить CSS для inline-редактирования

**Цель:** Добавить стили для `.inline-editable-hover` (hover outline) и `[contenteditable]` (подсветка при редактировании).

**Шаг 3.1:** Создать файл `frontend/css/inline-editor.css` (или добавить стили в существующий `frontend/styles.css` — выберите подходящий вариант).

**Шаг 3.2:** Скопировать следующий CSS:

```css
/* ============================================
   Inline Editor Styles
   ============================================ */

/**
 * Hover hint: показывает outline при наведении на редактируемый элемент
 */
.inline-editable-hover {
  outline: 2px dashed #4CAF50;
  outline-offset: 2px;
  cursor: pointer;
  transition: outline 0.2s ease;
  position: relative;
}

/**
 * Иконка редактирования (✏️) в правом верхнем углу при hover
 */
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
  z-index: 10;
}

/**
 * Активный редактируемый элемент (contenteditable="true")
 */
[contenteditable="true"] {
  outline: 2px solid #2196F3;
  outline-offset: 2px;
  background-color: #E3F2FD;
  padding: 4px;
  min-height: 20px;
  transition: background-color 0.2s ease, outline 0.2s ease;
}

/**
 * Focus state для contenteditable элемента
 */
[contenteditable="true"]:focus {
  outline: 2px solid #1976D2;
  background-color: #BBDEFB;
}

/**
 * Placeholder для пустого contenteditable элемента
 */
[contenteditable="true"]:empty::before {
  content: 'Введите текст...';
  color: #999;
  font-style: italic;
}
```

**Шаг 3.3:** Подключить CSS в `frontend/editor.html`:

- Если создали новый файл `frontend/css/inline-editor.css`, добавить в `<head>`:

```html
<link rel="stylesheet" href="css/inline-editor.css">
```

- Если добавили стили в существующий `frontend/styles.css`, то ничего дополнительно подключать не нужно.

**Самопроверка 3.3:**
- Файл CSS создан или стили добавлены
- CSS подключён в `editor.html`
- Открыть `editor.html` в браузере → DevTools → Elements → проверить что стили загрузились (нет 404 в Network)

---

### Этап 4: Добавить кнопку toggle в `frontend/editor.html`

**Цель:** Добавить кнопку "Enable Inline Editing" для включения/выключения inline-режима.

**Шаг 4.1:** Открыть `frontend/editor.html`.

**Шаг 4.2:** Найти секцию с кнопками редактора (например, "Save", "Publish", "Preview") — обычно это `<div class="editor-toolbar">` или похожий контейнер.

**Шаг 4.3:** Добавить кнопку toggle **после существующих кнопок**:

```html
<!-- Кнопка toggle для inline editing -->
<button id="toggleInlineMode" class="btn btn-secondary" title="Включить режим inline-редактирования">
  📝 Enable Inline Editing
</button>
```

**Примечание:** Если в вашем проекте используются другие классы для кнопок (например, `button-primary`), замените `btn btn-secondary` на соответствующие классы.

**Самопроверка 4.3:**
- Кнопка добавлена в HTML
- Открыть `editor.html` в браузере → кнопка видна в интерфейсе
- Кликнуть на кнопку → ничего не произойдёт (обработчик ещё не подключён — это нормально)

---

### Этап 5: Подключить и инициализировать InlineEditorManager в `frontend/editor.js`

**Цель:** Подключить скрипт `InlineEditorManager.js` и инициализировать класс при загрузке страницы.

**Шаг 5.1:** Открыть `frontend/editor.html`.

**Шаг 5.2:** Найти секцию перед закрывающим тегом `</body>`, где подключаются скрипты.

**Шаг 5.3:** Добавить подключение `InlineEditorManager.js` **перед** `editor.js`:

```html
<!-- Inline Editor Manager -->
<script src="js/InlineEditorManager.js"></script>

<!-- Основной скрипт редактора -->
<script src="editor.js"></script>
```

**Порядок важен:** `InlineEditorManager.js` должен загрузиться **до** `editor.js`, чтобы класс был доступен при инициализации.

**Шаг 5.4:** Открыть `frontend/editor.js`.

**Шаг 5.5:** Найти функцию инициализации редактора (обычно вызывается при `DOMContentLoaded` или внутри `window.onload`).

**Шаг 5.6:** Добавить инициализацию InlineEditorManager **в конец существующей функции инициализации**:

```javascript
// ===== INLINE EDITOR INITIALIZATION =====

let inlineEditorManager = null;
let inlineModeEnabled = false;

function initInlineEditor() {
  // Найти preview контейнер (замените селектор на ваш)
  const previewElement = document.querySelector('.preview-container'); // ← ЗАМЕНИТЕ на ваш селектор preview
  
  if (!previewElement) {
    console.warn('Preview container не найден. Inline editor не инициализирован.');
    return;
  }

  // Получить ID текущей страницы из URL (например, ?id=page-uuid)
  const urlParams = new URLSearchParams(window.location.search);
  const pageId = urlParams.get('id');

  if (!pageId) {
    console.warn('pageId не найден в URL. Inline editor не инициализирован.');
    return;
  }

  // Создать экземпляр InlineEditorManager
  inlineEditorManager = new InlineEditorManager(previewElement, pageId);
  console.log('InlineEditorManager инициализирован для pageId:', pageId);

  // Обработчик кнопки toggle
  const toggleBtn = document.getElementById('toggleInlineMode');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      inlineModeEnabled = !inlineModeEnabled;

      if (inlineModeEnabled) {
        inlineEditorManager.enableInlineMode();
        toggleBtn.textContent = '🚫 Disable Inline Editing';
        toggleBtn.classList.remove('btn-secondary');
        toggleBtn.classList.add('btn-danger');
      } else {
        inlineEditorManager.disableInlineMode();
        toggleBtn.textContent = '📝 Enable Inline Editing';
        toggleBtn.classList.remove('btn-danger');
        toggleBtn.classList.add('btn-secondary');
      }
    });

    console.log('Кнопка toggle inline mode подключена');
  } else {
    console.warn('Кнопка #toggleInlineMode не найдена в DOM');
  }

  // Keyboard shortcuts для undo/redo
  document.addEventListener('keydown', (e) => {
    if (!inlineModeEnabled || !inlineEditorManager.activeElement) return;

    // Ctrl+Z — undo
    if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
      e.preventDefault();
      inlineEditorManager.undo();
    }
    // Ctrl+Shift+Z — redo
    else if (e.ctrlKey && e.shiftKey && e.key === 'Z') {
      e.preventDefault();
      inlineEditorManager.redo();
    }
    // Ctrl+S — manual save (на будущее)
    else if (e.ctrlKey && e.key === 's') {
      e.preventDefault();
      console.log('Ctrl+S нажат, вызов saveChanges()');
      inlineEditorManager.saveChanges();
    }
  });

  console.log('Keyboard shortcuts для inline editor подключены (Ctrl+Z, Ctrl+Shift+Z, Ctrl+S)');
}

// Вызвать initInlineEditor при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
  // ... ваш существующий код инициализации редактора ...

  // Инициализировать inline editor
  initInlineEditor();
});
```

**Важно:**
- Замените `.preview-container` на селектор вашего preview-контейнера (например, `#preview`, `.page-preview`, `.editor-preview` и т.д.)
- Убедитесь, что `pageId` извлекается корректно (если у вас другой способ получения ID страницы — адаптируйте код)

**Самопроверка 5.6:**
- Код добавлен в `editor.js`
- Файл сохранён
- Открыть `editor.html?id=a1b2c3d4-e5f6-7890-abcd-ef1234567891` в браузере
- Открыть DevTools → Console
- Должны быть сообщения:
  - `"InlineEditorManager инициализирован для pageId: a1b2c3d4-e5f6-7890-abcd-ef1234567891"`
  - `"Кнопка toggle inline mode подключена"`
  - `"Keyboard shortcuts для inline editor подключены"`

---

### Этап 6: Аннотировать preview-элементы data-атрибутами

**Цель:** Добавить `data-inline-editable`, `data-block-id`, `data-field-path`, `data-block-type` к элементам preview, чтобы InlineEditorManager мог их обнаружить.

**Проблема:** InlineEditorManager ищет элементы с `[data-inline-editable="true"]`, но пока они не размечены.

**Решение:** Обновить код рендеринга preview (client-side или server-side).

**Вариант A: Client-side рендеринг (если preview генерируется в `editor.js`)**

Найдите функцию рендеринга блоков (например, `renderBlock(block)` или `renderPreview(page)`).

**Пример (для блока типа `page-header`):**

```javascript
function renderBlock(block) {
  const blockDiv = document.createElement('div');
  blockDiv.className = `block block-${block.type}`;
  blockDiv.dataset.blockId = block.id;

  if (block.type === 'page-header') {
    // Заголовок (редактируемое поле)
    const titleEl = document.createElement('h1');
    titleEl.textContent = block.data.title;
    
    // Добавить data-атрибуты для inline editing
    titleEl.dataset.inlineEditable = 'true';
    titleEl.dataset.blockId = block.id;
    titleEl.dataset.fieldPath = 'data.title';
    titleEl.dataset.blockType = block.type;
    
    blockDiv.appendChild(titleEl);

    // Подзаголовок (редактируемое поле)
    const subtitleEl = document.createElement('p');
    subtitleEl.textContent = block.data.subtitle;
    
    subtitleEl.dataset.inlineEditable = 'true';
    subtitleEl.dataset.blockId = block.id;
    subtitleEl.dataset.fieldPath = 'data.subtitle';
    subtitleEl.dataset.blockType = block.type;
    
    blockDiv.appendChild(subtitleEl);
  }
  
  // ... остальные типы блоков

  return blockDiv;
}
```

**Шаги:**
1. Найти функцию рендеринга preview в `editor.js`
2. Для каждого редактируемого поля (h1, h2, p, li, figcaption и т.д.) добавить data-атрибуты:
   - `data-inline-editable="true"`
   - `data-block-id="<block-id>"`
   - `data-field-path="data.title"` (или `data.subtitle`, `data.text` и т.д.)
   - `data-block-type="<block-type>"`
3. Сохранить изменения

**Вариант B: Server-side рендеринг (если preview генерируется на сервере)**

Обновите шаблоны PHP (или Twig/Blade templates) для добавления data-атрибутов.

**Пример (PHP template):**

```php
<h1 
  data-inline-editable="true" 
  data-block-id="<?= $block->getId() ?>" 
  data-field-path="data.title" 
  data-block-type="<?= $block->getType() ?>"
>
  <?= htmlspecialchars($block->getData()['title']) ?>
</h1>
```

**Самопроверка 6:**
- Preview-элементы имеют data-атрибуты
- Открыть `editor.html?id=...` в браузере
- DevTools → Elements → выбрать заголовок в preview
- Проверить наличие атрибутов: `data-inline-editable="true"`, `data-block-id="..."`, `data-field-path="data.title"`
- Если атрибутов нет — вернуться к коду рендеринга и добавить их

---

### Этап 7: Тестирование (manual QA)

**Цель:** Проверить работу frontend skeleton вручную.

**Тест 1: Включение inline-режима**

1. Открыть `frontend/editor.html?id=a1b2c3d4-e5f6-7890-abcd-ef1234567891` в браузере
2. Нажать кнопку **"Enable Inline Editing"**
3. **Ожидаемый результат:**
   - Кнопка изменилась на "🚫 Disable Inline Editing"
   - В консоли: `"Inline mode: enabled"` и `"Inline mode: добавлены listeners к N элементам"`

**Самопроверка 1:**
- ✅ Кнопка изменилась
- ✅ Сообщения в консоли появились
- ✅ Если `N = 0` → проверить data-атрибуты (вернуться к Этапу 6)

---

**Тест 2: Hover на элемент**

1. Inline-режим включён
2. Навести курсор на заголовок (h1 или h2) в preview
3. **Ожидаемый результат:**
   - Появился зелёный пунктирный outline вокруг элемента
   - Иконка ✏️ в правом верхнем углу
   - Курсор изменился на `pointer`

**Самопроверка 2:**
- ✅ Outline появился
- ✅ Иконка видна
- ✅ Если outline не появился → проверить CSS (вернуться к Этапу 3)

---

**Тест 3: Клик на элемент (начать редактирование)**

1. Inline-режим включён
2. Кликнуть на заголовок
3. **Ожидаемый результат:**
   - Элемент получил синий outline (вместо зелёного)
   - Фон элемента изменился на светло-голубой
   - Элемент стал редактируемым (можно вводить текст)
   - В консоли: `"startEdit: <element>"` и `"Element contenteditable: true, snapshot сохранён для undo"`

**Самопроверка 3:**
- ✅ Элемент стал contenteditable
- ✅ Можно вводить текст
- ✅ Консоль показывает сообщения
- ✅ Если элемент не стал редактируемым → проверить обработчик `onClickElement` и `startEdit`

---

**Тест 4: Undo/Redo (Ctrl+Z / Ctrl+Shift+Z)**

1. Начать редактирование заголовка (кликнуть)
2. Изменить текст (например, добавить слово "TEST")
3. Нажать **Ctrl+Z**
4. **Ожидаемый результат:**
   - Текст вернулся к исходному состоянию
   - В консоли: `"✅ Undo применён"`
5. Нажать **Ctrl+Shift+Z**
6. **Ожидаемый результат:**
   - Изменение вернулось ("TEST" снова появился)
   - В консоли: `"✅ Redo применён"`

**Самопроверка 4:**
- ✅ Undo работает
- ✅ Redo работает
- ✅ Если undo/redo не работают → проверить keyboard shortcuts в `editor.js`

---

**Тест 5: Сохранение изменений (Ctrl+S или manual call)**

1. Начать редактирование заголовка
2. Изменить текст на "✅ MANUAL TEST"
3. Нажать **Ctrl+S** (или вызвать `inlineEditorManager.saveChanges()` из console)
4. **Ожидаемый результат:**
   - В консоли: `"Сохранение изменений: { blockId: '...', fieldPath: 'data.title', markdown: '✅ MANUAL TEST' }"`
   - Отправлен PATCH запрос (DevTools → Network → PATCH)
   - Ответ: `{ success: true, page: {...}, block: {...} }`
   - В консоли: `"✅ Сохранено успешно"`

**Самопроверка 5:**
- ✅ PATCH запрос отправлен
- ✅ Ответ `success: true`
- ✅ В БД обновился блок (проверить GET запросом: `fetch('/healthcare-cms-backend/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891').then(r => r.json()).then(console.log)`)
- ✅ Если PATCH вернул ошибку → проверить payload, backend логи, убедиться что data-атрибуты корректны

---

**Тест 6: Выключение inline-режима**

1. Inline-режим включён
2. Нажать кнопку **"🚫 Disable Inline Editing"**
3. **Ожидаемый результат:**
   - Кнопка изменилась на "📝 Enable Inline Editing"
   - Outline исчез с элементов
   - Элементы больше не реагируют на hover
   - В консоли: `"Inline mode: disabled"`

**Самопроверка 6:**
- ✅ Inline-режим выключился
- ✅ Можно снова включить — повторить Тест 1

---

### Этап 8: Troubleshooting (если что-то не работает)

**Проблема 1: Кнопка toggle не реагирует на клик**

**Причины:**
- Обработчик не подключён
- ID кнопки не совпадает с селектором (`#toggleInlineMode`)

**Решение:**
1. Проверить ID кнопки в HTML: `<button id="toggleInlineMode">`
2. Проверить что `initInlineEditor()` вызывается (console.log в начале функции)
3. Проверить что `toggleBtn` найдена: `console.log(toggleBtn)` после `document.getElementById('toggleInlineMode')`

---

**Проблема 2: Hover outline не появляется**

**Причины:**
- CSS не подключён или не загрузился
- Нет элементов с `data-inline-editable="true"`
- Класс `.inline-editable-hover` не применяется

**Решение:**
1. DevTools → Network → проверить что `inline-editor.css` загрузился (200 OK)
2. DevTools → Elements → проверить что у элементов есть `data-inline-editable="true"`
3. Включить inline-режим → навести курсор → DevTools → Elements → проверить что класс `.inline-editable-hover` добавляется к элементу

---

**Проблема 3: Элемент не становится contenteditable при клике**

**Причины:**
- Listener `click` не добавлен (inline-режим не включён)
- `startEdit()` не вызывается
- Элемент не имеет `data-inline-editable="true"`

**Решение:**
1. Убедиться что inline-режим включён (кнопка = "🚫 Disable")
2. DevTools → Console → при клике должно быть сообщение `"startEdit: <element>"`
3. Если сообщения нет → проверить `onClickElement` (добавить `console.log` в начало метода)

---

**Проблема 4: PATCH запрос возвращает 404 или 500**

**Причины:**
- Неправильный URL (проверить `apiUrl`)
- Backend не запущен или endpoint не реализован
- Неправильный `pageId` (не найден в БД)

**Решение:**
1. DevTools → Network → найти PATCH запрос → проверить URL
2. Убедиться что backend работает: `fetch('/healthcare-cms-backend/api/health').then(r => r.json()).then(console.log)` → должен вернуть `{ status: 'ok' }`
3. Проверить pageId в URL: `?id=a1b2c3d4-e5f6-7890-abcd-ef1234567891`
4. Проверить backend логи (если 500)

---

**Проблема 5: Undo/Redo не работают**

**Причины:**
- Keyboard shortcuts не подключены
- `activeElement` = null (не начато редактирование)
- Stack пуст (не было изменений)

**Решение:**
1. Проверить что `initInlineEditor()` подключил keyboard listeners (console.log в конце функции)
2. Начать редактирование → изменить текст → только после этого Ctrl+Z сработает
3. Проверить что snapshot сохраняется: `console.log(inlineEditorManager.undoStack)` — должен быть массив с элементами

---

## Acceptance Criteria (критерии приёмки)

### Frontend skeleton считается готовым, если:

- [x] **Подключён Turndown.js:** `typeof TurndownService === "function"` в console
- [x] **Создан InlineEditorManager.js:** файл существует, класс экспортирован в `window.InlineEditorManager`
- [x] **CSS подключён:** стили `.inline-editable-hover` и `[contenteditable]` работают
- [x] **Кнопка toggle добавлена:** кнопка видна в интерфейсе, ID = `toggleInlineMode`
- [x] **InlineEditorManager инициализирован:** в console при загрузке страницы сообщение `"InlineEditorManager инициализирован"`
- [x] **Inline-режим включается:** клик на кнопку → сообщение `"Inline mode: enabled"`, элементы получают listeners
- [x] **Hover outline работает:** наведение курсора → зелёный outline + иконка ✏️
- [x] **Contenteditable работает:** клик на элемент → синий outline, фон изменился, можно вводить текст
- [x] **Undo/Redo работают:** Ctrl+Z откатывает изменение, Ctrl+Shift+Z возвращает
- [x] **PATCH запрос отправляется:** Ctrl+S → PATCH `/api/pages/{id}/inline` → ответ `{ success: true }`
- [x] **Data-атрибуты присутствуют:** элементы preview имеют `data-inline-editable`, `data-block-id`, `data-field-path`

---

## Что дальше (следующие этапы)

После того как frontend skeleton работает, переходим к:

1. **Floating Toolbar** (Этап 2) — кнопки форматирования (B, I, U, S, Link, Lists)
2. **Link & Image Popovers** (Этап 3) — поповеры для вставки ссылок и выбора изображений
3. **Auto-save debouncing** (Этап 4) — автоматическое сохранение через 2 секунды после изменения
4. **Error handling** (Этап 6) — обработка 409 Conflict, beforeunload warning, localStorage backup

---

## Дополнительные заметки

### Как проверить, что data-атрибуты корректны (helper script)

Вставьте в console:

```javascript
// Найти все элементы с data-inline-editable
const editables = document.querySelectorAll('[data-inline-editable="true"]');
console.log('Найдено редактируемых элементов:', editables.length);

// Показать data-атрибуты каждого элемента
editables.forEach((el, i) => {
  console.log(`Элемент ${i + 1}:`, {
    tagName: el.tagName,
    blockId: el.dataset.blockId,
    fieldPath: el.dataset.fieldPath,
    blockType: el.dataset.blockType,
    text: el.textContent.substring(0, 50) + '...'
  });
});
```

**Ожидаемый вывод:**
```
Найдено редактируемых элементов: 3
Элемент 1: { tagName: 'H1', blockId: '42def4c1-2da4-41ca-b9af-230eeb326865', fieldPath: 'data.title', blockType: 'page-header', text: 'Полезные гайды' }
...
```

---

### Как вручную вызвать saveChanges() из console

```javascript
// Начать редактирование заголовка (кликнуть на него в UI)
// Затем в console:

inlineEditorManager.saveChanges()
  .then(result => console.log('PATCH result:', result))
  .catch(error => console.error('PATCH error:', error));
```

---

### Как проверить, что PATCH запрос дошёл до backend и обновил БД

**Шаг 1:** Отправить PATCH (Ctrl+S или manual call)

**Шаг 2:** В console выполнить GET запрос:

```javascript
fetch('/healthcare-cms-backend/api/pages/a1b2c3d4-e5f6-7890-abcd-ef1234567891')
  .then(r => r.json())
  .then(page => {
    const block = page.blocks.find(b => b.id === '42def4c1-2da4-41ca-b9af-230eeb326865');
    console.log('Block data.title:', block.data.title);
  });
```

**Ожидаемый вывод:** `Block data.title: "✅ MANUAL TEST"` (или ваш изменённый текст)

---

## Итог

После выполнения всех шагов у вас будет **рабочий frontend skeleton** для inline-редактора:

✅ Кнопка toggle включает/выключает inline-режим  
✅ Hover показывает outline на редактируемых элементах  
✅ Клик делает элемент contenteditable  
✅ Изменения сохраняются через PATCH запрос (HTML → Markdown)  
✅ Undo/Redo работают через Ctrl+Z / Ctrl+Shift+Z  
✅ Keyboard shortcuts подключены (Ctrl+S для manual save)  

**Следующий шаг:** Добавить FloatingToolbar с кнопками форматирования (B, I, U, S, Link, Lists) — это будет **Этап 2**.

---

**Автор:** Анна Лютенко + GitHub Copilot  
**Дата:** 15 октября 2025  
**Версия:** 1.0
