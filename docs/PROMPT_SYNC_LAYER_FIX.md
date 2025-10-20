# Prompt для исправления проблемы синхронизирующего слоя

## Контекст задачи

Ты работаешь с PHP backend проектом Healthcare CMS. Обнаружена критическая проблема: backend возвращает JSON responses с **непоследовательным именованием полей** — смесь `snake_case` и `camelCase`. Frontend ожидает **только camelCase**.

**Примеры проблемы:**
- `PageController::create()` возвращает `page_id` ❌
- `PageController::list()` возвращает `createdAt` ✅
- Непоследовательность ломает frontend

**Твоя цель:** Исправить проблему в 3 фазы.

---

## Phase 1: Hotfix (немедленное исправление)

### Задача 1.1: Создать JsonSerializer

**Создай файл:** `backend/src/Infrastructure/Serializer/JsonSerializer.php`

**Требования:**
- Namespace: `Infrastructure\Serializer`
- Класс со статическими методами
- Метод `toCamelCase(array $data): array` — рекурсивно конвертирует все ключи массива из snake_case в camelCase
- Метод `snakeToCamel(string $key): string` — конвертирует одну строку (например: `show_in_menu` → `showInMenu`)

**Алгоритм:**
1. Для каждого ключа в массиве:
   - Конвертировать ключ: `show_in_menu` → `showInMenu`
   - Если значение — массив, рекурсивно обработать его
   - Если значение — примитив/null, оставить как есть
2. Вернуть новый массив с camelCase ключами

**Код шаблон:**
```php
<?php

declare(strict_types=1);

namespace Infrastructure\Serializer;

class JsonSerializer
{
    /**
     * Recursively convert all array keys from snake_case to camelCase
     */
    public static function toCamelCase(array $data): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Конвертировать ключ
            $camelKey = self::snakeToCamel($key);
            
            // Рекурсивно обработать вложенные массивы
            if (is_array($value)) {
                $result[$camelKey] = self::toCamelCase($value);
            } else {
                $result[$camelKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert snake_case string to camelCase
     * Examples: show_in_menu → showInMenu, created_by → createdBy
     */
    private static function snakeToCamel(string $key): string
    {
        // ТВОЙ КОД: используй preg_replace_callback для замены _x на X
        // Паттерн: /_([a-z])/ 
        // Замена: uppercase буква без подчеркивания
    }
}
```

**Критерии успеха:**
- `toCamelCase(['page_id' => '123'])` → `['pageId' => '123']`
- `toCamelCase(['show_in_menu' => true, 'created_by' => 'user'])` → `['showInMenu' => true, 'createdBy' => 'user']`
- Работает с вложенными массивами: `['page' => ['show_in_menu' => true]]` → `['page' => ['showInMenu' => true]]`

---

### Задача 1.2: Применить в JsonResponseTrait

**Файл:** `backend/src/Presentation/Controller/JsonResponseTrait.php`

**Что сделать:**
1. Открой файл
2. Найди метод `jsonResponse()`
3. Добавь импорт: `use Infrastructure\Serializer\JsonSerializer;`
4. **ПЕРЕД** `json_encode()` добавь конвертацию: `$data = JsonSerializer::toCamelCase($data);`

**Было:**
```php
protected function jsonResponse($data, int $statusCode = 200): void
{
    header('Content-Type: application/json', true, $statusCode);
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
```

**Стало:**
```php
protected function jsonResponse($data, int $statusCode = 200): void
{
    header('Content-Type: application/json', true, $statusCode);
    http_response_code($statusCode);
    
    // Normalize all keys to camelCase
    $data = JsonSerializer::toCamelCase($data);
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
```

---

### Задача 1.3: Тестирование

**Manual test commands:**

1. **POST /api/pages** (создание страницы):
```bash
curl -X POST http://localhost/healthcare-cms-backend/public/api/pages \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","slug":"test","status":"draft"}'
```
**Ожидаемый response:**
```json
{
  "success": true,
  "pageId": "UUID"  ← должен быть camelCase!
}
```

