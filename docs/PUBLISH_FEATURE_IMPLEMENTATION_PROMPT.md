# Подробный промпт для завершения функционала публикации страниц

**Дата создания:** 2025-10-14  
**Статус:** Готово к реализации  
**Приоритет:** Критично для production

---

## Контекст и цель

Необходимо завершить интеграцию визуального редактора с публичным сайтом. Большая часть инфраструктуры уже готова:
- ✅ DB миграция написана (но не применена)
- ✅ Entity `Page` поддерживает `renderedHtml` и `menuTitle`
- ✅ Repository сохраняет/читает новые поля
- ✅ Use-case `PublishPage` генерирует HTML через `RenderPageHtml`
- ✅ Frontend отправляет настройки меню

**Что требуется доделать:**
1. Применить DB миграцию
2. Исправить dependency injection в контроллере публикации
3. Обновить PublicPageController для отдачи сохранённого HTML
4. Привести в соответствие названия полей (menu_label → menu_title)
5. Добавить UI для поля "Название в меню"
6. Написать E2E тесты

---

## Шаг 1: Применить DB миграцию (КРИТИЧНО)

### Описание
Добавить колонки `rendered_html` и `menu_title` в таблицу `pages`.

### Файл
`database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql`

### Команда выполнения (Windows PowerShell)

```powershell
# Установить переменные окружения для MySQL
$env:DB_HOST = "localhost"
$env:DB_NAME = "healthcare_cms"
$env:DB_USER = "root"
$env:DB_PASSWORD = ""  # Или ваш пароль

# Выполнить миграцию
& "C:\xampp\mysql\bin\mysql.exe" -h $env:DB_HOST -u $env:DB_USER -p$env:DB_PASSWORD $env:DB_NAME -e "SOURCE database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql"
```

Альтернативно (если SOURCE не работает):

```powershell
Get-Content "database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql" | & "C:\xampp\mysql\bin\mysql.exe" -h localhost -u root healthcare_cms
```

### Проверка успешного применения

```sql
-- Проверить наличие новых колонок
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'healthcare_cms'
  AND TABLE_NAME = 'pages'
  AND COLUMN_NAME IN ('rendered_html', 'menu_title');
```

Ожидаемый результат:
```
+----------------+-----------+-------------+---------------------------------------------+
| COLUMN_NAME    | DATA_TYPE | IS_NULLABLE | COLUMN_COMMENT                              |
+----------------+-----------+-------------+---------------------------------------------+
| rendered_html  | longtext  | YES         | Pre-rendered static HTML at publish time   |
| menu_title     | varchar   | YES         | Custom menu item label (overrides title)   |
+----------------+-----------+-------------+---------------------------------------------+
```

### Возможные проблемы
- **Колонки уже существуют:** Миграция использует `IF NOT EXISTS`, поэтому безопасна для повторного запуска.
- **Ошибка доступа:** Убедитесь, что пользователь MySQL имеет права ALTER на БД.

---

## Шаг 2: Исправить PageController::publish() — добавить RenderPageHtml (КРИТИЧНО)

### Описание
Use-case `PublishPage` ожидает `RenderPageHtml` в конструкторе, но контроллер не передаёт эту зависимость.

### Файл для редактирования
`backend/src/Presentation/Controller/PageController.php`

### Текущий код (строки ~179-201)

```php
/**
 * PUT /api/pages/:id/publish
 */
public function publish(string $id): void
{
    $startTime = ApiLogger::logRequest();

    try {
        $pageRepository = new MySQLPageRepository();

        $useCase = new PublishPage($pageRepository);  // ❌ ОШИБКА: не хватает RenderPageHtml
        $useCase->execute($id);

        $response = [
            'success' => true,
            'message' => 'Page published successfully'
        ];
        ApiLogger::logResponse(200, $response, $startTime);
        $this->jsonResponse($response, 200);
    } catch (\Exception $e) {
        $error = ['error' => $e->getMessage()];
        ApiLogger::logError('PageController::publish() error', $e, ['pageId' => $id]);
        ApiLogger::logResponse(400, $error, $startTime);
        $this->jsonResponse($error, 400);
    }
}
```

