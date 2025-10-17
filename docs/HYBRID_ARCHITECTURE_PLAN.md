# Архитектура гибридной системы CMS + Статические шаблоны

**Дата создания:** 6 октября 2025  
**Автор:** Claude + Anna  
**Версия:** 1.0

---

## 🎯 Цель и требования

### Бизнес-требования
1. **Посетители сайта** видят страницы, свёрстанные по прототипу
2. **Владелец сайта** может создавать новые страницы через CMS
3. **Владелец сайта** может редактировать страницы-прототипы через CMS

### Технические требования
- Архитектура должна строиться по принципу **Clean Architecture** (слои: Entity → Use Case → Controller → UI)
- Минимальные изменения в текущем коде
- Постепенная миграция от статических шаблонов к динамическому CMS
- Возможность переключения между статическим и динамическим режимом для каждой страницы

---

## 🏛️ Архитектурные принципы Clean Architecture

### Слои приложения (от внутреннего к внешнему)

```
┌─────────────────────────────────────────────┐
│  UI Layer (Presentation)                    │
│  - Контроллеры                              │
│  - Шаблоны Vue.js                           │
│  - HTML Templates                           │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Interface Adapters                         │
│  - Repositories (MySQL)                     │
│  - API Response Formatters                  │
│  - Data Mappers                             │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Application Layer (Use Cases)              │
│  - CreatePage                               │
│  - GetPageWithBlocks                        │
│  - ImportStaticTemplate                     │
│  - RenderPage                               │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Domain Layer (Entities)                    │
│  - Page Entity                              │
│  - Block Entity                             │
│  - Template ValueObject                     │
│  - Business Rules                           │
└─────────────────────────────────────────────┘
```

### Правила зависимостей
- **Внутренние слои не знают о внешних**
- Domain не зависит от Application
- Application не зависит от Infrastructure
- **Зависимости направлены только внутрь** (к Domain)

---

## 📐 Архитектура гибридной системы

### Концептуальная модель

```
                    ПУБЛИЧНЫЙ САЙТ
                          │
                          ↓
              ┌───────────────────────┐
              │ PublicPageController  │
              └───────────────────────┘
                          │
          ┌───────────────┴───────────────┐
          ↓                               ↓
┌─────────────────────┐       ┌─────────────────────┐
│  ДИНАМИЧЕСКИЙ РЕЖИМ │       │  СТАТИЧЕСКИЙ РЕЖИМ  │
│                     │       │                     │
│  1. Проверить БД    │       │  1. Нет в БД        │
│  2. Есть запись?    │       │  2. Искать template │
│  3. Рендерить из БД │       │  3. Рендерить HTML  │
└─────────────────────┘       └─────────────────────┘
          │                               │
          └───────────────┬───────────────┘
                          ↓
                  КОНТЕНТ ДЛЯ ПОСЕТИТЕЛЯ


                    АДМИНКА CMS
                          │
                          ↓
              ┌───────────────────────┐
              │  Template Manager UI  │
              └───────────────────────┘
                          │
                          ↓
            ┌─────────────────────────────┐
            │  ImportStaticTemplate       │
            │  Use Case                   │
            └─────────────────────────────┘
                          │
                          ↓
        ┌─────────────────────────────────────┐
        │  1. Прочитать статический HTML      │
        │  2. Распарсить на блоки             │
        │  3. Создать Page Entity             │
        │  4. Создать Block Entities          │
        │  5. Сохранить в БД                  │
        └─────────────────────────────────────┘
                          │
                          ↓
          Страница теперь динамическая,
          можно редактировать через CMS
```

---

## 🧱 СЛОЙ 1: Domain (Entities)

### 1.1. Новая Entity: StaticTemplate

**Файл:** `backend/src/Domain/Entity/StaticTemplate.php`

