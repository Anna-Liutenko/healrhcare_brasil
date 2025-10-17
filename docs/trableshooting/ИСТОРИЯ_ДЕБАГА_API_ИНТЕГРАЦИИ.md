# 📋 История отладки API интеграции Visual Editor ↔ Backend

**Дата:** 3-4 октября 2025
**Проект:** Healthcare Brazil CMS
**Компоненты:**
- Frontend: Visual Editor (Vue 3, Composition API)
- Backend: PHP 8.2 (Clean Architecture, DDD)
- API: REST JSON
- База данных: MySQL 8.0

---

## 🎯 Общий контекст

### Исходная задача
Интегрировать визуальный редактор страниц с PHP бэкендом для:
1. Аутентификации пользователя
2. Сохранения страниц с блоками в базу данных
3. Публикации страниц
4. Загрузки страниц из API

### Стартовое состояние
- ✅ Бэкенд полностью готов (API endpoints, database, authentication)
- ✅ Visual Editor работает с localStorage
- ❌ Нет интеграции между фронтендом и бэкендом
- ❌ Нет API клиента на фронтенде

---

## 🐛 Проблема #1: Авторизация

### Симптомы
1. Форма входа показывала буквально `{{ loginError }}` и `{{ isLoggingIn }}` вместо переменных Vue
2. После успешного входа, при перезагрузке страницы пользователь "забывался"
3. Ошибка: `Cannot read properties of undefined (reading 'id')`

### Что было не так

#### 1.1. Модальное окно входа было ВНЕ Vue-приложения
```html
<!-- НЕПРАВИЛЬНО -->
    </div> <!-- Конец #app -->
</div>

<!-- Login Modal -->
<div v-if="showLoginModal">...</div>
```

**Проблема:** Vue не контролировал этот элемент, поэтому все директивы (`v-if`, `{{ }}`) отображались как текст.

#### 1.2. API возвращал пользователя напрямую, а код ожидал вложенный объект
```javascript
// api-client.js (НЕПРАВИЛЬНО)
async getCurrentUser() {
    const data = await this.request('/api/auth/me');
    this.currentUser = data.user;  // ❌ Но API возвращает просто data!
    return data.user;
}
```

**Проблема:** API возвращал `{ id: "...", username: "anna", ... }`, а код искал `data.user`, получая `undefined`.

#### 1.3. Нет персистентности после перезагрузки
```javascript
// editor.js (НЕПРАВИЛЬНО)
async mounted() {
    this.isAuthenticated = this.apiClient.isAuthenticated();
    // ❌ Не загружаем пользователя!
}
```

**Проблема:** Токен сохранялся в localStorage, но объект `currentUser` терялся при перезагрузке страницы.

### Как исправили

#### 1.1. Переместили модальное окно внутрь `<div id="app">`
```html
<!-- ПРАВИЛЬНО -->
<div id="app">
    <!-- весь контент -->

    <!-- Login Modal -->
    <div v-if="showLoginModal">...</div>
</div> <!-- Конец #app -->
```

#### 1.2. Исправили обработку ответа API
```javascript
// api-client.js (ПРАВИЛЬНО)
async getCurrentUser() {
    const data = await this.request('/api/auth/me');
    this.currentUser = data;  // ✅ Используем data напрямую
    return data;
}
```

#### 1.3. Добавили персистентность через localStorage
```javascript
// editor.js (ПРАВИЛЬНО)
async mounted() {
    this.isAuthenticated = this.apiClient.isAuthenticated();

    if (this.isAuthenticated) {
        // Быстро загружаем из localStorage
        const savedUser = localStorage.getItem('cms_current_user');
        if (savedUser) {
            this.currentUser = JSON.parse(savedUser);
        }

        // Проверяем актуальность через API
        const apiUser = await this.apiClient.getCurrentUser();
        this.currentUser = apiUser;

        // Обновляем localStorage
        localStorage.setItem('cms_current_user', JSON.stringify(apiUser));
    }
}

// При логине
async handleLogin() {
    const result = await this.apiClient.login(...);
    this.currentUser = result.user;

    // Сохраняем в localStorage
    localStorage.setItem('cms_current_user', JSON.stringify(result.user));
}
```

---

## 🐛 Проблема #2: HTTP 400 при сохранении страницы

### Симптомы
```
HTTP 400: Bad Request
```
Страница не сохранялась, но детали ошибки были неясны.

### Что было не так (Хронология попыток)

#### 2.1. Попытка 1: Неправильный тип страницы
```javascript
// editor.js (НЕПРАВИЛЬНО)
const pageData = {
    type: 'page',  // ❌ Невалидное значение enum
    // ...
};
```