### Исправленный код

```php
/**
 * PUT /api/pages/:id/publish
 */
public function publish(string $id): void
{
    $startTime = ApiLogger::logRequest();

    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new \Infrastructure\Repository\MySQLBlockRepository();
        
        // Создаём зависимость для рендеринга HTML
        $renderPageHtml = new \Application\UseCase\RenderPageHtml(
            $pageRepository,
            $blockRepository
        );

        // Передаём обе зависимости в PublishPage
        $useCase = new PublishPage($pageRepository, $renderPageHtml);
        $useCase->execute($id);

        $response = [
            'success' => true,
            'message' => 'Page published successfully'
        ];
        ApiLogger::logResponse(200, $response, $startTime);
        $this->jsonResponse($response, 200);
    } catch (\Exception $e) {
        $error = ['error' => $e->getMessage()];
        ApiLogger::logError('PageController::publish() error', $e, ['pageId' => $id]);
        ApiLogger::logResponse(400, $error, $startTime);
        $this->jsonResponse($error, 400);
    }
}
```

### Проверка
```bash
# Проверить синтаксис PHP
php -l backend/src/Presentation/Controller/PageController.php
```

Ожидаемый вывод: `No syntax errors detected`

---

## Шаг 3: Обновить PublicPageController::show() — отдавать rendered_html (КРИТИЧНО)

### Описание
Публичный контроллер должен проверять наличие `rendered_html` и отдавать его напрямую вместо повторного рендеринга.

### Файл для редактирования
`backend/src/Presentation/Controller/PublicPageController.php`

### Текущая логика (метод `show`, строки ~58-77)

```php
public function show(string $slug): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);

        $result = $useCase->executeBySlug($slug);
        if (empty($result) || empty($result['page'])) {
            // Try static template
            if ($this->tryRenderStaticTemplate($slug)) {
                return;
            }
            $this->render404();
            return;
        }

        $this->renderPage($result);  // ❌ Всегда рендерит runtime
    } catch (\Exception $e) {
        // If static template exists, try it
        if ($this->tryRenderStaticTemplate($slug)) {
            return;
        }
        $this->render404();
    }
}
```

### Исправленный код

```php
public function show(string $slug): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);

        $result = $useCase->executeBySlug($slug);
        if (empty($result) || empty($result['page'])) {
            // Try static template
            if ($this->tryRenderStaticTemplate($slug)) {
                return;
            }
            $this->render404();
            return;
        }

        // ✅ НОВАЯ ЛОГИКА: проверяем наличие pre-rendered HTML
        $page = $result['page'];
        
        // Если страница опубликована и есть сохранённый HTML — отдаём его
        if ($page['status'] === 'published' && !empty($page['rendered_html'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo $page['rendered_html'];
            return;
        }

        // Fallback: runtime рендеринг (для draft/preview или если rendered_html отсутствует)
        $this->renderPage($result);
    } catch (\Exception $e) {
        // If static template exists, try it
        if ($this->tryRenderStaticTemplate($slug)) {
            return;
        }
        $this->render404();
    }
}
```

### Важные моменты
- **Статус published:** Проверяем, что страница опубликована (не draft/archived).
- **Наличие rendered_html:** Если NULL или пустая строка — fallback на runtime рендер.
- **Content-Type:** Устанавливаем явно, чтобы браузер корректно отобразил HTML.

### Проверка
```bash
php -l backend/src/Presentation/Controller/PublicPageController.php
```

---

## Шаг 4: Переименовать menu_label → menu_title в backend

### Описание
Привести названия полей в соответствие с планом (везде используем `menu_title`).

### Файлы для редактирования

#### 4.1. MenuController.php

**Файл:** `backend/src/Presentation/Controller/MenuController.php`

**Текущий код (строки ~45-52):**

```php
// Формируем menu items
$menuItems = array_map(function($page) {
    return [
        'label' => !empty($page['menu_label']) ? $page['menu_label'] : $page['title'],
        'url' => '/' . $page['slug'],
        'slug' => $page['slug'],
        'position' => (int) $page['menu_order']
    ];
}, $pages);
```