```php
<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTime;

/**
 * StaticTemplate Entity
 * 
 * Представляет статический HTML-шаблон, который может быть импортирован в CMS
 */
class StaticTemplate
{
    public function __construct(
        private string $slug,           // guides, blog, home и т.д.
        private string $filePath,       // /templates/guides.html
        private string $title,          // "Гайды по здравоохранению в Бразилии"
        private PageType $suggestedType,// Предлагаемый тип страницы
        private DateTime $fileModifiedAt,
        private ?string $pageId = null  // null = ещё не импортирован, иначе UUID страницы в БД
    ) {}

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSuggestedType(): PageType
    {
        return $this->suggestedType;
    }

    public function getFileModifiedAt(): DateTime
    {
        return $this->fileModifiedAt;
    }

    public function getPageId(): ?string
    {
        return $this->pageId;
    }

    public function isImported(): bool
    {
        return $this->pageId !== null;
    }

    public function markAsImported(string $pageId): void
    {
        $this->pageId = $pageId;
    }
}
```

### 1.2. Обновление Page Entity

**Добавить новое поле:**

```php
// В классе Page добавить:
private ?string $sourceTemplateSlug = null;  // Если страница создана из шаблона

public function getSourceTemplateSlug(): ?string
{
    return $this->sourceTemplateSlug;
}

public function setSourceTemplateSlug(?string $slug): void
{
    $this->sourceTemplateSlug = $slug;
}
```

### 1.3. Новый ValueObject: TemplateMetadata

**Файл:** `backend/src/Domain/ValueObject/TemplateMetadata.php`

```php
<?php

declare(strict_types=1);

namespace Domain\ValueObject;

/**
 * TemplateMetadata ValueObject
 * 
 * Метаданные статического шаблона (извлекаются из HTML)
 */
class TemplateMetadata
{
    public function __construct(
        private string $title,
        private string $description,
        private array $keywords,
        private array $detectedBlocks  // ['main-screen', 'text-block', 'article-cards']
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getDetectedBlocks(): array
    {
        return $this->detectedBlocks;
    }
}
```

### 1.4. Обновление миграции БД

**Файл:** `database/migrations/005_add_source_template_to_pages.sql`

```sql
-- Добавление поля source_template_slug в таблицу pages
ALTER TABLE pages 
ADD COLUMN source_template_slug VARCHAR(255) NULL 
COMMENT 'Slug статического шаблона, из которого создана страница' 
AFTER created_by;

-- Индекс для быстрого поиска страниц, созданных из шаблонов
CREATE INDEX idx_source_template ON pages(source_template_slug);
```

---

## 🎯 СЛОЙ 2: Application (Use Cases)

### 2.1. Use Case: GetPageWithBlocks (существующий, модифицировать)

**Файл:** `backend/src/Application/UseCase/GetPageWithBlocks.php`

**Изменения:**
```php
// Без изменений - этот Use Case работает только с БД
// Логика fallback к статическим шаблонам НЕ здесь (нарушение SRP)
```

### 2.2. Use Case: RenderStaticTemplate (НОВЫЙ)

**Файл:** `backend/src/Application/UseCase/RenderStaticTemplate.php`

```php
<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\StaticTemplate;
use Domain\Repository\StaticTemplateRepositoryInterface;
use InvalidArgumentException;

/**
 * Use Case: Render Static Template
 * 
 * Отображение статического HTML-шаблона
 */
class RenderStaticTemplate
{
    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository
    ) {}

    public function execute(string $slug): string
    {
        // 1. Найти шаблон по slug
        $template = $this->templateRepository->findBySlug($slug);

        if ($template === null) {
            throw new InvalidArgumentException("Static template '{$slug}' not found");
        }

        // 2. Проверить существование файла
        if (!file_exists($template->getFilePath())) {
            throw new InvalidArgumentException("Template file not found: {$template->getFilePath()}");
        }

        // 3. Прочитать и вернуть HTML
        return file_get_contents($template->getFilePath());
    }
}
```

### 2.3. Use Case: ImportStaticTemplate (НОВЫЙ)

**Файл:** `backend/src/Application/UseCase/ImportStaticTemplate.php`

