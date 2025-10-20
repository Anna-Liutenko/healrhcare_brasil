# Анализ слоя синхронизации Frontend ↔ Backend

**Дата:** 2025-10-18  
**Проблема:** Публичные страницы не рендерятся так же, как в визуальном редакторе

---

## 1. ИССЛЕДОВАНИЕ: Как общаются Backend и Frontend

### 1.1 Frontend → Backend (Сохранение)

**Поток данных:**
```
editor.js (savePage) 
  ↓ [camelCase JSON]
POST /api/pages
  ↓
PageController::create/update
  ↓
CreatePageWithBlocks / UpdatePageWithBlocks (Use Case)
  ↓
PageRepository::save() + BlockRepository::save()
  ↓
MySQL (snake_case columns)
```

**Формат отправки (editor.js:1384-1476):**
```javascript
{
  title: "...",
  slug: "...",
  status: "published",
  blocks: [{type: "...", data: {...}, position: 0}],
  showInMenu: true,        // ✅ camelCase
  menuOrder: 1,            // ✅ camelCase
  menuTitle: "..."         // ✅ camelCase
}
```

### 1.2 Backend → Frontend (Загрузка в редактор)

**Поток данных:**
```
GET /api/pages/:id
  ↓
PageController::get()
  ↓
GetPageWithBlocks Use Case
  ↓
EntityToArrayTransformer::pageToArray() ✅ (ПОСЛЕ ИСПРАВЛЕНИЯ)
  ↓ [camelCase JSON]
editor.js (loadPageFromAPI)
```

**Формат ответа (EntityToArrayTransformer.php:18-58):**
```php
[
  'id' => '...',
  'title' => '...',
  'slug' => '...',
  'showInMenu' => true,    // ✅ camelCase
  'menuOrder' => 1,        // ✅ camelCase
  'menuTitle' => '...',    // ✅ camelCase
  'blocks' => [...]
]
```

### 1.3 Backend → Публичная страница (Рендеринг)

**Поток данных:**
```
GET /testovaya-1
  ↓
PublicPageController::show()
  ↓
GetPageWithBlocks Use Case
  ↓
EntityToArrayTransformer::pageToArray() ✅ (ПОСЛЕ ИСПРАВЛЕНИЯ)
  ↓ [camelCase array]
PublicPageController::renderPage()
  ↓
HTML рендеринг блоков
  ↓
Браузер
```

**Логика рендеринга (PublicPageController.php:141-334):**
- Получает массив блоков из Use Case
- Для каждого блока читает `$block['type']` и `$block['data']`
- Генерирует HTML вручную через `switch` / `if-else`
- НЕ использует frontend рендеринг (editor.js::renderBlock)

### 1.4 Frontend → Экспорт HTML (Скачать HTML)

**Поток данных:**
```
Кнопка "Экспорт HTML"
  ↓
editor.js::exportHTML() (строка 1535)
  ↓
editor.js::renderBlock() (строка 950)
  ↓
Генерация HTML на клиенте
  ↓
Download файла
```

**Формат экспорта:**
- Загружает `styles.css` через fetch
- Встраивает CSS в `<style>` тег
- Для каждого блока вызывает `this.renderBlock(block)`
- Создаёт полный HTML документ с header/footer

---

## 2. ПРОБЛЕМА: Что не хватает для синхронизации

### 2.1 Корневая причина

**ДВА РАЗНЫХ РЕНДЕРЕРА:**

| Компонент | Рендерер | Язык | Логика |
|-----------|----------|------|--------|
| **Визуальный редактор** | `editor.js::renderBlock()` | JavaScript | 950-1300 строки |
| **Публичная страница** | `PublicPageController::renderPage()` | PHP | 141-334 строки |
| **Экспорт HTML** | `editor.js::renderBlock()` | JavaScript | То же, что редактор ✅ |

**ПОСЛЕДСТВИЯ:**
1. ❌ PublicPageController рендерит блоки **по-своему**
2. ❌ Логика рендеринга **дублируется** (JS + PHP)
3. ❌ При изменении блоков нужно править **два места**
4. ❌ Публичная страница и редактор могут показывать **разный результат**

