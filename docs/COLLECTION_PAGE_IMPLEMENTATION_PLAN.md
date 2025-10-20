# 📋 План реализации страницы-коллекции "Все материалы"

**Дата:** 19 октября 2025  
**Автор:** GitHub Copilot  
**Задача:** Автоматическая сборка страницы с карточками всех опубликованных статей и гайдов

---

## 🎯 Use Case (User Story)

```
1. Пользователь нажимает "Создать статью" в редакторе
2. Пишет статью, заполняет SEO Description (будет использоваться как snippet)
3. Сохраняет и публикует страницу
4. На странице "Все материалы" (slug: all-materials) автоматически появляется карточка с:
   - Названием страницы (page.title)
   - Сниппетом (page.seoDescription)
   - Картинкой (page.collectionConfig.cardImage или fallback)
5. Пользователь может поменять картинку карточки на странице "Все материалы"
6. Статьи и Гайды отображаются в разных секциях
```

---

## 🏛️ Архитектурный анализ (Clean Architecture)

### ✅ Что уже есть

1. **Domain Layer (Entity)**
   - ✅ `PageType` enum с типами: `Regular`, `Article`, `Guide`, `Collection`
   - ✅ `PageType::isContent()` — определяет, является ли страница контентом (article/guide)
   - ✅ `PageType::isCollection()` — определяет, является ли страница коллекцией
   - ✅ `Page::collectionConfig` (JSON) — поле для настроек коллекции
   - ✅ `MediaFile` entity — для работы с картинками

2. **Infrastructure Layer**
   - ✅ `FileSystemStaticTemplateRepository` — знает о шаблоне `all-materials` (type: 'collection')
   - ✅ `MySQLPageRepository` — CRUD для страниц
   - ✅ Шаблон `backend/templates/all-materials.html` уже существует

3. **Presentation Layer**
   - ✅ `PublicPageController::show()` — рендерит публичные страницы
   - ✅ Поддержка блока `article-cards` для отображения карточек

### ❌ Что нужно добавить

1. **Application Layer (Use Cases)**
   - ❌ `GetCollectionItems` — получить список страниц для коллекции
   - ❌ `UpdateCollectionCardImage` — обновить картинку карточки

2. **Domain Layer (Business Logic)**
   - ❌ Валидация `collectionConfig` структуры
   - ❌ Метод `Page::getCardImage()` для получения картинки карточки

3. **Infrastructure Layer**
   - ❌ Логика фильтрации страниц по типу для коллекции
   - ❌ Fallback картинки по умолчанию

4. **Presentation Layer**
   - ❌ Endpoint `GET /api/pages/:id/collection-items` — получить элементы коллекции
   - ❌ Endpoint `PATCH /api/pages/:id/card-image` — обновить картинку карточки
   - ❌ Рендеринг коллекции в `PublicPageController`

---

## 📐 Структура `collectionConfig` (JSON)

```json
{
  "type": "auto-collection",           // Тип коллекции (auto = автосборка)
  "sourceTypes": ["article", "guide"], // Какие типы страниц собирать
  "sortBy": "publishedAt",             // Сортировка (publishedAt | title | createdAt)
  "sortOrder": "desc",                 // desc | asc
  "limit": null,                       // null = без лимита, число = макс. кол-во
  "excludePages": [],                  // Массив ID страниц, которые нужно исключить
  "sections": [                        // Секции (для раздельного отображения)
    {
      "title": "Гайды",
      "sourceTypes": ["guide"]
    },
    {
      "title": "Статьи из блога",
      "sourceTypes": ["article"]
    }
  ],
  "cardImages": {                      // Кастомные картинки для карточек (переопределение)
    "page-uuid-1": "/uploads/custom-card-1.jpg",
    "page-uuid-2": "/uploads/custom-card-2.jpg"
  }
}
```

---

## 🔄 Алгоритм работы системы

### 1️⃣ При публикации новой статьи (Article/Guide)

