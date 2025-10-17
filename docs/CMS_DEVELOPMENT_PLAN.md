# Healthcare Brazil CMS - План разработки

**Обновлено:** 4 октября 2025 (с учётом дебага API интеграции)
**Автор:** Claude + Anna

> ⚠️ **ВАЖНО:** Этот план обновлён на основе реального опыта отладки. См. документ `ИСТОРИЯ_ДЕБАГА_API_ИНТЕГРАЦИИ.md` для деталей.

---

## Архитектура: Clean Architecture

### 1. Entities (Неизменяемое ядро)

#### 1.1. Naming Convention & Data Contracts ⭐ NEW

**Правила именования полей:**
- **PHP Backend (Database, API):** snake_case (`custom_name`, `created_at`)
- **JavaScript Frontend:** camelCase (`customName`, `createdAt`)
- **URL slugs:** kebab-case (`my-page-slug`)

**Обязательно использовать mappers для конвертации между форматами!**

#### 1.2. Core Entities

```javascript
Page {
  id: string                    // UUID v4
  title: string                 // min 1, max 255 chars
  slug: string                  // pattern: /^[a-z0-9-]+$/ (только lowercase latin, numbers, hyphens)
  status: PageStatus            // ENUM: 'draft' | 'published' | 'archived' | 'scheduled'
  visibility: PageVisibility    // ⭐ NEW: ENUM: 'public' | 'unlisted' | 'private'
  type: PageType                // ENUM: 'regular' | 'article' | 'guide' | 'collection'
  seo: {
    metaTitle: string           // max 255 chars
    metaDescription: string     // max 500 chars
    metaKeywords: string        // max 255 chars
  }
  tracking: {
    pageSpecificCode: string    // Счётчики только для этой страницы
  }
  blocks: Block[]               // Связь 1:N с ON DELETE CASCADE
  createdAt: Date
  updatedAt: Date
  publishedAt: Date | null      // Устанавливается при publishPage()
  archivedAt: Date | null       // ⭐ NEW: Устанавливается при archivePage()
  createdBy: string             // UUID userId
  lastEditedBy: string | null   // ⭐ NEW: UUID userId, обновляется при каждом updatePage()
}

Block {
  id: string                    // UUID v4
  pageId: string                // UUID (foreign key)
  type: BlockType               // ENUM: см. ниже
  position: number              // >= 0, integer, уникален в рамках pageId
  data: object                  // JSON, любые данные блока
  isEditable: boolean           // ⭐ NEW: можно ли редактировать inline
  editableFields: string[]      // ⭐ NEW: какие поля можно редактировать ['data.title', 'data.text', 'data.image']
}

// ⭐ ВАЖНО: Явные ENUM значения (предотвращает ошибку "type: 'page' не валиден")
enum BlockType {
  MAIN_SCREEN = 'main-screen',
  TEXT_BLOCK = 'text-block',
  SERVICE_CARDS = 'service-cards',
  ARTICLE_CARDS = 'article-cards',
  ABOUT_SECTION = 'about-section',
  PAGE_HEADER = 'page-header',
  CTA_SECTION = 'cta-section',
  FAQ_BLOCK = 'faq-block'
}

enum PageType {
  REGULAR = 'regular',
  ARTICLE = 'article',
  GUIDE = 'guide',
  COLLECTION = 'collection'
}

enum PageStatus {
  DRAFT = 'draft',
  PUBLISHED = 'published',
  ARCHIVED = 'archived',
  SCHEDULED = 'scheduled'         // ⭐ NEW: Запланировано к публикации
}

enum PageVisibility {              // ⭐ NEW
  PUBLIC = 'public',               // Видно всем, в меню, в поиске
  UNLISTED = 'unlisted',           // Доступно только по прямой ссылке
  PRIVATE = 'private'              // Доступно только авторизованным
}

User {
  id: string                    // UUID v4
  username: string              // unique, min 3, max 50 chars
  email: string                 // unique, valid email format
  passwordHash: string          // bcrypt hash
  role: UserRole                // ENUM: 'super_admin' | 'admin' | 'editor'
  createdAt: Date
  lastLoginAt: Date | null
  isActive: boolean             // default: true
}

Menu {
  id: string
  items: MenuItem[]
}

MenuItem {
  id: string
  label: string
  pageId: string
  position: number
  parentId: string | null       // Для вложенных меню
}

MediaFile {
  id: string
  filename: string
  url: string
  type: 'image' | 'svg'
  size: number
  uploadedBy: string (userId)
  uploadedAt: Date
}

GlobalSettings {
  id: string
  siteName: string
  logo: string (url)
  favicon: string (url)
  tracking: {
    globalCode: string          // Google Analytics, Facebook Pixel и т.д.
  }
  widgets: {
    customCode: string          // Любые виджеты (чат, соц. кнопки и т.д.)
  }
}
```

#### 1.3. Validation Rules ⭐ NEW

**Для каждого поля - явные правила валидации:**