### 2.2 Конкретные проблемы синхронизации

#### Проблема 1: Разные алгоритмы рендеринга блоков

**Пример: article-cards**

**Frontend (editor.js:1032-1063):**
```javascript
renderArticleCards(block) {
  const cards = data.cards || [];
  const columns = data.columns || 3;
  
  const cardsHtml = cards.map((card, idx) => {
    const imageUrl = this.buildMediaUrl(this.normalizeRelativeUrl(rawImage));
    return `
      <div class="article-card">
        <img src="${imageUrl}" alt="${card.title}">
        <div class="article-card-content">
          <h3>${card.title}</h3>
          <p>${card.text}</p>
          <a href="${card.link}">Читать далее &rarr;</a>
        </div>
      </div>`;
  }).join('');
  
  return `<div class="articles-grid" style="grid-template-columns: repeat(${columns}, 1fr);">
    ${cardsHtml}
  </div>`;
}
```

**Backend (PublicPageController.php:245-272):**
```php
if ($type === 'article-cards' || $type === 'cards' || $type === 'articles') {
    $cards = $data['cards'] ?? $data['items'] ?? [];
    $html .= '<div class="block block-cards">';
    if (!empty($data['title'])) {
        $html .= '<h2>' . htmlspecialchars($data['title']) . '</h2>';
    }
    foreach ($cards as $card) {
        $cardTitle = $card['title'] ?? $card['heading'] ?? '';
        $cardText = $card['text'] ?? $card['excerpt'] ?? '';
        $cardImage = '';
        if (!empty($card['image'])) {
            if (is_array($card['image']) && isset($card['image']['url'])) {
                $cardImage = $card['image']['url'];
            } elseif (is_string($card['image'])) {
                $cardImage = $card['image'];
            }
        }
        $html .= '<article class="card">';
        if (!empty($cardImage)) {
            $html .= '<div class="card-image"><img src="' . htmlspecialchars($cardImage) . '" alt="' . htmlspecialchars($cardTitle) . '" style="max-width:200px;display:block;"/></div>';
        }
        // ... остальная логика
    }
}
```

**РАЗЛИЧИЯ:**
- ✅ Frontend: использует `articles-grid` класс + grid columns
- ❌ Backend: использует `block-cards` класс + разные стили
- ✅ Frontend: вызывает `buildMediaUrl()` для изображений
- ❌ Backend: читает image напрямую без нормализации
- ✅ Frontend: поддерживает `data.columns`
- ❌ Backend: НЕ использует columns

#### Проблема 2: Нет единой точки истины для рендеринга

**Текущее состояние:**
```
blocks.js (определения блоков)
  ↓
editor.js::renderBlock() (JS рендеринг)
  ↙        ↘
Export HTML  ❌ PublicPageController (PHP рендеринг - ОТДЕЛЬНАЯ ЛОГИКА)
```

#### Проблема 3: Использование rendered_html

**PublicPageController.php:82-94:**
```php
if (isset($page['status']) && $page['status'] === 'published' 
    && isset($page['rendered_html']) && !empty($page['rendered_html'])) {
    
    echo $this->fixUploadsUrls($page['rendered_html']);
    exit;
}

// Fallback: runtime render (preview/draft)
$this->renderPage($result);
```

**ПРОБЛЕМА:**
- Поле `rendered_html` НЕ заполняется при сохранении страницы
- Backend пытается отрендерить "на лету" через `renderPage()`
- Результат НЕ совпадает с тем, что показывает редактор

---

## 3. ПРЕДЛОЖЕННОЕ РЕШЕНИЕ

### Вариант A: Unified Renderer (Preferred)

**Концепция:** Сохранять `rendered_html` при публикации страницы, используя **единый источник рендеринга** — frontend.

#### Архитектура:

