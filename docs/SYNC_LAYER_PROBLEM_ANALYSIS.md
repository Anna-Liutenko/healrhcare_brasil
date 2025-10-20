# Анализ проблемы синхронизирующего слоя

**Дата:** 2025-01-11  
**Статус:** 🔴 КРИТИЧЕСКАЯ ПРОБЛЕМА ОБНАРУЖЕНА

---

## Резюме проблемы

После детального анализа кодовой базы (исключая устаревшую документацию) обнаружена **критическая несогласованность** в синхронизирующем слое между frontend и backend:

### ❌ ПРОБЛЕМА: Смешанные конвенции именования

Backend использует **snake_case** в базе данных и entity, но **непоследовательно конвертирует** в response:
- ✅ Frontend отправляет `camelCase` → mappers.js → `snake_case` → Backend
- ❌ Backend возвращает **СМЕСЬ** `snake_case` и `camelCase` → Frontend не может корректно обработать

---

## Фактические данные

### 1. Frontend Mappers (✅ Корректно реализовано)

**Файл:** `frontend/utils/mappers.js`

```javascript
// Конвертация Frontend → API
export function blockToAPI(block) {
    const converted = convertObjectKeys(plainBlock, camelToSnake);
    // showInMenu → show_in_menu
    // createdBy → created_by
    // menuPosition → menu_position
}

// Конвертация API → Frontend
export function blockFromAPI(apiBlock) {
    const converted = convertObjectKeys(plainBlock, snakeToCamel);
    // show_in_menu → showInMenu
    // created_by → createdBy
}
```

**Использование:** `api-client.js` корректно применяет mappers для блоков:
```javascript
async createPage(pageData) {
    const payload = toPlainObject({
        ...pageData,
        blocks: (pageData.blocks || []).map((block) => blockToAPI(block))
    });
}

async getPage(pageId) {
    if (data?.page && Array.isArray(data.page.blocks)) {
        data.page.blocks = data.page.blocks.map(blockFromAPI);
    }
}
```

---

### 2. Backend Use Cases (⚠️ СМЕШАННАЯ КОНВЕНЦИЯ)

#### CreatePage.php (lines 60-73)
```php
$page = new Page(
    // ...
    showInMenu: $data['showInMenu'] ?? false,           // ✅ Ожидает camelCase
    createdBy: $data['created_by'] ?? $data['createdBy'], // ⚠️ Поддерживает ОБА формата
    collectionConfig: $data['collectionConfig'] ?? null,  // ✅ camelCase
    pageSpecificCode: $data['pageSpecificCode'] ?? null   // ✅ camelCase
);
```

**Проблема:** Двойная поддержка (`created_by ?? createdBy`) = костыль, маскирующий проблему.

#### UpdatePage.php (line 112)
```php
// Support both camelCase and snake_case for custom name
if (isset($data['customName']) || isset($data['custom_name'])) {
    $customName = $data['customName'] ?? $data['custom_name'];
    $page->setCustomName($customName);
}
```

**Проблема:** Явный комментарий о поддержке обоих форматов = признак отсутствия стандарта.

---

### 3. Backend Controllers (🔴 КРИТИЧЕСКАЯ ПРОБЛЕМА)

#### PageController::create() (lines 87-92)
```php
$result = [
    'success' => true,
    'page_id' => $response->pageId  // ❌ SNAKE_CASE!
];
$this->jsonResponse($result, 201);
```

**Проблема:** Возвращает `page_id`, но должен возвращать `pageId`.

#### PageController::list() (lines 179-190)
```php
$result = array_map(function($page) {
    return [
        'id' => $page->getId(),           // ✅ camelCase
        'title' => $page->getTitle(),     // ✅ camelCase
        'slug' => $page->getSlug(),       // ✅ camelCase
        'status' => $page->getStatus()->getValue(),  // ✅ camelCase
        'type' => $page->getType()->value,           // ✅ camelCase
        'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),  // ✅ camelCase
        'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),  // ✅ camelCase
    ];
}, $pages);
```

**Проблема:** Этот endpoint возвращает `camelCase`, но CREATE возвращает `snake_case`!

#### PageController::get() (неизвестен точный формат response)
Нужно проверить, возвращает ли этот endpoint:
- `show_in_menu` или `showInMenu`?
- `created_by` или `createdBy`?
- `menu_order` или `menuOrder`?

---

### 4. Domain Entity (✅ camelCase, но...)