```
Page:
  title:
    - required: true
    - min_length: 1
    - max_length: 255
    - type: string

  slug:
    - required: true
    - pattern: /^[a-z0-9-]+$/
    - unique: true (в рамках всей таблицы)
    - auto_generate: transliterate(title) если пусто
    - message: "Slug must contain only lowercase letters, numbers, and hyphens"

  type:
    - required: true
    - enum: ['regular', 'article', 'guide', 'collection']
    - message: "Type must be one of: regular, article, guide, collection"

  createdBy:
    - required: true
    - format: UUID v4
    - exists: в таблице users
    - message: "CreatedBy must be a valid UUID of existing user"

Block:
  type:
    - required: true
    - enum: BlockType values
    - message: "Type must be one of: main-screen, text-block, service-cards, etc."

  position:
    - required: true
    - type: integer
    - min: 0
    - unique: в рамках pageId

  custom_name:
    - optional: true
    - max_length: 255
    - NOTE: snake_case в API, camelCase на фронтенде
```

---

### 2. Use Cases (Бизнес-логика)

#### Pages
- `createPage(pageData)` - создать страницу **+ сохранить все blocks в одной транзакции** ⭐
- `updatePage(id, pageData)` - обновить страницу **+ заменить blocks (DELETE старые → INSERT новые)** ⭐
- `deletePage(id)` - удалить страницу **+ каскадно удалить blocks** ⭐
- `getPageBySlug(slug)` - получить страницу **+ все blocks отсортированные по position** ⭐
- `getPageById(id)` - получить страницу по ID **+ все blocks** ⭐
- `getAllPages()` - список всех страниц **(БЕЗ blocks для производительности)** ⭐
- `publishPage(id)` - опубликовать страницу **+ установить publishedAt, status = 'published'** ⭐
- `unpublishPage(id)` - снять с публикации **+ сбросить publishedAt, status = 'draft'** ⭐
- `archivePage(id, archivedBy)` - ⭐ NEW: скрыть страницу (status = 'archived', установить archivedAt)
- `restorePage(id, restoredBy)` - ⭐ NEW: восстановить из архива (status = 'draft', archivedAt = null)
- `changePageVisibility(id, visibility, changedBy)` - ⭐ NEW: изменить видимость (public/unlisted/private)
- `getPreviewUrl(id)` - ⭐ NEW: получить preview URL с JWT токеном для неопубликованных страниц

**⚠️ КРИТИЧЕСКИ ВАЖНО:**
- При `createPage()` и `updatePage()` **ОБЯЗАТЕЛЬНО обрабатывать blocks**
- Использовать транзакции для атомарности
- Валидировать все поля перед сохранением
- Возвращать детальные ошибки валидации

#### Blocks
- `createBlock(pageId, blockData)` - создать блок
- `updateBlock(blockId, data)` - обновить блок
- `updateBlockField(blockId, fieldPath, newValue, editedBy)` - ⭐ NEW: обновить одно поле блока (для inline-редактирования)
- `deleteBlock(blockId)` - удалить блок
- `reorderBlocks(pageId, blockIds)` - изменить порядок блоков
- `updateBlockMedia(blockId, mediaType, mediaUrl)` - обновить картинку/SVG в блоке

**⭐ ВАЖНО:** При работе с блоками:
- Всегда проверять существование pageId
- Валидировать position (>= 0, уникален в рамках pageId)
- В `updatePage()`: сначала удалить все старые blocks, потом создать новые
- В `updateBlockField()`: проверять что fieldPath входит в editableFields

#### Users
- `createUser(userData)` - создать пользователя (только super_admin)
- `updateUser(id, userData)` - обновить пользователя (только super_admin)
- `deleteUser(id)` - удалить пользователя (только super_admin)
- `changeUserRole(id, newRole)` - изменить роль (только super_admin)
- `activateUser(id)` / `deactivateUser(id)` - активировать/деактивировать
- `getAllUsers()` - список пользователей (только super_admin)

#### Auth
- `login(username, password)` - вход **+ вернуть { token, user }** ⭐
- `logout(token)` - выход
- `getCurrentUser(token)` - получить текущего пользователя **+ обновить lastLoginAt** ⭐
- `checkPermission(userId, action)` - проверка прав

#### Media
- `uploadFile(file, userId)` - загрузить файл
- `deleteFile(id)` - удалить файл
- `getAllFiles()` - список всех файлов
- `getFilesByType(type)` - получить файлы по типу

#### Menu
- `createMenuItem(menuItemData)` - создать пункт меню
- `updateMenuItem(id, data)` - обновить пункт меню
- `deleteMenuItem(id)` - удалить пункт меню
- `reorderMenuItems(itemIds)` - изменить порядок

#### Global Settings
- `updateGlobalSettings(settings)` - обновить глобальные настройки
- `getGlobalSettings()` - получить настройки

#### 2.1. Error Handling Standards ⭐ NEW

**Каждый Use Case должен возвращать детальные ошибки:**

```javascript
// Успешный ответ
{
  "success": true,
  "data": { /* result */ }
}

// Ошибка валидации
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Slug must contain only lowercase letters, numbers, and hyphens",
    "details": {
      "field": "slug",
      "value": "новая-страница",
      "constraint": "Must match pattern /^[a-z0-9-]+$/",
      "received_type": "string with cyrillic"
    }
  }
}

// Ошибка конфликта
{
  "success": false,
  "error": {
    "code": "CONFLICT",
    "message": "Slug 'about' already exists",
    "details": {
      "field": "slug",
      "value": "about",
      "existing_page_id": "abc-123"
    }
  }
}
```

