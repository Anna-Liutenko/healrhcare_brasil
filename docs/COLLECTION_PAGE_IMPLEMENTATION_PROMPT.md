# 🤖 Промпт для реализации Collection Page

**Дата:** 19 октября 2025  
**Версия:** 1.0 для маломощных LLM  
**Архитектура:** Clean Architecture (PHP 8.x + MySQL + Vue.js)

---

## 📌 Контекст системы

**Что уже работает:**
- ✅ CMS для создания страниц (Regular, Article, Guide, Collection)
- ✅ База данных с таблицей `pages` (поле `collection_config` JSON)
- ✅ Enum `PageType` с типом `Collection`
- ✅ Блоки: `main-screen`, `article-cards`, `text-block`, `image-block`
- ✅ Публичный контроллер для отображения страниц

**Что нужно добавить:**
- ❌ Автоматическая сборка страницы с карточками статей/гайдов
- ❌ Возможность менять картинки карточек
- ❌ Разделение статей и гайдов на секции

---

## 🎯 Задача

Создать страницу "Все материалы" (slug: `all-materials`), которая **автоматически** собирает карточки всех опубликованных статей и гайдов.

**Что должно произойти:**
1. Пользователь создаёт статью → публикует
2. Статья **автоматически** появляется на странице `/all-materials`
3. Редактор может поменять картинку карточки (необязательно)
4. Статьи и гайды показываются в разных секциях

---

## 📦 Шаг 1: Domain Layer — Метод получения картинки

### Файл: `backend/src/Domain/Entity/Page.php`

**ЧТО ДЕЛАТЬ:** Добавить метод `getCardImage()` в класс `Page`

**ГДЕ:** После метода `getCollectionConfig()` (примерно строка 140-180)

**КОД ДЛЯ ВСТАВКИ:**

```php
    /**
     * Получить URL картинки для карточки в коллекции
     * 
     * Приоритет:
     * 1. Кастомная картинка из collectionConfig.cardImages
     * 2. Картинка из блока main-screen
     * 3. Картинка из блока article-cards
     * 4. Дефолтная картинка
     * 
     * @param array|null $blocks Блоки страницы
     * @return string URL картинки
     */
    public function getCardImage(?array $blocks = null): string
    {
        // 1. Кастомная картинка из collectionConfig
        if ($this->collectionConfig && 
            isset($this->collectionConfig['cardImages'][$this->id])) {
            return $this->collectionConfig['cardImages'][$this->id];
        }
        
        // 2-3. Извлечь из блоков (если переданы)
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
        
        // 4. Fallback
        return '/uploads/default-card.jpg';
    }

    /**
     * Установить кастомную картинку для карточки в коллекции
     * 
     * @param string $imageUrl URL картинки
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

---

## 📦 Шаг 2: Application Layer — Use Case для получения элементов коллекции

### Файл: `backend/src/Application/UseCase/GetCollectionItems.php`

**ЧТО ДЕЛАТЬ:** Создать новый файл

**КОД ФАЙЛА:**

```php
<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;

/**
 * Use Case: Получить элементы коллекции
 * 
 * Собирает список страниц (статей/гайдов) для отображения на странице-коллекции
 */
class GetCollectionItems
{
    private PageRepositoryInterface $pageRepository;
    private BlockRepositoryInterface $blockRepository;
    
    public function __construct(
        PageRepositoryInterface $pageRepository,
        BlockRepositoryInterface $blockRepository
    ) {
        $this->pageRepository = $pageRepository;
        $this->blockRepository = $blockRepository;
    }
    
