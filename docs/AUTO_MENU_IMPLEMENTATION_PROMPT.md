# 🎯 Промпт: Автоматическое меню (Вариант 1)

**Дата:** 5 октября 2025  
**Цель:** Реализовать автоматическую синхронизацию меню с опубликованными страницами  
**Подход:** Страницы управляют меню через настройки публикации (без отдельной админки меню)

---

## 📋 КОНТЕКСТ ПРОЕКТА

### Текущая ситуация
- ✅ Backend API работает (24 endpoints)
- ✅ База данных MySQL настроена (10 таблиц)
- ✅ Визуальный редактор работает (`editor.html`)
- ✅ Медиабиблиотека работает (`media-library.html`)
- ⚠️ Menu Editor создан, но неудобен для клиента (требует ручного управления)

### Проблема
**Menu Editor** требует:
1. Создать страницу в редакторе
2. Опубликовать страницу
3. Перейти в Menu Editor
4. Вручную добавить пункт меню
5. Привязать к странице

**Это сложно для "продавца мандаринов"!**

### Решение (Вариант 1)
Страница публикуется → **автоматически** появляется в меню (если включена опция).

---

## 🎯 ТЕХНИЧЕСКОЕ ЗАДАНИЕ

### Требования

1. **Простота для клиента**
   - Один чекбокс при публикации: ✅ "Показывать в меню"
   - Автоматическая синхронизация (снял с публикации → исчез из меню)
   - Опциональная настройка: позиция в меню, название в меню

2. **Сохранение существующего функционала**
   - Визуальный редактор остаётся без изменений (только +UI для меню)
   - Все типы страниц работают (regular, article, guide, collection)
   - Drag & Drop блоков, inline-редактирование — всё остаётся

3. **Удаление Menu Editor**
   - После реализации автоматического меню, старый Menu Editor не нужен
   - Удалить файлы: menu-editor.html, menu-editor.js, menu-editor.css
   - Удалить промпт: MENU_EDITOR_PROMPT.md

---

## 📝 ПОШАГОВЫЙ ПЛАН РЕАЛИЗАЦИИ

---

## **ШАГ 1: Миграция БД** ⏳

### Задача
Добавить 3 поля в таблицу `pages` для управления меню.

### Файл: `database/migrations/011_add_menu_fields_to_pages.sql`

```sql
-- ================================================
-- Миграция: Добавление полей для автоматического меню
-- Дата: 5 октября 2025
-- Автор: Claude + Anna
-- ================================================

-- Добавляем поля для автоматического меню
ALTER TABLE pages 
ADD COLUMN show_in_menu BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN menu_position INT NULL,
ADD COLUMN menu_label VARCHAR(255) NULL;

-- Индекс для быстрой выборки пунктов меню
CREATE INDEX idx_pages_menu 
ON pages(status, visibility, show_in_menu, menu_position)
WHERE status = 'published' AND visibility = 'public' AND show_in_menu = TRUE;

-- Комментарии для документации
COMMENT ON COLUMN pages.show_in_menu IS 'Показывать страницу в главном меню (работает только для published + public)';
COMMENT ON COLUMN pages.menu_position IS 'Порядковый номер в меню (меньшее число = выше, NULL = автоматическая позиция)';
COMMENT ON COLUMN pages.menu_label IS 'Название в меню (если NULL → используется title страницы)';

-- Вывод результата
SELECT 
    CONCAT('✅ Добавлено ', COUNT(*), ' полей в таблицу pages') AS result
FROM information_schema.columns 
WHERE table_name = 'pages' 
  AND column_name IN ('show_in_menu', 'menu_position', 'menu_label');
```

### Действия
1. Создать файл `database/migrations/011_add_menu_fields_to_pages.sql`
2. Запустить миграцию:
   ```bash
   mysql -u root healthcare_cms < database/migrations/011_add_menu_fields_to_pages.sql
   ```
3. Проверить результат:
   ```sql
   DESCRIBE pages;
   -- Должны быть поля: show_in_menu, menu_position, menu_label
   ```

### Критерии успеха
- ✅ 3 новых поля в таблице `pages`
- ✅ Индекс `idx_pages_menu` создан
- ✅ Комментарии добавлены

---

## **ШАГ 2: Обновить Backend Entity (Page.php)** ⏳

### Задача
Добавить 3 новых свойства в класс `Page`.