```
Editor (сохранение)
  ↓
savePage() 
  ↓
Генерация HTML через renderBlock() (JS)
  ↓
POST /api/pages {rendered_html: "...full HTML..."}
  ↓
UpdatePageWithBlocks Use Case
  ↓
Page::setRenderedHtml($html)
  ↓
MySQL (rendered_html column)

---

Публичная страница (рендеринг)
  ↓
GET /testovaya-1
  ↓
PublicPageController::show()
  ↓
Если status=published И rendered_html существует:
    echo rendered_html (ПРЯМАЯ ОТДАЧА) ✅
Иначе:
    renderPage() (fallback для draft)
```

#### Изменения:

1. **Frontend: editor.js::savePage()**
   - После сборки `pageDataForAPI`, вызвать `this.generateRenderedHTML()`
   - Добавить `renderedHtml` в payload

2. **Backend: UpdatePageWithBlocks Use Case**
   - Принимать `renderedHtml` в DTO
   - Вызывать `$page->setRenderedHtml($renderedHtml)`

3. **Backend: PublicPageController::show()**
   - УЖЕ работает! (строки 82-94) ✅

#### Преимущества:
- ✅ Один источник рендеринга (editor.js)
- ✅ Публичная страница = точная копия редактора
- ✅ Быстрая загрузка (готовый HTML, не требует рендеринга)
- ✅ Нет дублирования логики

#### Недостатки:
- ⚠️ Большой размер поля `rendered_html` в БД
- ⚠️ Требует пересохранения всех страниц при изменении CSS
- ⚠️ Draft страницы всё равно используют PHP рендеринг

---

### Вариант B: Backend Rendering via Template Engine

**Концепция:** Создать PHP рендерер блоков, который **точно повторяет** логику `editor.js::renderBlock()`.

#### Архитектура:

```
PublicPageController::show()
  ↓
GetPageWithBlocks Use Case
  ↓
BlockRenderer::render($blocks)  // NEW PHP CLASS
  ↓
foreach block:
    BlockRenderer::renderArticleCards()
    BlockRenderer::renderMainScreen()
    ... (методы как в editor.js)
  ↓
HTML output
```

#### Изменения:

1. Создать `src/Presentation/Renderer/BlockRenderer.php`
2. Портировать логику из `editor.js::renderBlock()` → PHP
3. Заменить `PublicPageController::renderPage()` вызовом `BlockRenderer`

#### Преимущества:
- ✅ Не требует сохранения rendered_html
- ✅ Draft и Published рендерятся одинаково
- ✅ Можно менять CSS без пересохранения страниц

#### Недостатки:
- ❌ Дублирование логики (JS + PHP)
- ❌ Нужно синхронизировать два рендерера
- ❌ Медленнее (рендеринг каждый раз)

---

### Вариант C: Headless Rendering (Advanced)

**Концепция:** Backend рендерит HTML через **headless browser** (Puppeteer/Playwright).

#### Не рекомендуется:
- ❌ Слишком сложно
- ❌ Требует Node.js на сервере
- ❌ Медленно (запуск браузера)

---

## 4. ОЦЕНКА СТАБИЛЬНОСТИ: Вариант A (Unified Renderer)

### Проверка архитектуры

#### ✅ Соответствие Clean Architecture

```
Domain Layer (Entity)
  └── Page::setRenderedHtml(string $html)  ✅ Бизнес-логика

Application Layer (Use Case)  
  └── UpdatePageWithBlocks::execute()
      └── $page->setRenderedHtml($data['renderedHtml'])  ✅ Сценарий

Presentation Layer (Controller)
  └── PageController::update()  ✅ HTTP обработка
  
Infrastructure Layer (Repository)
  └── MySQLPageRepository::update()
      └── UPDATE pages SET rendered_html = ?  ✅ Хранение
```

#### ✅ Не нарушает SOLID

- **SRP:** Page entity отвечает за хранение HTML
- **OCP:** Можно добавить другие форматы (JSON, Markdown) без изменения кода
- **LSP:** Page остаётся заменяемым объектом
- **ISP:** Нет лишних зависимостей
- **DIP:** Use Case зависит от интерфейса Repository

#### ✅ Расширяемость