    /**
     * Выполнить: получить элементы коллекции
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @return array Массив с секциями и карточками
     */
    public function execute(string $collectionPageId): array
    {
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
        $cardImages = $config['cardImages'] ?? [];
        
        // 3. Загрузить все опубликованные страницы нужных типов
        $allPages = [];
        foreach ($sourceTypes as $type) {
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
        
        // 6. Сформировать карточки
        $cards = [];
        foreach ($allPages as $page) {
            // Загрузить блоки для извлечения картинки
            $blocks = $this->blockRepository->findByPageId($page->getId());
            
            $cards[] = [
                'id' => $page->getId(),
                'title' => $page->getTitle(),
                'snippet' => $page->getSeoDescription() ?? '',
                'image' => $page->getCardImage($blocks),
                'url' => '/' . $page->getSlug(),
                'type' => $page->getType()->value,
                'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        // 7. Группировать по секциям (если заданы)
        if ($sections) {
            return $this->groupBySections($cards, $sections);
        }
        
        // 8. Вернуть одну секцию со всеми карточками
        return [
            'sections' => [
                [
                    'title' => 'Все материалы',
                    'items' => $cards
                ]
            ]
        ];
    }
    
    /**
     * Получить значение поля страницы для сортировки
     */
    private function getPageFieldValue($page, string $field)
    {
        switch ($field) {
            case 'publishedAt':
                return $page->getPublishedAt()?->getTimestamp() ?? 0;
            case 'createdAt':
                return $page->getCreatedAt()->getTimestamp();
            case 'updatedAt':
                return $page->getUpdatedAt()?->getTimestamp() ?? 0;
            case 'title':
                return $page->getTitle();
            default:
                return 0;
        }
    }
    
    /**
     * Группировать карточки по секциям
     */
    private function groupBySections(array $cards, array $sections): array
    {
        $result = ['sections' => []];
        
        foreach ($sections as $section) {
            $sectionTitle = $section['title'] ?? 'Без названия';
            $sectionTypes = $section['sourceTypes'] ?? [];
            
            // Фильтровать карточки по типам секции
            $sectionCards = array_filter($cards, function($card) use ($sectionTypes) {
                return in_array($card['type'], $sectionTypes);
            });
            
            $result['sections'][] = [
                'title' => $sectionTitle,
                'items' => array_values($sectionCards)
            ];
        }
        
        return $result;
    }
}
```

---

## 📦 Шаг 3: Application Layer — Use Case для обновления картинки

### Файл: `backend/src/Application/UseCase/UpdateCollectionCardImage.php`

**ЧТО ДЕЛАТЬ:** Создать новый файл

**КОД ФАЙЛА:**

```php
<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;

/**
 * Use Case: Обновить картинку карточки в коллекции
 */
class UpdateCollectionCardImage
{
    private PageRepositoryInterface $pageRepository;
    
    public function __construct(PageRepositoryInterface $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }
    
    /**
     * Выполнить: обновить картинку карточки
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @param string $targetPageId UUID страницы, чью картинку меняем
     * @param string $imageUrl Новый URL картинки
     */
    public function execute(
        string $collectionPageId, 
        string $targetPageId, 
        string $imageUrl
    ): void {
        // 1. Загрузить страницу-коллекцию
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

## 📦 Шаг 4: Infrastructure Layer — Метод фильтрации в Repository

### Файл: `backend/src/Infrastructure/Repository/MySQLPageRepository.php`

**ЧТО ДЕЛАТЬ:** Добавить метод `findByTypeAndStatus()` в класс `MySQLPageRepository`

**ГДЕ:** После метода `findAll()` (примерно строка 300-320)

**КОД ДЛЯ ВСТАВКИ:**

```php
    /**
     * Найти все страницы по типу и статусу
     * 
     * @param string $type Тип страницы (article, guide, regular, collection)
     * @param string $status Статус страницы (draft, published, archived)
     * @return array Массив страниц
     */
    public function findByTypeAndStatus(string $type, string $status): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM pages 
            WHERE type = :type 
              AND status = :status
            ORDER BY published_at DESC
        ');
        
        $stmt->execute([
            ':type' => $type,
            ':status' => $status
        ]);
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map([$this, 'mapRowToPage'], $rows);
    }
```

---

## 📦 Шаг 5: Presentation Layer — Controller для API

### Файл: `backend/src/Presentation/Controller/CollectionController.php`

**ЧТО ДЕЛАТЬ:** Создать новый файл

**КОД ФАЙЛА:**

```php
<?php
declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetCollectionItems;
use Application\UseCase\UpdateCollectionCardImage;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;

/**
 * Controller: Управление коллекциями
 */
class CollectionController
{
    /**
     * GET /api/pages/:id/collection-items
     * 
     * Получить элементы коллекции (карточки статей/гайдов)
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
     * 
     * Обновить картинку карточки в коллекции
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

---

## 📦 Шаг 6: Presentation Layer — Рендеринг коллекции

### Файл: `backend/src/Presentation/Controller/PublicPageController.php`

**ЧТО ДЕЛАТЬ 1:** Добавить проверку типа страницы в метод `renderPage()`

**ГДЕ:** В начале метода `renderPage()` (примерно строка 180-200)

**НАЙТИ:**
```php
private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
{
    $page = $pageData->page;
    $blocks = $pageData->blocks;
```

**ЗАМЕНИТЬ НА:**
```php
private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
{
    $page = $pageData->page;
    $blocks = $pageData->blocks;
    
    // Если это коллекция — использовать специальный рендеринг
    if ($page['type'] === 'collection') {
        $this->renderCollectionPage($page);
        return;
    }
```

**ЧТО ДЕЛАТЬ 2:** Добавить метод `renderCollectionPage()` в конец класса

**КОД ДЛЯ ВСТАВКИ:**

```php
    /**
     * Рендеринг страницы-коллекции
     */
    private function renderCollectionPage(array $page): void
    {
        $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
        $blockRepo = new \Infrastructure\Repository\MySQLBlockRepository();
        
        $useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
        $collectionData = $useCase->execute($page['id']);
        
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($page['seo_title'] ?? $page['title'], ENT_QUOTES, 'UTF-8') . '</title>
    <meta name="description" content="' . htmlspecialchars($page['seo_description'] ?? '', ENT_QUOTES, 'UTF-8') . '">
    <link rel="stylesheet" href="/healthcare-cms-frontend/styles.css">
</head>
<body>
    <header class="main-header">
        <nav>
            <a href="/">Главная</a>
            <a href="/all-materials">Все материалы</a>
        </nav>
    </header>
    
    <main>
        <div class="container">
            <h1 style="font-family: var(--font-heading); font-size: 2.5rem; margin: 3rem 0 1rem;">
                ' . htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') . '
            </h1>
        </div>';
        
        // Рендерить секции
        foreach ($collectionData['sections'] as $section) {
            $html .= '<section style="padding-top: 3rem; padding-bottom: 3rem;">
                <div class="container">
                    <h3 style="font-family: var(--font-heading); font-size: 1.8rem; margin-bottom: 2rem;">
                        ' . htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') . '
                    </h3>
                    <div class="articles-grid">';
            
            // Рендерить карточки
            foreach ($section['items'] as $item) {
                $html .= '<div class="article-card">
                    <img src="' . htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') . '" 
                         alt="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '">
                    <div class="article-card-content">
                        <h3>' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</h3>
                        <p>' . htmlspecialchars($item['snippet'], ENT_QUOTES, 'UTF-8') . '</p>
                        <a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">Читать далее &rarr;</a>
                    </div>
                </div>';
            }
            
            $html .= '</div></div></section>';
        }
        
        $html .= '
    </main>
    
    <footer class="main-footer">
        <p>&copy; 2025 Healthcare Hacks Brazil</p>
    </footer>
</body>
</html>';
        
        echo $html;
        exit;
    }
```

---

## 📦 Шаг 7: Роутинг — Регистрация API endpoints

### Файл: `backend/public/index.php`

**ЧТО ДЕЛАТЬ:** Добавить маршруты для коллекций

**ГДЕ:** После маршрутов для PageController (примерно строка 250-270)

**КОД ДЛЯ ВСТАВКИ:**

```php
// Collection endpoints
if (preg_match('/^\/api\/pages\/([a-f0-9-]{36})\/collection-items$/', $path, $matches)) {
    $controller = new \Presentation\Controller\CollectionController();
    $controller->getItems($matches[1]);
    exit;
}

if (preg_match('/^\/api\/pages\/([a-f0-9-]{36})\/card-image$/', $path, $matches) && 
    $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $controller = new \Presentation\Controller\CollectionController();
    $controller->updateCardImage($matches[1]);
    exit;
}
```

---

## 📦 Шаг 8: Frontend — UI для редактирования картинок (опционально)

### Файл: `frontend/editor.js`

**ЧТО ДЕЛАТЬ:** Добавить методы для работы с коллекциями

**ГДЕ:** В объект Vue.js, в секцию `methods`

**КОД ДЛЯ ВСТАВКИ:**

```javascript
        // Загрузить элементы коллекции
        async loadCollectionItems() {
            if (this.pageData.type !== 'collection') return;
            
            try {
                const response = await fetch(`/api/pages/${this.pageData.id}/collection-items`);
                const result = await response.json();
                
                if (result.success) {
                    this.collectionItems = result.data;
                }
            } catch (error) {
                console.error('Failed to load collection items:', error);
            }
        },
        
        // Обновить картинку карточки
        async updateCardImage(targetPageId, imageUrl) {
            try {
                const response = await fetch(`/api/pages/${this.pageData.id}/card-image`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${this.authToken}`
                    },
                    body: JSON.stringify({
                        targetPageId: targetPageId,
                        imageUrl: imageUrl
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Картинка обновлена!');
                    await this.loadCollectionItems(); // Перезагрузить
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                console.error('Failed to update card image:', error);
                alert('Ошибка обновления картинки');
            }
        },
        
        // Изменить картинку карточки (открыть галерею)
        async changeCardImage(targetPageId) {
            const newImageUrl = prompt('Введите URL новой картинки:');
            if (newImageUrl) {
                await this.updateCardImage(targetPageId, newImageUrl);
            }
        },
```

**ЧТО ДЕЛАТЬ 2:** Добавить загрузку коллекций в `mounted()`

**НАЙТИ:**
```javascript
    async mounted() {
        await this.loadPage();
    }
```

**ЗАМЕНИТЬ НА:**
```javascript
    async mounted() {
        await this.loadPage();
        
        // Если это коллекция — загрузить элементы
        if (this.pageData.type === 'collection') {
            await this.loadCollectionItems();
        }
    }
```

**ЧТО ДЕЛАТЬ 3:** Добавить новое поле в `data()`

**НАЙТИ:**
```javascript
    data() {
        return {
            pageData: null,
```

**ДОБАВИТЬ:**
```javascript
            collectionItems: null,
```

---

## 📦 Шаг 9: Frontend — HTML для редактирования (опционально)

### Файл: `frontend/editor.html`

**ЧТО ДЕЛАТЬ:** Добавить секцию для редактирования коллекции

**ГДЕ:** После настроек SEO (примерно строка 400-450)

**КОД ДЛЯ ВСТАВКИ:**

```html
        <!-- Collection Editor -->
        <div v-if="pageData.type === 'collection' && collectionItems" 
             class="collection-editor">
            <h3>Элементы коллекции</h3>
            
            <div v-for="section in collectionItems.sections" :key="section.title">
                <h4>{{ section.title }}</h4>
                
                <div class="collection-cards">
                    <div v-for="item in section.items" :key="item.id" class="collection-card">
                        <img :src="item.image" :alt="item.title" style="width: 100px; height: 100px; object-fit: cover;">
                        <div>
                            <strong>{{ item.title }}</strong>
                            <p>{{ item.snippet }}</p>
                            <button @click="changeCardImage(item.id)">Изменить картинку</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
```

---

## 📦 Шаг 10: Создание страницы "Все материалы"

### Через API

**ЧТО ДЕЛАТЬ:** Выполнить POST-запрос для создания страницы

**Метод:** `POST /api/pages`

**Тело запроса:**

```json
{
  "title": "Все материалы",
  "slug": "all-materials",
  "type": "collection",
  "status": "published",
  "seoTitle": "Все материалы - Healthcare Hacks Brazil",
  "seoDescription": "Полная коллекция гайдов и статей о здравоохранении в Бразилии",
  "collectionConfig": {
    "sourceTypes": ["article", "guide"],
    "sortBy": "publishedAt",
    "sortOrder": "desc",
    "sections": [
      {
        "title": "Гайды",
        "sourceTypes": ["guide"]
      },
      {
        "title": "Статьи из блога",
        "sourceTypes": ["article"]
      }
    ],
    "cardImages": {}
  }
}
```

---

## ✅ Чек-лист реализации

### Backend (обязательно)

- [ ] **Шаг 1:** Добавить метод `getCardImage()` в `Page.php`
- [ ] **Шаг 2:** Создать файл `GetCollectionItems.php`
- [ ] **Шаг 3:** Создать файл `UpdateCollectionCardImage.php`
- [ ] **Шаг 4:** Добавить метод `findByTypeAndStatus()` в `MySQLPageRepository.php`
- [ ] **Шаг 5:** Создать файл `CollectionController.php`
- [ ] **Шаг 6:** Обновить `PublicPageController.php` (2 изменения)
- [ ] **Шаг 7:** Обновить роутинг в `index.php`
- [ ] **Шаг 10:** Создать страницу "Все материалы" через API

### Frontend (опционально)

- [ ] **Шаг 8:** Обновить `editor.js` (3 изменения)
- [ ] **Шаг 9:** Обновить `editor.html`

---

## 🧪 Тестирование

### Тест 1: Автоматическое появление статьи

1. Создать статью через редактор
2. Опубликовать статью
3. Открыть `/all-materials` в браузере
4. ✅ Статья должна появиться в секции "Статьи из блога"

### Тест 2: Разделение типов

1. Создать 2 гайда (type: guide)
2. Создать 2 статьи (type: article)
3. Открыть `/all-materials`
4. ✅ Гайды в секции "Гайды"
5. ✅ Статьи в секции "Статьи из блога"

### Тест 3: Изменение картинки (если реализован frontend)

1. Открыть "Все материалы" в редакторе
2. Нажать "Изменить картинку" на карточке
3. Ввести новый URL картинки
4. Открыть `/all-materials` в браузере
5. ✅ Картинка обновилась

---

## 🔧 Устранение проблем

### Проблема: "Page is not a collection"

**Причина:** Страница не имеет тип `collection`

**Решение:** Проверить в БД:
```sql
SELECT id, title, type FROM pages WHERE slug = 'all-materials';
```

Тип должен быть `collection`

### Проблема: Карточки не отображаются

**Причина 1:** Нет опубликованных статей/гайдов

**Решение:** Проверить:
```sql
SELECT id, title, type, status FROM pages WHERE type IN ('article', 'guide');
```

Должны быть записи со `status = 'published'`

**Причина 2:** Метод `findByTypeAndStatus()` не добавлен

**Решение:** Проверить, что метод существует в `MySQLPageRepository.php`

### Проблема: "Call to undefined method"

**Причина:** Не добавлен метод `getCardImage()` в класс `Page`

**Решение:** Повторить Шаг 1

---

## 📚 Справка по структуре

### Типы страниц (PageType)

- `regular` — обычная страница
- `article` — статья в блоге
- `guide` — гайд (инструкция)
- `collection` — коллекция (автосборка)

### Статусы страниц (PageStatus)

- `draft` — черновик
- `published` — опубликовано
- `archived` — архив

### Структура collectionConfig

```json
{
  "sourceTypes": ["article", "guide"],  // Какие типы собирать
  "sortBy": "publishedAt",              // Поле для сортировки
  "sortOrder": "desc",                  // Порядок (desc/asc)
  "sections": [                         // Секции для группировки
    {
      "title": "Название секции",
      "sourceTypes": ["guide"]          // Типы для этой секции
    }
  ],
  "cardImages": {                       // Кастомные картинки
    "uuid-страницы": "/uploads/image.jpg"
  }
}
```

---

## 🎯 Итог

**Минимальная реализация (MVP):** Шаги 1-7, 10 (~4-5 часов)

**Полная реализация:** Все шаги (~6-8 часов)

**Готово к реализации! 🚀**
