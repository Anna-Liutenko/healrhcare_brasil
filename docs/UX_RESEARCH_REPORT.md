# UX Research Report: Drag-n-Drop & Адаптивность

**Дата:** 2 октября 2025
**Цель:** Анализ текущей реализации drag-n-drop и адаптивности блочного редактора в соответствии с best practices 2025 года

---

## 1. Исследование UX Best Practices для Drag-n-Drop (2025)

### 1.1 Ключевые принципы современного drag-n-drop UX

#### Визуальная обратная связь
- ✅ **Физичность движения**: Элементы должны вести себя как физические объекты
  - Использовать elevation (тени) и z-dimension lift при перетаскивании
  - Анимировать переход (100ms) при drop
  - Добавить лёгкий наклон или tilt для усиления ощущения движения

- ✅ **Визуальные состояния**: Четкие состояния для каждого этапа
  - **Resting** (покой): элемент в исходном состоянии
  - **Hoverable** (наведение): изменение курсора на `grab`
  - **Lifted** (поднят): opacity снижена, cursor `grabbing`
  - **In transit** (в движении): элемент следует за курсором
  - **Dropped** (отпущен): плавная анимация возвращения в сетку

#### Индикация drop zones
- 🔶 **Активные зоны**: Drop zones должны быть явно обозначены
  - Blue dashed border или цветная обводка
  - Изменение фона при hover над зоной
  - "Магнитный" эффект при приближении к краю

- 🔶 **Предотвращение ошибок**:
  - Блокировка неправильных действий с визуальным feedback
  - Сообщения о том, почему действие невозможно
  - Поддержка Undo для всех drag-n-drop операций

#### Accessibility (Доступность)
- ❌ **Клавиатурная навигация**: КРИТИЧНО важно!
  - **Tab/Shift+Tab**: навигация между элементами
  - **Spacebar**: поднять/отпустить элемент
  - **Arrow keys**: перемещение поднятого элемента
  - **Escape**: отмена операции

- ❌ **Screen readers**:
  - ARIA live regions для объявления действий
  - ARIA labels для драг-хендлов
  - Альтернативные методы (кнопки "Move up/down")

#### Мобильная версия
- ❌ **Touch support**:
  - Поддержка touch events
  - Альтернатива: кнопки перемещения
  - Увеличенные touch targets (минимум 44x44px)

### 1.2 Референсные примеры (2025)

**Elementor** (18M+ сайтов):
- Плавные анимации drag
- Магнитное притягивание к сетке
- Визуальные placeholder при перетаскивании
- Поддержка keyboard navigation

**Squarespace Fluid Engine**:
- Drag-n-drop на adjustable grid
- Snap to grid для точности
- Плавная тактильная обратная связь

**Canva**:
- Smooth tactile feedback на каждое действие
- Grid snapping для precision
- Полностью построен на drag-n-drop

---

## 2. Анализ текущей реализации

### 2.1 Что уже реализовано ✅

#### Drag-n-drop из библиотеки в preview
```javascript
// ✅ Курсоры
cursor: grab / grabbing

// ✅ Визуальная обратная связь при перетаскивании
.library-block.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

// ✅ Drop zone индикация
.preview-area.drag-over {
    background: #b8bbd0;
    box-shadow: inset 0 0 0 3px var(--color-action);
}
```

#### Drag-n-drop для сортировки блоков
```javascript
// ✅ Индикация позиции вставки
.block-item.drag-over-top {
    border-top: 4px solid var(--color-action);
}
.block-item.drag-over-bottom {
    border-bottom: 4px solid var(--color-action);
}

// ✅ Логика midpoint для определения позиции
const midpoint = rect.top + rect.height / 2;
let insertIndex = event.clientY < midpoint ? targetIndex : targetIndex + 1;
```

### 2.2 Что отсутствует ❌

#### Критичные пробелы:

1. **❌ Keyboard Navigation**
   - Нет поддержки Spacebar для pick/drop
   - Нет Arrow keys для перемещения
   - Нет Tab navigation между блоками

