# Промпт: Восстановление редактора статей (Article Editor)

**Дата:** 10 октября 2025  
**Приоритет:** 🚨 ВЫСОКИЙ (Приоритет 1)  
**Статус:** Требует немедленного решения

---

## 🎯 Цель задачи

Восстановить функциональность редактора статей в визуальном редакторе Healthcare Brazil CMS. Кнопка "✍️ Написать статью" в toolbar не реагирует на клики, редактор статей не открывается.

---

## 📋 Контекст проблемы

### Что работало раньше (из скриншота):
- URL редактора статей: `http://localhost/visual-editor-standalone/#article-editor`
- Toolbar редактора статей показывал:
  - "🏠 Редактор статей" (заголовок)
  - "← Закрыть" (кнопка закрытия)
  - "✅ Сохранить и закрыть" (кнопка сохранения)
- Quill rich text editor отображался с полным функционалом:
  - Toolbar: форматирование текста (жирный, курсив, заголовки, списки, ссылки, изображения)
  - Lorem Ipsum текст с изображением
  - **Drag-n-drop изображений** с resize handles (изменение размера и позиции)
  - Quill ImageResize module работал корректно

### Что сломалось:
- Кнопка "✍️ Написать статью" в main toolbar не реагирует при клике
- Редактор статей не открывается
- Hash navigation `#article-editor` не срабатывает автоматически

### Возможные причины (гипотезы):
1. JavaScript метод `openArticleEditor()` не определён или содержит ошибку в `frontend/editor.js`
2. Vue app не обрабатывает `@click="openArticleEditor"` event handler
3. Vue router/hash navigation сломан (не отслеживает `window.location.hash`)
4. Vue state management: `showArticleEditor` (или аналогичное) не меняется на `true`
5. CSS для article editor скрыт или удалён (v-if/v-show условие не выполняется)
6. Quill instance не инициализируется при открытии article editor
7. Quill ImageResize module не подключён или сломан после CSS рефакторинга

---

## 🔍 Задачи для исследования и восстановления

### **Шаг 1: Анализ `frontend/editor.js`**

**Действия:**
1. Открыть `frontend/editor.js` (или аналогичный JS файл, содержащий Vue app).
2. Найти метод `openArticleEditor()` в Vue app definition:
   ```javascript
   methods: {
       openArticleEditor() {
           // Ожидаемая логика
       }
   }
   ```
3. Проверить, что метод:
   - Определён в `methods` объекте Vue app
   - Не содержит синтаксических ошибок
   - Устанавливает правильное состояние (например, `this.showArticleEditor = true`)
   - Обновляет hash в URL (`window.location.hash = '#article-editor'`)

**Ожидаемая структура метода:**
```javascript
openArticleEditor() {
    this.showArticleEditor = true;
    window.location.hash = '#article-editor';
    this.$nextTick(() => {
        this.initQuillEditor(); // Инициализация Quill после рендера DOM
    });
}
```

**Проверить:**
- [ ] Метод существует
- [ ] Метод корректно изменяет Vue state
- [ ] Метод не содержит ошибок в console (проверить DevTools)

---

### **Шаг 2: Проверка Vue state management**

**Действия:**
1. Найти Vue app data definition:
   ```javascript
   data() {
       return {
           showArticleEditor: false,
           // ... other state
       }
   }
   ```
2. Убедиться, что `showArticleEditor` (или аналогичное свойство) определено и используется в template.

3. Проверить template (`frontend/editor.html`):
   ```html
   <div v-if="showArticleEditor" class="article-editor">
       <!-- Article editor UI -->
   </div>
   ```

4. Убедиться, что `v-if` или `v-show` корректно реагирует на изменение state.

**Проверить:**
- [ ] `showArticleEditor` определено в Vue data
- [ ] Template содержит условие `v-if="showArticleEditor"`
- [ ] CSS не скрывает article editor через `display: none !important`

---

### **Шаг 3: Проверка event handler в template**

**Действия:**
1. Открыть `frontend/editor.html`.
2. Найти кнопку "✍️ Написать статью":
   ```html
   <button @click="openArticleEditor" class="toolbar-btn save">
       ✍️ Написать статью
   </button>
   ```