2. **GET /api/pages** (список страниц):
```bash
curl http://localhost/healthcare-cms-backend/public/api/pages \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Ожидаемый response:**
```json
[
  {
    "id": "UUID",
    "title": "Test",
    "createdAt": "2025-01-11 12:00:00",  ← camelCase!
    "updatedAt": "2025-01-11 12:00:00"   ← camelCase!
  }
]
```

3. **GET /api/pages/:id** (одна страница):
```bash
curl http://localhost/healthcare-cms-backend/public/api/pages/UUID \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Ожидаемый response:**
```json
{
  "page": {
    "id": "UUID",
    "showInMenu": true,     ← НЕ show_in_menu!
    "createdBy": "user123"  ← НЕ created_by!
  },
  "blocks": [...]
}
```

**Проверь все три endpoint'а вручную. Убедись что ВСЕ ключи в camelCase.**

---

## Phase 2: Proper Implementation (правильная архитектура)

### Задача 2.1: Создать EntityToArrayTransformer

**Создай файл:** `backend/src/Presentation/Transformer/EntityToArrayTransformer.php`

**Требования:**
- Namespace: `Presentation\Transformer`
- Статические методы для конвертации Domain Entities → JSON arrays
- Три метода: `pageToArray()`, `blockToArray()`, `userToArray()`

**Шаблон:**
```php
<?php

declare(strict_types=1);

namespace Presentation\Transformer;

use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Entity\User;

class EntityToArrayTransformer
{
    /**
     * Convert Page entity to array for JSON response
     * ALL keys MUST be camelCase
     */
    public static function pageToArray(Page $page, bool $includeBlocks = false): array
    {
        $result = [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->getValue(),
            'type' => $page->getType()->value,
            
            // ✅ camelCase (НЕ show_in_menu!)
            'showInMenu' => $page->getShowInMenu(),
            'showInSitemap' => $page->getShowInSitemap(),
            'menuOrder' => $page->getMenuOrder(),
            'menuTitle' => $page->getMenuTitle(),
            
            // SEO fields
            'seoTitle' => $page->getSeoTitle(),
            'seoDescription' => $page->getSeoDescription(),
            'seoKeywords' => $page->getSeoKeywords(),
            
            // ✅ camelCase (НЕ created_by!)
            'createdBy' => $page->getCreatedBy(),
            'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            
            // Optional fields
            'collectionConfig' => $page->getCollectionConfig(),
            'pageSpecificCode' => $page->getPageSpecificCode(),
            'sourceTemplateSlug' => $page->getSourceTemplateSlug(),
            'renderedHtml' => $page->getRenderedHtml(),
        ];
        
        if ($includeBlocks && method_exists($page, 'getBlocks')) {
            $result['blocks'] = array_map(
                [self::class, 'blockToArray'],
                $page->getBlocks()
            );
        }
        
        return $result;
    }
    
    /**
     * Convert Block entity to array for JSON response
     */
    public static function blockToArray(Block $block): array
    {
        return [
            'id' => $block->getId(),
            'pageId' => $block->getPageId(),  // ✅ camelCase!
            'type' => $block->getType(),
            'position' => $block->getPosition(),
            'customName' => $block->getCustomName(),  // ✅ camelCase!
            'clientId' => $block->getClientId(),      // ✅ camelCase!
            'data' => $block->getData(),
            'createdAt' => $block->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $block->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Convert User entity to array for JSON response
     */
    public static function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            // НЕ включать password hash!
        ];
    }
}
```

**Критически важно:** ВСЕ ключи в camelCase, никаких snake_case!

---

### Задача 2.2: Рефакторить PageController

**Файл:** `backend/src/Presentation/Controller/PageController.php`

**Что сделать:**
1. Добавить импорт: `use Presentation\Transformer\EntityToArrayTransformer;`
2. Изменить метод `get()`:

**Было:**
```php
public function get(string $id): void
{
    // ...
    $response = $this->getPageWithBlocks->execute($request);
    
    // СТАРЫЙ КОД: manual array construction
    $result = [
        'page' => [
            'id' => $response->page->getId(),
            'title' => $response->page->getTitle(),
            // ... много строк
        ],
        'blocks' => array_map(function($block) {
            return [
                'id' => $block->getId(),
                // ... много строк
            ];
        }, $response->blocks)
    ];
    
    $this->jsonResponse($result, 200);
}
```

**Стало:**
```php
public function get(string $id): void
{
    // ...
    $response = $this->getPageWithBlocks->execute($request);
    
    // ✅ НОВЫЙ КОД: использовать transformer
    $pageArray = EntityToArrayTransformer::pageToArray($response->page);
    $pageArray['blocks'] = array_map(
        [EntityToArrayTransformer::class, 'blockToArray'],
        $response->blocks
    );
    
    $this->jsonResponse(['page' => $pageArray], 200);
}
```

3. Изменить метод `list()`:

**Было:**
```php
public function list(): void
{
    // ...
    $result = array_map(function($page) {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            // ... manual construction
        ];
    }, $pages);
    
    $this->jsonResponse($result, 200);
}
```

**Стало:**
```php
public function list(): void
{
    // ...
    $result = array_map(
        [EntityToArrayTransformer::class, 'pageToArray'],
        $pages
    );
    
    $this->jsonResponse($result, 200);
}
```

4. Изменить метод `create()`:

**Было:**
```php
$result = [
    'success' => true,
    'page_id' => $response->pageId  // ❌ snake_case!
];
```

**Стало:**
```php
$result = [
    'success' => true,
    'pageId' => $response->pageId  // ✅ camelCase!
];
```

**Аналогично рефакторить методы:** `update()`, `publish()`, `delete()`

---

### Задача 2.3: Рефакторить MenuController

**Файл:** `backend/src/Presentation/Controller/MenuController.php`

**Применить тот же подход:**
1. Найти все места где создаются массивы для response
2. Заменить manual construction на `EntityToArrayTransformer::pageToArray()`
3. Убедиться что все ключи camelCase

**Пример:**
```php
// Было
$items[] = [
    'page_id' => $row['id'],  // ❌
    'menu_title' => $row['menu_title'],  // ❌
];

// Стало
$items[] = [
    'pageId' => $row['id'],  // ✅
    'menuTitle' => $row['menu_title'],  // ✅
];

// ИЛИ лучше: использовать transformer если есть Page entity
$items[] = EntityToArrayTransformer::pageToArray($page);
```

---

### Задача 2.4: Рефакторить MediaController

**Файл:** `backend/src/Presentation/Controller/MediaController.php`

**Добавить в EntityToArrayTransformer:**
```php
public static function mediaFileToArray(MediaFile $file): array
{
    return [
        'id' => $file->getId(),
        'filename' => $file->getFilename(),
        'url' => $file->getUrl(),
        'type' => $file->getType(),
        'size' => $file->getSize(),
        'uploadedBy' => $file->getUploadedBy(),  // ✅ camelCase!
        'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),  // ✅ camelCase!
    ];
}
```

**Применить в MediaController:**
```php
$result = array_map(
    [EntityToArrayTransformer::class, 'mediaFileToArray'],
    $files
);
```

---

### Задача 2.5: Рефакторить AuthController

**Файл:** `backend/src/Presentation/Controller/AuthController.php`

**Изменить метод `me()`:**
```php
public function me(): void
{
    // ...
    $user = // получить User entity
    
    // ✅ Использовать transformer
    $userData = EntityToArrayTransformer::userToArray($user);
    $this->jsonResponse($userData, 200);
}
```

**Изменить метод `login()`:**
```php
$result = [
    'success' => true,
    'token' => $token,
    'user' => EntityToArrayTransformer::userToArray($user)  // ✅
];
```

---

### Задача 2.6: Удалить automatic serialization

**После того как ВСЕ controllers отрефакторены:**

**Файл:** `backend/src/Presentation/Controller/JsonResponseTrait.php`