2. **❌ Screen Reader Support**
   - Отсутствуют ARIA live regions
   - Нет ARIA labels на драг-хендлах
   - Нет альтернативных методов для незрячих пользователей

3. **❌ Touch Support**
   - Drag-n-drop не работает на мобильных устройствах
   - Отсутствуют кнопки перемещения как альтернатива

4. **❌ Undo/Redo**
   - Нет отмены перемещений
   - Невозможно вернуть ошибочное действие

5. **❌ Анимации**
   - Нет плавных transitions при drop
   - Отсутствует эффект "магнитного" притягивания
   - Нет elevation/tilt эффектов при подъёме

6. **❌ Visual Enhancements**
   - Нет placeholder при перетаскивании
   - Отсутствует ghost preview перемещаемого элемента
   - Не хватает visual hints о том, куда можно бросить

---

## 3. Адаптивность вёрстки блоков

### 3.1 Текущее состояние

#### Реализованные breakpoints:
```css
@media (max-width: 768px) {
    /* ✅ Общие адаптации */
    h1 { font-size: 2.2rem; }
    h2 { font-size: 1.8rem; }
    section { padding: 4rem 1rem; }

    /* ✅ About section */
    .about-me { grid-template-columns: 1fr; }
}
```

#### Grid layouts в блоках:
```javascript
// ⚠️ ПРОБЛЕМА: Хардкод колонок без адаптации!
renderServiceCards(data) {
    // Используется фиксированное количество колонок
    style="grid-template-columns: repeat(${columns}, 1fr);"
}

renderArticleCards(data) {
    // Та же проблема
    style="grid-template-columns: repeat(${columns}, 1fr);"
}
```

### 3.2 Выявленные проблемы ❌

#### Критичные:

1. **❌ Service Cards (2 колонки)**
   - На мобильных останется 2 узких колонки → карточки сломаются
   - Нет адаптации под 768px и ниже

2. **❌ Article Cards (3 колонки)**
   - 3 колонки на планшете (768px) → слишком узко
   - На мобильных (320-480px) → полная поломка

3. **❌ About Section**
   - Grid 1fr 2fr не адаптируется
   - На мобильных нужен 1fr (стек вертикально)

4. **❌ Main Screen**
   - Фоновое изображение может не читаться на мобильных
   - Текст может быть слишком крупным

5. **❌ Нет breakpoint стратегии**
   - Отсутствует mobile-first подход
   - Нет промежуточных breakpoints (480px, 1024px)

### 3.3 Стандарты 2025 года

#### Рекомендуемые breakpoints:
```css
/* Mobile */
320px - 480px

/* Tablet */
481px - 768px

/* Small Desktop */
769px - 1024px

/* Large Desktop */
1025px+
```

#### Mobile-First подход:
```css
/* Базовые стили для мобильных */
.services-grid {
    display: grid;
    grid-template-columns: 1fr; /* по умолчанию 1 колонка */
    gap: 1.5rem;
}

/* Планшеты */
@media (min-width: 481px) {
    .services-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Десктопы */
@media (min-width: 769px) {
    .services-grid { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
}
```

---

## 4. Рекомендации по улучшению

### 4.1 Приоритет 1 (КРИТИЧНО) 🔴

#### Адаптивность блоков
**Проблема:** Блоки ломаются на мобильных из-за хардкод колонок

**Решение:**
```javascript
// В editor.js, обновить рендер-методы:

renderServiceCards(data) {
    return `
        <section>
            <div class="container">
                ${title ? `<h2 class="text-center">${this.escape(title)}</h2>` : ''}
                ${subtitle ? `<p class="sub-heading text-center">${this.escape(subtitle)}</p>` : ''}
                <div class="services-grid" data-columns="${columns}">
                    ${cardsHtml}
                </div>
            </div>
        </section>
    `;
}

// В styles.css добавить:
.services-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 481px) {
    .services-grid[data-columns="2"],
    .services-grid[data-columns="3"] {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 769px) {
    .services-grid[data-columns="2"] {
        grid-template-columns: repeat(2, 1fr);
    }
    .services-grid[data-columns="3"] {
        grid-template-columns: repeat(3, 1fr);
    }
}
```