3. Убедиться, что:
   - Атрибут `@click="openArticleEditor"` присутствует
   - Название метода написано корректно (без опечаток)
   - Кнопка не имеет `disabled` атрибута
   - Кнопка видна и доступна для клика (не перекрыта другими элементами)

**Проверить:**
- [ ] `@click="openArticleEditor"` есть в template
- [ ] Кнопка не `disabled`
- [ ] Кнопка видна в UI

---

### **Шаг 4: Проверка hash navigation**

**Действия:**
1. Найти код, который обрабатывает изменения `window.location.hash`:
   ```javascript
   mounted() {
       window.addEventListener('hashchange', this.handleHashChange);
       this.handleHashChange(); // Initial check
   },
   methods: {
       handleHashChange() {
           const hash = window.location.hash;
           if (hash === '#article-editor') {
               this.showArticleEditor = true;
               this.$nextTick(() => {
                   this.initQuillEditor();
               });
           }
       }
   }
   ```

2. Убедиться, что:
   - Event listener `hashchange` добавлен в `mounted()` hook
   - Метод `handleHashChange()` вызывается при изменении hash
   - Hash `#article-editor` корректно обрабатывается

**Проверить:**
- [ ] `hashchange` listener добавлен
- [ ] `handleHashChange()` метод определён
- [ ] Hash routing работает для article editor

---

### **Шаг 5: Восстановление Quill editor с ImageResize**

**Действия:**
1. Найти метод инициализации Quill в `frontend/editor.js`:
   ```javascript
   initQuillEditor() {
       if (this.quillEditor) return; // Avoid re-initialization
       
       this.quillEditor = new Quill('#article-editor-content', {
           theme: 'snow',
           modules: {
               toolbar: [
                   [{ 'header': [1, 2, 3, false] }],
                   ['bold', 'italic', 'underline', 'strike'],
                   [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                   ['link', 'image'],
                   ['clean']
               ],
               imageResize: {
                   modules: ['Resize', 'DisplaySize']
               }
           }
       });
   }
   ```

2. Убедиться, что:
   - `quill-image-resize-module@3.0.0` подключён в `editor.html`:
     ```html
     <script src="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
     ```
   - Quill config включает `imageResize` module
   - Quill instance создаётся после рендера DOM (`$nextTick()`)

**Проверить:**
- [ ] `quill-image-resize-module` подключён в HTML
- [ ] Quill config содержит `imageResize` module
- [ ] `initQuillEditor()` вызывается после открытия article editor
- [ ] Resize handles появляются при клике на изображение

---

### **Шаг 6: Проверка CSS для article editor**

**Действия:**
1. Убедиться, что CSS для article editor не был удалён при рефакторинге в `editor-ui.css`.
2. Проверить наличие стилей:
   ```css
   .article-editor {
       position: fixed;
       top: 0;
       left: 0;
       width: 100%;
       height: 100vh;
       background: white;
       z-index: 1000;
       display: flex;
       flex-direction: column;
   }
   
   .article-editor-toolbar {
       /* Toolbar styles */
   }
   
   #article-editor-content {
       flex: 1;
       padding: 2rem;
       overflow-y: auto;
   }
   
   .ql-editor {
       font-size: 16px;
       line-height: 1.6;
   }
   ```

3. Убедиться, что Quill CSS подключён:
   ```html
   <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
   ```

**Проверить:**
- [ ] CSS для `.article-editor` есть в `editor-ui.css`
- [ ] Quill CSS подключён
- [ ] Стили Quill toolbar и content корректны

---

### **Шаг 7: Debugging в браузере**

**Действия:**
1. Открыть `http://localhost/visual-editor-standalone/editor.html` в браузере.
2. Открыть DevTools → Console.
3. Кликнуть на кнопку "✍️ Написать статью".
4. Проверить console на наличие ошибок:
   - `Uncaught TypeError: Cannot read property '...' of undefined`
   - `Uncaught ReferenceError: openArticleEditor is not defined`
   - Другие JS ошибки

5. В Console выполнить:
   ```javascript
   // Проверить доступность Vue app
   window.app || window.__VUE_APP__
   
   // Проверить существование метода
   app.openArticleEditor
   
   // Вызвать метод вручную
   app.openArticleEditor()
   
   // Проверить state
   app.showArticleEditor
   ```

