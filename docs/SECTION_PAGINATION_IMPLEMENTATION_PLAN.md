# 📋 План реализации: Section-Based Pagination для коллекций
**Дата:** 21 октября 2025  
**Цель:** Разделить коллекцию на независимые секции (Гайды и Статьи) с отдельной пагинацией

---

## 🎯 Общая концепция

### Текущее поведение:
- Коллекция показывает ВСЕ страницы (guides + articles) вместе
- Пагинация применяется ко всему списку
- Результат: 12 элементов могут быть смесью гайдов и статей

### Желаемое поведение:
- Коллекция имеет вкладки: **"Гайды"** и **"Статьи"**
- Каждая вкладка — независимая секция с собственной пагинацией
- URL: `?section=guides&page=1` или `?section=articles&page=1`
- На странице отображается **только одна секция** (12 гайдов ИЛИ 12 статей)

---

## 📅 Этап 1: Исправление багов безопасности (5-10 мин)

### 🔴 Задача 1.1: Исправить баг getSnippet() в UpdateCollectionCardImage
**Файл:** `backend/src/Application/UseCase/UpdateCollectionCardImage.php`  
**Строка:** ~46

**Проблема:**
```php
'snippet' => $targetPage->getSnippet(),  // ❌ Метод не существует!
```

**Решение:**
```php
'snippet' => $targetPage->getSeoDescription() ?? '',  // ✅ Правильный метод
```

**Шаги:**
1. Открыть `UpdateCollectionCardImage.php`
2. Найти строку 46 с `getSnippet()`
3. Заменить на `getSeoDescription() ?? ''`
4. Сохранить файл

---

### 🔴 Задача 1.2: Добавить валидацию imageUrl
**Файл:** `backend/src/Application/UseCase/UpdateCollectionCardImage.php`  
**Метод:** `execute()`

**Проблема:**
- `$imageUrl` принимается без валидации
- Может быть XSS через `javascript:alert(1)` или `data:text/html,...`

**Решение:** Добавить валидацию перед `setCardImage()`

```php
public function execute(string $collectionPageId, string $targetPageId, string $imageUrl): array
{
    // Validation: sanitize and check URL scheme
    $sanitized = filter_var($imageUrl, FILTER_SANITIZE_URL);
    if (!$sanitized || !filter_var($sanitized, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Invalid image URL');
    }
    
    // Block dangerous schemes
    if (preg_match('/^(javascript|data|vbscript):/i', $sanitized)) {
        throw new InvalidArgumentException('Unsafe URL scheme');
    }
    
    // Ensure HTTPS or relative path
    if (!preg_match('~^(https://|/)~i', $sanitized)) {
        throw new InvalidArgumentException('URL must be HTTPS or relative path');
    }

    $collection = $this->pageRepository->findById($collectionPageId);
    // ... rest of the method
    
    $targetPage->setCardImage($sanitized);  // Use sanitized URL
    // ...
}
```

**Шаги:**
1. Открыть `UpdateCollectionCardImage.php`
2. В начале метода `execute()` добавить блок валидации (после строки с `public function execute(...)`)
3. Заменить `$imageUrl` на `$sanitized` в вызове `setCardImage()`
4. Сохранить файл

---

### 🔴 Задача 1.3: Добавить auth check в CollectionController
**Файл:** `backend/src/Presentation/Controller/CollectionController.php`  
**Метод:** `patchCardImage()` (или аналогичный для PATCH /card-image)

**Проблема:**
- Endpoint доступен без проверки авторизации
- Любой может менять картинки карточек

**Решение:** Добавить проверку роли пользователя

```php
public function patchCardImage(string $collectionId): void
{
    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Role check (только admin и editor)
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, ['super_admin', 'admin', 'editor'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    // CSRF check (если используется)
    // validateCsrfToken($_POST['csrf_token'] ?? '');
    
    // Continue with existing logic
    $input = json_decode(file_get_contents('php://input'), true);
    // ...
}
```