**Проблема:** Backend ожидал один из: `regular`, `article`, `guide`, `collection`.
**Решение:** Изменили на `type: 'regular'`.
**Результат:** ❌ Ошибка 400 осталась.

#### 2.2. Попытка 2: Двойная сериализация JSON
```javascript
// editor.js (НЕПРАВИЛЬНО)
blocks: this.blocks.map(block => ({
    data: JSON.stringify(block.data)  // ❌ Первый stringify
}))

// api-client.js
async createPage(pageData) {
    return await this.request('/api/pages', {
        body: JSON.stringify(pageData)  // ❌ Второй stringify!
    });
}
```

**Проблема:** `block.data` превращался в строку `"{\"title\":\"...\"}"`, затем снова в строку.
**Решение:** Убрали первый `JSON.stringify`.
**Результат:** ❌ Ошибка 400 осталась.

#### 2.3. Попытка 3: Vue Proxy объекты
```javascript
// editor.js (НЕПРАВИЛЬНО)
blocks: this.blocks.map(block => ({
    data: block.data  // ❌ Это Vue Proxy объект!
}))
```

**Проблема:** Vue 3 оборачивает реактивные объекты в Proxy, который не сериализуется правильно.
**Решение:** Конвертируем в обычный объект через `JSON.parse(JSON.stringify())`.
**Результат:** ❌ Ошибка 400 осталась.

#### 2.4. Попытка 4: Недостаточная обработка ошибок
```javascript
// api-client.js (НЕПРАВИЛЬНО)
if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
}
```

**Проблема:** Backend возвращал детали ошибки в JSON, но мы их не читали.
**Решение:** Улучшили обработку ошибок:

```javascript
// api-client.js (ПРАВИЛЬНО)
if (!response.ok) {
    const errorDetails = {
        status: response.status,
        statusText: response.statusText,
        message: data.message || data.error || 'Unknown error',
        details: data.details || data.errors || null,
        fullResponse: data
    };

    let errorMessage = `HTTP ${response.status}: ${errorDetails.message}`;
    if (errorDetails.details) {
        errorMessage += `\nДетали: ${JSON.stringify(errorDetails.details, null, 2)}`;
    }

    throw new Error(errorMessage);
}
```

**Результат:** ✅ **Теперь увидели реальную ошибку:**

```
HTTP 400: Slug must contain only lowercase letters, numbers, and hyphens
```

#### 2.5. Истинная проблема: Кириллица в slug
```javascript
// editor.js (НЕПРАВИЛЬНО)
generateSlug(title) {
    return title
        .toLowerCase()
        .replace(/[^a-z0-9а-я]+/g, '-')  // ❌ Разрешает кириллицу!
        .replace(/^-+|-+$/g, '');
}

// Результат: "Новая страница" → "новая-страница"
```

**Проблема:** Slug содержал кириллицу, а backend требует только латиницу.

**Решение:** Добавили транслитерацию:

```javascript
// editor.js (ПРАВИЛЬНО)
generateSlug(title) {
    const translitMap = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
        'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya'
    };

    return title
        .toLowerCase()
        .split('')
        .map(char => translitMap[char] || char)
        .join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Результат: "Новая страница" → "novaya-stranitsa" ✅
```

**Результат:** ✅ Страница успешно создалась!

---

## 🐛 Проблема #3: Блоки не сохраняются

### Симптомы
```sql
SELECT COUNT(*) FROM blocks WHERE page_id = '...';
-- Результат: 0
```

Страница создавалась, но без блоков.

### Что было не так

#### 3.1. Backend игнорировал блоки при создании
```php
// PageController.php (НЕПРАВИЛЬНО)
public function create(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $pageRepository = new MySQLPageRepository();

    $useCase = new CreatePage($pageRepository);
    $page = $useCase->execute($data);  // ✅ Создаёт страницу

    // ❌ $data['blocks'] игнорируется!

    $this->jsonResponse([
        'success' => true,
        'pageId' => $page->getId()
    ], 201);
}
```

**Проблема:** Use Case `CreatePage` создавал только страницу, но не работал с блоками.

**Решение:** Добавили сохранение блоков в контроллер:

```php
// PageController.php (ПРАВИЛЬНО)
public function create(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $pageRepository = new MySQLPageRepository();
    $blockRepository = new MySQLBlockRepository();

    // Создаём страницу
    $useCase = new CreatePage($pageRepository);
    $page = $useCase->execute($data);

    // ✅ Сохраняем блоки (если есть)
    if (isset($data['blocks']) && is_array($data['blocks'])) {
        foreach ($data['blocks'] as $index => $blockData) {
            $block = new \Domain\Entity\Block(
                id: \Ramsey\Uuid\Uuid::uuid4()->toString(),
                pageId: $page->getId(),
                type: $blockData['type'] ?? 'text-block',
                position: $blockData['position'] ?? $index,
                data: $blockData['data'] ?? [],
                customName: $blockData['custom_name'] ?? null
            );

            $blockRepository->save($block);
        }
    }

    $this->jsonResponse([
        'success' => true,
        'pageId' => $page->getId()
    ], 201);
}
```

#### 3.2. Несоответствие naming convention (camelCase vs snake_case)
```php
// UpdatePage.php (НЕПРАВИЛЬНО)
$block = new Block(
    // ...
    customName: $blockData['customName'] ?? null  // ❌ Но фронтенд шлёт custom_name!
);
```

**Проблема:** Фронтенд отправлял `custom_name` (snake_case), а бэкенд ожидал `customName` (camelCase).

**Решение:** Изменили на `$blockData['custom_name']`:

```php
// UpdatePage.php (ПРАВИЛЬНО)
$block = new Block(
    id: Uuid::uuid4()->toString(),
    pageId: $pageId,
    type: $blockData['type'] ?? 'text-block',
    position: $blockData['position'] ?? $index,
    data: $blockData['data'] ?? [],
    customName: $blockData['custom_name'] ?? null  // ✅ snake_case
);
```

**Результат:** ✅ Все 5 блоков успешно сохранились!

---

## 🛠️ Проблема #4: Отсутствие инструментов отладки

### Симптомы
- Приходилось постоянно открывать DevTools → Network tab → Payload
- Логи разбросаны по консоли
- Сложно отследить полный flow выполнения

### Решение: Визуальная панель отладки

#### 4.1. Создали компонент Debug Panel
```javascript
// editor.js
debugMsg(message, type = 'info', data = null) {
    const timestamp = new Date().toLocaleTimeString('ru-RU');
    this.debugLog.push({
        time: timestamp,
        message,
        type, // info, success, warning, error
        data: data ? JSON.stringify(data, null, 2) : null
    });

    // Также выводим в консоль
    const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
    console[consoleMethod](`[${timestamp}] ${message}`, data || '');

    // Автопрокрутка
    this.$nextTick(() => {
        const debugContent = document.querySelector('.debug-content');
        if (debugContent) {
            debugContent.scrollTop = debugContent.scrollHeight;
        }
    });
}
```

#### 4.2. UI для панели
```html
<!-- Debug Panel -->
<div v-if="showDebugPanel" class="debug-panel">
    <div class="debug-header">
        <span>🐛 Debug Log</span>
        <div>
            <button @click="clearDebugLog">Очистить</button>
            <button @click="showDebugPanel = false">✕</button>
        </div>
    </div>
    <div class="debug-content">
        <div v-for="(log, index) in debugLog" :key="index"
             class="debug-log-item" :class="'log-' + log.type">
            <span class="log-time">{{ log.time }}</span>
            <span class="log-message">{{ log.message }}</span>
            <pre v-if="log.data" class="log-data">{{ log.data }}</pre>
        </div>
    </div>
</div>
```

#### 4.3. Интеграция в критические методы
```javascript
async savePage() {
    this.debugMsg('========== НАЧАЛО СОХРАНЕНИЯ ==========', 'info');

    if (!this.isAuthenticated) {
        this.debugMsg('Требуется авторизация', 'error');
        return;
    }

    this.debugMsg('Авторизация OK', 'success', {
        userId: this.currentUser.id,
        username: this.currentUser.username
    });

    const pageData = { /* ... */ };

    this.debugMsg('Подготовленные данные страницы (pageData)', 'info', pageData);

    pageData.blocks.forEach((block, i) => {
        this.debugMsg(`Блок ${i}: ${block.type}`, 'info', {
            type: block.type,
            position: block.position,
            data: block.data
        });
    });

    try {
        const response = await this.apiClient.createPage(pageData);
        this.debugMsg('Ответ от API после создания', 'success', response);
    } catch (error) {
        this.debugMsg('ОШИБКА СОХРАНЕНИЯ', 'error', {
            message: error.message,
            stack: error.stack
        });
    }
}
```

**Результат:** ✅ Теперь весь процесс сохранения виден в удобном интерфейсе с цветовой кодировкой и временными метками!

---

## 📊 Итоговая статистика проблем