### Файл: `backend/src/Domain/Entities/Page.php`

**Добавить в класс:**

```php
// ... existing code ...

class Page
{
    // ... existing properties ...
    
    private ?bool $showInMenu;
    private ?int $menuPosition;
    private ?string $menuLabel;
    
    // ... existing constructor ...
    
    public function __construct(
        string $id,
        string $title,
        string $slug,
        string $status,
        string $type,
        \DateTime $createdAt,
        string $createdBy,
        ?string $visibility = 'public',
        ?\DateTime $updatedAt = null,
        ?\DateTime $publishedAt = null,
        ?string $lastEditedBy = null,
        ?bool $showInMenu = false,         // NEW
        ?int $menuPosition = null,         // NEW
        ?string $menuLabel = null          // NEW
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->slug = $slug;
        $this->status = $status;
        $this->type = $type;
        $this->createdAt = $createdAt;
        $this->createdBy = $createdBy;
        $this->visibility = $visibility;
        $this->updatedAt = $updatedAt;
        $this->publishedAt = $publishedAt;
        $this->lastEditedBy = $lastEditedBy;
        $this->showInMenu = $showInMenu;       // NEW
        $this->menuPosition = $menuPosition;   // NEW
        $this->menuLabel = $menuLabel;         // NEW
    }
    
    // Getters
    public function getShowInMenu(): bool
    {
        return $this->showInMenu ?? false;
    }
    
    public function getMenuPosition(): ?int
    {
        return $this->menuPosition;
    }
    
    public function getMenuLabel(): ?string
    {
        return $this->menuLabel;
    }
    
    // Setters
    public function setShowInMenu(bool $showInMenu): void
    {
        $this->showInMenu = $showInMenu;
    }
    
    public function setMenuPosition(?int $position): void
    {
        $this->menuPosition = $position;
    }
    
    public function setMenuLabel(?string $label): void
    {
        $this->menuLabel = $label;
    }
    
    // ... rest of the class ...
}
```

### Критерии успеха
- ✅ 3 новых свойства добавлены
- ✅ Constructor обновлён
- ✅ Getters/Setters добавлены

---

## **ШАГ 3: Обновить PageRepository** ⏳

### Задача
Добавить метод `getMenuPages()` для получения страниц меню.

### Файл: `backend/src/Infrastructure/Persistence/MySQLPageRepository.php`

**Добавить метод:**

```php
// ... existing code ...

class MySQLPageRepository implements PageRepositoryInterface
{
    // ... existing methods ...
    
    /**
     * Get pages for public menu
     * Returns only published + public + show_in_menu = true
     * Sorted by menu_position ASC
     * 
     * @return Page[]
     */
    public function getMenuPages(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                title,
                slug,
                status,
                type,
                visibility,
                show_in_menu,
                menu_position,
                menu_label,
                created_at,
                created_by
            FROM pages
            WHERE status = 'published'
              AND visibility = 'public'
              AND show_in_menu = TRUE
            ORDER BY 
                CASE WHEN menu_position IS NULL THEN 999999 ELSE menu_position END ASC,
                created_at DESC
        ");
        
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $pages = [];
        foreach ($rows as $row) {
            $pages[] = new Page(
                id: $row['id'],
                title: $row['title'],
                slug: $row['slug'],
                status: $row['status'],
                type: $row['type'],
                createdAt: new \DateTime($row['created_at']),
                createdBy: $row['created_by'],
                visibility: $row['visibility'] ?? 'public',
                showInMenu: (bool)$row['show_in_menu'],
                menuPosition: $row['menu_position'] ? (int)$row['menu_position'] : null,
                menuLabel: $row['menu_label']
            );
        }
        
        return $pages;
    }
    
    // ... rest of the class ...
}
```

**Также обновить методы `save()` и `update()` для сохранения новых полей:**

