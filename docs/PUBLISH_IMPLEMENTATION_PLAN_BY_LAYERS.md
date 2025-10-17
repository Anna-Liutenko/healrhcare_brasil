# План реализации функционала публикации — распределение по слоям Clean Architecture

Дата: 2025-10-12  
Основа: [PUBLISH_FEATURE_REQUIREMENTS_2025_10_12.md](./PUBLISH_FEATURE_REQUIREMENTS_2025_10_12.md)

---

## Архитектура проекта (Clean Architecture)

Проект использует чистую архитектуру с четырьмя основными слоями:

```
backend/src/
├── Domain/              # Бизнес-логика (entities, value objects, repository interfaces)
├── Application/         # Use Cases (оркестрация бизнес-логики)
├── Infrastructure/      # Реализация репозиториев, внешние зависимости (DB, файлы)
└── Presentation/        # Контроллеры, HTTP запросы/ответы
```

**Принципы:**
- **Domain** не зависит ни от чего (чистая бизнес-логика).
- **Application** зависит только от Domain (use-cases оркеструют entities через repository interfaces).
- **Infrastructure** зависит от Domain (реализует интерфейсы репозиториев).
- **Presentation** зависит от Application и Domain (контроллеры вызывают use-cases).

---

## Распределение изменений по слоям

### 🟦 Слой 1: Domain (Entities, Value Objects, Repository Interfaces)

**Цель:** добавить поддержку новых полей (`rendered_html`, `menu_title`) в сущность Page и интерфейс репозитория.

#### 1.1. Domain\Entity\Page

**Файл:** `backend/src/Domain/Entity/Page.php`

**Изменения:**
- Добавить приватные поля:
  ```php
  private ?string $renderedHtml = null;
  private ?string $menuTitle = null;
  ```
- Обновить конструктор (добавить параметры `?string $renderedHtml = null, ?string $menuTitle = null`).
- Добавить геттеры/сеттеры:
  ```php
  public function getRenderedHtml(): ?string
  public function setRenderedHtml(?string $html): void
  
  public function getMenuTitle(): ?string
  public function setMenuTitle(?string $menuTitle): void
  ```

**Обоснование:** Entity Page — это domain-объект, который инкапсулирует состояние страницы. Новые поля (`rendered_html`, `menu_title`) — часть состояния, поэтому они должны быть в entity.

**Бизнес-правило (опционально):**
- Метод `publish()` может опционально требовать наличия `renderedHtml` (валидация). Но на практике рендеринг выполняется в use-case, поэтому в entity только setter.

---

#### 1.2. Domain\Repository\PageRepositoryInterface

**Файл:** `backend/src/Domain/Repository/PageRepositoryInterface.php`

**Изменения:**
- Интерфейс уже имеет методы `save(Page $page)` и `findBySlug(string $slug)` → изменений не требуется (сохранение новых полей — ответственность реализации в Infrastructure).

**Обоснование:** Интерфейс репозитория определяет контракт "сохранить/найти страницу", но не детали полей. Новые поля прозрачно передаются через entity.

---

### 🟩 Слой 2: Application (Use Cases)

**Цель:** реализовать бизнес-логику публикации (генерация `rendered_html`, установка `published_at`).

#### 2.1. Application\UseCase\PublishPage

**Файл:** `backend/src/Application/UseCase/PublishPage.php`

**Текущее состояние:**
```php
class PublishPage {
    public function execute(string $pageId): void {
        $page = $this->pageRepository->findById($pageId);
        if (!$page) throw new PageNotFoundException();
        
        $page->publish(); // sets status to 'published' and published_at
        $this->pageRepository->save($page);
    }
}
```

**Изменения:**
1. Внедрить зависимость `RenderStaticTemplate` use-case (или создать новый use-case `RenderPageHtml`).
2. После `$page->publish()` вызвать рендеринг:
   ```php
   // Generate static HTML
   $html = $this->renderPageHtml->execute($page);
   $page->setRenderedHtml($html);
   ```