**Шаги:**
1. Открыть `CollectionController.php`
2. Найти метод для PATCH /card-image
3. В начале метода добавить auth и role checks
4. Сохранить файл

---

## 📅 Этап 2: Реализация section-based pagination (30-40 мин)

### 🟡 Задача 2.1: Обновить GetCollectionItems use case
**Файл:** `backend/src/Application/UseCase/GetCollectionItems.php`

**Цель:** Добавить параметр `$sectionSlug` для фильтрации по типу страницы

**Текущая сигнатура:**
```php
public function execute(string $collectionPageId, int $page = 1, int $limit = 12): array
```

**Новая сигнатура:**
```php
public function execute(
    string $collectionPageId, 
    ?string $sectionSlug = null,
    int $page = 1, 
    int $limit = 12
): array
```

**Изменения в логике:**

#### Шаг 2.1.1: Определить mapping секций
```php
// В начале метода execute()
// Map section slug to page types
$sectionTypeMap = [
    'guides' => ['guide'],
    'articles' => ['article'],
    null => ['guide', 'article']  // default: все типы
];

// Validate section
if ($sectionSlug !== null && !isset($sectionTypeMap[$sectionSlug])) {
    throw new InvalidArgumentException("Invalid section: {$sectionSlug}");
}

// Get allowed types for this section
$allowedTypes = $sectionTypeMap[$sectionSlug];
```

#### Шаг 2.1.2: Фильтровать страницы по типам секции
**Найти блок:**
```php
// 3. Загрузить все опубликованные страницы нужных типов
$allPages = [];
foreach ($sourceTypes as $type) {
    $pages = $this->pageRepository->findByTypeAndStatus($type, 'published');
    $allPages = array_merge($allPages, $pages);
}
```

**Заменить на:**
```php
// 3. Загрузить страницы, отфильтрованные по секции
$allPages = [];
foreach ($sourceTypes as $type) {
    // Skip types not in current section
    if (!in_array($type, $allowedTypes, true)) {
        continue;
    }
    
    $pages = $this->pageRepository->findByTypeAndStatus($type, 'published');
    $allPages = array_merge($allPages, $pages);
}
```

#### Шаг 2.1.3: Обновить возврат данных (добавить секцию в meta)
**Найти блок:**
```php
// 9. Добавить мета-информацию о пагинации
$result['pagination'] = [
    'currentPage' => $page,
    'totalPages' => $totalPages,
    'totalItems' => $totalItems,
    'itemsPerPage' => $limit,
    'hasNextPage' => $page < $totalPages,
    'hasPrevPage' => $page > 1
];
```

**Добавить:**
```php
$result['pagination'] = [
    'currentPage' => $page,
    'totalPages' => $totalPages,
    'totalItems' => $totalItems,
    'itemsPerPage' => $limit,
    'hasNextPage' => $page < $totalPages,
    'hasPrevPage' => $page > 1,
    'currentSection' => $sectionSlug  // ✅ Добавить текущую секцию
];
```

**Полный листинг изменений:** см. раздел "Код для копирования" ниже.

---

### 🟡 Задача 2.2: Обновить CollectionController API
**Файл:** `backend/src/Presentation/Controller/CollectionController.php`  
**Метод:** `getItems()`

**Текущий код:**
```php
public function getItems(string $pageId): void
{
    // Read pagination params
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 12;

    $useCase = new GetCollectionItems($this->pageRepository, $this->blockRepository);
    $result = $useCase->execute($pageId, $page, $limit);
    
    // ... return JSON
}
```

**Новый код:**
```php
public function getItems(string $pageId): void
{
    // Read pagination params
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 12;
    
    // ✅ NEW: Read section param
    $section = $_GET['section'] ?? null;
    
    // Validate section (optional: whitelist)
    if ($section !== null && !in_array($section, ['guides', 'articles'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid section']);
        exit;
    }

    $useCase = new GetCollectionItems($this->pageRepository, $this->blockRepository);
    $result = $useCase->execute($pageId, $section, $page, $limit);  // ✅ Pass section
    
    // ... return JSON
}
```