```php
public function save(Page $page): void
{
    $stmt = $this->pdo->prepare("
        INSERT INTO pages (
            id, title, slug, status, type, visibility,
            show_in_menu, menu_position, menu_label,
            created_at, created_by
        ) VALUES (
            :id, :title, :slug, :status, :type, :visibility,
            :show_in_menu, :menu_position, :menu_label,
            :created_at, :created_by
        )
    ");
    
    $stmt->execute([
        ':id' => $page->getId(),
        ':title' => $page->getTitle(),
        ':slug' => $page->getSlug(),
        ':status' => $page->getStatus(),
        ':type' => $page->getType(),
        ':visibility' => $page->getVisibility(),
        ':show_in_menu' => $page->getShowInMenu() ? 1 : 0,
        ':menu_position' => $page->getMenuPosition(),
        ':menu_label' => $page->getMenuLabel(),
        ':created_at' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
        ':created_by' => $page->getCreatedBy()
    ]);
}

public function update(Page $page): void
{
    $stmt = $this->pdo->prepare("
        UPDATE pages SET
            title = :title,
            slug = :slug,
            status = :status,
            type = :type,
            visibility = :visibility,
            show_in_menu = :show_in_menu,
            menu_position = :menu_position,
            menu_label = :menu_label,
            updated_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':id' => $page->getId(),
        ':title' => $page->getTitle(),
        ':slug' => $page->getSlug(),
        ':status' => $page->getStatus(),
        ':type' => $page->getType(),
        ':visibility' => $page->getVisibility(),
        ':show_in_menu' => $page->getShowInMenu() ? 1 : 0,
        ':menu_position' => $page->getMenuPosition(),
        ':menu_label' => $page->getMenuLabel()
    ]);
}
```

### Критерии успеха
- ✅ Метод `getMenuPages()` создан
- ✅ Методы `save()` и `update()` обновлены
- ✅ SQL запрос корректно сортирует (position ASC, потом created_at DESC)

---

## **ШАГ 4: Создать MenuController** ⏳

### Задача
Создать новый контроллер с методом `getPublicMenu()`.

### Файл: `backend/src/Presentation/Controllers/MenuController.php`

```php
<?php

namespace Presentation\Controllers;

use Infrastructure\Persistence\MySQLPageRepository;
use Infrastructure\Database\Database;

class MenuController
{
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * GET /api/menu/public
     * Returns public menu items (published + public + show_in_menu = true)
     */
    public function getPublicMenu(): void
    {
        try {
            $pdo = Database::getConnection();
            $pageRepository = new MySQLPageRepository($pdo);
            
            // Get pages for menu
            $pages = $pageRepository->getMenuPages();
            
            // Map to menu items
            $menuItems = array_map(function($page) {
                return [
                    'label' => $page->getMenuLabel() ?? $page->getTitle(),
                    'url' => '/' . $page->getSlug(),
                    'slug' => $page->getSlug(),
                    'position' => $page->getMenuPosition()
                ];
            }, $pages);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $menuItems
            ], 200);
            
        } catch (\Exception $e) {
            error_log("MenuController::getPublicMenu() error: " . $e->getMessage());
            
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to load menu',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
}
```

### Критерии успеха
- ✅ Файл создан
- ✅ Метод `getPublicMenu()` реализован
- ✅ Обработка ошибок добавлена

---

## **ШАГ 5: Обновить роутинг** ⏳

### Задача
Добавить маршрут для нового endpoint.

### Файл: `backend/public/index.php`

**Найти секцию с роутингом и добавить:**

```php
// ... existing routes ...

// ==========================================
// MENU ENDPOINTS
// ==========================================

// GET /api/menu/public - Get public menu items
if ($method === 'GET' && $path === '/api/menu/public') {
    require_once __DIR__ . '/../src/Presentation/Controllers/MenuController.php';
    $controller = new \Presentation\Controllers\MenuController();
    $controller->getPublicMenu();
    exit;
}

// ... existing old menu routes (можно оставить для обратной совместимости) ...
```

### Критерии успеха
- ✅ Маршрут добавлен
- ✅ Endpoint доступен по `GET /api/menu/public`

---

## **ШАГ 6: Тестирование Backend** ⏳

### Задача
Проверить работу нового endpoint.

### Команды для тестирования

**1. Создать тестовую страницу с меню:**

```bash
curl -X POST http://localhost/healthcare-cms-backend/public/api/pages \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Тестовая страница",
    "slug": "test-page",
    "type": "regular",
    "status": "published",
    "visibility": "public",
    "show_in_menu": true,
    "menu_position": 1,
    "menu_label": "Тест",
    "created_by": "test-user-id",
    "blocks": []
  }'
```

**2. Получить публичное меню:**

```bash
curl http://localhost/healthcare-cms-backend/public/api/menu/public
```

**Ожидаемый ответ:**