3. Сохранить обновлённую entity:
   ```php
   $this->pageRepository->save($page);
   ```

**Новый use-case: RenderPageHtml** (рекомендуется создать отдельно)

**Файл:** `backend/src/Application/UseCase/RenderPageHtml.php`

**Цель:** генерация статичного HTML из page entity + blocks.

**Зависимости:**
- `PageRepositoryInterface` (получить blocks через `GetPageWithBlocks` или напрямую).
- `BlockRepositoryInterface` (получить блоки страницы).
- `RenderStaticTemplate` (может быть переиспользован или создан новый рендерер).

**Логика:**
```php
class RenderPageHtml {
    public function execute(Page $page): string {
        // 1. Получить blocks страницы через BlockRepository
        $blocks = $this->blockRepository->findByPageId($page->getId());
        
        // 2. Сгенерировать HTML (аналогично логике из PublicPageController::renderPage)
        //    - Header (site name, logo, menu)
        //    - Body (blocks в правильном порядке)
        //    - Footer
        //    - CSS (editor-public.css)
        
        // 3. Вернуть полный HTML string
        return $html;
    }
}
```

**Альтернатива:** переиспользовать `RenderStaticTemplate` (если он может рендерить не только шаблоны, но и динамические страницы). Возможно потребуется рефакторинг.

**Обоснование:** Use-case — это оркестрация бизнес-процесса. "Опубликовать страницу" включает генерацию HTML → это часть бизнес-логики публикации.

---

#### 2.2. Application\UseCase\UpdatePage (или CreatePage)

**Файл:** `backend/src/Application/UseCase/UpdatePage.php`

**Изменения:**
- При сохранении страницы передавать `menu_title` из API payload в entity:
  ```php
  $page->setMenuTitle($data['menu_title'] ?? null);
  ```

**Обоснование:** UpdatePage use-case отвечает за обновление атрибутов страницы. Новое поле `menu_title` — это атрибут, который передаётся из UI.

---

### 🟨 Слой 3: Infrastructure (Repository Implementations, DB, External Services)

**Цель:** сохранять и читать новые поля (`rendered_html`, `menu_title`) из MySQL.

#### 3.1. Infrastructure\Repository\MySQLPageRepository

**Файл:** `backend/src/Infrastructure/Repository/MySQLPageRepository.php`

**Изменения:**

**1. Метод `save(Page $page)`:**
- Обновить SQL INSERT/UPDATE для включения новых колонок:
  ```sql
  INSERT INTO pages (
      id, title, slug, status, type,
      seo_title, seo_description, seo_keywords,
      show_in_menu, menu_title, show_in_sitemap, menu_order,
      created_at, updated_at, published_at, trashed_at,
      created_by, collection_config, page_specific_code,
      rendered_html
  ) VALUES (
      :id, :title, :slug, :status, :type,
      :seo_title, :seo_description, :seo_keywords,
      :show_in_menu, :menu_title, :show_in_sitemap, :menu_order,
      :created_at, :updated_at, :published_at, :trashed_at,
      :created_by, :collection_config, :page_specific_code,
      :rendered_html
  )
  ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      slug = VALUES(slug),
      ...
      menu_title = VALUES(menu_title),
      rendered_html = VALUES(rendered_html),
      ...
  ```
- Добавить биндинг параметров:
  ```php
  'menu_title' => $page->getMenuTitle(),
  'rendered_html' => $page->getRenderedHtml(),
  ```

**2. Метод `hydrate(array $row): Page` (создание entity из DB row):**
- Добавить маппинг новых полей из БД:
  ```php
  return new Page(
      id: $row['id'],
      title: $row['title'],
      slug: $row['slug'],
      // ... existing fields ...
      renderedHtml: $row['rendered_html'] ?? null,
      menuTitle: $row['menu_title'] ?? null
  );
  ```