---

### 🟡 Задача 2.3: Обновить PublicPageController
**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`  
**Метод:** `renderCollectionPage()`

**Текущий код (строка ~463):**
```php
// Read page number from URL
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;

$useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
$collectionData = $useCase->execute($page['id'], $currentPage, $limit);
```

**Новый код:**
```php
// Read page number and section from URL
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$section = $_GET['section'] ?? 'guides';  // ✅ Default to 'guides'
$limit = 12;

// Validate section
if (!in_array($section, ['guides', 'articles'], true)) {
    $section = 'guides';  // Fallback
}

$useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
$collectionData = $useCase->execute($page['id'], $section, $currentPage, $limit);  // ✅ Pass section
```

**Добавить вкладки в HTML (строка ~500, перед `<div class="articles-grid">`)**

```php
// Render section tabs
$html .= '<div class="collection-tabs" style="text-align: center; margin: 2rem 0;">
    <a href="?section=guides&page=1" class="tab-link ' . ($section === 'guides' ? 'active' : '') . '">Гайды</a>
    <a href="?section=articles&page=1" class="tab-link ' . ($section === 'articles' ? 'active' : '') . '">Статьи</a>
</div>';
```

**Добавить CSS для вкладок (в начале HTML, внутри `<style>`)**
```css
.collection-tabs {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 2rem 0;
}
.tab-link {
    padding: 0.75rem 2rem;
    background: var(--bg-accent);
    color: var(--text-dark);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.tab-link:hover {
    background: var(--color-action);
    color: var(--color-white);
}
.tab-link.active {
    background: var(--color-action);
    color: var(--color-white);
}
```

---

## 📅 Этап 3: Синхронизация фронтенда (редактор) (20-30 мин)

### 🟢 Задача 3.1: Обновить editor.js — добавить поддержку секций
**Файл:** `frontend/editor.js`

#### Шаг 3.1.1: Добавить данные для секций
**Найти блок `data()` в Vue app:**
```javascript
data() {
    return {
        // ... existing fields
        collectionPagination: null,
        currentCollectionPage: 1,
        // ✅ ADD:
        currentCollectionSection: 'guides',  // Default section
        availableSections: [
            { slug: 'guides', title: 'Гайды', icon: '📖' },
            { slug: 'articles', title: 'Статьи', icon: '📄' }
        ]
    }
}
```

#### Шаг 3.1.2: Обновить loadCollectionItems()
**Найти метод `loadCollectionItems(page = 1)`:**
```javascript
async loadCollectionItems(page = 1) {
    if (!this.currentPage.id) return;
    
    this.collectionLoading = true;
    try {
        // ✅ ADD section param
        const response = await fetch(
            `${API_BASE}/pages/${this.currentPage.id}/collection-items?page=${page}&limit=12&section=${this.currentCollectionSection}`
        );
        
        const data = await response.json();
        
        if (data.success && data.data.sections) {
            // Обновить текущую секцию (берём первую)
            const section = data.data.sections[0];
            this.collectionItems = section.items || [];
            this.collectionPagination = data.data.pagination;
            this.currentCollectionPage = page;
        }
    } catch (error) {
        console.error('Failed to load collection items:', error);
        alert('Ошибка загрузки коллекции');
    } finally {
        this.collectionLoading = false;
    }
}
```

#### Шаг 3.1.3: Добавить метод переключения секций
```javascript
switchCollectionSection(sectionSlug) {
    this.currentCollectionSection = sectionSlug;
    this.currentCollectionPage = 1;  // Reset to page 1
    this.loadCollectionItems(1);
}
```

---

### 🟢 Задача 3.2: Обновить editor.html — добавить вкладки секций
**Файл:** `frontend/editor.html`

**Найти блок Collection Editor (строка ~314):**
```html
<!-- Collection Editor -->
<div v-if="currentPage.type === 'collection'" class="settings-section">
    <h3>Управление коллекцией</h3>
    
    <!-- ✅ ADD: Section tabs -->
    <div class="collection-section-tabs">
        <button 
            v-for="section in availableSections" 
            :key="section.slug"
            @click="switchCollectionSection(section.slug)"
            :class="['section-tab', { active: currentCollectionSection === section.slug }]"
        >
            <span class="tab-icon">{{ section.icon }}</span>
            <span class="tab-title">{{ section.title }}</span>
        </button>
    </div>
    
    <!-- Existing collection editor UI -->
    <div class="collection-editor">
        <!-- ... existing cards UI -->
    </div>
</div>
```

---

### 🟢 Задача 3.3: Добавить CSS для вкладок в editor-ui.css
**Файл:** `frontend/editor-ui.css`

**Добавить в конец файла:**
```css
/* Collection section tabs */
.collection-section-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--bg-accent);
}