**Коды ошибок:**
- `400 VALIDATION_ERROR` - данные не прошли валидацию
- `401 UNAUTHORIZED` - не авторизован
- `403 FORBIDDEN` - нет прав доступа
- `404 NOT_FOUND` - ресурс не найден
- `409 CONFLICT` - конфликт (например, slug уже существует)
- `500 SERVER_ERROR` - внутренняя ошибка сервера

---

### 3. Interface Adapters (Репозитории)

#### Repositories (связь с БД через API)
- `PageRepository` - работа со страницами
- `BlockRepository` - работа с блоками **+ каскадное удаление при deletePage()** ⭐
- `UserRepository` - работа с пользователями
- `MenuRepository` - работа с меню
- `MediaRepository` - работа с медиафайлами
- `SettingsRepository` - работа с настройками

#### 3.1. API Endpoints (PHP) ⭐ UPDATED

```
Auth:
POST   /api/auth/login         → { token, user }
POST   /api/auth/logout        → { success }
GET    /api/auth/me            → user object (NOT { user: {...} })  ⭐ ВАЖНО!

Pages:
GET    /api/pages              → [{ id, title, slug, status, type, createdAt }] (БЕЗ blocks)
GET    /api/pages/:id          → { page, blocks }  ⭐ С блоками!
GET    /api/pages/slug/:slug   → { page, blocks }  ⭐ С блоками!
POST   /api/pages              → { success, pageId }  ⭐ + сохранить blocks!
PUT    /api/pages/:id          → { success }  ⭐ + заменить blocks!
PUT    /api/pages/:id/publish  → { success }  ⭐ Отдельный endpoint
PUT    /api/pages/:id/archive  → { success }  ⭐ NEW: Скрыть страницу
PUT    /api/pages/:id/restore  → { success }  ⭐ NEW: Восстановить из архива
PUT    /api/pages/:id/visibility → { success }  ⭐ NEW: Изменить видимость (body: { visibility })
GET    /api/pages/:id/preview-url → { previewUrl, token }  ⭐ NEW: Получить preview URL
DELETE /api/pages/:id          → { success }  ⭐ + каскадно удалить blocks

Blocks (опционально, если нужен отдельный CRUD):
POST   /api/blocks             → { success, blockId }
PUT    /api/blocks/:id         → { success }
PATCH  /api/blocks/:id/field   → { success }  ⭐ NEW: Обновить одно поле (body: { fieldPath, value })
DELETE /api/blocks/:id         → { success }
PUT    /api/blocks/reorder     → { success }

Users:
GET    /api/users              → [users] (super_admin only)
POST   /api/users              → { success, userId } (super_admin only)
PUT    /api/users/:id          → { success } (super_admin only)
DELETE /api/users/:id          → { success } (super_admin only)

Media:
GET    /api/media              → [files]
POST   /api/media/upload       → { success, fileUrl, fileId }
DELETE /api/media/:id          → { success }

Menu:
GET    /api/menu               → menu object
POST   /api/menu               → { success, menuItemId }
PUT    /api/menu/:id           → { success }
DELETE /api/menu/:id           → { success }

Settings:
GET    /api/settings           → settings object
PUT    /api/settings           → { success }
```

#### 3.2. Backend Controller Standards ⭐ NEW

**Каждый контроллер должен:**

1. Логировать входящий запрос (ApiLogger::logRequest())
2. Валидировать JSON
3. Вызывать Use Case
4. **Сохранять связанные данные (например, blocks вместе с page)** ⭐
5. Обрабатывать исключения с детальными сообщениями
6. Логировать ответ (ApiLogger::logResponse())

**Пример правильного контроллера:**

```php
// PageController.php
public function create(): void
{
    try {
        ApiLogger::logRequest();

        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();

        // Создание страницы
        $useCase = new CreatePage($pageRepository);
        $page = $useCase->execute($data);

        // ⭐ КРИТИЧЕСКИ ВАЖНО: Сохранение блоков (если есть)
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as $index => $blockData) {
                $block = new Block(
                    id: Uuid::uuid4()->toString(),
                    pageId: $page->getId(),
                    type: $blockData['type'] ?? 'text-block',
                    position: $blockData['position'] ?? $index,
                    data: $blockData['data'] ?? [],
                    customName: $blockData['custom_name'] ?? null  // ⭐ snake_case!
                );

                $blockRepository->save($block);
            }
        }

        $response = ['success' => true, 'pageId' => $page->getId()];
        ApiLogger::logResponse(201, $response);
        $this->jsonResponse($response, 201);

    } catch (InvalidArgumentException $e) {
        $error = [
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage()
            ]
        ];
        ApiLogger::logResponse(400, $error);
        $this->jsonResponse($error, 400);

    } catch (\Exception $e) {
        $error = [
            'success' => false,
            'error' => [
                'code' => 'SERVER_ERROR',
                'message' => 'Internal server error',
                'details' => $e->getMessage()
            ]
        ];
        error_log("PageController::create() error: " . $e->getMessage());
        ApiLogger::logResponse(500, $error);
        $this->jsonResponse($error, 500);
    }
}
```