```
User публикует Page (type: Article | Guide)
   ↓
UpdatePage Use Case сохраняет Page в БД
   ↓
Никаких дополнительных действий НЕ ТРЕБУЕТСЯ
   (коллекция соберется динамически при запросе)
```

### 2️⃣ При рендеринге страницы "Все материалы" (Collection)

```
GET /all-materials
   ↓
PublicPageController::show('all-materials')
   ↓
GetPageWithBlocks('all-materials')
   ↓
Page.type === Collection?
   ↓ YES
GetCollectionItems Use Case
   ↓
1. Читает collectionConfig из Page
2. Фильтрует опубликованные страницы по sourceTypes
3. Сортирует по sortBy/sortOrder
4. Группирует по секциям (если есть)
5. Для каждой страницы формирует card:
   - title: page.title
   - snippet: page.seoDescription
   - image: cardImages[page.id] ?? page.firstBlockImage ?? DEFAULT_IMAGE
   - url: /page.slug
   ↓
Рендерит HTML с блоками article-cards
```

### 3️⃣ При изменении картинки карточки

```
User открывает страницу "Все материалы" в редакторе
   ↓
Видит блоки с article-cards
   ↓
Нажимает "Изменить картинку" на карточке статьи X
   ↓
PATCH /api/pages/all-materials-id/card-image
{
  "pageId": "статья-X-uuid",
  "imageUrl": "/uploads/new-image.jpg"
}
   ↓
UpdateCollectionCardImage Use Case
   ↓
1. Загружает Page (all-materials)
2. Обновляет collectionConfig.cardImages[pageId] = imageUrl
3. Сохраняет Page
   ↓
Коллекция перерендерится с новой картинкой
```

---

## 🎨 Алгоритм выбора картинки карточки

### Приоритет картинок (от высшего к низшему):

```
1. collectionConfig.cardImages[pageId] 
   ↓ (если не установлено)
2. Первый блок типа main-screen/hero с image
   ↓ (если нет)
3. Первый блок article-cards → cards[0].image
   ↓ (если нет)
4. Первый MediaFile, загруженный для этой страницы
   ↓ (если нет)
5. Дефолтная картинка /uploads/default-card.jpg
```

### Реализация (PHP):

```php
class Page {
    public function getCardImage(): string {
        // 1. Кастомная картинка из collectionConfig
        if ($this->collectionConfig && isset($this->collectionConfig['cardImages'][$this->id])) {
            return $this->collectionConfig['cardImages'][$this->id];
        }
        
        // 2-4. Извлечение из блоков (требует загрузки блоков)
        // Этот метод будет вызываться в Use Case с доступом к BlockRepository
        
        // 5. Fallback
        return '/uploads/default-card.jpg';
    }
}
```

---

## 📝 Детальная реализация по слоям

### 🔹 СЛОЙ 1: Domain Layer

#### 1.1. Обновить `Page.php`

```php
// backend/src/Domain/Entity/Page.php

/**
 * Получить URL картинки для карточки в коллекции
 * 
 * @param array|null $blocks Блоки страницы (опционально)
 * @return string URL картинки
 */
public function getCardImage(?array $blocks = null): string
{
    // 1. Кастомная картинка из collectionConfig
    if ($this->collectionConfig && 
        isset($this->collectionConfig['cardImages'][$this->id])) {
        return $this->collectionConfig['cardImages'][$this->id];
    }
    
    // 2. Извлечь из блоков (если переданы)
    if ($blocks) {
        foreach ($blocks as $block) {
            $data = $block->getData();
            
            // Main-screen / hero с картинкой
            if (in_array($block->getType(), ['main-screen', 'hero']) && 
                isset($data['image']['url'])) {
                return $data['image']['url'];
            }
            
            // Article-cards с картинками
            if ($block->getType() === 'article-cards' && 
                isset($data['cards'][0]['image']['url'])) {
                return $data['cards'][0]['image']['url'];
            }
        }
    }
    
    // 3. Fallback (можно расширить до запроса MediaFile из БД)
    return '/uploads/default-card.jpg';
}

/**
 * Обновить картинку карточки в коллекции
 */
public function setCardImage(string $imageUrl): void
{
    if (!$this->collectionConfig) {
        $this->collectionConfig = [];
    }
    
    if (!isset($this->collectionConfig['cardImages'])) {
        $this->collectionConfig['cardImages'] = [];
    }
    
    $this->collectionConfig['cardImages'][$this->id] = $imageUrl;
    $this->touch();
}
```