**Исправленный код:**

```php
// Формируем menu items
$menuItems = array_map(function($page) {
    return [
        'label' => !empty($page['menu_title']) ? $page['menu_title'] : $page['title'],
        'url' => '/' . $page['slug'],
        'slug' => $page['slug'],
        'position' => (int) $page['menu_order']
    ];
}, $pages);
```

#### 4.2. PageController.php (если используется menu_label)

Проверьте методы `create()` и `update()` — если там обрабатывается `menu_label`, замените на `menu_title`.

**Поиск:**
```bash
grep -n "menu_label" backend/src/Presentation/Controller/PageController.php
```

Если найдены вхождения — заменить на `menu_title`.

### Проверка
```bash
# Найти все оставшиеся вхождения menu_label
grep -r "menu_label" backend/src/
```

Ожидается: **0 результатов** (или только в комментариях/документации).

---

## Шаг 5: Переименовать menuLabel → menuTitle в frontend

### Описание
Frontend должен отправлять `menu_title` (не `menu_label`) в API.

### Файл для редактирования
`frontend/editor.js`

### Изменения

#### 5.1. Переименовать свойство в data()

**Текущий код (строки ~35-40):**

```javascript
// Menu / Navigation settings for the current page (editor-only model)
pageSettings: {
    showInMenu: false,
    menuPosition: null,
    menuLabel: ''  // ❌ Старое название
},
```

**Исправленный код:**

```javascript
// Menu / Navigation settings for the current page (editor-only model)
pageSettings: {
    showInMenu: false,
    menuPosition: null,
    menuTitle: ''  // ✅ Новое название
},
```

#### 5.2. Обновить загрузку из API (метод loadPage)

**Найти строку (примерно ~1294):**

```javascript
this.pageSettings.menuLabel = pagePayload.menu_label || '';
```

**Заменить на:**

```javascript
this.pageSettings.menuTitle = pagePayload.menu_title || '';
```

#### 5.3. Обновить отправку в API (метод savePage)

**Найти строку (примерно ~1389):**

```javascript
menu_label: this.pageSettings.menuLabel || null
```

**Заменить на:**

```javascript
menu_title: this.pageSettings.menuTitle || null
```

### Глобальная замена (рекомендуется)

Используйте поиск/замену в VS Code:
- **Найти:** `menuLabel`
- **Заменить на:** `menuTitle`
- **Область:** `frontend/editor.js`

Проверьте каждое вхождение вручную перед заменой.

### Проверка

```bash
# Убедиться, что menuLabel больше нет
grep -n "menuLabel" frontend/editor.js
```

Ожидается: **0 результатов**.

---

## Шаг 6: Добавить UI для поля "Название в меню"

### Описание
Добавить видимое input-поле в панель настроек редактора, которое появляется при включённой галочке "Показать в меню".

### Файл для редактирования
`frontend/editor.html` (или шаблон настроек в `editor.js`, если он встроен)

### Текущий UI (примерная разметка)

```html
<div class="settings-section">
    <label>
        <input type="checkbox" v-model="pageSettings.showInMenu" :disabled="pageData.status !== 'published'">
        Показать в меню
    </label>
    <small v-if="pageData.status !== 'published'" style="color: #999;">
        Доступно только для опубликованных страниц
    </small>
</div>
```

### Добавить поле для menuTitle

```html
<div class="settings-section">
    <label>
        <input type="checkbox" v-model="pageSettings.showInMenu" :disabled="pageData.status !== 'published'">
        Показать в меню
    </label>
    <small v-if="pageData.status !== 'published'" style="color: #999;">
        Доступно только для опубликованных страниц
    </small>
    
    <!-- ✅ НОВОЕ ПОЛЕ: Название в меню -->
    <div v-if="pageSettings.showInMenu" style="margin-top: 10px;">
        <label for="menu-title-input">Название в меню (опционально):</label>
        <input 
            type="text" 
            id="menu-title-input"
            v-model="pageSettings.menuTitle" 
            placeholder="Оставьте пустым, чтобы использовать название страницы"
            style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;"
        >
        <small style="color: #666;">
            Если не заполнено, будет использовано название страницы.
        </small>
    </div>
</div>
```