```json
{
  "success": true,
  "data": [
    {
      "label": "Тест",
      "url": "/test-page",
      "slug": "test-page",
      "position": 1
    }
  ]
}
```

**3. Создать страницу БЕЗ меню:**

```bash
curl -X POST http://localhost/healthcare-cms-backend/public/api/pages \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Скрытая страница",
    "slug": "hidden",
    "type": "article",
    "status": "published",
    "visibility": "public",
    "show_in_menu": false,
    "created_by": "test-user-id",
    "blocks": []
  }'
```

**4. Проверить меню ещё раз:**

```bash
curl http://localhost/healthcare-cms-backend/public/api/menu/public
# Должна быть ТОЛЬКО "Тест", "Скрытая страница" не должна быть в списке
```

### Критерии успеха
- ✅ Endpoint `/api/menu/public` возвращает 200 OK
- ✅ Возвращается только страницы с `show_in_menu=true`
- ✅ Сортировка по `menu_position` работает
- ✅ Поле `menu_label` используется (если заполнено) или `title` (если пусто)

---

## **ШАГ 7: Обновить Frontend API Client** ⏳

### Задача
Добавить метод для получения публичного меню.

### Файл: `frontend/api-client.js`

**Найти секцию MENU API и добавить:**

```javascript
// ==========================================
// MENU API
// ==========================================

/**
 * Get public menu items
 * GET /api/menu/public
 * Returns only published + public + show_in_menu=true pages
 * @returns {Promise<Array>} Menu items
 */
async getPublicMenu() {
    const response = await this.request('/api/menu/public', {
        method: 'GET'
    });
    
    if (response.success && Array.isArray(response.data)) {
        return response.data;
    }
    
    return [];
}

// ... existing menu methods (getMenu, createMenuItem, etc.) ...
```

### Критерии успеха
- ✅ Метод `getPublicMenu()` добавлен
- ✅ Возвращает массив пунктов меню
- ✅ Обрабатывает ошибки (возвращает пустой массив)

---

## **ШАГ 8: Обновить UI визуального редактора** ⏳

### Задача
Добавить UI элементы для управления меню в правую панель редактора.

### Файл: `frontend/editor.html`

**Найти секцию с настройками публикации (справа) и добавить:**

```html
<!-- ... existing page settings ... -->

<!-- 🆕 НОВАЯ СЕКЦИЯ: Навигация -->
<div class="settings-section">
    <h3>🌐 Навигация</h3>
    
    <div class="form-group">
        <label class="checkbox-label">
            <input 
                type="checkbox" 
                v-model="pageSettings.showInMenu"
                :disabled="pageStatus !== 'published'"
            >
            <span>Показывать в главном меню</span>
        </label>
        <small class="help-text">
            <span v-if="pageStatus !== 'published'" style="color: #dc3545;">
                ⚠️ Доступно только для опубликованных страниц
            </span>
            <span v-else>
                ✅ Страница будет в верхнем меню сайта (Главная | О нас | Контакты)
            </span>
        </small>
    </div>
    
    <!-- Показываем только если showInMenu = true -->
    <div v-if="pageSettings.showInMenu && pageStatus === 'published'">
        <div class="form-group">
            <label>Порядок в меню</label>
            <input 
                type="number" 
                v-model.number="pageSettings.menuPosition" 
                min="1"
                placeholder="Автоматически"
                class="form-control"
            >
            <small class="help-text">
                Меньшее число = выше в меню (1 = первый пункт). Оставьте пустым для автоматической позиции.
            </small>
        </div>
        
        <div class="form-group">
            <label>Название в меню (опционально)</label>
            <input 
                type="text" 
                v-model="pageSettings.menuLabel" 
                :placeholder="pageTitle || 'Будет использовано название страницы'"
                class="form-control"
                maxlength="50"
            >
            <small class="help-text">
                Если не заполнено, будет: "{{ pageTitle }}"
            </small>
        </div>
        
        <!-- Превью пункта меню -->
        <div class="menu-preview" v-if="pageTitle">
            <strong>Превью в меню:</strong>
            <div class="preview-item">
                📍 {{ pageSettings.menuLabel || pageTitle }}
            </div>
        </div>
    </div>
</div>

<!-- ... rest of settings ... -->
```

**Добавить стили в `frontend/styles.css`:**