#### 3.3. API Request/Response Logging ⭐ NEW

**Backend (PHP) - Middleware:**

```php
// Middleware: ApiLogger.php
class ApiLogger {
    public static function logRequest() {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'headers' => getallheaders(),
            'body' => file_get_contents('php://input'),
            'ip' => $_SERVER['REMOTE_ADDR']
        ];

        file_put_contents(
            __DIR__ . '/../../logs/api-requests.log',
            json_encode($data) . PHP_EOL,
            FILE_APPEND
        );
    }

    public static function logResponse($statusCode, $responseData) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $statusCode,
            'data' => $responseData
        ];

        file_put_contents(
            __DIR__ . '/../../logs/api-responses.log',
            json_encode($data) . PHP_EOL,
            FILE_APPEND
        );
    }
}
```

---

### 4. Frameworks & Drivers (UI/Внешний слой)

#### 4.1. Vue.js Architecture Standards ⭐ NEW

**Правила для Vue компонентов:**

1. **Все интерактивные элементы ВНУТРИ `<div id="app">`** ⭐
   - Login modal должен быть ВНУТРИ, иначе Vue не видит его
   - Debug Panel
   - Notification toasts
   - Любые элементы с v-if, v-for, {{ }}

2. **Persistence через localStorage:** ⭐
   ```javascript
   // При login
   localStorage.setItem('cms_current_user', JSON.stringify(user));
   localStorage.setItem('cms_auth_token', token);

   // При mount
   const savedUser = localStorage.getItem('cms_current_user');
   if (savedUser) {
       this.currentUser = JSON.parse(savedUser);
   }

   // Проверка актуальности через API
   const apiUser = await this.apiClient.getCurrentUser();
   this.currentUser = apiUser;

   // При logout
   localStorage.removeItem('cms_current_user');
   localStorage.removeItem('cms_auth_token');
   ```

3. **Обработка Vue Proxy объектов:** ⭐
   ```javascript
   // НЕПРАВИЛЬНО
   data: block.data  // Это Vue Proxy!

   // ПРАВИЛЬНО
   data: JSON.parse(JSON.stringify(block.data))  // Plain object
   // ИЛИ использовать utility
   data: toPlainObject(block.data)
   ```

4. **Использование mappers:** ⭐
   ```javascript
   import { blockToAPI, blockFromAPI, generateSlug } from './utils/mappers.js';

   // При отправке
   const pageData = {
       blocks: this.blocks.map(blockToAPI)  // camelCase → snake_case
   };

   // При получении
   this.blocks = apiBlocks.map(blockFromAPI);  // snake_case → camelCase
   ```

#### Админка (`/admin`)

**Страницы:**
1. `/admin` - вход в систему ⭐ Login modal внутри #app
2. `/admin/pages` - список всех страниц
3. `/admin/editor/:id` - визуальный редактор страницы ⭐ + Debug Panel
4. `/admin/media` - медиа-библиотека
5. `/admin/menu` - редактор меню (удалить: управление меню выполняется внутри визуального редактора)
6. `/admin/users` - управление пользователями (super_admin only)
7. `/admin/settings` - глобальные настройки

**Компоненты редактора:**
- Библиотека блоков (слева)
- Центральное поле (drag-n-drop блоков)
- Панель настроек (справа): SEO, tracking codes
- Верхняя панель: Save, Export HTML, Media Library
- **Debug Panel (правый нижний угол)** ⭐ NEW
- Inline-редактирование текста и картинок
- Preview блоков при наведении

#### 4.2. Frontend API Client Standards ⭐ NEW

**api-client.js должен:**

1. Логировать все запросы и ответы
2. Обрабатывать все типы ошибок с деталями
3. Конвертировать данные через mappers
4. Показывать прогресс длительных операций

```javascript
// api-client.js
class ApiClient {
    async request(endpoint, options = {}) {
        const requestId = ++this.requestId;

        // ⭐ Детальное логирование запроса
        console.log(`[${requestId}] 📤 REQUEST:`, {
            url: `${API_BASE_URL}${endpoint}`,
            method: config.method || 'GET',
            body: config.body
        });

        const response = await fetch(url, config);
        const data = await response.json();

        // ⭐ Детальное логирование ответа
        console.log(`[${requestId}] 📥 RESPONSE:`, {
            status: response.status,
            data
        });

        // ⭐ КРИТИЧЕСКИ ВАЖНО: Детальная обработка ошибок
        if (!response.ok) {
            const errorDetails = {
                status: response.status,
                code: data.error?.code || 'UNKNOWN',
                message: data.error?.message || data.message || 'Unknown error',
                details: data.error?.details || data.details || null
            };

            console.error(`[${requestId}] ❌ ERROR:`, errorDetails);

            let errorMessage = `HTTP ${response.status}: ${errorDetails.message}`;
            if (errorDetails.details) {
                errorMessage += `\n${JSON.stringify(errorDetails.details, null, 2)}`;
            }

            const error = new Error(errorMessage);
            error.details = errorDetails;
            throw error;
        }

        return data;
    }

    // ⭐ getCurrentUser возвращает user напрямую, НЕ { user: {...} }
    async getCurrentUser() {
        const data = await this.request('/api/auth/me');
        this.currentUser = data;  // НЕ data.user!
        return data;
    }

    // ⭐ Использовать mappers
    async createPage(pageData) {
        const apiData = {
            ...pageData,
            blocks: pageData.blocks?.map(blockToAPI) || []
        };

        return await this.request('/api/pages', {
            method: 'POST',
            body: JSON.stringify(apiData)
        });
    }
}
```