**Обоснование:** Infrastructure-слой реализует интерфейсы репозиториев, определённые в Domain. MySQL-репозиторий ответственен за персистентность новых полей.

---

#### 3.2. Database Migration

**Файл:** `database/migrations/YYYY_MM_DD_add_rendered_html_and_menu_title_to_pages.sql`

**SQL:**
```sql
-- Migration: Add rendered_html and menu_title to pages table
-- Date: 2025-10-12
-- Author: Healthcare CMS Team

ALTER TABLE pages
  ADD COLUMN rendered_html LONGTEXT NULL COMMENT 'Pre-rendered static HTML (cached at publish time)' AFTER page_specific_code,
  ADD COLUMN menu_title VARCHAR(255) NULL COMMENT 'Custom menu item label (overrides title)' AFTER show_in_menu;

-- Add unique index on slug if not exists (idempotent)
ALTER TABLE pages ADD UNIQUE INDEX ux_pages_slug (slug);
```

**Rollback SQL (optional):**
```sql
ALTER TABLE pages
  DROP COLUMN rendered_html,
  DROP COLUMN menu_title;
```

**Обоснование:** Infrastructure-слой управляет схемой базы данных. Миграция добавляет поддержку новых полей.

---

### 🟥 Слой 4: Presentation (Controllers, HTTP API)

**Цель:** обрабатывать HTTP-запросы на публикацию, сохранение `menu_title`, отдачу публичного HTML.

#### 4.1. Presentation\Controller\PageController

**Файл:** `backend/src/Presentation/Controller/PageController.php`

**Метод:** `publish(string $id): void` (уже существует)

**Изменения:**
- После успешной публикации вернуть в JSON ответе:
  ```php
  $this->jsonResponse([
      'success' => true,
      'message' => 'Page published successfully',
      'slug' => $page->getSlug(),
      'publicUrl' => '/' . $page->getSlug()
  ]);
  ```

**Метод:** `update(string $id): void` или `create(): void`

**Изменения:**
- Принять `menu_title` из JSON payload:
  ```php
  $data = json_decode(file_get_contents('php://input'), true);
  $menuTitle = $data['menu_title'] ?? null;
  ```
- Передать в use-case:
  ```php
  $updatePageUseCase->execute($id, [
      'title' => $data['title'],
      'slug' => $data['slug'],
      'menu_title' => $menuTitle,
      // ... other fields ...
  ]);
  ```

**Обоснование:** Presentation-слой — это точка входа HTTP запросов. Контроллер валидирует input, вызывает use-case, форматирует output.

---

#### 4.2. Presentation\Controller\PublicPageController