```css
/* Menu settings section */
.settings-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e0e0e0;
}

.settings-section:last-child {
    border-bottom: none;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 500;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"]:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.help-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #666;
    line-height: 1.4;
}

.menu-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #008d8d;
}

.preview-item {
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    background: white;
    border-radius: 4px;
    font-weight: 500;
    color: #032A49;
}
```

### Критерии успеха
- ✅ UI элементы добавлены в правую панель
- ✅ Чекбокс "Показывать в меню" работает
- ✅ Поля позиции и названия показываются только если чекбокс включен
- ✅ Превью пункта меню отображается
- ✅ Валидация: чекбокс disabled для черновиков

---

## **ШАГ 9: Обновить логику редактора** ⏳

### Задача
Добавить сохранение/загрузку настроек меню.

### Файл: `frontend/editor.js`

**1. Добавить в data():**

```javascript
data() {
    return {
        // ... existing data ...
        
        pageSettings: {
            showInMenu: false,      // По умолчанию выключено
            menuPosition: null,     // Автоматическая позиция
            menuLabel: null         // Используем title страницы
        }
    }
}
```

**2. Обновить метод `savePage()`:**

```javascript
async savePage() {
    this.debugMsg('========== НАЧАЛО СОХРАНЕНИЯ ==========', 'info');
    
    // ... existing validation ...
    
    // Prepare page data
    const pageData = {
        title: this.pageTitle,
        slug: this.pageSlug,
        type: this.pageType,
        status: this.pageStatus,
        visibility: this.pageVisibility || 'public',
        
        // 🆕 Menu settings (snake_case для backend)
        show_in_menu: this.pageSettings.showInMenu && this.pageStatus === 'published',
        menu_position: this.pageSettings.menuPosition,
        menu_label: this.pageSettings.menuLabel,
        
        // SEO
        seo: {
            meta_title: this.seoMetaTitle,
            meta_description: this.seoMetaDescription,
            meta_keywords: this.seoMetaKeywords
        },
        
        // Blocks
        blocks: this.blocks.map(blockToAPI),
        
        created_by: this.currentUser.id
    };
    
    this.debugMsg('Данные страницы с настройками меню', 'info', pageData);
    
    // ... rest of save logic ...
}
```

**3. Обновить метод `loadPage()`:**

```javascript
async loadPage(pageId) {
    try {
        this.isLoading = true;
        const response = await this.apiClient.getPageById(pageId);
        
        // ... existing loading logic ...
        
        // 🆕 Load menu settings
        this.pageSettings.showInMenu = response.show_in_menu ?? false;
        this.pageSettings.menuPosition = response.menu_position;
        this.pageSettings.menuLabel = response.menu_label;
        
        this.debugMsg('Настройки меню загружены', 'info', this.pageSettings);
        
    } catch (error) {
        this.debugMsg('Ошибка загрузки страницы', 'error', error);
        this.showError('Не удалось загрузить страницу');
    } finally {
        this.isLoading = false;
    }
}
```

**4. Добавить watcher для автоматического отключения меню при смене статуса:**

```javascript
watch: {
    pageStatus(newStatus) {
        // Если статус != published, отключаем показ в меню
        if (newStatus !== 'published') {
            this.pageSettings.showInMenu = false;
            this.debugMsg('Статус изменён на ' + newStatus + ', показ в меню отключен', 'warning');
        }
    }
}
```

### Критерии успеха
- ✅ Настройки меню сохраняются при `savePage()`
- ✅ Настройки меню загружаются при `loadPage()`
- ✅ Watcher отключает меню при смене статуса на draft
- ✅ Debug Panel показывает логи сохранения/загрузки

---

## **ШАГ 10: Тестирование Frontend** ⏳

### Задача
Проверить работу UI в редакторе.

### Сценарии тестирования

**Тест 1: Создание новой страницы с меню**

1. Открыть `http://localhost/healthcare-cms-frontend/editor.html`
2. Создать новую страницу:
   - Название: "О нашем проекте"
   - Slug: автоматически → "o-nashem-proekte"
   - Тип: regular
   - Статус: published
3. В правой панели "Навигация":
   - ✅ Показывать в меню
   - Позиция: 1
   - Название в меню: "О нас"
4. Добавить блок (любой)
5. Нажать "Сохранить"
6. Проверить Debug Panel: должно быть `show_in_menu: true`
7. Проверить в БД:
   ```sql
   SELECT title, show_in_menu, menu_position, menu_label FROM pages WHERE slug = 'o-nashem-proekte';
   ```