6. Проверить DOM:
   ```javascript
   // Проверить наличие article editor в DOM
   document.querySelector('.article-editor')
   
   // Проверить display style
   getComputedStyle(document.querySelector('.article-editor')).display
   ```

**Проверить:**
- [ ] Нет ошибок в Console при клике
- [ ] `app.openArticleEditor` определён
- [ ] Ручной вызов `app.openArticleEditor()` работает
- [ ] `.article-editor` появляется в DOM

---

### **Шаг 8: Восстановление функциональности**

**Возможные решения (в зависимости от найденных проблем):**

#### Проблема 1: Метод `openArticleEditor()` не определён
**Решение:** Добавить метод в `methods` секцию Vue app:
```javascript
openArticleEditor() {
    this.showArticleEditor = true;
    window.location.hash = '#article-editor';
    this.$nextTick(() => {
        this.initQuillEditor();
    });
}
```

#### Проблема 2: `showArticleEditor` не определён в data
**Решение:** Добавить в `data()`:
```javascript
data() {
    return {
        showArticleEditor: false,
        quillEditor: null,
        // ... other state
    }
}
```

#### Проблема 3: Template не содержит article editor UI
**Решение:** Добавить в `frontend/editor.html`:
```html
<div v-if="showArticleEditor" class="article-editor">
    <div class="article-editor-toolbar">
        <div class="toolbar-title">🏠 Редактор статей</div>
        <div class="toolbar-actions">
            <button @click="closeArticleEditor" class="toolbar-btn">
                ← Закрыть
            </button>
            <button @click="saveAndCloseArticle" class="toolbar-btn save">
                ✅ Сохранить и закрыть
            </button>
        </div>
    </div>
    <div id="article-editor-content"></div>
</div>
```

#### Проблема 4: Quill не инициализируется
**Решение:** Добавить метод инициализации:
```javascript
initQuillEditor() {
    if (this.quillEditor) {
        return; // Already initialized
    }
    
    const container = document.getElementById('article-editor-content');
    if (!container) {
        console.error('Article editor container not found');
        return;
    }
    
    this.quillEditor = new Quill('#article-editor-content', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            imageResize: {
                modules: ['Resize', 'DisplaySize']
            }
        },
        placeholder: 'Начните писать статью...'
    });
}
```

#### Проблема 5: ImageResize module не работает
**Решение:** 
1. Убедиться, что скрипт подключён в `<head>`:
   ```html
   <script src="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
   ```

2. Зарегистрировать module в Quill:
   ```javascript
   // Before creating Quill instance
   Quill.register('modules/imageResize', ImageResize);
   ```

#### Проблема 6: Hash navigation не работает
**Решение:** Добавить hash change handler:
```javascript
mounted() {
    window.addEventListener('hashchange', this.handleHashChange);
    this.handleHashChange(); // Check initial hash
},
beforeUnmount() {
    window.removeEventListener('hashchange', this.handleHashChange);
},
methods: {
    handleHashChange() {
        const hash = window.location.hash;
        if (hash === '#article-editor') {
            this.showArticleEditor = true;
            this.$nextTick(() => {
                this.initQuillEditor();
            });
        } else if (this.showArticleEditor) {
            this.showArticleEditor = false;
        }
    }
}
```

---

## 🧪 План тестирования

### Manual Testing:
1. Открыть редактор: `http://localhost/visual-editor-standalone/editor.html`
2. Кликнуть "✍️ Написать статью" → article editor должен открыться
3. Проверить Quill toolbar → все кнопки видны и работают
4. Вставить изображение через Quill toolbar (кнопка 🖼️)
5. Кликнуть на изображение → должны появиться resize handles
6. Drag изображение → должно перемещаться
7. Resize через handles → изображение должно менять размер
8. Кликнуть "← Закрыть" → article editor должен закрыться
9. Вручную открыть через URL: `http://localhost/visual-editor-standalone/editor.html#article-editor` → должен открыться автоматически