#### 4.3. Debug Tools (ОБЯЗАТЕЛЬНО) ⭐ NEW

**Debug Panel - обязательный компонент:**

1. **Визуальная панель с логами**
   - Цветовая кодировка (info=синий, success=зелёный, warning=жёлтый, error=красный)
   - Временные метки
   - JSON данные в readable формате
   - Кнопка очистки, кнопка скрытия/показа

2. **Метод debugMsg():**
   ```javascript
   debugMsg(message, type = 'info', data = null) {
       const timestamp = new Date().toLocaleTimeString('ru-RU');

       this.debugLog.push({
           time: timestamp,
           message,
           type,  // 'info' | 'success' | 'warning' | 'error'
           data: data ? JSON.stringify(data, null, 2) : null
       });

       // Дублируем в консоль
       const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
       console[consoleMethod](`[${timestamp}] ${message}`, data || '');
   }
   ```

3. **Интеграция в критические методы:**
   ```javascript
   async savePage() {
       this.debugMsg('========== НАЧАЛО СОХРАНЕНИЯ ==========', 'info');

       this.debugMsg('Авторизация проверена', 'success', {
           userId: this.currentUser.id
       });

       const pageData = { /* ... */ };
       this.debugMsg('Данные страницы', 'info', pageData);

       try {
           const response = await this.apiClient.createPage(pageData);
           this.debugMsg('Страница сохранена', 'success', response);
       } catch (error) {
           this.debugMsg('ОШИБКА', 'error', {
               message: error.message,
               details: error.details
           });
       }
   }
   ```

#### 4.4. Slug Generation Standards ⭐ NEW

**Обязательно использовать транслитерацию:**

```javascript
// utils/mappers.js
export function transliterate(text) {
    const map = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
        'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
        // Uppercase
        'А': 'A', 'Б': 'B', 'В': 'V', /* ... */
    };

    return text.split('').map(c => map[c] || c).join('');
}

export function generateSlug(title) {
    return transliterate(title)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// В компоненте
watch: {
    pageTitle(newTitle) {
        this.pageSlug = generateSlug(newTitle);
    }
}
```

---

## План разработки

### Этап 0: Подготовка (НОВЫЙ ЭТАП - ПЕРЕД ВСЕМИ) ⭐

**0.1. Документация API Contract**
- [ ] Создать файл `API_CONTRACT.md` с полным описанием всех endpoints
- [ ] Указать request/response форматы для каждого endpoint
- [ ] Документировать все enum значения (PageType, BlockType, PageStatus)
- [ ] Указать правила валидации для всех полей
- [ ] Примеры успешных и ошибочных запросов

**0.2. Создание Utility библиотек**
- [ ] `utils/mappers.js` - конвертация данных между frontend/backend
  - [ ] `toPlainObject()` - Vue Proxy → Plain Object
  - [ ] `blockToAPI()` / `blockFromAPI()` - camelCase ↔ snake_case
  - [ ] `transliterate()` - кириллица → латиница
  - [ ] `generateSlug()` - title → slug с транслитерацией
- [ ] `utils/validators.js` - валидация данных на фронтенде
  - [ ] `validateSlug()` - проверка pattern /^[a-z0-9-]+$/
  - [ ] `validateEmail()`
  - [ ] `validateUUID()`

**0.3. Настройка Debug Tools**
- [ ] Создать компонент Debug Panel (HTML + CSS)
- [ ] Реализовать метод `debugMsg(message, type, data)`
- [ ] Настроить backend logging (ApiLogger middleware)
- [ ] Создать директории для логов (api-requests.log, api-responses.log, errors.log)

### Этап 1: Доработка визуального редактора
- [x] Редактор статей с Quill.js
- [x] Drag-n-drop блоков из библиотеки
- [x] Drag-n-drop для сортировки блоков
- [x] Preview блоков при наведении
- [x] Упрощение блока с ботом
- [x] Замена эмодзи на SVG-иконки
- [x] **Debug Panel интеграция** ⭐
- [x] **API Client с детальной обработкой ошибок** ⭐

### Этап 2: База данных (MySQL)
- [x] Создать новую БД `healthcare_cms`
- [x] Таблицы: `pages`, `blocks`, `users`, `sessions`, `menu`, `media`, `settings`
- [x] **Связи между таблицами с ON DELETE CASCADE для blocks** ⭐
- [x] Индексы и оптимизация
- [ ] **Создать файл SEED_DATA.sql с тестовыми данными** ⭐