### Альтернатива: если настройки в отдельном компоненте

Если UI настроек генерируется через Vue-шаблон в `editor.js` (секция `template:`), найдите соответствующий блок и добавьте аналогичную разметку.

### Проверка в браузере

1. Откройте редактор.
2. Создайте/откройте опубликованную страницу.
3. Включите галочку "Показать в меню".
4. Убедитесь, что появилось поле "Название в меню".
5. Введите кастомное название и сохраните страницу.
6. Проверьте в DevTools (Network → XHR), что payload содержит `menu_title`.

---

## Шаг 7: Написать E2E тесты для publish flow

### Описание
Автоматизировать проверку:
1. Создание страницы → публикация → проверка `/p/{slug}` возвращает `rendered_html`.
2. Редактирование опубликованной страницы → ре-публикация → проверка обновления HTML.

### Файл для создания
`backend/tests/Integration/PublishPageFlowTest.php`

### Пример теста

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Application\UseCase\CreatePage;
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Application\UseCase\GetPageWithBlocks;

class PublishPageFlowTest extends TestCase
{
    private MySQLPageRepository $pageRepository;
    private MySQLBlockRepository $blockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageRepository = new MySQLPageRepository();
        $this->blockRepository = new MySQLBlockRepository();
    }

    public function testPublishPageGeneratesRenderedHtml(): void
    {
        // Arrange: Создаём страницу
        $createUseCase = new CreatePage($this->pageRepository, $this->blockRepository);
        $pageData = [
            'title' => 'Test Publish Page',
            'slug' => 'test-publish-' . uniqid(),
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user-id',
            'blocks' => [
                [
                    'type' => 'text-block',
                    'position' => 0,
                    'data' => ['text' => 'Hello **World**']
                ]
            ]
        ];
        $pageId = $createUseCase->execute($pageData);

        // Act: Публикуем страницу
        $renderUseCase = new RenderPageHtml($this->pageRepository, $this->blockRepository);
        $publishUseCase = new PublishPage($this->pageRepository, $renderUseCase);
        $publishUseCase->execute($pageId);

        // Assert: Проверяем, что rendered_html сохранён
        $page = $this->pageRepository->findById($pageId);
        $this->assertNotNull($page);
        $this->assertEquals('published', $page->getStatus());
        $this->assertNotNull($page->getRenderedHtml());
        $this->assertStringContainsString('Hello', $page->getRenderedHtml());
        $this->assertStringContainsString('<strong>World</strong>', $page->getRenderedHtml()); // Markdown → HTML
    }

    public function testRepublishUpdatesRenderedHtml(): void
    {
        // Arrange: Создаём и публикуем
        $createUseCase = new CreatePage($this->pageRepository, $this->blockRepository);
        $pageData = [
            'title' => 'Test Re-publish',
            'slug' => 'test-republish-' . uniqid(),
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user-id',
            'blocks' => [['type' => 'text-block', 'position' => 0, 'data' => ['text' => 'Version 1']]]
        ];
        $pageId = $createUseCase->execute($pageData);

        $renderUseCase = new RenderPageHtml($this->pageRepository, $this->blockRepository);
        $publishUseCase = new PublishPage($this->pageRepository, $renderUseCase);
        $publishUseCase->execute($pageId);

        $page = $this->pageRepository->findById($pageId);
        $htmlV1 = $page->getRenderedHtml();
        $this->assertStringContainsString('Version 1', $htmlV1);

        // Act: Изменяем блок и ре-публикуем
        $blocks = $this->blockRepository->findByPageId($pageId);
        $block = $blocks[0];
        $data = $block->getData();
        $data['text'] = 'Version 2 **updated**';
        $block->setData($data);
        $this->blockRepository->save($block);

        $publishUseCase->execute($pageId); // Re-publish

        // Assert: HTML обновился
        $pageAfter = $this->pageRepository->findById($pageId);
        $htmlV2 = $pageAfter->getRenderedHtml();
        $this->assertStringContainsString('Version 2', $htmlV2);
        $this->assertStringContainsString('<strong>updated</strong>', $htmlV2);
        $this->assertStringNotContainsString('Version 1', $htmlV2);
    }

    protected function tearDown(): void
    {
        // Cleanup: удаляем тестовые страницы (optional)
        parent::tearDown();
    }
}
```

### Запуск тестов

```bash
cd backend
vendor/bin/phpunit tests/Integration/PublishPageFlowTest.php
```

---

## Шаг 8: E2E тест для menu_title

### Файл для создания
`backend/tests/Integration/MenuTitleTest.php`

### Пример теста

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Application\UseCase\CreatePage;
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;

class MenuTitleTest extends TestCase
{
    private MySQLPageRepository $pageRepository;
    private MySQLBlockRepository $blockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageRepository = new MySQLPageRepository();
        $this->blockRepository = new MySQLBlockRepository();
    }

    public function testMenuTitleOverridesPageTitle(): void
    {
        // Arrange: Создаём страницу с кастомным menu_title
        $createUseCase = new CreatePage($this->pageRepository, $this->blockRepository);
        $pageData = [
            'title' => 'Very Long Official Page Title That Should Not Appear in Menu',
            'slug' => 'test-menu-title-' . uniqid(),
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user-id',
            'blocks' => [],
            'show_in_menu' => 1,
            'menu_title' => 'Short Menu Name'
        ];
        $pageId = $createUseCase->execute($pageData);

        // Публикуем
        $renderUseCase = new RenderPageHtml($this->pageRepository, $this->blockRepository);
        $publishUseCase = new PublishPage($this->pageRepository, $renderUseCase);
        $publishUseCase->execute($pageId);

        // Act: Получаем меню через API (симулируем)
        $db = \Infrastructure\Database\Connection::getInstance();
        $stmt = $db->prepare("
            SELECT id, title, menu_title, slug, show_in_menu
            FROM pages
            WHERE id = :id AND status = 'published' AND show_in_menu = 1
        ");
        $stmt->execute(['id' => $pageId]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Assert
        $this->assertNotEmpty($page);
        $this->assertEquals('Short Menu Name', $page['menu_title']);
        
        // Проверяем, что в меню используется menu_title, а не title
        $menuLabel = !empty($page['menu_title']) ? $page['menu_title'] : $page['title'];
        $this->assertEquals('Short Menu Name', $menuLabel);
    }

    public function testMenuTitleFallsBackToPageTitleIfEmpty(): void
    {
        // Arrange: Создаём страницу БЕЗ menu_title
        $createUseCase = new CreatePage($this->pageRepository, $this->blockRepository);
        $pageData = [
            'title' => 'About Us',
            'slug' => 'test-menu-fallback-' . uniqid(),
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user-id',
            'blocks' => [],
            'show_in_menu' => 1,
            'menu_title' => null  // Оставляем пустым
        ];
        $pageId = $createUseCase->execute($pageData);

        $renderUseCase = new RenderPageHtml($this->pageRepository, $this->blockRepository);
        $publishUseCase = new PublishPage($this->pageRepository, $renderUseCase);
        $publishUseCase->execute($pageId);

        // Act
        $db = \Infrastructure\Database\Connection::getInstance();
        $stmt = $db->prepare("SELECT title, menu_title FROM pages WHERE id = :id");
        $stmt->execute(['id' => $pageId]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Assert: menu_title пустой → fallback на title
        $this->assertEmpty($page['menu_title']);
        $menuLabel = !empty($page['menu_title']) ? $page['menu_title'] : $page['title'];
        $this->assertEquals('About Us', $menuLabel);
    }
}
```