#### 1.2. Создать `CollectionConfig` Value Object (опционально, для типизации)

```php
// backend/src/Domain/ValueObject/CollectionConfig.php

<?php
declare(strict_types=1);

namespace Domain\ValueObject;

class CollectionConfig
{
    public function __construct(
        public readonly string $type,              // 'auto-collection'
        public readonly array $sourceTypes,        // ['article', 'guide']
        public readonly string $sortBy,            // 'publishedAt'
        public readonly string $sortOrder,         // 'desc'
        public readonly ?int $limit,               // null | int
        public readonly array $excludePages,       // []
        public readonly array $sections,           // [{title, sourceTypes}]
        public readonly array $cardImages          // [pageId => url]
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'auto-collection',
            sourceTypes: $data['sourceTypes'] ?? ['article', 'guide'],
            sortBy: $data['sortBy'] ?? 'publishedAt',
            sortOrder: $data['sortOrder'] ?? 'desc',
            limit: $data['limit'] ?? null,
            excludePages: $data['excludePages'] ?? [],
            sections: $data['sections'] ?? [],
            cardImages: $data['cardImages'] ?? []
        );
    }
    
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'sourceTypes' => $this->sourceTypes,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'limit' => $this->limit,
            'excludePages' => $this->excludePages,
            'sections' => $this->sections,
            'cardImages' => $this->cardImages,
        ];
    }
}
```

---

### 🔹 СЛОЙ 2: Application Layer (Use Cases)

#### 2.1. Создать `GetCollectionItems.php`

```php
// backend/src/Application/UseCase/GetCollectionItems.php

<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;

class GetCollectionItems
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository
    ) {}
    
    /**
     * Получить элементы коллекции (статьи/гайды для отображения)
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @return array ['sections' => [...], 'items' => [...]]
     */
    public function execute(string $collectionPageId): array
    {
        // 1. Загрузить страницу коллекции
        $collectionPage = $this->pageRepository->findById($collectionPageId);
        
        if (!$collectionPage || !$collectionPage->getType()->isCollection()) {
            throw new \InvalidArgumentException('Page is not a collection');
        }
        
        $config = $collectionPage->getCollectionConfig();
        if (!$config) {
            $config = [
                'sourceTypes' => ['article', 'guide'],
                'sortBy' => 'publishedAt',
                'sortOrder' => 'desc',
                'sections' => []
            ];
        }
        
        // 2. Получить все опубликованные страницы нужных типов
        $sourceTypes = $config['sourceTypes'] ?? ['article', 'guide'];
        $allItems = [];
        
        foreach ($sourceTypes as $type) {
            $pages = $this->pageRepository->findByTypeAndStatus($type, 'published');
            $allItems = array_merge($allItems, $pages);
        }
        
        // 3. Исключить страницы из excludePages
        $excludeIds = $config['excludePages'] ?? [];
        if (!empty($excludeIds)) {
            $allItems = array_filter($allItems, function($page) use ($excludeIds) {
                return !in_array($page->getId(), $excludeIds);
            });
        }
        
        // 4. Сортировка
        $sortBy = $config['sortBy'] ?? 'publishedAt';
        $sortOrder = $config['sortOrder'] ?? 'desc';
        usort($allItems, function($a, $b) use ($sortBy, $sortOrder) {
            $valueA = $this->getSortValue($a, $sortBy);
            $valueB = $this->getSortValue($b, $sortBy);
            $cmp = $valueA <=> $valueB;
            return $sortOrder === 'asc' ? $cmp : -$cmp;
        });
        
        // 5. Лимит
        if (isset($config['limit']) && $config['limit'] > 0) {
            $allItems = array_slice($allItems, 0, $config['limit']);
        }
        
        // 6. Формирование карточек с картинками
        $cards = [];
        foreach ($allItems as $page) {
            // Загрузить блоки страницы для извлечения картинки
            $blocks = $this->blockRepository->findByPageId($page->getId());
            
            $cards[] = [
                'id' => $page->getId(),
                'title' => $page->getTitle(),
                'snippet' => $page->getSeoDescription() ?: 'Описание отсутствует',
                'image' => $page->getCardImage($blocks),
                'url' => '/' . $page->getSlug(),
                'type' => $page->getType()->value,
                'publishedAt' => $page->getPublishedAt()?->format('Y-m-d')
            ];
        }
        
        // 7. Группировка по секциям (если заданы)
        $sections = $config['sections'] ?? [];
        if (!empty($sections)) {
            return $this->groupBySections($cards, $sections);
        }
        
        // Без секций — одна общая секция
        return [
            'sections' => [
                [
                    'title' => 'Все материалы',
                    'items' => $cards
                ]
            ]
        ];
    }
    
    private function getSortValue(Page $page, string $sortBy)
    {
        return match($sortBy) {
            'publishedAt' => $page->getPublishedAt()?->getTimestamp() ?? 0,
            'createdAt' => $page->getCreatedAt()->getTimestamp(),
            'title' => $page->getTitle(),
            default => 0
        };
    }
    
    private function groupBySections(array $cards, array $sections): array
    {
        $result = ['sections' => []];
        
        foreach ($sections as $section) {
            $sectionTitle = $section['title'] ?? 'Раздел';
            $sectionTypes = $section['sourceTypes'] ?? [];
            
            $sectionItems = array_filter($cards, function($card) use ($sectionTypes) {
                return in_array($card['type'], $sectionTypes);
            });
            
            $result['sections'][] = [
                'title' => $sectionTitle,
                'items' => array_values($sectionItems)
            ];
        }
        
        return $result;
    }
}
```