**Вернуть к оригиналу:**
```php
protected function jsonResponse($data, int $statusCode = 200): void
{
    header('Content-Type: application/json', true, $statusCode);
    http_response_code($statusCode);
    
    // ❌ УДАЛИТЬ эту строку (automatic serialization больше не нужна)
    // $data = JsonSerializer::toCamelCase($data);
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
```

**Почему удаляем:** Controllers теперь используют явные transformers, автоматическая конвертация больше не нужна и может скрывать ошибки.

---

### Задача 2.7: E2E тесты

**Создай файл:** `backend/tests/E2E/ResponseFormatTest.php`

**Требования:**
- Тестировать что ВСЕ responses возвращают camelCase ключи
- Использовать реальные HTTP запросы к API

**Шаблон:**
```php
<?php

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;

class ResponseFormatTest extends TestCase
{
    private string $baseUrl = 'http://localhost/healthcare-cms-backend/public';
    private string $token;
    
    protected function setUp(): void
    {
        // Получить auth token
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123'
        ]);
        
        $this->token = $response['token'];
    }
    
    public function testCreatePageReturnsCamelCase(): void
    {
        $response = $this->postJson('/api/pages', [
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft'
        ]);
        
        // ✅ Должен быть pageId, НЕ page_id
        $this->assertArrayHasKey('pageId', $response);
        $this->assertArrayNotHasKey('page_id', $response);
    }
    
    public function testGetPageReturnsCamelCase(): void
    {
        // Создать страницу
        $createResponse = $this->postJson('/api/pages', [
            'title' => 'Test',
            'slug' => 'test'
        ]);
        
        $pageId = $createResponse['pageId'];
        
        // Получить страницу
        $response = $this->getJson("/api/pages/{$pageId}");
        
        // ✅ Проверить camelCase ключи
        $this->assertArrayHasKey('page', $response);
        $page = $response['page'];
        
        $this->assertArrayHasKey('showInMenu', $page);
        $this->assertArrayNotHasKey('show_in_menu', $page);
        
        $this->assertArrayHasKey('createdBy', $page);
        $this->assertArrayNotHasKey('created_by', $page);
        
        $this->assertArrayHasKey('createdAt', $page);
        $this->assertArrayNotHasKey('created_at', $page);
    }
    
    public function testGetPagesListReturnsCamelCase(): void
    {
        $response = $this->getJson('/api/pages');
        
        $this->assertIsArray($response);
        
        if (count($response) > 0) {
            $firstPage = $response[0];
            
            $this->assertArrayHasKey('createdAt', $firstPage);
            $this->assertArrayNotHasKey('created_at', $firstPage);
        }
    }
    
    /**
     * Проверить что ВСЕ ключи в массиве используют camelCase
     */
    private function assertAllKeysCamelCase(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            // Проверить что ключ НЕ содержит underscore
            $this->assertStringNotContainsString('_', $key, 
                "Key '{$key}' at path '{$path}' contains underscore (should be camelCase)"
            );
            
            // Рекурсивно проверить вложенные массивы
            if (is_array($value)) {
                $this->assertAllKeysCamelCase($value, $path . '.' . $key);
            }
        }
    }
    
    // Helper methods
    
    private function postJson(string $endpoint, array $data): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    private function getJson(string $endpoint): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

**Запустить тесты:**
```bash
cd backend
vendor/bin/phpunit tests/E2E/ResponseFormatTest.php
```

**Все тесты должны пройти ✅**

---

## Phase 3: Documentation

### Задача 3.1: Обновить API_CONTRACT.md

**Файл:** `docs/API_CONTRACT.md`

**Добавить в начало документа секцию:**

```markdown
## Response Format Standards

### Naming Convention

**ALL JSON responses use camelCase for property names.**

✅ **Correct:**
```json
{
  "pageId": "123",
  "showInMenu": true,
  "createdBy": "user123",
  "createdAt": "2025-01-11 12:00:00"
}
```

❌ **Incorrect:**
```json
{
  "page_id": "123",       // ❌ snake_case
  "show_in_menu": true,   // ❌ snake_case
  "created_by": "user123" // ❌ snake_case
}
```

### Request Format