### Запуск

```bash
vendor/bin/phpunit tests/Integration/MenuTitleTest.php
```

---

## Шаг 9: Ручное тестирование (smoke test)

### Сценарий 1: Publish flow

1. **Создать новую страницу:**
   - Откройте редактор: `http://localhost/editor.html`
   - Войдите под админом.
   - Создайте страницу: "Test Publish", slug: "test-publish"
   - Добавьте text-block с текстом: "Hello **World**"
   - Нажмите "Сохранить".

2. **Опубликовать:**
   - Нажмите "Опубликовать".
   - Дождитесь уведомления "✅ Страница опубликована".

3. **Проверить публичную страницу:**
   - Откройте `http://localhost/p/test-publish`
   - Убедитесь, что текст отображается с форматированием (World жирный).
   - Откройте DevTools → Network → найдите запрос к `/p/test-publish` → проверьте, что HTML пришёл сразу (не было дополнительного API-запроса за блоками).

4. **Редактировать и ре-публиковать:**
   - Вернитесь в редактор, измените текст на "Updated **Content**".
   - Сохраните и повторно нажмите "Опубликовать".
   - Обновите `/p/test-publish` → убедитесь, что текст обновился.

### Сценарий 2: Custom menu_title

1. **Создать страницу:**
   - Название: "О здравоохранении в Бразилии"
   - Slug: "about-healthcare"
   - Опубликуйте.