**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`

**Метод:** `show(string $slug): void`

**Текущее состояние:**
- Ищет страницу по slug, рендерит через `RenderStaticTemplate` или `renderPage()` method.

**Изменения:**
- **Добавить проверку `rendered_html`:**
  ```php
  public function show(string $slug): void {
      $pageRepository = new MySQLPageRepository(Database::getInstance());
      $page = $pageRepository->findBySlug($slug);
      
      if (!$page) {
          // Try static template fallback
          if ($this->tryRenderStaticTemplate($slug)) {
              return;
          }
          http_response_code(404);
          echo "Page not found";
          return;
      }
      
      // Check if page is published and has pre-rendered HTML
      if ($page->getStatus()->getValue() === 'published' && $page->getRenderedHtml()) {
          header('Content-Type: text/html; charset=utf-8');
          echo $page->getRenderedHtml();
          return;
      }
      
      // Fallback: runtime rendering (for draft preview or missing rendered_html)
      $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);
      $pageData = $useCase->executeBySlug($slug);
      $this->renderPage($pageData);
  }
  ```

**Обоснование:** Публичный контроллер отдаёт HTML пользователям. Для published страниц отдаём pre-rendered HTML (быстро, стабильно). Для draft — runtime render (preview mode).

---

#### 4.3. Presentation\Controller\MenuController

**Файл:** `backend/src/Presentation/Controller/MenuController.php`

**Метод:** `getPublicMenu(): void`

**Текущее состояние:**
```php
SELECT id, title, slug, menu_position
FROM pages
WHERE status = 'published' AND show_in_menu = 1
ORDER BY menu_position ASC, id ASC
```

**Изменения:**
- Обновить SQL SELECT для использования `menu_title`:
  ```php
  SELECT 
      id, 
      COALESCE(menu_title, title) AS label,
      slug, 
      menu_position
  FROM pages
  WHERE status = 'published' AND show_in_menu = 1
  ORDER BY menu_position ASC, id ASC
  ```
- Вернуть `label` вместо `title` в JSON ответе:
  ```php
  $menuItems[] = [
      'id' => $page['id'],
      'label' => $page['label'], // custom menu_title or fallback to title
      'url' => '/' . $page['slug'],
      'slug' => $page['slug'],
      'position' => $page['menu_position']
  ];
  ```

**Обоснование:** MenuController формирует публичное меню. Использование `COALESCE(menu_title, title)` позволяет показывать custom название, если оно задано, или title как fallback.

---

### 🟪 Слой 5: Frontend (UI, API Client)

**Цель:** добавить UI для `menu_title`, обработку публикации, показ публичного URL.

#### 5.1. frontend/editor.js

**Изменения:**

**1. Добавить поле `menuTitle` в `pageSettings`:**
```javascript
data() {
    return {
        pageSettings: {
            showInMenu: false,
            menuTitle: '', // NEW: custom menu item label
            showInSitemap: true,
            menuOrder: 0
        }
    }
}
```

**2. Добавить UI элемент в HTML template (settings panel):**
```html
<!-- Inside .settings-content, after "Show in Menu" checkbox -->
<div class="settings-group" v-if="pageSettings.showInMenu">
    <label class="settings-label">
        Название в меню
        <input 
            type="text" 
            class="settings-input" 
            v-model="pageSettings.menuTitle"
            placeholder="Оставьте пустым для использования названия страницы"
            maxlength="255"
        />
    </label>
    <span class="settings-hint">
        Кастомное название пункта меню (например, "О нас" вместо длинного title)
    </span>