### Этап 3: Backend API (REST) ✅ ЗАВЕРШЁН
**Дата завершения:** 5 октября 2025  
**Прогресс:** 100%

- [x] Структура проекта (Clean Architecture)
- [x] **ApiLogger middleware для логирования** ⭐
- [x] **JSON Response Standardization** (success/error format)
- [x] Endpoints для Pages **+ сохранение blocks** ⭐
- [x] Endpoints для Auth (Login/Logout/Me)
- [x] Endpoints для Users (CRUD)
- [x] Endpoints для Media (upload, delete, list)
- [x] Endpoints для Menu (CRUD + reorder)
- [x] Endpoints для Settings (GET, PUT)
- [x] Middleware для проверки прав доступа
- [x] **Namespace Migration** (Healthcare\CMS\... → чистые namespaces)
- [x] **Логирование** (api-requests.log, api-responses.log, errors.log)
- [x] **Тестирование** (все endpoints проверены через curl)

**Итого:** 
- ✅ 24 работающих endpoints
- ✅ Централизованное логирование в JSON формате
- ✅ Единый формат ответов API
- ✅ Clean Architecture соблюдена
- ✅ Документация: API_ENDPOINTS_CHEATSHEET.md

### Этап 4: Frontend - Админка
- [x] Страница входа (`/admin`) **+ Login modal внутри #app** ⭐
- [x] **Персистентность currentUser через localStorage** ⭐
- [ ] Список страниц (`/admin/pages`)
- [x] Интеграция редактора с API **+ использование mappers** ⭐
- [x] **Debug Panel на странице редактора** ⭐
- [x] **Автоматическая генерация slug с транслитерацией** ⭐
- [ ] Переименование блоков (customName с сохранением в БД)
- [ ] SEO-поля в правой панели
- [ ] Поля для tracking codes (глобальные + страничные)
- [ ] Медиа-библиотека (`/admin/media`)
// - [ ] Редактор меню (`/admin/menu`)  (Убрано — меню редактируется в визуальном редакторе)
- [ ] Управление пользователями (`/admin/users`)
- [ ] Глобальные настройки (`/admin/settings`)
- [ ] Глобальные настройки (`/admin/settings`)
- [ ] Заверстать все страницы из прототипа и ввести их в CMS (чтобы при деплое был полный сайт)

### Этап 5: Инлайн-редактирование и медиа
**СЛОЙ 1 (Entities):**
- [ ] Обновить таблицу `pages`: добавить `visibility`, `archived_at`, `last_edited_by`
- [ ] Обновить таблицу `blocks`: добавить `is_editable`, `editable_fields`
- [ ] Создать миграцию SQL для новых полей
- [ ] Обновить Entity Page (PHP): новые поля + validation
- [ ] Обновить Entity Block (PHP): новые поля + validation

**СЛОЙ 2 (Use Cases):**
- [ ] Создать Use Case `ArchivePage` (status = 'archived', archivedAt = now)
- [ ] Создать Use Case `RestorePage` (status = 'draft', archivedAt = null)
- [ ] Создать Use Case `ChangePageVisibility` (public/unlisted/private)
- [ ] Создать Use Case `GetPreviewUrl` (генерация JWT токена для preview)
- [ ] Создать Use Case `UpdateBlockField` (обновление одного поля блока)
- [ ] Обновить Use Case `UpdatePage` (добавить lastEditedBy)
- [ ] Написать unit тесты для новых Use Cases

**СЛОЙ 3 (API Endpoints):**
- [ ] Backend: Endpoint `PUT /api/pages/:id/archive`
- [ ] Backend: Endpoint `PUT /api/pages/:id/restore`
- [ ] Backend: Endpoint `PUT /api/pages/:id/visibility`
- [ ] Backend: Endpoint `GET /api/pages/:id/preview-url`
- [ ] Backend: Endpoint `PATCH /api/blocks/:id/field`
- [ ] Frontend: api-client.js методы (archivePage, restorePage, changeVisibility, getPreviewUrl, updateBlockField)

**СЛОЙ 4 (UI - Inline редактирование):**
- [ ] Реализовать `setupInlineEditing()` в editor.js
- [ ] Добавить `[data-editable]` атрибуты в блоки
- [ ] Реализовать contenteditable для текстовых полей
- [ ] Реализовать debounce автосохранение (3 сек)
- [ ] Добавить CSS стили для подсветки редактируемых элементов
- [ ] Inline-замена картинок (кнопка "📷 Изменить")
- [ ] Обёртка `wrapImageWithEditButton()` для всех `<img>`
- [ ] Временное решение: file input для загрузки картинок

**СЛОЙ 4 (UI - Управление страницами):**
- [ ] Обновить toolbar: кнопка "← К списку страниц"
- [ ] Добавить breadcrumbs (Все страницы › Редактирование › "Название")
- [ ] Dropdown "Видимость" (Публичная / По ссылке / Приватная)
- [ ] Кнопка "👁️ Предпросмотр"
- [ ] Dropdown "⋮ Дополнительные действия" (Скрыть / Удалить)
- [ ] Индикаторы сохранения ("💾 Сохранение..." / "✅ Сохранено")
- [ ] Методы: archivePage(), deletePage(), changeVisibility(), openPreview(), goToPagesList()