**Page.php** использует camelCase геттеры:
```php
public function getShowInMenu(): bool { return $this->showInMenu; }
public function getCreatedBy(): string { return $this->createdBy; }
public function getMenuOrder(): int { return $this->menuOrder; }
```

**Но:** Repository использует snake_case для DB:
```php
// MySQLPageRepository.php (line 202)
'show_in_menu' => $page->isShowInMenu() ? 1 : 0,
'created_by' => $page->getCreatedBy(),
'menu_order' => $page->getMenuOrder(),
```

**Проблема:** Конвертация DB → Entity происходит корректно, но Entity → JSON response — НЕТ ЕДИНОГО СТАНДАРТА.

---

## Найденные inconsistencies

### Repository → Entity (✅ Работает)
```php
// MySQLPageRepository::mapRowToPage() (line 275)
showInMenu: (bool)$row['show_in_menu'],   // DB snake_case → Entity camelCase
createdBy: $row['created_by'],            // DB snake_case → Entity camelCase
```

### Entity → Controller Response (❌ НЕ РАБОТАЕТ)
```php
// PageController::create()
'page_id' => $response->pageId  // ❌ Должно быть 'pageId'

// PageController::list()
'createdAt' => $page->getCreatedAt()  // ✅ Правильно camelCase

// PageController::get() - НЕИЗВЕСТНО
// Возможно возвращает mixed snake_case/camelCase
```

### Frontend Mappers (✅ Работают, НО бессмысленны если backend непоследователен)
```javascript
// blockToAPI() конвертирует camelCase → snake_case
// blockFromAPI() конвертирует snake_case → camelCase

// НО: если backend возвращает MIXED format,
// mappers НЕ МОГУТ корректно обработать response
```

---

## Примеры ошибок в production

### Сценарий 1: Создание страницы
```javascript
// Frontend
const pageData = {
    title: "Test",
    showInMenu: true,
    createdBy: "user123"
};
await api.createPage(pageData);

// Backend получает (через mappers):
{
    "title": "Test",
    "show_in_menu": true,  // ✅ Конвертировано
    "created_by": "user123" // ✅ Конвертировано
}

// Backend возвращает:
{
    "success": true,
    "page_id": "abc123"  // ❌ SNAKE_CASE!
}

// Frontend ожидает:
{
    "success": true,
    "pageId": "abc123"  // ✅ CAMEL_CASE
}

// РЕЗУЛЬТАТ: Frontend получает response.page_id === undefined
```

### Сценарий 2: Получение страницы
```javascript
// Backend PageController::get() возвращает (предположительно):
{
    "page": {
        "id": "abc123",
        "title": "Test",
        "show_in_menu": true,      // ❌ SNAKE_CASE
        "created_by": "user123",   // ❌ SNAKE_CASE
        "createdAt": "2025-01-11"  // ✅ CAMEL_CASE (?)
    }
}

// Frontend применяет mappers.blockFromAPI() ТОЛЬКО к blocks:
data.page.blocks = data.page.blocks.map(blockFromAPI);

// НО НЕ применяет к page properties!
// РЕЗУЛЬТАТ: Frontend получает mixed format: page.show_in_menu, page.createdAt
```

---

## Root Cause Analysis

### Почему возникла проблема?

1. **Постепенная миграция на Clean Architecture:**
   - Старый код: Controllers напрямую возвращали DB rows (snake_case)
   - Новый код: Use Cases + DTOs (camelCase entities)
   - **Проблема:** Controllers не были унифицированы для JSON response

2. **Отсутствие Response Mappers:**
   - Frontend имеет `blockToAPI/blockFromAPI`
   - Backend НЕ имеет `EntityToResponse` mapper layer
   - **Проблема:** Каждый controller вручную строит JSON, используя разные конвенции

3. **DTOs не используют Response Transformers:**
   - `CreatePageResponse` содержит `public readonly string $pageId`
   - Controller возвращает `'page_id' => $response->pageId`
   - **Проблема:** DTO свойства в camelCase, но controller keys в snake_case

4. **Документация отстала от реальности:**
   - Docs утверждают "Phase 2.1 ready to execute"
   - Reality: Phase 2 на 70-80% выполнена, Phase 3 на 40-50%
   - **Проблема:** Разработчики продолжали рефакторинг без обновления стандартов

---

## Impact Assessment

### Затронутые компоненты

#### 🔴 HIGH IMPACT
- **PageController:** Mixed response formats
- **Frontend API Client:** Ожидает camelCase, получает snake_case
- **Vue Components:** Не могут корректно читать response properties