**Ожидаемый результат:**
- ✅ show_in_menu = 1
- ✅ menu_position = 1
- ✅ menu_label = "О нас"

---

**Тест 2: Проверка публичного меню**

1. После сохранения страницы из Теста 1
2. Открыть консоль браузера
3. Выполнить:
   ```javascript
   const api = new ApiClient();
   const menu = await api.getPublicMenu();
   console.log(menu);
   ```

**Ожидаемый результат:**
```json
[
  {
    "label": "О нас",
    "url": "/o-nashem-proekte",
    "slug": "o-nashem-proekte",
    "position": 1
  }
]
```

---

**Тест 3: Страница БЕЗ меню**

1. Создать новую страницу:
   - Название: "Статья блога"
   - Статус: published
   - ❌ Показывать в меню (ВЫКЛЮЧЕНО)
2. Сохранить
3. Проверить публичное меню (как в Тесте 2)

**Ожидаемый результат:**
- ✅ В меню только "О нас"
- ✅ "Статья блога" НЕ в меню

---

**Тест 4: Снятие с публикации**

1. Загрузить страницу "О нашем проекте"
2. Изменить статус: published → draft
3. Сохранить
4. Проверить: чекбокс "Показывать в меню" должен автоматически отключиться
5. Проверить публичное меню

**Ожидаемый результат:**
- ✅ Меню пустое (страница снята с публикации)

---

**Тест 5: Редактирование порядка в меню**

1. Создать 3 страницы с меню:
   - "Главная" (позиция: 1)
   - "О нас" (позиция: 2)
   - "Контакты" (позиция: 3)
2. Проверить публичное меню

**Ожидаемый результат:**
```json
[
  {"label": "Главная", "position": 1},
  {"label": "О нас", "position": 2},
  {"label": "Контакты", "position": 3}
]
```

3. Изменить позицию "Контакты" на 1
4. Сохранить
5. Проверить меню снова

**Ожидаемый результат:**
```json
[
  {"label": "Контакты", "position": 1},
  {"label": "Главная", "position": 1},
  {"label": "О нас", "position": 2}
]
```

### Критерии успеха
- ✅ Все 5 тестов проходят успешно
- ✅ UI работает интуитивно
- ✅ Debug Panel показывает корректные логи
- ✅ Публичное меню синхронизируется с настройками страниц

---

## **ШАГ 11: Удалить старый Menu Editor** ⏳

### Задача
Удалить ненужные файлы старого подхода.

### Файлы для удаления

**В workspace:**
```bash
Remove-Item "frontend\menu-editor.html" -Force
Remove-Item "frontend\menu-editor.js" -Force
Remove-Item "frontend\menu-editor.css" -Force
Remove-Item "docs\MENU_EDITOR_PROMPT.md" -Force
```

**В htdocs:**
```bash
Remove-Item "C:\xampp\htdocs\healthcare-cms-frontend\menu-editor.html" -Force
Remove-Item "C:\xampp\htdocs\healthcare-cms-frontend\menu-editor.js" -Force
Remove-Item "C:\xampp\htdocs\healthcare-cms-frontend\menu-editor.css" -Force
```

**Обновить навигацию:**
Удалить ссылку "Меню" из header во всех файлах админки:
- `editor.html`
- `media-library.html`

### Критерии успеха
- ✅ 4 файла удалены из workspace
- ✅ 3 файла удалены из htdocs
- ✅ Ссылка "Меню" удалена из навигации

---

## **ШАГ 12: Копирование в htdocs** ⏳

### Задача
Скопировать все обновлённые файлы.

### Команды

```powershell
# Копировать редактор
Copy-Item "frontend\editor.html" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\editor.js" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\api-client.js" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\styles.css" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force

# Копировать медиабиблиотеку (обновлённая навигация)
Copy-Item "frontend\media-library.html" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force

Write-Host "✅ Все файлы скопированы в htdocs"
```

### Критерии успеха
- ✅ Все файлы скопированы без ошибок

---

## **ШАГ 13: Финальное тестирование** ⏳

### Полный сценарий (end-to-end)