```php
<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Entity\StaticTemplate;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Repository\StaticTemplateRepositoryInterface;
use Domain\ValueObject\PageStatus;
use Infrastructure\Parser\HtmlTemplateParser;
use Ramsey\Uuid\Uuid;
use DateTime;

/**
 * Use Case: Import Static Template
 * 
 * Импортирует статический шаблон в CMS как динамическую страницу
 */
class ImportStaticTemplate
{
    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository,
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository,
        private HtmlTemplateParser $parser
    ) {}

    public function execute(string $templateSlug, string $createdBy): Page
    {
        // 1. Найти статический шаблон
        $template = $this->templateRepository->findBySlug($templateSlug);
        if ($template === null) {
            throw new InvalidArgumentException("Template '{$templateSlug}' not found");
        }

        // 2. Проверить, не импортирован ли уже
        if ($template->isImported()) {
            throw new InvalidArgumentException("Template already imported as page ID: {$template->getPageId()}");
        }

        // 3. Прочитать HTML файл
        $htmlContent = file_get_contents($template->getFilePath());

        // 4. Распарсить HTML и извлечь метаданные + блоки
        $parsedData = $this->parser->parse($htmlContent);

        // 5. Создать Page Entity
        $pageId = Uuid::uuid4()->toString();
        $page = new Page(
            id: $pageId,
            title: $parsedData['title'],
            slug: $template->getSlug(),
            status: PageStatus::Draft,  // Импортируем как черновик
            type: $template->getSuggestedType(),
            seoTitle: $parsedData['seoTitle'],
            seoDescription: $parsedData['seoDescription'],
            seoKeywords: $parsedData['seoKeywords'],
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: $createdBy,
            collectionConfig: null,
            pageSpecificCode: null
        );

        $page->setSourceTemplateSlug($templateSlug);

        // 6. Сохранить Page в БД
        $this->pageRepository->save($page);

        // 7. Создать Block Entities из распарсенных данных
        foreach ($parsedData['blocks'] as $index => $blockData) {
            $block = new Block(
                id: Uuid::uuid4()->toString(),
                pageId: $pageId,
                type: $blockData['type'],
                position: $index,
                data: $blockData['data'],
                customName: $blockData['customName'] ?? null
            );

            $this->blockRepository->save($block);
        }

        // 8. Пометить шаблон как импортированный
        $template->markAsImported($pageId);
        $this->templateRepository->update($template);

        return $page;
    }
}
```

### 2.4. Use Case: GetAllStaticTemplates (НОВЫЙ)

**Файл:** `backend/src/Application/UseCase/GetAllStaticTemplates.php`

```php
<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\StaticTemplateRepositoryInterface;

/**
 * Use Case: Get All Static Templates
 * 
 * Получить список всех доступных статических шаблонов
 */
class GetAllStaticTemplates
{
    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository
    ) {}

    public function execute(): array
    {
        return $this->templateRepository->findAll();
    }
}
```

---

## 🔌 СЛОЙ 3: Interface Adapters (Repositories)

### 3.1. Repository Interface: StaticTemplateRepository

**Файл:** `backend/src/Domain/Repository/StaticTemplateRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\StaticTemplate;

interface StaticTemplateRepositoryInterface
{
    /**
     * Найти шаблон по slug
     */
    public function findBySlug(string $slug): ?StaticTemplate;

    /**
     * Получить все доступные шаблоны
     * 
     * @return StaticTemplate[]
     */
    public function findAll(): array;

    /**
     * Обновить метаданные шаблона (например, pageId после импорта)
     */
    public function update(StaticTemplate $template): void;
}
```

### 3.2. Repository Implementation: FileSystemStaticTemplateRepository

**Файл:** `backend/src/Infrastructure/Repository/FileSystemStaticTemplateRepository.php`

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\StaticTemplate;
use Domain\Repository\StaticTemplateRepositoryInterface;
use Domain\ValueObject\PageType;
use DateTime;

/**
 * FileSystem-based Static Template Repository
 * 
 * Хранит информацию о статических шаблонах на файловой системе
 */
class FileSystemStaticTemplateRepository implements StaticTemplateRepositoryInterface
{
    private const TEMPLATES_DIR = __DIR__ . '/../../../templates/';
    