**ALL JSON requests accept camelCase properties.**

Frontend automatically converts camelCase → snake_case using `mappers.js`.

Backend Use Cases accept camelCase and may support snake_case for backward compatibility, but camelCase is the primary format.

### Transformation Layer

**Backend uses `EntityToArrayTransformer` to convert Domain Entities → JSON arrays.**

Location: `backend/src/Presentation/Transformer/EntityToArrayTransformer.php`

Methods:
- `pageToArray(Page $page): array` — converts Page entity
- `blockToArray(Block $block): array` — converts Block entity
- `userToArray(User $user): array` — converts User entity
- `mediaFileToArray(MediaFile $file): array` — converts MediaFile entity

All transformers guarantee camelCase output.
```

**Обновить примеры endpoints:**

```markdown
### POST /api/pages

**Response:**
```json
{
  "success": true,
  "pageId": "550e8400-e29b-41d4-a716-446655440000"  ← camelCase!
}
```

### GET /api/pages/:id

**Response:**
```json
{
  "page": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Healthcare Guide",
    "slug": "healthcare-guide",
    "showInMenu": true,        ← camelCase!
    "createdBy": "admin",      ← camelCase!
    "createdAt": "2025-01-11 12:00:00",
    "updatedAt": "2025-01-11 12:00:00"
  },
  "blocks": [
    {
      "id": "block-uuid",
      "pageId": "550e8400...",  ← camelCase!
      "customName": "intro",    ← camelCase!
      "data": { ... }
    }
  ]
}
```
```

---

### Задача 3.2: Создать RESPONSE_FORMAT_STANDARDS.md

**Создай файл:** `docs/RESPONSE_FORMAT_STANDARDS.md`

**Содержимое:**

```markdown
# Response Format Standards

## Правила именования свойств в JSON

### 1. Всегда используй camelCase

**Правильно:**
- `pageId`, `userId`, `blockId`
- `showInMenu`, `showInSitemap`
- `createdBy`, `createdAt`, `updatedAt`
- `menuOrder`, `menuTitle`
- `seoTitle`, `seoDescription`

**Неправильно:**
- `page_id`, `user_id`, `block_id` ❌
- `show_in_menu`, `show_in_sitemap` ❌
- `created_by`, `created_at`, `updated_at` ❌
- `menu_order`, `menu_title` ❌

### 2. Использовать EntityToArrayTransformer

**НЕ создавай массивы вручную в Controllers:**

❌ **Bad:**
```php
public function get(string $id): void
{
    $page = $this->pageRepository->findById($id);
    
    $result = [
        'id' => $page->getId(),
        'title' => $page->getTitle(),
        'created_at' => $page->getCreatedAt()  // ❌ легко ошибиться!
    ];
    
    $this->jsonResponse($result);
}
```

✅ **Good:**
```php
public function get(string $id): void
{
    $page = $this->pageRepository->findById($id);
    
    $result = EntityToArrayTransformer::pageToArray($page);  // ✅
    
    $this->jsonResponse($result);
}
```

### 3. Примеры трансформации

#### Page Entity → JSON
```php
$page = new Page(...);

$array = EntityToArrayTransformer::pageToArray($page);
// Result:
[
    'id' => '123',
    'title' => 'Test',
    'showInMenu' => true,     // ✅ camelCase
    'createdBy' => 'admin',   // ✅ camelCase
    'createdAt' => '2025-01-11 12:00:00'
]
```

#### Block Entity → JSON
```php
$block = new Block(...);

$array = EntityToArrayTransformer::blockToArray($block);
// Result:
[
    'id' => 'block-123',
    'pageId' => 'page-456',    // ✅ camelCase
    'customName' => 'intro',   // ✅ camelCase
    'position' => 0
]
```

### 4. Frontend Compatibility

Frontend использует `mappers.js` для конвертации:

**Request (Frontend → Backend):**
```javascript
// Frontend отправляет camelCase
const data = {
    title: "Test",
    showInMenu: true,
    createdBy: "admin"
};

// mappers.js конвертирует в snake_case для backward compatibility
blockToAPI(data) → {
    title: "Test",
    show_in_menu: true,
    created_by: "admin"
}

// Backend Use Cases принимают ОБА формата (но camelCase предпочтительнее)
```

**Response (Backend → Frontend):**
```javascript
// Backend возвращает camelCase (через EntityToArrayTransformer)
{
    pageId: "123",
    showInMenu: true,
    createdAt: "2025-01-11"
}

// Frontend получает и использует напрямую (no conversion needed)
page.showInMenu  // ✅ работает
```

### 5. Testing

**Каждый новый endpoint должен иметь E2E тест:**

```php
public function testMyEndpointReturnsCamelCase(): void
{
    $response = $this->getJson('/api/my-endpoint');
    
    // Проверить что НЕТ snake_case ключей
    $this->assertArrayNotHasKey('created_at', $response);
    $this->assertArrayNotHasKey('show_in_menu', $response);
    
    // Проверить что ЕСТЬ camelCase ключи
    $this->assertArrayHasKey('createdAt', $response);
    $this->assertArrayHasKey('showInMenu', $response);
}
```

### 6. Checklist для Code Review

При добавлении нового endpoint:

- [ ] Controller использует `EntityToArrayTransformer`
- [ ] Все ключи в response — camelCase
- [ ] Нет manual array construction для entities
- [ ] Добавлен E2E тест проверяющий формат response
- [ ] Обновлена документация в `API_CONTRACT.md`

---

## Миграция существующего кода

### Phase 1: Hotfix (DONE ✅)
- Создан `JsonSerializer::toCamelCase()`
- Добавлена автоматическая конвертация в `JsonResponseTrait`
- Все старые responses теперь возвращают camelCase

### Phase 2: Refactoring (IN PROGRESS)
- Создан `EntityToArrayTransformer`
- Рефакторинг controllers для использования transformers
- Удаление автоматической конвертации (после завершения рефакторинга)

### Phase 3: Testing & Documentation (TODO)
- E2E тесты для всех endpoints
- Обновление API documentation
- Code review checklist

---

## Troubleshooting

### Проблема: Получаю snake_case в response

**Решение:**
1. Проверь что Controller использует `EntityToArrayTransformer`
2. Проверь что не создаешь массив вручную с `snake_case` ключами
3. Проверь что `JsonSerializer` корректно конвертирует (если Phase 1 еще активна)

### Проблема: Frontend не может прочитать property

**Пример:** `response.page_id` вместо `response.pageId`

**Решение:**
1. Backend должен вернуть `pageId` (camelCase)
2. Проверь response в Network tab DevTools
3. Если видишь `page_id` — backend возвращает неправильный формат
4. Исправь Controller чтобы использовать transformer

### Проблема: Use Case не принимает camelCase

**Решение:**
1. Use Cases должны поддерживать camelCase (primary) и snake_case (fallback)
2. Пример: `$data['createdBy'] ?? $data['created_by']`
3. После Phase 2 — удалить fallback, оставить только camelCase
```

---

### Задача 3.3: Обновить BACKEND_CURRENT_STATE.md

**Файл:** `docs/BACKEND_CURRENT_STATE.md`

**Найти секцию с Phase progress, заменить:**

**Было:**
```markdown
## Implementation Progress

### Phase 0-1: Infrastructure (✅ COMPLETE)
- DI Container
- Domain Exceptions
- DTOs

### Phase 2.1: UpdatePageInline (🟡 READY TO EXECUTE)
- UpdatePageInlineRequest/Response created
- Use case ready

### Phase 2.2-2.3: (⏳ QUEUE)
### Phase 3: (⏳ QUEUE)
```

**Стало:**
```markdown
## Implementation Progress

### Phase 0-1: Infrastructure (✅ COMPLETE 100%)
- ✅ DI Container (`bootstrap/container.php`)
- ✅ Domain Exceptions (PageNotFoundException, BlockNotFoundException)
- ✅ DTOs (10 Request/Response pairs created)