1. **Открыть редактор:** `http://localhost/healthcare-cms-frontend/editor.html`
2. **Создать главную страницу:**
   - Название: "Медицина в Бразилии"
   - Статус: published
   - ✅ Показывать в меню
   - Позиция: 1
   - Название в меню: "Главная"
   - Добавить блок main-screen
3. **Сохранить** → проверить Debug Panel
4. **Создать страницу "О нас":**
   - Статус: published
   - ✅ Показывать в меню
   - Позиция: 2
   - Название в меню: "О проекте"
5. **Создать страницу "Контакты":**
   - Статус: published
   - ✅ Показывать в меню
   - Позиция: 3
6. **Создать статью блога:**
   - Статус: published
   - ❌ Показывать в меню (ВЫКЛЮЧЕНО)
7. **Проверить публичное меню:**
   ```bash
   curl http://localhost/healthcare-cms-backend/public/api/menu/public
   ```

**Ожидаемый результат:**
```json
{
  "success": true,
  "data": [
    {"label": "Главная", "url": "/meditsina-v-brazilii", "position": 1},
    {"label": "О проекте", "url": "/o-nas", "position": 2},
    {"label": "Контакты", "url": "/kontakty", "position": 3}
  ]
}
```

**Статья блога НЕ в меню!**

8. **Снять с публикации "Контакты":**
   - Загрузить страницу → Статус: draft → Сохранить
9. **Проверить меню снова:**
   ```bash
   curl http://localhost/healthcare-cms-backend/public/api/menu/public
   ```

**Ожидаемый результат:**
```json
{
  "success": true,
  "data": [
    {"label": "Главная", "url": "/meditsina-v-brazilii", "position": 1},
    {"label": "О проекте", "url": "/o-nas", "position": 2}
  ]
}
```

**"Контакты" исчезли из меню!**

### Критерии успеха
- ✅ Меню синхронизируется автоматически
- ✅ Статус draft → страница исчезает из меню
- ✅ show_in_menu = false → страница не в меню
- ✅ Позиции работают корректно
- ✅ menu_label используется (или title, если пусто)

---

## 📊 ЧЕКЛИСТ ЗАВЕРШЕНИЯ

### Backend
- [ ] Миграция SQL создана и выполнена
- [ ] Page.php обновлён (+3 поля)
- [ ] PageRepository обновлён (+метод getMenuPages, обновлены save/update)
- [ ] MenuController создан
- [ ] Роутинг обновлён
- [ ] Endpoint `/api/menu/public` работает

### Frontend
- [ ] api-client.js обновлён (+метод getPublicMenu)
- [ ] editor.html обновлён (+UI настроек меню)
- [ ] editor.js обновлён (+логика сохранения/загрузки)
- [ ] styles.css обновлён (+стили для menu settings)

### Cleanup
- [ ] Menu Editor удалён (4 файла)
- [ ] Навигация обновлена (удалена ссылка "Меню")
- [ ] Все файлы скопированы в htdocs

### Тестирование
- [ ] Backend endpoint работает (curl тесты)
- [ ] UI тесты пройдены (5 сценариев)
- [ ] End-to-end тест пройден
- [ ] Debug Panel показывает корректные логи

### Документация
- [ ] PROJECT_STATUS.md обновлён
- [ ] CMS_DEVELOPMENT_PLAN.md обновлён (отметить автоматическое меню как ✅)

---

## 🎯 КРИТЕРИИ УСПЕХА

**Для клиента ("продавца мандаринов"):**
1. ✅ Создал страницу → Опубликовал → Она в меню (один клик)
2. ✅ Снял с публикации → Исчезла из меню (автоматически)
3. ✅ НЕ НУЖНО управлять меню отдельно
4. ✅ Интуитивно понятный UI (чекбокс + 2 поля)

**Технические:**
1. ✅ Меню синхронизируется с состоянием страниц
2. ✅ Производительность: индекс `idx_pages_menu` для быстрых запросов
3. ✅ Обратная совместимость: старые endpoints не сломаны
4. ✅ Логирование: все операции в Debug Panel

---

## 🚀 НАЧАЛО РАБОТЫ

1. Прочитать весь промпт
2. Начать с Шага 1 (Миграция БД)
3. Выполнять шаги последовательно
4. Проверять критерии успеха после каждого шага
5. После Шага 13 — отметить в чеклисте

**Удачи!** 🎉

---

**Дата создания:** 5 октября 2025  
**Автор:** Claude + Anna  
**Версия:** 1.0
