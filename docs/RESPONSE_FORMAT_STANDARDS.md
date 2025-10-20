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
3. После Phase 2 — удалить fallback, оставить только camelCase</content>
<parameter name="filePath">c:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\docs\RESPONSE_FORMAT_STANDARDS.md