#### 2.2. Создать `UpdateCollectionCardImage.php`

```php
// backend/src/Application/UseCase/UpdateCollectionCardImage.php

<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;

class UpdateCollectionCardImage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository
    ) {}
    
    /**
     * Обновить картинку карточки для конкретной страницы в коллекции
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @param string $targetPageId UUID страницы, чью картинку меняем
     * @param string $imageUrl Новый URL картинки
     */
    public function execute(string $collectionPageId, string $targetPageId, string $imageUrl): void
    {
        // 1. Загрузить страницу коллекции
        $collectionPage = $this->pageRepository->findById($collectionPageId);
        
        if (!$collectionPage || !$collectionPage->getType()->isCollection()) {
            throw new \InvalidArgumentException('Page is not a collection');
        }
        
        // 2. Обновить collectionConfig.cardImages[targetPageId]
        $config = $collectionPage->getCollectionConfig() ?? [];
        
        if (!isset($config['cardImages'])) {
            $config['cardImages'] = [];
        }
        
        $config['cardImages'][$targetPageId] = $imageUrl;
        
        // 3. Сохранить
        $collectionPage->setCollectionConfig($config);
        $this->pageRepository->update($collectionPage);
    }
}
```

---

### 🔹 СЛОЙ 3: Infrastructure Layer

#### 3.1. Обновить `MySQLPageRepository.php`

Добавить метод для фильтрации по типу и статусу:

```php
// backend/src/Infrastructure/Repository/MySQLPageRepository.php

/**
 * Найти все страницы по типу и статусу
 * 
 * @param string $type PageType value ('article', 'guide', etc.)
 * @param string $status PageStatus value ('published', 'draft', etc.)
 * @return Page[]
 */
public function findByTypeAndStatus(string $type, string $status): array
{
    $stmt = $this->db->prepare('
        SELECT * FROM pages
        WHERE type = :type AND status = :status
        ORDER BY published_at DESC
    ');
    
    $stmt->execute([
        'type' => $type,
        'status' => $status
    ]);
    
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    return array_map([$this, 'mapRowToPage'], $rows);
}
```

---