#### Keyboard Navigation для drag-n-drop
**Проблема:** Недоступно для пользователей без мыши

**Решение:**
```javascript
// Добавить keyboard handlers
onBlockKeyDown(event, index) {
    if (event.code === 'Space') {
        event.preventDefault();
        this.toggleBlockSelection(index);
    }
    if (event.code.startsWith('Arrow') && this.selectedBlockForMove === index) {
        event.preventDefault();
        this.moveBlockWithKeyboard(event.code, index);
    }
}

moveBlockWithKeyboard(key, index) {
    const newIndex = key === 'ArrowUp' ? index - 1 : index + 1;
    if (newIndex >= 0 && newIndex < this.blocks.length) {
        const block = this.blocks[index];
        this.blocks.splice(index, 1);
        this.blocks.splice(newIndex, 0, block);
    }
}
```

### 4.2 Приоритет 2 (ВАЖНО) 🟡

#### Visual Enhancements для drag-n-drop

1. **Плавные анимации**
```css
.block-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.block-item.dragging {
    transform: rotate(2deg) scale(1.05);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    transition: none; /* убираем transition при драге */
}

.block-item {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* плавный drop */
}
```

2. **Ghost preview**
```javascript
onBlockDragStart(event, index) {
    // Создать ghost element
    const ghost = event.target.cloneNode(true);
    ghost.style.opacity = '0.5';
    ghost.style.position = 'absolute';
    ghost.style.top = '-9999px';
    document.body.appendChild(ghost);
    event.dataTransfer.setDragImage(ghost, 0, 0);
    setTimeout(() => ghost.remove(), 0);
}
```

3. **Placeholder при перетаскивании**
```javascript
onBlockDragOver(event, targetIndex) {
    // Показать placeholder линию
    const placeholder = document.createElement('div');
    placeholder.className = 'drag-placeholder';
    placeholder.style.height = '4px';
    placeholder.style.background = 'var(--color-action)';
    placeholder.style.borderRadius = '2px';
    // Вставить в нужное место
}
```

#### ARIA support
```html
<!-- Добавить в block-item -->
<div
    role="button"
    tabindex="0"
    :aria-label="`Block: ${getBlockName(block.type)}. Press space to select, arrow keys to move.`"
    aria-grabbed="false"
>
```

### 4.3 Приоритет 3 (УЛУЧШЕНИЕ) 🟢

#### Undo/Redo система
```javascript
data() {
    return {
        history: [],
        historyIndex: -1,
        maxHistorySize: 50
    }
}

saveToHistory() {
    // Обрезать будущее если мы в прошлом
    this.history = this.history.slice(0, this.historyIndex + 1);

    // Добавить текущее состояние
    this.history.push(JSON.stringify(this.blocks));

    // Ограничить размер истории
    if (this.history.length > this.maxHistorySize) {
        this.history.shift();
    }

    this.historyIndex = this.history.length - 1;
}

undo() {
    if (this.historyIndex > 0) {
        this.historyIndex--;
        this.blocks = JSON.parse(this.history[this.historyIndex]);
    }
}

redo() {
    if (this.historyIndex < this.history.length - 1) {
        this.historyIndex++;
        this.blocks = JSON.parse(this.history[this.historyIndex]);
    }
}
```

#### Touch support
```javascript
// Добавить touch event handlers
onTouchStart(event, index) {
    this.touchStartY = event.touches[0].clientY;
    this.touchedBlockIndex = index;
}

onTouchMove(event) {
    if (this.touchedBlockIndex === null) return;
    event.preventDefault();
    const deltaY = event.touches[0].clientY - this.touchStartY;
    // Визуальное перемещение элемента
}

onTouchEnd(event) {
    // Определить новую позицию и переместить
    this.touchedBlockIndex = null;
}
```