    // Маппинг slug → файл и метаданные
    private const TEMPLATE_MAP = [
        'home' => [
            'file' => 'home.html',
            'title' => 'Главная страница',
            'type' => 'regular'
        ],
        'guides' => [
            'file' => 'guides.html',
            'title' => 'Гайды по здравоохранению',
            'type' => 'collection'
        ],
        'blog' => [
            'file' => 'blog.html',
            'title' => 'Блог',
            'type' => 'collection'
        ],
        'all-materials' => [
            'file' => 'all-materials.html',
            'title' => 'Все материалы',
            'type' => 'collection'
        ],
        'bot' => [
            'file' => 'bot.html',
            'title' => 'Бот-помощник',
            'type' => 'regular'
        ],
        'article' => [
            'file' => 'article.html',
            'title' => 'Шаблон статьи',
            'type' => 'article'
        ]
    ];

    // Кэш импортированных шаблонов (slug => pageId)
    private array $importedCache = [];

    public function __construct()
    {
        // Загрузить из файла mapping импортированных шаблонов
        $this->loadImportedCache();
    }

    public function findBySlug(string $slug): ?StaticTemplate
    {
        if (!isset(self::TEMPLATE_MAP[$slug])) {
            return null;
        }

        $config = self::TEMPLATE_MAP[$slug];
        $filePath = self::TEMPLATES_DIR . $config['file'];

        if (!file_exists($filePath)) {
            return null;
        }

        return new StaticTemplate(
            slug: $slug,
            filePath: $filePath,
            title: $config['title'],
            suggestedType: PageType::from($config['type']),
            fileModifiedAt: new DateTime('@' . filemtime($filePath)),
            pageId: $this->importedCache[$slug] ?? null
        );
    }

    public function findAll(): array
    {
        $templates = [];

        foreach (array_keys(self::TEMPLATE_MAP) as $slug) {
            $template = $this->findBySlug($slug);
            if ($template !== null) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    public function update(StaticTemplate $template): void
    {
        // Сохранить в кэш
        if ($template->isImported()) {
            $this->importedCache[$template->getSlug()] = $template->getPageId();
        } else {
            unset($this->importedCache[$template->getSlug()]);
        }

        // Сохранить в файл
        $this->saveImportedCache();
    }

    private function loadImportedCache(): void
    {
        $cacheFile = self::TEMPLATES_DIR . '.imported_templates.json';
        
        if (file_exists($cacheFile)) {
            $this->importedCache = json_decode(file_get_contents($cacheFile), true) ?? [];
        }
    }

    private function saveImportedCache(): void
    {
        $cacheFile = self::TEMPLATES_DIR . '.imported_templates.json';
        file_put_contents($cacheFile, json_encode($this->importedCache, JSON_PRETTY_PRINT));
    }
}
```

### 3.3. HTML Template Parser (Infrastructure Service)

**Файл:** `backend/src/Infrastructure/Parser/HtmlTemplateParser.php`

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Parser;

use DOMDocument;
use DOMXPath;

/**
 * HTML Template Parser
 * 
 * Парсит статические HTML-шаблоны и извлекает:
 * - Метаданные (title, description, keywords)
 * - Блоки контента
 */
class HtmlTemplateParser
{
    public function parse(string $htmlContent): array
    {
        libxml_use_internal_errors(true);
        
        $dom = new DOMDocument();
        $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new DOMXPath($dom);

        return [
            'title' => $this->extractTitle($xpath),
            'seoTitle' => $this->extractMetaTag($xpath, 'title'),
            'seoDescription' => $this->extractMetaTag($xpath, 'description'),
            'seoKeywords' => $this->extractMetaTag($xpath, 'keywords'),
            'blocks' => $this->extractBlocks($xpath, $dom)
        ];
    }

    private function extractTitle(DOMXPath $xpath): string
    {
        $titleNode = $xpath->query('//title')->item(0);
        return $titleNode ? trim($titleNode->textContent) : 'Untitled';
    }

    private function extractMetaTag(DOMXPath $xpath, string $name): ?string
    {
        $metaNode = $xpath->query("//meta[@name='{$name}']/@content")->item(0);
        return $metaNode ? trim($metaNode->textContent) : null;
    }

    private function extractBlocks(DOMXPath $xpath, DOMDocument $dom): array
    {
        $blocks = [];
        
        // Ищем секции с классами, указывающими на тип блока
        $sections = $xpath->query("//section[contains(@class, 'hero') or contains(@class, 'services') or contains(@class, 'about') or contains(@class, 'articles')]");

        foreach ($sections as $index => $section) {
            $blockType = $this->detectBlockType($section);
            $blockData = $this->extractBlockData($section, $blockType, $dom);

            $blocks[] = [
                'type' => $blockType,
                'data' => $blockData,
                'customName' => null
            ];
        }

        return $blocks;
    }

    private function detectBlockType(\DOMElement $element): string
    {
        $classes = $element->getAttribute('class');

        if (str_contains($classes, 'hero')) {
            return 'main-screen';
        } elseif (str_contains($classes, 'services')) {
            return 'service-cards';
        } elseif (str_contains($classes, 'about')) {
            return 'about-section';
        } elseif (str_contains($classes, 'articles')) {
            return 'article-cards';
        }

        return 'text-block';
    }

    private function extractBlockData(\DOMElement $element, string $blockType, DOMDocument $dom): array
    {
        // Упрощённая логика - в реальности нужно более сложное извлечение
        $html = $dom->saveHTML($element);

        return [
            'rawHtml' => $html,
            'extractedAt' => date('Y-m-d H:i:s')
        ];
    }
}
```

---

## 🎨 СЛОЙ 4: Presentation (Controllers)

### 4.1. PublicPageController (МОДИФИЦИРОВАТЬ)

**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`

**Применяем Dependency Injection + Strategy Pattern:**

```php
<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\RenderStaticTemplate;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\StaticTemplateRepositoryInterface;

class PublicPageController
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private StaticTemplateRepositoryInterface $templateRepository
    ) {}