#### 🟡 MEDIUM IMPACT
- **Use Cases:** Используют camelCase внутри, но не контролируют output
- **DTOs:** Определены в camelCase, но не применяются для response serialization

#### 🟢 LOW IMPACT
- **Domain Entities:** Корректны (camelCase)
- **Repositories:** Корректны (DB snake_case ↔ Entity camelCase)
- **Frontend Mappers:** Корректны, но бесполезны если backend непоследователен

---

## Решение

### Option 1: Backend Response Transformer Layer (✅ РЕКОМЕНДУЕТСЯ)

**Создать:** `Presentation/Transformer/` directory

```php
// EntityToArrayTransformer.php
class EntityToArrayTransformer
{
    /**
     * Convert Page entity to camelCase array for JSON response
     */
    public static function pageToArray(Page $page): array
    {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'showInMenu' => $page->getShowInMenu(),      // ✅ camelCase
            'createdBy' => $page->getCreatedBy(),        // ✅ camelCase
            'menuOrder' => $page->getMenuOrder(),        // ✅ camelCase
            'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            // ... all fields
        ];
    }

    public static function blockToArray(Block $block): array
    {
        return [
            'id' => $block->getId(),
            'pageId' => $block->getPageId(),      // ✅ camelCase
            'customName' => $block->getCustomName(), // ✅ camelCase
            // ...
        ];
    }
}
```

**Применить в Controllers:**
```php
// PageController::get()
$page = $this->getPageWithBlocks->execute($request);
$pageArray = EntityToArrayTransformer::pageToArray($page);
$pageArray['blocks'] = array_map(
    [EntityToArrayTransformer::class, 'blockToArray'],
    $page->getBlocks()
);
$this->jsonResponse(['page' => $pageArray], 200);
```

**Advantages:**
- ✅ Single source of truth для response format
- ✅ Все controllers используют одинаковую конвенцию
- ✅ Легко тестировать
- ✅ Соответствует Clean Architecture (Presentation layer concern)

**Disadvantages:**
- Требует рефакторинга всех controllers
- Дополнительный boilerplate код

---

### Option 2: DTO Response Serialization (альтернатива)

**Добавить в DTOs:**
```php
// CreatePageResponse.php
final class CreatePageResponse
{
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'pageId' => $this->pageId,  // ✅ camelCase
            'message' => $this->message,
        ];
    }
}
```

**Применить в Controllers:**
```php
// PageController::create()
$response = $this->createPage->execute($request);
$this->jsonResponse($response->toArray(), 201);  // ✅ Всегда camelCase
```

**Advantages:**
- Меньше новых файлов
- DTOs контролируют свою сериализацию

**Disadvantages:**
- DTOs отвечают за presentation concern (нарушение разделения ответственности)
- Не покрывает endpoints без DTOs (list(), etc.)

---

### Option 3: JSON Serializer Middleware (наиболее масштабируемо)

**Создать:** `Infrastructure/Serializer/JsonSerializer.php`

```php
class JsonSerializer
{
    /**
     * Recursively convert all array keys to camelCase
     */
    public static function toCamelCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camelKey = self::snakeToCamel($key);
            $result[$camelKey] = is_array($value)
                ? self::toCamelCase($value)
                : $value;
        }
        return $result;
    }
}
```

**Применить в JsonResponseTrait:**
```php
trait JsonResponseTrait
{
    protected function jsonResponse($data, int $statusCode = 200): void
    {
        header('Content-Type: application/json', true, $statusCode);
        http_response_code($statusCode);
        
        // ✅ Всегда конвертировать в camelCase
        $normalized = JsonSerializer::toCamelCase($data);
        echo json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }
}
```

**Advantages:**
- ✅ Автоматическая нормализация ВСЕХ responses
- ✅ Не требует изменения controllers
- ✅ Единственное место для исправления

**Disadvantages:**
- Может скрыть проблемы в коде (маскирует ошибки)
- Performance overhead (рекурсивная обработка каждого response)

---

## Recommended Solution

**Комбинация Option 1 + Option 3:**

1. **Short-term fix:** Добавить `JsonSerializer::toCamelCase()` в `JsonResponseTrait`
   - ✅ Немедленно исправляет все responses
   - Позволяет frontend работать корректно

2. **Long-term refactoring:** Создать `EntityToArrayTransformer`
   - Постепенно рефакторить controllers
   - Удалить automatic serialization после завершения рефакторинга
   - Оставить явные transformers для maintainability