### Phase 2: Use Cases Refactoring (✅ 70-80% COMPLETE)
**Completed:**
- ✅ UpdatePageInline — uses DTO + Domain Exceptions
- ✅ GetPageWithBlocks — uses DTO
- ✅ CreatePage — uses DTO
- ✅ DeletePage — uses DTO
- ✅ PublishPage — uses DTO

**Remaining:**
- ⏳ UpdatePage — partially refactored (needs full DTO adoption)
- ⏳ GetAllPages — returns array, needs DTO wrapper
- ⏳ RenderPageHtml — needs review

### Phase 3: Controllers Refactoring (✅ 40-50% COMPLETE)
**Completed:**
- ✅ PageController — uses constructor injection (7 use cases)
- ✅ index.php — uses `$container->make(PageController::class)`

**Remaining:**
- ⏳ AuthController — still instantiated directly (needs DI)
- ⏳ MenuController — needs DI
- ⏳ MediaController — needs DI
- ⏳ UserController — needs DI
- ⏳ SettingsController — needs DI

### Phase 4: Response Format Standardization (🔄 IN PROGRESS)
**Problem discovered:** Backend returns mixed snake_case/camelCase in responses.

**Solution implemented:**
- ✅ Phase 1: JsonSerializer hotfix (automatic camelCase conversion)
- 🔄 Phase 2: EntityToArrayTransformer (proper architecture)
- ⏳ Phase 3: Documentation updates

**Status:** Phase 1 complete, Phase 2 in progress.

See: `docs/SYNC_LAYER_PROBLEM_ANALYSIS.md`
```

---

## Критерии успеха

### Phase 1 успешно завершена если:
- ✅ `JsonSerializer::toCamelCase()` работает корректно
- ✅ Все существующие endpoints возвращают camelCase
- ✅ Manual тесты проходят (POST/GET/LIST endpoints)
- ✅ Frontend может корректно читать responses

### Phase 2 успешно завершена если:
- ✅ `EntityToArrayTransformer` создан со всеми методами
- ✅ Все Controllers используют transformers (не manual array construction)
- ✅ Automatic serialization удалена из `JsonResponseTrait`
- ✅ E2E тесты проходят
- ✅ Нет регрессии в функциональности

### Phase 3 успешно завершена если:
- ✅ `API_CONTRACT.md` обновлен (все примеры в camelCase)
- ✅ `RESPONSE_FORMAT_STANDARDS.md` создан
- ✅ `BACKEND_CURRENT_STATE.md` отражает реальный прогресс
- ✅ Code review checklist создан

---

## Важные замечания

### 1. Не ломай существующую функциональность
- Тестируй каждое изменение
- Проверяй что frontend по-прежнему работает
- Если что-то ломается — откати изменение и исправь проблему

### 2. Последовательность важна
- Phase 1 ПЕРЕД Phase 2 (hotfix перед refactoring)
- Phase 2.6 (удаление automatic serialization) ПОСЛЕ того как ВСЕ controllers отрефакторены
- Phase 3 ПОСЛЕ Phase 2 (документация после имплементации)

### 3. Тестирование обязательно
- Manual testing после Phase 1
- E2E tests в Phase 2
- Проверка всех endpoints перед удалением automatic serialization

### 4. camelCase vs snake_case
- **Backend responses:** ТОЛЬКО camelCase
- **Backend DB:** snake_case (не трогать!)
- **Backend Use Cases:** принимают camelCase (primary) + snake_case (fallback)
- **Frontend:** camelCase везде

---

## Начинай работу

**Первый шаг:** Создай `JsonSerializer` (Phase 1, Task 1.1)

**Порядок выполнения:**
1. Phase 1.1 → 1.2 → 1.3 (Hotfix)
2. Phase 2.1 → 2.2 → 2.3 → 2.4 → 2.5 (Refactoring)
3. Phase 2.6 (Удаление automatic serialization) ПОСЛЕ 2.1-2.5
4. Phase 2.7 (E2E tests)
5. Phase 3.1 → 3.2 → 3.3 (Documentation)

**Удачи! 🚀**