.section-tab {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.section-tab:hover {
    color: var(--text-dark);
    background: var(--bg-accent);
}

.section-tab.active {
    color: var(--color-action);
    border-bottom-color: var(--color-action);
    font-weight: 600;
}

.tab-icon {
    font-size: 1.2rem;
}

.tab-title {
    font-family: var(--font-heading);
}
```

---

## 📅 Этап 4: Синхронизация и тестирование (15-20 мин)

### 🔵 Задача 4.1: Синхронизировать файлы в XAMPP
```powershell
# Запустить sync script
powershell -NoProfile -ExecutionPolicy Bypass -File sync-to-xampp.ps1
```

**Проверить скопированные файлы:**
- `backend/src/Application/UseCase/GetCollectionItems.php`
- `backend/src/Application/UseCase/UpdateCollectionCardImage.php`
- `backend/src/Presentation/Controller/CollectionController.php`
- `backend/src/Presentation/Controller/PublicPageController.php`
- `frontend/editor.js`
- `frontend/editor.html`
- `frontend/editor-ui.css`

---

### 🔵 Задача 4.2: Перезапустить Apache
```powershell
& 'C:\xampp\apache\bin\httpd.exe' -k restart
```

---

### 🔵 Задача 4.3: Тестирование API
**Test 1: Проверить секцию "Гайды"**
```powershell
$r = Invoke-RestMethod -UseBasicParsing 'http://localhost/healthcare-cms-backend/public/api/pages/4b970956-6f44-4922-8b45-faad71252e9d/collection-items?section=guides&page=1&limit=12'
Write-Output "GUIDES_COUNT=" + $r.data.sections[0].items.Count
Write-Output "PAGINATION=" + ($r.data.pagination | ConvertTo-Json -Compress)
```

**Ожидаемый результат:**
- `GUIDES_COUNT` должно быть <= 12
- Все элементы должны иметь `type = 'guide'`
- `pagination.currentSection = 'guides'`

**Test 2: Проверить секцию "Статьи"**
```powershell
$r = Invoke-RestMethod -UseBasicParsing 'http://localhost/healthcare-cms-backend/public/api/pages/4b970956-6f44-4922-8b45-faad71252e9d/collection-items?section=articles&page=1&limit=12'
Write-Output "ARTICLES_COUNT=" + $r.data.sections[0].items.Count
Write-Output "PAGINATION=" + ($r.data.pagination | ConvertTo-Json -Compress)
```

**Ожидаемый результат:**
- `ARTICLES_COUNT` должно быть <= 12
- Все элементы должны иметь `type = 'article'`
- `pagination.currentSection = 'articles'`

---

### 🔵 Задача 4.4: Тестирование публичной страницы
**Открыть в браузере:**
```
http://localhost/healthcare-cms-backend/public/new-page-1761012634503?section=guides&page=1
```

**Проверить:**
1. ✅ Вкладки "Гайды" и "Статьи" отображаются
2. ✅ При клике на вкладку меняется секция (URL меняется на `?section=...`)
3. ✅ На странице "Гайды" показываются только гайды
4. ✅ На странице "Статьи" показываются только статьи
5. ✅ Пагинация работает внутри каждой секции отдельно
6. ✅ Кнопки "Предыдущая" / "Следующая" сохраняют `?section=` параметр

---

### 🔵 Задача 4.5: Тестирование редактора
**Открыть редактор:**
```
http://localhost/healthcare-cms-frontend/editor.html
```

**Шаги:**
1. Залогиниться
2. Открыть страницу коллекции (id = 4b970956-6f44-4922-8b45-faad71252e9d)
3. Прокрутить до секции "Управление коллекцией"

**Проверить:**
1. ✅ Вкладки 📖 Гайды и 📄 Статьи отображаются
2. ✅ При клике на вкладку загружается соответствующая секция
3. ✅ Карточки обновляются (только гайды ИЛИ только статьи)
4. ✅ Пагинация работает внутри текущей вкладки
5. ✅ Кнопка 🖼️ открывает галерею
6. ✅ После выбора картинки делается PATCH /card-image
7. ✅ Картинка обновляется визуально в редакторе

---

### 🔵 Задача 4.6: Проверка безопасности
**Test Auth Check:**
```powershell
# Попытка PATCH без авторизации (должно вернуть 401)
Invoke-RestMethod -Method PATCH `
    -Uri 'http://localhost/healthcare-cms-backend/public/api/pages/4b970956-6f44-4922-8b45-faad71252e9d/card-image' `
    -Body '{"targetPageId":"some-id","imageUrl":"http://example.com/img.jpg"}' `
    -ContentType 'application/json' `
    -ErrorAction Stop
```

**Ожидаемый результат:** HTTP 401 Unauthorized

**Test URL Validation:**
```powershell
# Попытка XSS через javascript: (должно вернуть 400)
# (Требуется валидная сессия/auth)
Invoke-RestMethod -Method PATCH `
    -Uri 'http://localhost/healthcare-cms-backend/public/api/pages/4b970956-6f44-4922-8b45-faad71252e9d/card-image' `
    -Body '{"targetPageId":"some-id","imageUrl":"javascript:alert(1)"}' `
    -ContentType 'application/json' `
    -ErrorAction Stop
```

**Ожидаемый результат:** HTTP 400 или InvalidArgumentException

---

## 📦 Приложение: Полный код для копирования

### A. GetCollectionItems.php (новая версия execute())
```php
public function execute(
    string $collectionPageId, 
    ?string $sectionSlug = null,
    int $page = 1, 
    int $limit = 12
): array
{
    // Map section slug to page types
    $sectionTypeMap = [
        'guides' => ['guide'],
        'articles' => ['article'],
        null => ['guide', 'article']  // default: все типы
    ];

    // Validate section
    if ($sectionSlug !== null && !isset($sectionTypeMap[$sectionSlug])) {
        throw new \InvalidArgumentException("Invalid section: {$sectionSlug}");
    }

    // Get allowed types for this section
    $allowedTypes = $sectionTypeMap[$sectionSlug];

    // 1. Загрузить страницу-коллекцию
    $collectionPage = $this->pageRepository->findById($collectionPageId);
    
    if (!$collectionPage || !$collectionPage->getType()->isCollection()) {
        throw new \InvalidArgumentException('Page is not a collection');
    }
    
    // 2. Прочитать конфигурацию
    $config = $collectionPage->getCollectionConfig() ?? [];
    $sourceTypes = $config['sourceTypes'] ?? ['article', 'guide'];
    $sortBy = $config['sortBy'] ?? 'publishedAt';
    $sortOrder = $config['sortOrder'] ?? 'desc';
    $sections = $config['sections'] ?? null;
    $excludePages = $config['excludePages'] ?? [];
    
    // 3. Загрузить страницы, отфильтрованные по секции
    $allPages = [];
    foreach ($sourceTypes as $type) {
        // Skip types not in current section
        if (!in_array($type, $allowedTypes, true)) {
            continue;
        }
        
        $pages = $this->pageRepository->findByTypeAndStatus($type, 'published');
        $allPages = array_merge($allPages, $pages);
    }
    
    // 4. Исключить страницы из excludePages
    $allPages = array_filter($allPages, function($page) use ($excludePages) {
        return !in_array($page->getId(), $excludePages);
    });
    
    // 5. Сортировать страницы
    usort($allPages, function($a, $b) use ($sortBy, $sortOrder) {
        $aValue = $this->getPageFieldValue($a, $sortBy);
        $bValue = $this->getPageFieldValue($b, $sortBy);
        
        $comparison = $aValue <=> $bValue;
        return $sortOrder === 'desc' ? -$comparison : $comparison;
    });
    
    // 6. Применить пагинацию (offset/limit)
    $offset = ($page - 1) * $limit;
    $totalItems = count($allPages);
    $totalPages = $limit > 0 ? (int)ceil($totalItems / $limit) : 1;
    $paginatedPages = array_slice($allPages, $offset, $limit);

    // 7. Сформировать карточки (только для текущей страницы)
    $cards = [];
    foreach ($paginatedPages as $paginatedPage) {
        // Загрузить блоки для извлечения картинки
        $blocks = $this->blockRepository->findByPageId($paginatedPage->getId());

        $cards[] = [
            'id' => $paginatedPage->getId(),
            'title' => $paginatedPage->getTitle(),
            'snippet' => $paginatedPage->getSeoDescription() ?? '',
            'image' => $paginatedPage->getCardImage($blocks),
            'url' => '/' . $paginatedPage->getSlug(),
            'type' => $paginatedPage->getType()->value,
            'publishedAt' => $paginatedPage->getPublishedAt()?->format('Y-m-d H:i:s')
        ];
    }
    
    // 8. Группировать по секциям (если заданы) — НО при section-mode возвращаем одну секцию
    $sectionTitle = $sectionSlug === 'guides' ? 'Гайды' : 
                   ($sectionSlug === 'articles' ? 'Статьи' : 'Все материалы');
    
    $result = [
        'sections' => [
            [
                'title' => $sectionTitle,
                'items' => $cards
            ]
        ]
    ];

    // 9. Добавить мета-информацию о пагинации
    $result['pagination'] = [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalItems' => $totalItems,
        'itemsPerPage' => $limit,
        'hasNextPage' => $page < $totalPages,
        'hasPrevPage' => $page > 1,
        'currentSection' => $sectionSlug  // ✅ Добавить текущую секцию
    ];

    return $result;
}
```

---

## ✅ Чеклист финальной проверки

- [ ] Все файлы обновлены (7 файлов: 4 backend + 3 frontend)
- [ ] Синхронизация в XAMPP выполнена
- [ ] Apache перезапущен
- [ ] API /collection-items?section=guides работает
- [ ] API /collection-items?section=articles работает
- [ ] Публичная страница показывает вкладки
- [ ] Редактор показывает вкладки секций
- [ ] Пагинация работает внутри каждой секции отдельно
- [ ] Auth check работает (401 без логина)
- [ ] URL validation работает (400 для javascript:)
- [ ] Баг getSnippet() исправлен

---

## 🎯 Ожидаемые результаты

### До изменений:
- Коллекция показывает 12 элементов (смесь гайдов и статей)
- Пагинация по всем элементам вместе

### После изменений:
- Коллекция имеет 2 вкладки: "Гайды" и "Статьи"
- Каждая вкладка — независимая секция
- Пагинация работает **внутри каждой секции** отдельно
- URL: `?section=guides&page=1` или `?section=articles&page=1`
- Безопасность: auth check и URL validation

---

## 📞 Вопросы перед началом?

1. Готовы ли начать с Этапа 1 (исправление багов безопасности)?
2. Есть ли вопросы по плану?
3. Нужны ли дополнительные пояснения по какому-либо шагу?