### 🔹 СЛОЙ 4: Presentation Layer (Controllers)

#### 4.1. Создать `CollectionController.php`

```php
// backend/src/Presentation/Controller/CollectionController.php

<?php
declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetCollectionItems;
use Application\UseCase\UpdateCollectionCardImage;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;

class CollectionController
{
    /**
     * GET /api/pages/:id/collection-items
     * Получить элементы коллекции (для редактора и фронтенда)
     */
    public function getItems(string $pageId): void
    {
        try {
            $pageRepo = new MySQLPageRepository();
            $blockRepo = new MySQLBlockRepository();
            
            $useCase = new GetCollectionItems($pageRepo, $blockRepo);
            $result = $useCase->execute($pageId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * PATCH /api/pages/:id/card-image
     * Обновить картинку карточки
     */
    public function updateCardImage(string $pageId): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['targetPageId']) || !isset($input['imageUrl'])) {
                throw new \InvalidArgumentException('Missing targetPageId or imageUrl');
            }
            
            $pageRepo = new MySQLPageRepository();
            $useCase = new UpdateCollectionCardImage($pageRepo);
            
            $useCase->execute(
                $pageId,
                $input['targetPageId'],
                $input['imageUrl']
            );
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Card image updated'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

#### 4.2. Обновить `PublicPageController.php`

Добавить рендеринг коллекции:

```php
// В методе renderPage() добавить проверку на Collection

private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
{
    $page = $pageData->page;
    
    // Если это страница-коллекция, рендерим динамически
    if (is_array($page) && isset($page['type']) && $page['type'] === 'collection') {
        $this->renderCollectionPage($page);
        return;
    }
    
    // ... остальной код рендеринга ...
}