**СЛОЙ 4 (Preview страница):**
- [ ] Создать `preview.html` (рендеринг без inline-редактирования)
- [ ] Backend: PreviewController с проверкой JWT токена
- [ ] Кнопка "Вернуться к редактированию"
- [ ] Индикатор "Режим предпросмотра"

**Интеграция медиа-библиотеки:**
- [ ] Интеграция медиа-библиотеки в редактор (вместо file input)
- [ ] Редактирование SVG-кода иконок
- [ ] Удаление контейнеров внутри блоков

**Время:** 10-12 дней

### Этап 6: Frontend - Публичный сайт + Гибридная архитектура ⭐ ОБНОВЛЕНО
**См. подробную документацию:** `HYBRID_ARCHITECTURE_PLAN.md`

**6.1. Domain Layer (Entities)**
- [ ] Создать Entity: `StaticTemplate` (slug, filePath, title, suggestedType, pageId)
- [ ] Создать ValueObject: `TemplateMetadata` (title, description, keywords, detectedBlocks)
- [ ] Обновить Entity `Page`: добавить поле `sourceTemplateSlug`
- [ ] Создать миграцию: `005_add_source_template_to_pages.sql`

**6.2. Application Layer (Use Cases)**
- [ ] Use Case: `RenderStaticTemplate` - отображение статического шаблона
- [ ] Use Case: `ImportStaticTemplate` - импорт шаблона в CMS с парсингом HTML
- [ ] Use Case: `GetAllStaticTemplates` - список доступных шаблонов
- [ ] Обновить Use Case `GetPageWithBlocks` - без изменений (работает только с БД)

**6.3. Interface Adapters (Repositories)**
- [ ] Repository Interface: `StaticTemplateRepositoryInterface`
- [ ] Repository Implementation: `FileSystemStaticTemplateRepository`
  - Хранит TEMPLATE_MAP (slug → файл + метаданные)
  - Кэширует импортированные шаблоны в `.imported_templates.json`
- [ ] Infrastructure Service: `HtmlTemplateParser`
  - Парсит HTML через DOMDocument
  - Извлекает метаданные (title, meta tags)
  - Определяет типы блоков (hero → main-screen, services → service-cards)

**6.4. Presentation Layer (Controllers)**
- [x] Модифицировать `PublicPageController::show()`:
  - СТРАТЕГИЯ 1: Попытка загрузить из БД (GetPageWithBlocks)
  - СТРАТЕГИЯ 2: Fallback к статическому шаблону (RenderStaticTemplate)
  - СТРАТЕГИЯ 3: Отдать 404
- [ ] Создать `TemplateController`:
  - `GET /api/templates` - список всех шаблонов
  - `POST /api/templates/{slug}/import` - импорт шаблона в CMS
- [ ] Обновить роутинг в `backend/public/index.php`

**6.5. Frontend UI**
- [x] Template Manager UI (`frontend/template-manager.html`)
- [ ] Интеграция с новыми API endpoints
- [ ] Обновить `api-client.js`: добавить методы `getAllTemplates()` и `importTemplate()`

**6.6. Публичный сайт**
- [x] Роутинг по slug (PHP с fallback к статическим шаблонам)
- [x] Рендеринг страниц из БД (если есть) или из HTML (если нет)
- [ ] SEO meta-теги (из БД или из HTML)
- [ ] Меню навигации (динамическое из БД + статические страницы)
- [ ] Глобальные tracking codes
- [ ] Виджеты из настроек

**Время:** 3-4 дня

### Этап 7: Деплой на Ubuntu
- [ ] Настройка nginx или Apache
- [ ] Перенос БД
- [ ] Настройка PHP
- [ ] SSL-сертификат
- [ ] Резервное копирование

### Этап 8: Тестирование и QA (НОВЫЙ ЭТАП) ⭐

**8.1. Unit тесты**
- [ ] Тесты для utils/mappers.js (transliterate, generateSlug, blockToAPI)
- [ ] Тесты для utils/validators.js
- [ ] Тесты для generateSlug() с кириллицей

**8.2. Integration тесты**
- [ ] Тест полного цикла: создание страницы → сохранение → загрузка
- [ ] Тест авторизации: login → reload → still authenticated
- [ ] Тест ошибок: невалидный slug → получаем детальную ошибку 400
- [ ] Тест сохранения blocks вместе с page
- [ ] Тест каскадного удаления blocks при delete page

**8.3. Checklist перед деплоем**
- [ ] Все logs работают (frontend console, backend log files)
- [ ] Debug Panel доступен в админке (скрыт для production)
- [ ] Ошибки API возвращают детальные сообщения
- [ ] Транслитерация slug работает для всех кириллических символов
- [ ] currentUser персистит при перезагрузке страницы
- [ ] Blocks сохраняются вместе с Page
- [ ] ON DELETE CASCADE работает для blocks