2. **Настроить меню:**
   - В настройках включите "Показать в меню".
   - В поле "Название в меню" введите: "О нас"
   - Сохраните.

3. **Проверить меню:**
   - Откройте API: `http://localhost/api/menu/public`
   - Убедитесь, что в JSON-ответе пункт меню имеет label: "О нас" (а не "О здравоохранении в Бразилии").

4. **Проверить fallback:**
   - Создайте ещё одну страницу без заполнения "Название в меню".
   - Включите "Показать в меню".
   - Проверьте `/api/menu/public` → label должен быть равен page.title.

---

## Чек-лист финальной проверки

Перед деплоем в production выполните:

- [ ] ✅ DB миграция применена, колонки `rendered_html` и `menu_title` существуют
- [ ] ✅ `PageController::publish()` передаёт `RenderPageHtml` в `PublishPage`
- [ ] ✅ `PublicPageController::show()` проверяет и отдаёт `rendered_html` для published страниц
- [ ] ✅ Все вхождения `menu_label` заменены на `menu_title` (backend + frontend)
- [ ] ✅ UI поле "Название в меню" отображается в редакторе при включённой галочке
- [ ] ✅ E2E тесты написаны и проходят
- [ ] ✅ Smoke test выполнен вручную: publish → проверка HTML → re-publish → обновление
- [ ] ✅ Smoke test меню: кастомный menu_title → fallback на title
- [ ] ✅ Проверены PHP syntax errors (`php -l`) для изменённых файлов
- [ ] ✅ Нет console errors в браузере при работе с редактором
- [ ] ✅ API возвращает корректные JSON-ответы (`/api/menu/public`, `/api/pages/:id/publish`)

---

## Возможные проблемы и решения

### Проблема 1: "Column 'rendered_html' not found"

**Причина:** Миграция не применена.

**Решение:** Выполните Шаг 1 (применить миграцию).

### Проблема 2: "Too few arguments to function PublishPage::__construct()"

**Причина:** Не передан `RenderPageHtml` в конструктор.

**Решение:** Выполните Шаг 2 (исправить PageController::publish).

### Проблема 3: Публичная страница возвращает пустой экран или 500 ошибку

**Причина:** `PublicPageController` пытается отдать `rendered_html`, но он NULL или повреждён.

**Решение:**
- Проверьте в БД: `SELECT rendered_html FROM pages WHERE slug = 'test-publish' LIMIT 1;`
- Если NULL → ре-публикуйте страницу.
- Если повреждён → проверьте логи `backend/logs/`, ищите ошибки в `RenderPageHtml`.

### Проблема 4: Меню показывает page.title вместо menu_title

**Причина:** Не выполнен Шаг 4 (переименование menu_label → menu_title в MenuController).

**Решение:** Замените `$page['menu_label']` на `$page['menu_title']` в `MenuController::getPublicMenu()`.

### Проблема 5: Frontend не отправляет menu_title в API

**Причина:** Не выполнен Шаг 5 (переименование menuLabel → menuTitle в editor.js).

**Решение:** Проверьте DevTools → Network → XHR → payload должен содержать `menu_title`, а не `menu_label`.