    /**
     * Отобразить публичную страницу по slug
     */
    public function show(string $slug): void
    {
        try {
            // СТРАТЕГИЯ 1: Попытка загрузить из БД (динамический режим)
            $useCase = new GetPageWithBlocks($this->pageRepository);
            $result = $useCase->execute($slug);

            // Рендерим динамическую страницу
            $this->renderDynamicPage($result['page'], $result['blocks']);
            return;

        } catch (\Exception $e) {
            // Страница не найдена в БД
        }

        try {
            // СТРАТЕГИЯ 2: Попытка отобразить статический шаблон (fallback)
            $useCase = new RenderStaticTemplate($this->templateRepository);
            $html = $useCase->execute($slug);

            // Отдаём статический HTML как есть
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            return;

        } catch (\Exception $e) {
            // Шаблон тоже не найден
        }

        // СТРАТЕГИЯ 3: 404 Not Found
        $this->render404();
    }

    private function renderDynamicPage($page, $blocks): void
    {
        // Логика рендеринга динамической страницы из БД
        // (существующий код)
        header('Content-Type: text/html; charset=UTF-8');
        include __DIR__ . '/../../../templates/dynamic-page-renderer.php';
    }

    private function render404(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>404 - Страница не найдена</h1>';
    }
}
```

### 4.2. TemplateController (НОВЫЙ - для админки)

**Файл:** `backend/src/Presentation/Controller/TemplateController.php`

```php
<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetAllStaticTemplates;
use Application\UseCase\ImportStaticTemplate;
use Domain\Repository\StaticTemplateRepositoryInterface;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Infrastructure\Parser\HtmlTemplateParser;
use Presentation\Middleware\AuthMiddleware;