**8.4. Подготовка к передаче клиенту** ⭐ NEW
- [ ] **Удалить/минимизировать логирование (Security & Performance):**
  - [ ] Frontend: Удалить все `console.log()` из production build
  - [ ] Frontend: Удалить Debug Panel или ограничить доступ только для super_admin
  - [ ] Backend: Отключить детальное логирование запросов (или логировать только ошибки)
  - [ ] Backend: Удалить вывод stack trace в production ошибках
  - [ ] Backend: Убрать логирование sensitive данных (пароли, токены, email)
- [ ] **Настроить production режим:**
  - [ ] Vue.js: Включить production mode (minification, no dev warnings)
  - [ ] PHP: Установить `display_errors = Off` в php.ini
  - [ ] PHP: Установить `error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED`
  - [ ] Настроить ротацию логов (logrotate)
- [ ] **Документация для клиента:**
  - [ ] Создать USER_MANUAL.md (инструкция для пользователей)
  - [ ] Создать ADMIN_MANUAL.md (инструкция для администраторов)
  - [ ] Создать DEPLOYMENT.md (инструкция по развёртыванию)
  - [ ] Создать BACKUP.md (инструкция по резервному копированию)
- [ ] **Безопасность:**
  - [ ] Проверить что .env файлы не в git
  - [ ] Проверить что логи не содержат пароли/токены
  - [ ] Проверить что API endpoints защищены авторизацией
  - [ ] Проверить SQL injection защиту (prepared statements)
  - [ ] Проверить XSS защиту (escape HTML в выводе)

---

## Технологии

**Frontend:**
- Vue.js 3
- Quill.js (редактор статей)
- Vanilla JS (drag-n-drop)
- **utils/mappers.js** - конвертация данных ⭐
- **Debug Panel** - инструмент отладки ⭐

**Backend:**
- PHP 8.x
- MySQL 8.x
- JWT или Session-based auth
- **ApiLogger middleware** - логирование запросов/ответов ⭐
- **Детальная обработка ошибок** ⭐

**Сервер:**
- Ubuntu
- Nginx или Apache
- SSL (Let's Encrypt)

---

## Заметки

### Про счётчики и пиксели
- **Глобальные коды** (Google Analytics, Facebook Pixel) вставляются в `<head>` на всех страницах
- **Страничные коды** (специфичные для страницы) вставляются только на конкретной странице
- Должны быть отдельные поля для `<head>` и `<body>` кодов

### Про роли
- **super_admin**: полный доступ + управление пользователями
- **admin**: создание/редактирование страниц, но не управление пользователями
- **editor**: только редактирование существующих страниц

### Про переиспользование
- Все стили и цвета вынести в CSS-переменные
- Блоки должны быть универсальными (data-driven)
- Дизайн админки должен соответствовать прототипу
- **ОБЯЗАТЕЛЬНО использовать mappers для конвертации данных** ⭐
- **ОБЯЗАТЕЛЬНО использовать транслитерацию для slug** ⭐
- **ОБЯЗАТЕЛЬНО логировать все API запросы/ответы** ⭐

### Уроки из дебага ⭐ NEW

**Что ОБЯЗАТЕЛЬНО делать:**

1. ✅ **API Contract First** - документировать API ДО начала разработки
2. ✅ **Детальные ошибки** - каждая ошибка валидации должна указывать поле и причину
3. ✅ **Mappers** - всегда использовать utility функции для конвертации данных
4. ✅ **Debug Tools** - Debug Panel и логирование с первого дня
5. ✅ **Транслитерация** - для многоязычных проектов всегда делать транслитерацию slug
6. ✅ **Тесты** - интеграционные тесты для критических flows
7. ✅ **Персистентность** - всегда сохранять состояние auth в localStorage
8. ✅ **Vue Proxy** - всегда конвертировать перед отправкой в API
9. ✅ **Логирование** - на фронтенде И бэкенде
10. ✅ **Валидация** - на фронтенде И бэкенде

**Что проверять при code review:**

- ✅ Используются ли mappers для конвертации данных?
- ✅ Конвертируются ли Vue Proxy через toPlainObject()?
- ✅ Возвращаются ли детальные ошибки из API?
- ✅ Логируются ли все запросы/ответы?
- ✅ Есть ли интеграционные тесты?
- ✅ Работает ли Debug Panel?
- ✅ Персистит ли currentUser при перезагрузке?
- ✅ Работает ли транслитерация slug?
- ✅ Сохраняются ли связанные данные (Page + Blocks)?
- ✅ Все ли элементы Vue внутри `<div id="app">`?

---

## Связанные документы

- `ИСТОРИЯ_ДЕБАГА_API_ИНТЕГРАЦИИ.md` - Полная история отладки с примерами ошибок
- `АНАЛИЗ_ДЕБАГА_И_УЛУЧШЕНИЯ_ПЛАНА.md` - Анализ по слоям Clean Architecture
- `PROJECT_STRUCTURE.md` - Структура проекта
- `API_CONTRACT.md` - Документация API (создать на этапе 0.1)

---

**Дата создания:** 2025-01-10
**Последнее обновление:** 2025-10-04
**Автор:** Claude + Anna