| # | Проблема | Время на поиск | Решение | Сложность |
|---|----------|----------------|---------|-----------|
| 1 | Login modal вне Vue app | 10 мин | Переместить в #app | 🟢 Легко |
| 2 | API возвращает user напрямую | 15 мин | Убрать data.user | 🟢 Легко |
| 3 | Нет персистентности user | 20 мин | localStorage + mount | 🟡 Средне |
| 4 | Неправильный тип страницы | 5 мин | Изменить на 'regular' | 🟢 Легко |
| 5 | Двойной JSON.stringify | 10 мин | Убрать первый | 🟡 Средне |
| 6 | Vue Proxy объекты | 15 мин | JSON.parse(JSON.stringify()) | 🟡 Средне |
| 7 | Недостаточная обработка ошибок | 30 мин | Улучшить errorDetails | 🟡 Средне |
| 8 | Кириллица в slug | 45 мин | Транслитерация | 🟡 Средне |
| 9 | Блоки не сохраняются | 20 мин | Добавить в create() | 🟢 Легко |
| 10 | custom_name vs customName | 10 мин | Использовать snake_case | 🟢 Легко |

**Итого:** ~3 часа отладки

---

## 💡 Что нужно было сделать изначально

### 1. На этапе проектирования

#### 1.1. Согласовать API Contract
```yaml
# API_CONTRACT.yaml (Пример)
POST /api/pages:
  request:
    title: string (required)
    slug: string (required, lowercase latin + numbers + hyphens)
    type: enum(regular, article, guide, collection)
    createdBy: uuid (required)
    blocks: array
      - type: string
        custom_name: string | null  # ✅ Явно указать naming
        position: integer
        data: object
  response:
    success: boolean
    pageId: uuid
```

**Почему важно:**
- Избежали бы проблемы с `type: 'page'`
- Сразу знали бы о требованиях к slug
- Понимали бы формат `custom_name` vs `customName`

#### 1.2. Документировать валидацию данных
```php
// CreatePage.php
/**
 * VALIDATION RULES:
 * - title: required, min 1 char
 * - slug: required, regex: /^[a-z0-9-]+$/
 * - type: enum (regular|article|guide|collection)
 * - createdBy: required, valid UUID
 * - blocks: optional, array of Block objects
 */
```

### 2. На этапе разработки

#### 2.1. Написать интеграционные тесты
```javascript
// tests/api/create-page.test.js
describe('POST /api/pages', () => {
    it('should reject slug with cyrillic characters', async () => {
        const response = await fetch('/api/pages', {
            body: JSON.stringify({
                slug: 'новая-страница',  // Кириллица
                // ...
            })
        });

        expect(response.status).toBe(400);
        expect(response.json().message).toContain('lowercase letters');
    });

    it('should save page with blocks', async () => {
        const response = await createPage({
            title: 'Test',
            slug: 'test-page',
            blocks: [{ type: 'main-screen', data: {} }]
        });

        const blocks = await getBlocks(response.pageId);
        expect(blocks.length).toBe(1);  // ✅ Проверяем, что блок сохранился
    });
});
```

**Почему важно:**
- Обнаружили бы проблему с кириллицей сразу
- Заметили бы, что блоки не сохраняются

#### 2.2. Добавить детальное логирование с самого начала
```javascript
// api-client.js (С САМОГО НАЧАЛА)
async request(endpoint, options = {}) {
    console.log('📤 API Request:', {
        url: `${API_BASE_URL}${endpoint}`,
        method: config.method || 'GET',
        body: config.body
    });

    const response = await fetch(url, config);
    const data = await response.json();

    console.log('📥 API Response:', {
        status: response.status,
        data
    });

    // ✅ Детальная обработка ошибок
    if (!response.ok) {
        const errorDetails = {
            message: data.message || data.error,
            details: data.details || data.errors,
            fullResponse: data
        };
        console.error('❌ API Error:', errorDetails);
        throw new Error(errorDetails.message);
    }

    return data;
}
```

#### 2.3. Создать Debug Panel с первой итерации
- Упростило бы отладку в разы
- Не нужно было бы постоянно смотреть в DevTools

### 3. На этапе интеграции

#### 3.1. Проверить Vue Reactivity
```javascript
// Добавить utility функцию
function toPlainObject(obj) {
    return JSON.parse(JSON.stringify(obj));
}

// Использовать везде для API данных
const pageData = {
    blocks: this.blocks.map(block => ({
        data: toPlainObject(block.data)  // ✅
    }))
};
```