</div>
```

**3. Обновить метод `savePage()` для отправки `menu_title`:**
```javascript
async savePage() {
    // ... existing validation ...
    
    const payload = {
        title: this.pageData.title,
        slug: this.pageData.slug,
        status: this.pageData.status,
        // ... other fields ...
        show_in_menu: (this.pageSettings.showInMenu && this.pageData.status === 'published') ? 1 : 0,
        menu_title: this.pageSettings.menuTitle || null, // NEW
        // ... blocks ...
    };
    
    await this.apiClient.savePage(this.currentPageId, payload);
}
```

**4. Обновить метод `loadPage(id)` для загрузки `menu_title`:**
```javascript
async loadPage(pageId) {
    const response = await this.apiClient.getPage(pageId);
    const pagePayload = response.page;
    
    this.pageSettings = {
        showInMenu: !!pagePayload.show_in_menu,
        menuTitle: pagePayload.menu_title || '', // NEW
        // ... other settings ...
    };
}
```

**5. Обновить метод `publishPage()` для отображения публичного URL:**
```javascript
async publishPage() {
    if (!this.currentPageId) {
        this.showNotification('Сначала сохраните страницу', 'error');
        return;
    }
    
    try {
        const response = await this.apiClient.publishPage(this.currentPageId);
        this.pageData.status = 'published';
        
        // Show public URL to admin
        const publicUrl = window.location.origin + '/' + (response.slug || this.pageData.slug);
        this.showNotification(`Страница опубликована! Доступна по адресу: ${publicUrl}`, 'success');
        
        console.log('Public URL:', publicUrl);
    } catch (error) {
        console.error('Publish error:', error);
        this.showNotification('Ошибка публикации', 'error');
    }
}
```

**Обоснование:** Frontend — это UI-слой. Мы добавляем элементы управления для нового поля и связываем их с API.

---

#### 5.2. frontend/api-client.js

**Метод:** `publishPage(pageId)`

**Текущее состояние:**
```javascript
async publishPage(pageId) {
    return await this.request(`/api/pages/${pageId}/publish`, {
        method: 'PUT'
    });
}
```

**Изменения:**
- Обеспечить, что response содержит `slug` и `publicUrl` (backend уже должен отдавать их).
- Никаких изменений в коде не требуется, но убедиться, что response корректно парсится.

**Обоснование:** API client — это тонкая обёртка над HTTP. Если backend возвращает нужные данные, frontend их получит автоматически.

---

### 📋 Слой 6: Tests (Unit, Integration, E2E)

**Цель:** покрыть тестами новую функциональность.

#### 6.1. Unit Tests

**Файл:** `backend/tests/Unit/PublishPageTest.php` (создать новый)

**Тесты:**
1. `testPublishPageSetsStatusAndPublishedAt()` — проверить, что `publish()` устанавливает статус и дату.
2. `testPublishPageGeneratesRenderedHtml()` — проверить, что `rendered_html` не null после публикации.
3. `testPublishPageSavesRenderedHtml()` — mock repository, проверить вызов `save()` с entity содержащей `rendered_html`.

**Файл:** `backend/tests/Unit/MySQLPageRepositoryTest.php` (обновить существующий)

**Тесты:**
1. `testSavePageWithRenderedHtml()` — создать Page с `rendered_html`, сохранить, загрузить из БД, проверить что поле сохранилось.
2. `testSavePageWithMenuTitle()` — аналогично для `menu_title`.

---

#### 6.2. Integration Tests

**Файл:** `backend/tests/Integration/PublishFlowIntegrationTest.php` (создать новый)

**Тест:** полный flow создания → публикации → проверки:
```php
public function testCreatePublishAndRetrievePageWithRenderedHtml(): void {
    // 1. Create page
    $createUseCase = new CreatePage($pageRepo, $blockRepo);
    $pageId = $createUseCase->execute([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'blocks' => [/* ... */]
    ], 'admin-user-id');
    
    // 2. Publish page
    $publishUseCase = new PublishPage($pageRepo, $renderHtmlUseCase);
    $publishUseCase->execute($pageId);
    
    // 3. Retrieve page and verify rendered_html is set
    $page = $pageRepo->findById($pageId);
    $this->assertNotNull($page->getRenderedHtml());
    $this->assertStringContainsString('<html', $page->getRenderedHtml());
    $this->assertEquals('published', $page->getStatus()->getValue());
}
```

---

#### 6.3. E2E Tests (Playwright)

**Файл:** `frontend/e2e/tests/editor.spec.js` (обновить существующий)

**Тест:** добавить проверку `menu_title`:
```javascript
test('should create page with custom menu title and verify in public menu', async ({ page }) => {
    // 1. Login
    // 2. Create page
    // 3. Set menu_title to "Custom Label"
    await page.fill('input[name="menu_title"]', 'Custom Label');
    // 4. Save and publish
    // 5. Fetch public menu API
    const menuRes = await fetch(`${apiBase}/api/menu/public`);
    const menu = await menuRes.json();
    // 6. Assert menu contains item with label "Custom Label"
    expect(menu.some(item => item.label === 'Custom Label')).toBe(true);
});
```

**Тест:** проверка обновления опубликованной страницы:
```javascript
test('should re-publish page and verify updated rendered_html', async ({ page }) => {
    // 1. Create and publish page
    // 2. Edit page (change block text)
    // 3. Re-publish
    // 4. Fetch public URL and verify new content is present
});
```

---

## Итоговая карта изменений по слоям

| Слой | Файл | Тип изменения | Описание |
|------|------|---------------|----------|
| **Domain** | `Domain/Entity/Page.php` | Изменение | Добавить поля `renderedHtml`, `menuTitle` + getters/setters |
| **Domain** | `Domain/Repository/PageRepositoryInterface.php` | Без изменений | Интерфейс прозрачно поддерживает новые поля через entity |
| **Application** | `Application/UseCase/PublishPage.php` | Изменение | Вызвать `RenderPageHtml`, установить `rendered_html` |
| **Application** | `Application/UseCase/RenderPageHtml.php` | Создание | Новый use-case для генерации статичного HTML |
| **Application** | `Application/UseCase/UpdatePage.php` | Изменение | Обработать `menu_title` из payload |
| **Infrastructure** | `Infrastructure/Repository/MySQLPageRepository.php` | Изменение | SQL INSERT/UPDATE для `rendered_html`, `menu_title` |
| **Infrastructure** | `database/migrations/...sql` | Создание | ALTER TABLE для добавления колонок |
| **Presentation** | `Presentation/Controller/PageController.php` | Изменение | Вернуть `slug`, `publicUrl` в response; принять `menu_title` |
| **Presentation** | `Presentation/Controller/PublicPageController.php` | Изменение | Отдавать `rendered_html` для published страниц |
| **Presentation** | `Presentation/Controller/MenuController.php` | Изменение | SELECT использует `COALESCE(menu_title, title)` |
| **Frontend** | `frontend/editor.js` | Изменение | UI для `menu_title`, показ public URL после publish |
| **Frontend** | `frontend/api-client.js` | Без изменений (опционально) | Убедиться что response парсится корректно |
| **Tests** | `backend/tests/Unit/PublishPageTest.php` | Создание | Unit-тесты для PublishPage use-case |
| **Tests** | `backend/tests/Integration/...Test.php` | Создание | Integration тест полного flow |
| **Tests** | `frontend/e2e/tests/editor.spec.js` | Изменение | E2E тесты для `menu_title` и re-publish |

---

## Порядок реализации (рекомендуемый)

### Этап 1: Domain + Infrastructure (фундамент)
1. Обновить `Domain/Entity/Page.php` (добавить поля).
2. Создать DB миграцию и выполнить её.
3. Обновить `Infrastructure/Repository/MySQLPageRepository.php` (save/hydrate).
4. Написать unit-тесты для repository (save/load новых полей).

**Milestone:** можно сохранять и загружать `rendered_html`, `menu_title` в/из БД.

---

### Этап 2: Application (бизнес-логика)
1. Создать `Application/UseCase/RenderPageHtml.php` (генерация HTML).
2. Обновить `Application/UseCase/PublishPage.php` (вызвать render + set rendered_html).
3. Обновить `Application/UseCase/UpdatePage.php` (обработать menu_title).
4. Написать unit-тесты для use-cases.

**Milestone:** публикация генерирует и сохраняет `rendered_html`.

---

### Этап 3: Presentation (HTTP API)
1. Обновить `Presentation/Controller/PageController.php` (publish response, accept menu_title).
2. Обновить `Presentation/Controller/PublicPageController.php` (отдавать rendered_html).
3. Обновить `Presentation/Controller/MenuController.php` (использовать menu_title).
4. Написать integration тесты (API endpoints).

**Milestone:** API работает корректно (можно публиковать, получать rendered_html, menu_title в меню).

---

### Этап 4: Frontend (UI)
1. Обновить `frontend/editor.js` (UI для menu_title, показ public URL).
2. Написать E2E тесты (Playwright).

**Milestone:** полный flow работает end-to-end.

---

### Этап 5: Deployment & Documentation
1. Развернуть DB миграцию на production.
2. Развернуть backend код.
3. Развернуть frontend код.
4. Smoke test на production.
5. Обновить документацию (API_ENDPOINTS_CHEATSHEET.md, README.md).

---

## Зависимости между слоями (граф изменений)

```
        Domain/Entity/Page (add fields)
                 ↓
        Infrastructure/MySQLPageRepository (persist fields)
                 ↓
        Application/RenderPageHtml (new use-case)
                 ↓
        Application/PublishPage (orchestrate render + save)
                 ↓
        Presentation/PageController (API publish endpoint)
                 ↓
        Frontend/editor.js (UI + API call)