**Сценарии:**
1. **Новый тип блока?** → Добавить в `editor.js::renderBlock()` → автоматически работает
2. **Изменить стили?** → Пересохранить все Published страницы (batch script)
3. **Мультиязычность?** → Хранить `rendered_html_en`, `rendered_html_pt`

### Риски и митигация

| Риск | Вероятность | Митигация |
|------|-------------|-----------|
| Большой размер БД | Средняя | COMPRESS column, CDN для изображений |
| Устаревший HTML при изменении CSS | Высокая | Batch script для re-render + версионирование |
| Кеш-инвалидация | Низкая | При сохранении очищать CDN cache |

### Тестирование

**Unit тесты:**
```php
test_page_saves_rendered_html() {
    $page = new Page(...);
    $page->setRenderedHtml('<html>...</html>');
    
    assertEquals('<html>...</html>', $page->getRenderedHtml());
}
```

**Integration тесты:**
```php
test_published_page_returns_rendered_html() {
    // Создать страницу с rendered_html
    $response = $this->get('/testovaya-1');
    
    $response->assertStatus(200);
    $response->assertSee('<div class="article-card">');
}
```

**E2E тесты:**
```javascript
test('public page matches editor preview', async () => {
    // Сохранить страницу в редакторе
    await editor.savePage();
    
    // Открыть публичную страницу
    await page.goto('/testovaya-1');
    
    // Сравнить HTML блоков
    const editorHTML = await editor.getPreviewHTML();
    const publicHTML = await page.getHTML('.block');
    
    expect(publicHTML).toContain(editorHTML);
});
```

---

## 5. ФИНАЛЬНАЯ РЕКОМЕНДАЦИЯ

### ✅ Вариант A: Unified Renderer

**Обоснование:**
1. **Единственный источник истины** — editor.js::renderBlock()
2. **Публичная страница = точная копия редактора** (гарантировано)
3. **Архитектура остаётся стабильной** (не нарушает SOLID/Clean)
4. **Уже частично реализовано** (PublicPageController читает rendered_html)

**План внедрения:**

### Phase 1: Frontend (editor.js)
1. Создать метод `generateRenderedHTML()` (использует `renderBlock()`)
2. В `savePage()` добавить `renderedHtml` в payload
3. Отправить `renderedHtml` только если `status === 'published'`

### Phase 2: Backend (Use Case + Entity)
1. Обновить `UpdatePageWithBlocksRequest` DTO: добавить `?string $renderedHtml`
2. Обновить `UpdatePageWithBlocks::execute()`: вызвать `$page->setRenderedHtml()`
3. Убедиться что `Page::getRenderedHtml()` работает (already exists)

### Phase 3: Миграция существующих страниц
1. Написать скрипт для re-render всех Published страниц:
   ```php
   // scripts/regenerate_rendered_html.php
   foreach ($publishedPages as $page) {
       // Потребуется JS рендеринг или временный PHP рендерер
   }
   ```

### Phase 4: Тестирование
1. Unit тесты для `Page::setRenderedHtml()`
2. Integration тесты для API endpoint
3. E2E тесты для публичных страниц

---

## 6. АЛЬТЕРНАТИВА (если Вариант A не подходит)

### Вариант A': Hybrid (rendered_html + runtime fallback)

**Использовать Вариант A**, но добавить:
- Кнопку "Regenerate HTML" в админке
- Автоматический fallback на PHP рендеринг если `rendered_html` пустой
- Версионирование HTML (hash CSS + blocks для инвалидации кеша)

**Преимущества:**
- Гибкость (можно пересоздать HTML)
- Graceful degradation (работает без rendered_html)

---

## ВЫВОДЫ

1. **Корневая проблема:** Два разных рендерера (JS + PHP)
2. **Решение:** Unified Renderer (сохранять HTML из editor.js)
3. **Стабильность:** Архитектура остаётся чистой, не нарушает SOLID
4. **Рекомендация:** Внедрить Вариант A (4 фазы)

**Следующие шаги:** Получить одобрение и начать Phase 1 (frontend changes).