#### 3.2. Проверить naming consistency
```javascript
// Создать mapper
const blockToAPI = (block) => ({
    type: block.type,
    custom_name: block.customName,  // camelCase → snake_case
    position: block.position,
    data: toPlainObject(block.data)
});

const blockFromAPI = (apiBlock) => ({
    type: apiBlock.type,
    customName: apiBlock.custom_name,  // snake_case → camelCase
    position: apiBlock.position,
    data: apiBlock.data
});
```

#### 3.3. Добавить транслитерацию сразу
```javascript
// utils/transliterate.js
export function transliterate(text) {
    const map = { /* ... */ };
    return text.split('').map(c => map[c] || c).join('');
}

// editor.js
import { transliterate } from './utils/transliterate.js';

generateSlug(title) {
    return transliterate(title)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
```

---

## 📚 Уроки и выводы

### Для разработки с AI

#### ✅ Хорошие практики
1. **Тестирование API вручную перед интеграцией** - curl запросы помогли выявить валидацию slug
2. **Детальное логирование** - Debug Panel сэкономил часы работы
3. **Проверка данных на каждом этапе** - логи this.blocks → pageData → API payload
4. **Чтение полного ответа от API** - errorDetails показали реальную проблему

#### ❌ Что AI упустил
1. **Vue Proxy объекты** - забыли, что Vue 3 оборачивает данные в Proxy
2. **Двойная сериализация** - не учли, что fetch() сам делает stringify
3. **Кириллица в slug** - не проверили валидацию backend перед написанием кода
4. **Блоки в CreatePage** - не заметили, что Use Case не работает с блоками
5. **Naming convention** - не согласовали camelCase vs snake_case

### Для будущих проектов

#### Обязательный checklist перед интеграцией:
- [ ] API контракт документирован
- [ ] Валидация данных известна
- [ ] Naming convention согласован (camelCase / snake_case / kebab-case)
- [ ] Интеграционные тесты написаны
- [ ] Детальное логирование добавлено
- [ ] Debug tools готовы
- [ ] Reactivity framework учтён (Vue Proxy, React state, etc.)
- [ ] Тестовые curl запросы работают

#### Паттерны отладки:
1. **Логировать данные ДО и ПОСЛЕ трансформации**
2. **Проверять Network tab перед исправлением backend**
3. **Читать полный ответ ошибки от API**
4. **Использовать визуальные инструменты отладки**
5. **Тестировать с реальными данными (кириллица, спецсимволы)**

---

## 🎉 Итоговое состояние

### ✅ Что работает
- Авторизация с персистентностью
- Сохранение страниц с блоками в базу
- Транслитерация slug (кириллица → латиница)
- Обновление страниц
- Debug Panel с детальными логами
- API возвращает детальные ошибки

### 📊 Результаты
```sql
-- Проверка сохранённой страницы
SELECT id, title, slug, type, status FROM pages
WHERE id = '75f53538-dd6c-489a-9b20-d0004bb5086b';

-- id: 75f53538-dd6c-489a-9b20-d0004bb5086b
-- title: Новая страница
-- slug: novaya-stranitsa  ✅ Транслитерация работает
-- type: regular          ✅ Правильный enum
-- status: published      ✅ Автоматическая публикация

-- Проверка блоков
SELECT COUNT(*) FROM blocks WHERE page_id = '75f53538-dd6c-489a-9b20-d0004bb5086b';
-- Результат: 5 блоков  ✅ Все сохранились
```

### 📁 Изменённые файлы
```
C:\xampp\htdocs\visual-editor\
├── api-client.js              # ✅ Создан, улучшена обработка ошибок
├── index.html                 # ✅ Login modal, Debug Panel
├── editor.js                  # ✅ Auth, save, debug methods, transliteration
└── styles.css                 # ✅ Debug Panel стили

C:\xampp\htdocs\healthcare-backend\src\
├── Presentation\Controller\
│   └── PageController.php     # ✅ Сохранение блоков в create()
└── Application\UseCase\
    └── UpdatePage.php         # ✅ Исправлен custom_name

C:\xampp\htdocs\healthcare-cms-frontend\
└── page.html                  # ✅ Создан фронтенд для отображения
```

---

## 🔗 Связанные документы
- `Отладка ошибки 400.md` - Предыдущая версия документации
- `CMS_DEVELOPMENT_PLAN.md` - План разработки проекта
- `PROJECT_STRUCTURE.md` - Структура проекта

---

**Статус:** ✅ Проблемы решены, API интеграция работает полностью
**Последнее обновление:** 4 октября 2025, 23:50