```

**Параллельные ветки:**
- `Application/UpdatePage` → `Presentation/PageController` (save menu_title)
- `Presentation/MenuController` (use menu_title in SQL)
- `Presentation/PublicPageController` (serve rendered_html)

---

## Риски и миtigations (по слоям)

### Domain
- **Риск:** добавление полей ломает существующие конструкторы.
- **Mitigation:** использовать named parameters (PHP 8+) или добавить поля как optional с default values.

### Application
- **Риск:** `RenderPageHtml` может быть медленным для больших страниц.
- **Mitigation:** выполнять рендеринг асинхронно (queue job) или установить timeout. Рендеринг выполняется только при публикации, не на каждом запросе.

### Infrastructure
- **Риск:** миграция может упасть на большой production БД (long table lock).
- **Mitigation:** выполнить миграцию в maintenance window; использовать `ALTER TABLE ... ALGORITHM=INPLACE` если MySQL поддерживает.

### Presentation
- **Риск:** `rendered_html` может быть очень большим (LONGTEXT до 4GB).
- **Mitigation:** установить лимит размера HTML (например, 5MB) в use-case; использовать compression (gzip) при отдаче клиенту.

### Frontend
- **Риск:** старые браузеры не поддерживают новые JS features.
- **Mitigation:** frontend уже использует современный JS (Vue 3); добавить polyfills если требуется поддержка IE11 (маловероятно в 2025).

---

## Checklist для code review

### Domain
- [ ] Новые поля имеют корректные типы (`?string` для nullable).
- [ ] Getters/setters следуют naming conventions (`getRenderedHtml`, `setRenderedHtml`).
- [ ] Entity не имеет зависимостей от других слоёв.

### Application
- [ ] Use-cases не содержат SQL запросов (используют repository interfaces).
- [ ] Use-cases не зависят от HTTP (не используют `$_POST`, `header()`, etc).
- [ ] Бизнес-логика инкапсулирована в use-cases, а не в контроллерах.

### Infrastructure
- [ ] SQL запросы используют prepared statements (защита от SQL injection).
- [ ] Все новые колонки имеют корректные типы и индексы.
- [ ] Миграция идемпотентна (можно запустить повторно без ошибок).

### Presentation
- [ ] Контроллеры валидируют input перед передачей в use-cases.
- [ ] HTTP статус-коды корректны (200, 400, 404, 409, 500).
- [ ] JSON responses имеют consistent структуру (`{ success: bool, data?: any, error?: string }`).

### Frontend
- [ ] UI элементы доступны (accessibility: labels, placeholders).
- [ ] Валидация на клиенте (maxlength, required fields).
- [ ] Обработка ошибок API (показ user-friendly сообщений).

### Tests
- [ ] Unit-тесты покрывают edge cases (null values, empty strings).
- [ ] Integration тесты используют тестовую БД (не production).
- [ ] E2E тесты изолированы (cleanup после каждого теста).

---

## Финальная оценка трудозатрат (с учётом слоёв)

| Слой | Задачи | Время (часы) |
|------|--------|-------------|
| Domain | Entity + interface | 0.5 |
| Infrastructure | Repository + migration | 2 |
| Application | RenderPageHtml + PublishPage + UpdatePage | 4 |
| Presentation | 3 контроллера | 3 |
| Frontend | UI + API client | 2 |
| Tests | Unit + Integration + E2E | 4 |
| Deployment | Миграция + deploy + smoke test | 1 |
| **Итого** | | **16.5 часов** |

**Реалистичная оценка с учётом debugging, code review, документации:** **20–24 часа** (2.5–3 рабочих дня для одного разработчика).

---

Дата создания: 2025-10-12  
Автор: Healthcare CMS Team  
Статус: готов к реализации