private function renderCollectionPage(array $page): void
{
    $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
    $blockRepo = new \Infrastructure\Repository\MySQLBlockRepository();
    
    $useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
    $collectionData = $useCase->execute($page['id']);
    
    // Генерируем HTML с секциями
    $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($page['title']) . '</title>
    <link rel="stylesheet" href="/healthcare-cms-frontend/styles.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="/" class="logo">Healthcare Hacks Brazil</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="/">Главная</a></li>
                    <li><a href="/guides">Гайды</a></li>
                    <li><a href="/blog">Блог</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="unified-background">
        <section class="page-header">
            <div class="container">
                <h2>' . htmlspecialchars($page['title']) . '</h2>
                <p class="sub-heading">' . htmlspecialchars($page['seoDescription'] ?? '') . '</p>
            </div>
        </section>';
    
    // Секции с карточками
    foreach ($collectionData['sections'] as $section) {
        $html .= '<section style="padding-top: 3rem; padding-bottom: 3rem;">
            <div class="container">
                <h3 style="font-family: var(--font-heading); font-size: 1.8rem; margin-bottom: 2rem;">
                    ' . htmlspecialchars($section['title']) . '
                </h3>
                <div class="articles-grid">';
        
        foreach ($section['items'] as $item) {
            $html .= '<div class="article-card">
                <img src="' . htmlspecialchars($item['image']) . '" alt="' . htmlspecialchars($item['title']) . '">
                <div class="article-card-content">
                    <h3>' . htmlspecialchars($item['title']) . '</h3>
                    <p>' . htmlspecialchars($item['snippet']) . '</p>
                    <a href="' . htmlspecialchars($item['url']) . '">Читать далее &rarr;</a>
                </div>
            </div>';
        }
        
        $html .= '</div></div></section>';
    }
    
    $html .= '</main>
    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2025 Healthcare Hacks Brazil</p>
        </div>
    </footer>
</body>
</html>';
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
```

#### 4.3. Обновить роутинг в `index.php`

```php
// backend/public/index.php

// Collection endpoints
if (preg_match('/^\/api\/pages\/([a-f0-9-]{36})\/collection-items$/', $path, $matches)) {
    $controller = new \Presentation\Controller\CollectionController();
    $controller->getItems($matches[1]);
    exit;
}

if (preg_match('/^\/api\/pages\/([a-f0-9-]{36})\/card-image$/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $controller = new \Presentation\Controller\CollectionController();
    $controller->updateCardImage($matches[1]);
    exit;
}
```

---

### 🔹 СЛОЙ 5: Frontend (Editor UI)

#### 5.1. Обновить `editor.js` — поддержка редактирования коллекции

```javascript
// frontend/editor.js

// Новый метод для загрузки элементов коллекции
async loadCollectionItems() {
    if (this.pageData.type !== 'collection') return;
    
    try {
        const response = await apiClient.request(
            `/api/pages/${this.pageData.id}/collection-items`
        );
        
        if (response.success) {
            this.collectionItems = response.data;
            console.log('Collection items loaded:', this.collectionItems);
        }
    } catch (error) {
        console.error('Failed to load collection items:', error);
    }
}

// Метод для обновления картинки карточки
async updateCardImage(targetPageId, imageUrl) {
    try {
        const response = await apiClient.request(
            `/api/pages/${this.pageData.id}/card-image`,
            {
                method: 'PATCH',
                body: JSON.stringify({
                    targetPageId,
                    imageUrl
                })
            }
        );
        
        if (response.success) {
            alert('Картинка карточки обновлена!');
            await this.loadCollectionItems(); // Перезагрузить
        }
    } catch (error) {
        alert('Ошибка обновления картинки: ' + error.message);
    }
}

// В методе mounted() добавить:
async mounted() {
    // ... existing code ...
    
    if (this.pageData.type === 'collection') {
        await this.loadCollectionItems();
    }
}
```

#### 5.2. Добавить UI для редактирования картинок карточек

В `editor.html` добавить секцию для коллекций:

```html
<!-- Collection Items Editor -->
<div v-if="pageData.type === 'collection' && collectionItems" 
     class="collection-editor">
    <h3>Элементы коллекции</h3>
    
    <div v-for="section in collectionItems.sections" :key="section.title">
        <h4>{{ section.title }}</h4>
        
        <div class="collection-cards">
            <div v-for="item in section.items" :key="item.id" 
                 class="collection-card">
                <img :src="item.image" :alt="item.title">
                <h5>{{ item.title }}</h5>
                <p>{{ item.snippet }}</p>
                <button @click="changeCardImage(item.id)" 
                        class="btn-secondary">
                    Изменить картинку
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 🎯 Разделение Статей и Гайдов

### Решение №1: Через `collectionConfig.sections`

Страница "Все материалы" имеет конфиг:

```json
{
  "sections": [
    {
      "title": "Гайды",
      "sourceTypes": ["guide"]
    },
    {
      "title": "Статьи из блога",
      "sourceTypes": ["article"]
    }
  ]
}
```

`GetCollectionItems` Use Case автоматически разделит карточки по секциям.

### Решение №2: Создать две отдельные коллекции

- **Страница "Гайды"** (slug: `guides`) — type: `collection`, sourceTypes: `['guide']`
- **Страница "Блог"** (slug: `blog`) — type: `collection`, sourceTypes: `['article']`
- **Страница "Все материалы"** (slug: `all-materials`) — type: `collection`, sourceTypes: `['guide', 'article']`

---

## 📋 Чеклист реализации

### Backend (PHP)

- [ ] Обновить `Page.php` — добавить `getCardImage()`, `setCardImage()`
- [ ] Создать `GetCollectionItems.php` Use Case
- [ ] Создать `UpdateCollectionCardImage.php` Use Case
- [ ] Добавить `findByTypeAndStatus()` в `MySQLPageRepository`
- [ ] Создать `CollectionController.php`
- [ ] Обновить роутинг в `index.php`
- [ ] Обновить `PublicPageController::renderPage()` для коллекций

### Frontend (JavaScript)

- [ ] Добавить `loadCollectionItems()` в `editor.js`
- [ ] Добавить `updateCardImage()` в `editor.js`
- [ ] Добавить UI для редактирования картинок в `editor.html`
- [ ] Добавить поддержку `type="collection"` в форме создания страницы

### Database

- [ ] (Опционально) Добавить индекс `idx_type_status_published` на `pages(type, status, published_at)`

### Testing

- [ ] Unit-тесты для `GetCollectionItems`
- [ ] E2E тест: создать article → проверить появление в коллекции
- [ ] E2E тест: изменить картинку карточки

---

## 🚀 Пример использования

### Шаг 1: Создать страницу "Все материалы"

```http
POST /api/pages
{
  "title": "Все материалы",
  "slug": "all-materials",
  "type": "collection",
  "status": "published",
  "seoTitle": "Все материалы - Healthcare Hacks Brazil",
  "seoDescription": "Полная коллекция гайдов и статей о медицине в Бразилии",
  "collectionConfig": {
    "sourceTypes": ["article", "guide"],
    "sortBy": "publishedAt",
    "sortOrder": "desc",
    "sections": [
      {"title": "Гайды", "sourceTypes": ["guide"]},
      {"title": "Статьи из блога", "sourceTypes": ["article"]}
    ]
  }
}
```

### Шаг 2: Создать статью

```http
POST /api/pages
{
  "title": "Как выбрать врача в Бразилии",
  "slug": "kak-vybrat-vracha",
  "type": "article",
  "status": "published",
  "seoDescription": "Полезные советы по выбору врача для экспатов"
}
```

→ Статья автоматически появится на странице `/all-materials`

### Шаг 3: Изменить картинку карточки

```http
PATCH /api/pages/{all-materials-id}/card-image
{
  "targetPageId": "{article-id}",
  "imageUrl": "/uploads/custom-doctor-image.jpg"
}
```

---

## 🎨 UI/UX рекомендации

1. **В редакторе страницы "Все материалы"**
   - Показать превью всех карточек
   - Кнопка "Изменить картинку" у каждой карточки
   - Drag-and-drop для изменения порядка (опционально)

2. **При создании новой статьи**
   - Показать превью карточки перед публикацией
   - Предложить загрузить картинку для карточки

3. **Fallback картинка**
   - Создать `/uploads/default-card.jpg` с брендированным дизайном
   - Показывать плейсхолдер, если нет картинки

---

## 🔒 Безопасность

1. **Валидация `imageUrl`**
   - Проверять, что URL начинается с `/uploads/`
   - Проверять существование файла

2. **Права доступа**
   - Только авторизованные пользователи могут менять картинки
   - Проверка роли (admin/editor)

3. **XSS защита**
   - `htmlspecialchars()` для всех данных в рендеринге
   - CSP headers для страниц

---

## 📚 Итоговая архитектура

```
GET /all-materials
   ↓
PublicPageController::renderCollectionPage()
   ↓
GetCollectionItems Use Case
   ↓
┌─────────────────────────────┐
│ 1. Load Collection Page     │
│ 2. Read collectionConfig    │
│ 3. Query published pages    │
│    WHERE type IN (...)      │
│ 4. Sort & group by sections │
│ 5. Resolve card images:     │
│    - Custom (cardImages)    │
│    - From blocks            │
│    - Fallback default       │
│ 6. Return structured data   │
└─────────────────────────────┘
   ↓
Render HTML with article-cards sections
   ↓
Browser displays collection page
```

---

## ✅ Итог

**Ваша архитектура уже поддерживает коллекции!**

1. ✅ `PageType::Collection` — есть в Entity
2. ✅ `collectionConfig` (JSON) — есть в БД
3. ✅ Шаблон `all-materials.html` — есть
4. ✅ Рендеринг `article-cards` — есть в контроллере

**Нужно добавить:**
- Use Cases для сборки коллекции
- API endpoints для редактирования картинок
- Frontend UI для управления карточками

**Преимущества решения:**
- ✅ Динамическая сборка (не нужно обновлять коллекцию вручную)
- ✅ Гибкость (секции, сортировка, фильтры)
- ✅ Кастомизация картинок без изменения исходных страниц
- ✅ Разделение Статей/Гайдов через секции
- ✅ Соответствует Clean Architecture

---

**Готово к реализации! 🚀**