class TemplateController
{
    use JsonResponseTrait;

    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository,
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository,
        private HtmlTemplateParser $parser
    ) {}

    /**
     * GET /api/templates
     * Получить список всех статических шаблонов
     */
    public function index(): void
    {
        try {
            AuthMiddleware::requireAuth();

            $useCase = new GetAllStaticTemplates($this->templateRepository);
            $templates = $useCase->execute();

            $response = array_map(function($template) {
                return [
                    'slug' => $template->getSlug(),
                    'title' => $template->getTitle(),
                    'filePath' => $template->getFilePath(),
                    'suggestedType' => $template->getSuggestedType()->value,
                    'isImported' => $template->isImported(),
                    'pageId' => $template->getPageId(),
                    'fileModifiedAt' => $template->getFileModifiedAt()->format('Y-m-d H:i:s')
                ];
            }, $templates);

            $this->jsonResponse(['success' => true, 'templates' => $response], 200);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * POST /api/templates/{slug}/import
     * Импортировать статический шаблон в CMS
     */
    public function import(string $slug): void
    {
        try {
            $currentUser = AuthMiddleware::requireAuth();

            $useCase = new ImportStaticTemplate(
                $this->templateRepository,
                $this->pageRepository,
                $this->blockRepository,
                $this->parser
            );

            $page = $useCase->execute($slug, $currentUser->getId());

            $this->jsonResponse([
                'success' => true,
                'pageId' => $page->getId(),
                'message' => "Template '{$slug}' imported successfully"
            ], 201);

        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 400);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }
}
```

### 4.3. Обновление роутинга

**Файл:** `backend/public/index.php`

```php
// Добавить новые роуты для работы с шаблонами

// API: Список шаблонов
if ($method === 'GET' && $uri === '/api/templates') {
    $templateRepository = new FileSystemStaticTemplateRepository();
    $controller = new TemplateController($templateRepository, $pageRepository, $blockRepository, new HtmlTemplateParser());
    $controller->index();
    exit;
}

// API: Импорт шаблона
if ($method === 'POST' && preg_match('#^/api/templates/([a-z0-9-]+)/import$#', $uri, $matches)) {
    $slug = $matches[1];
    $templateRepository = new FileSystemStaticTemplateRepository();
    $controller = new TemplateController($templateRepository, $pageRepository, $blockRepository, new HtmlTemplateParser());
    $controller->import($slug);
    exit;
}
```

---

## 🖥️ СЛОЙ 5: UI (Frontend)

### 5.1. Template Manager UI (уже создан)

**Файл:** `frontend/template-manager.html`

**Доработать интеграцию с новыми API endpoints:**

```javascript
// В методе loadTemplates()
async loadTemplates() {
    this.debugMsg('Загрузка списка шаблонов...', 'info');

    try {
        // Новый endpoint: GET /api/templates
        const response = await this.apiClient.request('/api/templates');

        if (response.success) {
            this.templates = response.templates.map(t => ({
                slug: t.slug,
                title: t.title,
                status: t.isImported ? 'cms' : 'static',
                pageId: t.pageId,
                filePath: t.filePath,
                suggestedType: t.suggestedType,
                fileModifiedAt: t.fileModifiedAt
            }));

            this.debugMsg(`Загружено ${this.templates.length} шаблонов`, 'success');
        }
    } catch (error) {
        this.debugMsg('Ошибка загрузки шаблонов', 'error', error);
    }
}

// В методе importTemplate()
async importTemplate(slug) {
    this.debugMsg(`Импорт шаблона "${slug}"...`, 'info');

    try {
        // Новый endpoint: POST /api/templates/{slug}/import
        const response = await this.apiClient.request(`/api/templates/${slug}/import`, {
            method: 'POST'
        });

        if (response.success) {
            this.debugMsg(`Шаблон "${slug}" успешно импортирован!`, 'success', {
                pageId: response.pageId
            });

            // Обновить список
            await this.loadTemplates();
        }
    } catch (error) {
        this.debugMsg(`Ошибка импорта шаблона "${slug}"`, 'error', error);
        alert(`Не удалось импортировать шаблон: ${error.message}`);
    }
}
```

### 5.2. Обновление API Client

**Файл:** `frontend/api-client.js`

```javascript
class ApiClient {
    // ... existing methods ...

    async getAllTemplates() {
        return await this.request('/api/templates');
    }

    async importTemplate(slug) {
        return await this.request(`/api/templates/${slug}/import`, {
            method: 'POST'
        });
    }
}
```

---

## 📊 Диаграмма потока данных

### Сценарий 1: Посетитель запрашивает страницу

```
Пользователь → http://site.com/page/guides
                        ↓
            PublicPageController::show('guides')
                        ↓
        ┌───────────────┴───────────────┐
        ↓                               ↓
  GetPageWithBlocks               RenderStaticTemplate
  Use Case                        Use Case
        ↓                               ↓
  PageRepository                  StaticTemplateRepository
        ↓                               ↓
  MySQL: SELECT * FROM pages      FileSystem: read templates/guides.html
        ↓                               ↓
  Страница НАЙДЕНА?               Шаблон НАЙДЕН?
        ↓                               ↓
  ДА: Рендер из БД                ДА: Вернуть HTML
        ↓                               ↓
        └───────────────┬───────────────┘
                        ↓
                  HTML Response
```

### Сценарий 2: Владелец импортирует шаблон

```
Админ → Нажимает "Импортировать" в Template Manager
                        ↓
        POST /api/templates/guides/import
                        ↓
        TemplateController::import('guides')
                        ↓
        ImportStaticTemplate Use Case
                        ↓
        ┌───────────────┴───────────────┐
        ↓                               ↓
  StaticTemplateRepository          HtmlTemplateParser
        ↓                               ↓
  Получить шаблон                   Распарсить HTML
        ↓                               ↓
  templates/guides.html          Извлечь блоки и метаданные
        ↓                               ↓
        └───────────────┬───────────────┘
                        ↓
                Create Page Entity
                Create Block Entities
                        ↓
                PageRepository::save()
                BlockRepository::save()
                        ↓
        Обновить .imported_templates.json
                        ↓
            Response: { success: true, pageId: "..." }
```

---

## 🔄 Постепенная миграция

### Фаза 1: Подготовка (текущая фаза)
- ✅ Статические шаблоны готовы (home, guides, blog, all-materials, bot, article)
- ✅ CMS работает
- ⏳ Добавить архитектурные слои для гибридной системы

### Фаза 2: Реализация (1-2 дня)
1. Создать Entity: StaticTemplate
2. Создать Use Cases: RenderStaticTemplate, ImportStaticTemplate
3. Создать Repository: FileSystemStaticTemplateRepository
4. Создать Parser: HtmlTemplateParser
5. Модифицировать PublicPageController
6. Создать TemplateController
7. Обновить роутинг

### Фаза 3: Тестирование (1 день)
1. Проверить fallback к статическим шаблонам
2. Протестировать импорт каждого шаблона
3. Проверить редактирование импортированных страниц
4. Убедиться, что после импорта страница рендерится из БД

### Фаза 4: Деплой (текущая среда)
1. Синхронизировать код в XAMPP
2. Запустить миграцию БД (добавить source_template_slug)
3. Проверить работу на localhost
4. Документировать процесс для production

---

## 🎯 Преимущества архитектуры

### 1. Соблюдение Clean Architecture
- ✅ Domain не зависит от Infrastructure
- ✅ Use Cases изолированы и тестируемы
- ✅ Легко заменить FileSystem на DB для хранения шаблонов
- ✅ Легко добавить новые источники контента (API, CDN и т.д.)

### 2. Гибкость
- Каждая страница может работать в статическом или динамическом режиме
- Владелец решает, когда импортировать шаблон
- Возможность откатиться к статической версии

### 3. Постепенная миграция
- Не нужно импортировать все сразу
- Посетители сразу видят контент (статические шаблоны)
- Владелец импортирует страницы по мере необходимости

### 4. Производительность
- Статические шаблоны = мгновенная загрузка (без запросов к БД)
- Кэширование импортированных страниц
- Минимальная нагрузка на сервер

---

## 📝 Следующие шаги

1. **Создать все Entity и ValueObject** (Domain слой)
2. **Реализовать Use Cases** (Application слой)
3. **Создать Repositories** (Infrastructure слой)
4. **Модифицировать Controllers** (Presentation слой)
5. **Обновить UI** (уже готов template-manager.html)
6. **Написать тесты** для каждого слоя
7. **Синхронизировать в XAMPP** и протестировать

---

**Вопросы для обсуждения:**
1. Нужна ли таблица `static_templates` в БД или достаточно JSON-файла `.imported_templates.json`?
2. Как обрабатывать обновления статических шаблонов после импорта?
3. Добавить ли возможность "экспорта" страницы обратно в статический HTML?