---

## Команды для быстрого запуска всех проверок

```powershell
# 1. Применить миграцию
Get-Content "database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql" | & "C:\xampp\mysql\bin\mysql.exe" -h localhost -u root healthcare_cms

# 2. Проверить синтаксис PHP
php -l backend/src/Presentation/Controller/PageController.php
php -l backend/src/Presentation/Controller/PublicPageController.php
php -l backend/src/Presentation/Controller/MenuController.php

# 3. Запустить PHPUnit тесты
cd backend
vendor/bin/phpunit tests/Integration/PublishPageFlowTest.php
vendor/bin/phpunit tests/Integration/MenuTitleTest.php

# 4. Проверить отсутствие menu_label в коде
grep -r "menu_label" backend/src/
grep -r "menuLabel" frontend/

# 5. Запустить локальный сервер и smoke test вручную
# Откройте http://localhost/editor.html
# Выполните сценарии из Шага 9
```

---

## Итоговая структура изменений

### Backend (PHP)

| Файл | Изменение | Статус |
|------|-----------|--------|
| `database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql` | Создана миграция | ✅ Готово |
| `backend/src/Domain/Entity/Page.php` | Добавлены `renderedHtml`, `menuTitle` | ✅ Готово |
| `backend/src/Infrastructure/Repository/MySQLPageRepository.php` | Поддержка чтения/записи новых полей | ✅ Готово |
| `backend/src/Application/UseCase/PublishPage.php` | Генерация и сохранение `rendered_html` | ✅ Готово |
| `backend/src/Presentation/Controller/PageController.php` | Исправить DI в `publish()` | ❌ TODO |
| `backend/src/Presentation/Controller/PublicPageController.php` | Отдача `rendered_html` | ❌ TODO |
| `backend/src/Presentation/Controller/MenuController.php` | Замена `menu_label` → `menu_title` | ❌ TODO |

### Frontend (JavaScript/HTML)

| Файл | Изменение | Статус |
|------|-----------|--------|
| `frontend/editor.js` | Переименовать `menuLabel` → `menuTitle` | ❌ TODO |
| `frontend/editor.html` (или шаблон) | Добавить UI input для `menuTitle` | ❌ TODO |

### Tests

| Файл | Описание | Статус |
|------|----------|--------|
| `backend/tests/Integration/PublishPageFlowTest.php` | E2E тест публикации и ре-публикации | ❌ TODO |
| `backend/tests/Integration/MenuTitleTest.php` | E2E тест menu_title | ❌ TODO |

---

## Оценка времени реализации

- **Шаг 1 (миграция):** 5 минут
- **Шаг 2 (PageController):** 10 минут
- **Шаг 3 (PublicPageController):** 15 минут
- **Шаг 4 (rename backend):** 10 минут
- **Шаг 5 (rename frontend):** 10 минут
- **Шаг 6 (UI field):** 20 минут
- **Шаг 7-8 (тесты):** 40 минут
- **Шаг 9 (smoke test):** 20 минут

**Итого:** ~2 часа чистого времени разработки.

---

## Следующие шаги после завершения

1. **Коммит и PR:**
   - Создать feature branch: `git checkout -b feature/publish-rendered-html`
   - Закоммитить все изменения.
   - Открыть PR с описанием из этого документа.

2. **Code Review:**
   - Проверить корректность DI.
   - Убедиться в отсутствие SQL-инъекций (используем prepared statements).
   - Проверить escape для HTML-атрибутов (в Markdown renderer уже есть).

3. **Deploy в production:**
   - Применить миграцию на production БД.
   - Задеплоить backend код.
   - Задеплоить frontend код.
   - Выполнить smoke test на production.

4. **Мониторинг:**
   - Проверить логи `backend/logs/` на наличие ошибок рендеринга.
   - Мониторить время загрузки публичных страниц (должно быть быстрее, т.к. HTML pre-rendered).

---

**Дата обновления:** 2025-10-14  
**Автор:** AI Assistant  
**Статус:** Готово к реализации

Этот промпт можно передать любому разработчику или использовать как контрольный список для самостоятельной реализации.