### Playwright E2E Test (создать после восстановления):
```javascript
// frontend/e2e/tests/article-editor.spec.js
test('Article editor opens and allows editing', async ({ page }) => {
    await page.goto('http://localhost/visual-editor-standalone/editor.html');
    
    // Click "Написать статью" button
    await page.click('button:has-text("✍️ Написать статью")');
    
    // Wait for article editor to appear
    await page.waitForSelector('.article-editor', { state: 'visible' });
    
    // Check that Quill editor is present
    await page.waitForSelector('.ql-editor', { state: 'visible' });
    
    // Type some text
    await page.fill('.ql-editor', 'Test article content');
    
    // Check text is present
    const content = await page.textContent('.ql-editor');
    expect(content).toContain('Test article content');
    
    // Close article editor
    await page.click('button:has-text("← Закрыть")');
    
    // Verify article editor closed
    await page.waitForSelector('.article-editor', { state: 'hidden' });
});

test('Article editor ImageResize works', async ({ page }) => {
    await page.goto('http://localhost/visual-editor-standalone/editor.html#article-editor');
    
    await page.waitForSelector('.ql-editor', { state: 'visible' });
    
    // Insert image via toolbar
    await page.click('.ql-image');
    // ... (simulate image insertion)
    
    // Click on image
    await page.click('.ql-editor img');
    
    // Check for resize handles
    const handles = await page.locator('.ql-image-resize-handle');
    await expect(handles).toHaveCount(8); // 4 corners + 4 sides
});
```

---

## ✅ Критерии успешного восстановления

- [ ] Кнопка "✍️ Написать статью" реагирует на клик
- [ ] Article editor открывается в full-screen overlay
- [ ] Quill editor отображается с полным toolbar
- [ ] Можно вводить и форматировать текст
- [ ] Можно вставлять изображения через Quill toolbar
- [ ] Изображения имеют resize handles при клике
- [ ] Drag-n-drop изображений работает (перемещение и изменение размера)
- [ ] Кнопка "← Закрыть" закрывает article editor
- [ ] Кнопка "✅ Сохранить и закрыть" сохраняет контент (если бэкенд готов)
- [ ] Hash navigation `#article-editor` автоматически открывает editor
- [ ] Нет ошибок в Console
- [ ] Playwright E2E тест проходит

---

## 📚 Справочные материалы

### Quill.js Documentation:
- **Quill Docs:** https://quilljs.com/docs/quickstart/
- **Quill Modules:** https://quilljs.com/docs/modules/
- **Quill ImageResize:** https://github.com/kensnyder/quill-image-resize-module

### Vue 3 Documentation:
- **Vue Event Handling:** https://vuejs.org/guide/essentials/event-handling.html
- **Vue Conditional Rendering:** https://vuejs.org/guide/essentials/conditional.html
- **Vue Lifecycle Hooks:** https://vuejs.org/api/composition-api-lifecycle.html

### Файлы в проекте:
- **Визуальный редактор:** `frontend/editor.html`
- **JavaScript:** `frontend/editor.js` (или встроенный в `editor.html`)
- **CSS:** `frontend/editor-ui.css`
- **Quill CSS:** CDN link в `<head>` секции

---

## 🎯 Немедленные действия

1. **Прочитать `frontend/editor.js`** (или встроенный `<script>` в `editor.html`):
   - Найти `openArticleEditor()` метод
   - Проверить Vue data: `showArticleEditor`
   - Проверить `mounted()` hook для hash navigation

2. **Проверить `frontend/editor.html`**:
   - Найти кнопку "✍️ Написать статью" и `@click` handler
   - Найти article editor template (`v-if="showArticleEditor"`)
   - Проверить Quill dependencies в `<head>`

3. **Открыть браузер DevTools**:
   - Кликнуть на кнопку и проверить Console на ошибки
   - Вручную вызвать `app.openArticleEditor()` в Console
   - Проверить DOM на наличие `.article-editor` элемента

4. **Восстановить missing код**:
   - Если метод отсутствует → добавить
   - Если state отсутствует → добавить
   - Если template отсутствует → добавить

5. **Протестировать локально**:
   - Manual test workflow (открыть, редактировать, закрыть)
   - Проверить ImageResize (вставить изображение, resize)
   - Проверить hash navigation

6. **Написать Playwright тест**:
   - Создать `frontend/e2e/tests/article-editor.spec.js`
   - Добавить functional и visual тесты

---

**Статус:** Готов к выполнению  
**Документ создан:** 10 октября 2025  
**Автор:** GitHub Copilot