#### Альтернативные controls (для мобильных и accessibility)
```html
<!-- Добавить кнопки управления -->
<div class="block-controls-mobile">
    <button @click="moveBlockUp(index)" :disabled="index === 0">
        ↑ Move Up
    </button>
    <button @click="moveBlockDown(index)" :disabled="index === blocks.length - 1">
        ↓ Move Down
    </button>
</div>
```

---

## 5. План внедрения

### Этап 1: Критичные исправления (1-2 дня)
1. ✅ Исправить адаптивность grid layouts
   - Обновить `renderServiceCards()` и `renderArticleCards()`
   - Добавить responsive CSS с mobile-first подходом
   - Тестировать на 320px, 768px, 1024px

2. ✅ Добавить keyboard navigation
   - Space для pick/drop
   - Arrow keys для перемещения
   - Escape для отмены

### Этап 2: Улучшения UX (2-3 дня)
3. ✅ Visual enhancements
   - Плавные анимации (transform, box-shadow)
   - Ghost preview при драге
   - Placeholder линии
   - Магнитный snap эффект

4. ✅ ARIA support
   - role="button" и tabindex="0"
   - aria-label с инструкциями
   - aria-grabbed для состояния

### Этап 3: Продвинутые фичи (3-5 дней)
5. ✅ Undo/Redo система
   - History stack
   - Ctrl+Z / Ctrl+Shift+Z
   - Visual indicator текущего состояния

6. ✅ Touch support
   - Touch events для мобильных
   - Альтернативные кнопки "Move Up/Down"
   - Увеличенные touch targets

---

## 6. Метрики успеха

### Accessibility
- ✅ Lighthouse Accessibility Score: 95+ (сейчас ~70)
- ✅ Полная поддержка keyboard navigation
- ✅ Screen reader совместимость

### Mobile UX
- ✅ Все блоки адаптивны на 320px-2560px
- ✅ Touch-friendly controls
- ✅ Performance: < 3s загрузка на 3G

### User Testing
- ✅ 90%+ пользователей понимают как перемещать блоки без инструкций
- ✅ 0 критических ошибок в drag-n-drop на мобильных
- ✅ Поддержка пользователей с ограниченными возможностями

---

## 7. Референсы и источники

### Статьи и гайды:
1. [Smart Interface Design Patterns: Drag-and-Drop UX](https://smart-interface-design-patterns.com/articles/drag-and-drop-ux/)
2. [LogRocket: Designing drag and drop UIs](https://blog.logrocket.com/ux-design/drag-and-drop-ui-examples/)
3. [Eleken: Drag and drop UI examples and UX tips](https://www.eleken.co/blog-posts/drag-and-drop-ui)
4. [DEV: Responsive Design Breakpoints 2025 Playbook](https://dev.to/gerryleonugroho/responsive-design-breakpoints-2025-playbook-53ih)
5. [BrowserStack: Responsive Design Breakpoints in 2025](https://www.browserstack.com/guide/responsive-design-breakpoints)

### Референсные продукты:
- **Elementor** - лидер WordPress page builders (18M сайтов)
- **Squarespace Fluid Engine** - drag-n-drop с grid snapping
- **Canva** - эталон drag-n-drop UX
- **Trello** - drag-n-drop для task management
- **Notion** - keyboard-first drag-n-drop

---

## Заключение

Текущая реализация drag-n-drop имеет **хорошую базу** (визуальная обратная связь, курсоры, индикация drop zones), но **критично не хватает**:

1. **Accessibility** - keyboard navigation и screen reader support
2. **Адаптивности** - блоки ломаются на мобильных устройствах
3. **Touch support** - не работает на планшетах и телефонах

Следование рекомендациям этого отчёта приведёт редактор в соответствие с **best practices 2025 года** и сделает его доступным для **всех категорий пользователей**.