---

## Action Plan

### Phase 1: Hotfix (1-2 часа)
1. ✅ Создать `JsonSerializer::toCamelCase()`
2. ✅ Применить в `JsonResponseTrait::jsonResponse()`
3. ✅ Протестировать критические endpoints:
   - POST /api/pages → должен вернуть `{success, pageId}`
   - GET /api/pages/:id → должен вернуть `{page: {showInMenu, createdBy}}`
   - GET /api/pages → должен вернуть массив с `createdAt`, `updatedAt`

### Phase 2: Proper Implementation (3-5 дней)
4. Создать `Presentation/Transformer/EntityToArrayTransformer.php`
5. Реализовать:
   - `pageToArray(Page $page): array`
   - `blockToArray(Block $block): array`
   - `userToArray(User $user): array`
6. Рефакторить controllers по одному:
   - PageController ✅
   - MenuController
   - MediaController
   - AuthController
7. Удалить automatic serialization из `JsonResponseTrait`
8. Добавить E2E тесты для проверки response format

### Phase 3: Documentation (1 день)
9. Обновить `API_CONTRACT.md`:
   - Задокументировать: все responses в camelCase
   - Указать: все requests принимают camelCase (через frontend mappers)
10. Создать `RESPONSE_FORMAT_STANDARDS.md`:
    - Правила именования properties
    - Примеры использования transformers
11. Обновить `BACKEND_CURRENT_STATE.md`:
    - Отразить реальный статус Phase 2-3 (70-80% / 40-50%)

---

## Testing Checklist

### Manual Testing
- [ ] POST /api/pages → response.pageId exists (not page_id)
- [ ] GET /api/pages/:id → page.showInMenu, page.createdBy (not snake_case)
- [ ] GET /api/pages → array items have createdAt, updatedAt
- [ ] PATCH /api/pages/:id/inline → response format consistent
- [ ] Frontend editor saves/loads pages correctly

### Automated Testing
- [ ] E2E test: Create page → response has camelCase keys
- [ ] E2E test: Get page → all properties camelCase
- [ ] Unit test: EntityToArrayTransformer::pageToArray()
- [ ] Unit test: JsonSerializer::toCamelCase() с вложенными массивами

---

## Lessons Learned

### Что привело к проблеме:
1. **Постепенная миграция без централизованного плана** → разные части кода используют разные конвенции
2. **Отсутствие API contract testing** → никто не заметил mixed formats в responses
3. **Документация не обновлялась** → разработчики не знали текущего состояния системы

### Как предотвратить в будущем:
1. **API Contract Tests:** Добавить E2E тесты, проверяющие формат каждого response
2. **Code Review Checklist:** Проверять naming convention в каждом новом endpoint
3. **Automated Linting:** PHPStan custom rule: "All JSON responses must use camelCase keys"
4. **Documentation-as-Code:** Генерировать OpenAPI spec из кода (чтобы docs не отставали)

---

## References

**Affected Files:**
- ✅ `frontend/utils/mappers.js` — корректен, но требует backend compliance
- ❌ `backend/src/Presentation/Controller/PageController.php` — mixed naming
- ⚠️ `backend/src/Application/UseCase/CreatePage.php` — dual format support (костыль)
- ⚠️ `backend/src/Application/UseCase/UpdatePage.php` — dual format support (костыль)
- ✅ `backend/src/Infrastructure/Repository/MySQLPageRepository.php` — корректен

**Related Documentation:**
- `docs/API_CONTRACT.md` — требует обновления (добавить response format rules)
- `docs/BACKEND_CURRENT_STATE.md` — требует обновления (отразить реальный прогресс)
- `docs/CLEAN_ARCHITECTURE_VIOLATIONS_ANALYSIS.md` — нужно добавить эту проблему

---

## Заключение

**Проблема синхронизирующего слоя реальна и критична:**
- Frontend корректно конвертирует requests (camelCase → snake_case)
- Backend НЕ корректно конвертирует responses (mixed snake_case/camelCase)
- Это приводит к ошибкам в production при чтении response properties

**Решение:**
1. **Hotfix:** Automatic camelCase serialization в JsonResponseTrait
2. **Refactoring:** EntityToArrayTransformer для явного контроля
3. **Testing:** E2E тесты для предотвращения регрессии

**Приоритет:** 🔴 ВЫСОКИЙ — должен быть исправлен перед production deploy.
