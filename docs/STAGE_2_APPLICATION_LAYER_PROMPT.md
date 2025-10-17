# Stage 2: Application Layer — Use-Cases для публикации с rendered_html

**Дата:** 2025-10-13  
**Основа:** [PUBLISH_IMPLEMENTATION_PLAN_BY_LAYERS.md](./PUBLISH_IMPLEMENTATION_PLAN_BY_LAYERS.md)  
**Предыдущий этап:** [STAGE_1_DOMAIN_INFRASTRUCTURE_PROMPT.md](./STAGE_1_DOMAIN_INFRASTRUCTURE_PROMPT.md) ✅

---

## Цель Stage 2

Реализовать **Application layer** (use-cases) для функционала публикации страниц с генерацией статичного HTML:

1. ✅ **Stage 1 выполнен:** Domain (Page entity с `rendered_html`, `menu_title`) + Infrastructure (MySQLPageRepository, migration) работают.
2. 🎯 **Stage 2:** Создать use-case `RenderPageHtml`, обновить `PublishPage` и `UpdatePage` для работы с новыми полями.
3. 📝 **Milestone:** После Stage 2 публикация страницы должна генерировать и сохранять `rendered_html` в БД.

---

## Архитектурный контекст

### Clean Architecture: Application Layer

**Application layer** (use-cases) — это **бизнес-логика приложения**:
- Оркеструет взаимодействие между Domain entities и Infrastructure repositories.
- **Не содержит** SQL, HTTP, UI логику (это Infrastructure и Presentation).
- **Зависит только от:** Domain (entities, repository interfaces).

**Примеры use-cases в проекте:**
- `CreatePage` — создать новую страницу.
- `UpdatePage` — обновить существующую страницу.
- `PublishPage` — опубликовать страницу (установить статус published).
- `GetPageWithBlocks` — получить страницу с её блоками.

**Новый use-case для Stage 2:**
- `RenderPageHtml` — сгенерировать статичный HTML из Page entity и её блоков.

---

## Задачи Stage 2

### Задача 2.1: Создать `Application/UseCase/RenderPageHtml.php`

**Цель:** Генерация полного HTML-документа из Page entity и её блоков.

**Зависимости:**
- `PageRepositoryInterface` (для получения page).
- `BlockRepositoryInterface` (для получения блоков страницы).
- Переиспользовать логику рендеринга из `Presentation/Controller/PublicPageController::renderPage()`.

**Входные параметры:**
- `Page $page` — entity страницы.
- (Опционально) `array $blocks` — если блоки уже загружены (оптимизация).

**Выходные данные:**
- `string` — полный HTML-документ (с `<html>`, `<head>`, `<body>`, CSS).

**Логика рендеринга (алгоритм):**

1. **Загрузить блоки страницы** (если не переданы в параметре):
   ```php
   $blocks = $this->blockRepository->findByPageId($page->getId());
   ```

2. **Сгенерировать HTML structure:**
   - **Header:**
     - Site name / logo (можно взять из конфига или хардкодить).
     - Публичное меню (получить через `MenuController` логику или напрямую из PageRepository).
   - **Body:**
     - Блоки в правильном порядке (`order_position` ASC).
     - Рендеринг каждого типа блока (`text`, `image`, `code`, etc.) в HTML.
   - **Footer:**
     - Copyright, ссылки (опционально).
   - **CSS:**
     - Встроить `editor-public.css` или ссылка на `/styles/editor-public.css`.

3. **Вернуть HTML string:**
   ```php
   return $htmlDocument;
   ```

**Структура файла `backend/src/Application/UseCase/RenderPageHtml.php`:**

```php
<?php

declare(strict_types=1);

namespace ExpatsHealth\CMS\Application\UseCase;

use ExpatsHealth\CMS\Domain\Entity\Page;
use ExpatsHealth\CMS\Domain\Repository\PageRepositoryInterface;
use ExpatsHealth\CMS\Domain\Repository\BlockRepositoryInterface;

/**
 * Use Case: Render Page to Static HTML
 * 
 * Generates a complete HTML document from a Page entity and its blocks.
 * Used for pre-rendering at publish time (static HTML caching).
 */
class RenderPageHtml
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
     * Execute: render Page to HTML
     * 
     * @param Page $page Page entity to render
     * @param array|null $blocks Optional pre-loaded blocks (optimization)
     * @return string Complete HTML document
     */
    public function execute(Page $page, ?array $blocks = null): string
    {
        // 1. Load blocks if not provided
        if ($blocks === null) {
            $blocks = $this->blockRepository->findByPageId($page->getId());
        }

        // Sort blocks by order_position
        usort($blocks, fn($a, $b) => $a->getOrderPosition() <=> $b->getOrderPosition());

        // 2. Build HTML document
        $html = $this->buildHtmlDocument($page, $blocks);

        return $html;
    }

    /**
     * Build complete HTML document
     */
    private function buildHtmlDocument(Page $page, array $blocks): string
    {
        $title = htmlspecialchars($page->getTitle(), ENT_QUOTES, 'UTF-8');
        $seoTitle = htmlspecialchars($page->getSeoTitle() ?? $page->getTitle(), ENT_QUOTES, 'UTF-8');
        $seoDescription = htmlspecialchars($page->getSeoDescription() ?? '', ENT_QUOTES, 'UTF-8');
        $seoKeywords = htmlspecialchars($page->getSeoKeywords() ?? '', ENT_QUOTES, 'UTF-8');

        // Build blocks HTML
        $blocksHtml = $this->renderBlocks($blocks);

        // Build menu HTML
        $menuHtml = $this->renderMenu();

        // Complete HTML document
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$seoTitle}</title>
    <meta name="description" content="{$seoDescription}">
    <meta name="keywords" content="{$seoKeywords}">
    <link rel="stylesheet" href="/styles/editor-public.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <h1 class="site-title"><a href="/">Healthcare CMS</a></h1>
            <nav class="site-nav">
                {$menuHtml}
            </nav>
        </div>
    </header>

    <main class="page-content">
        <div class="container">
            <h1 class="page-title">{$title}</h1>
            <div class="blocks-container">
                {$blocksHtml}
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2025 Healthcare CMS. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Render blocks to HTML
     */
    private function renderBlocks(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $type = $block->getType();
            $content = $block->getContent();

            switch ($type) {
                case 'text':
                    $html .= $this->renderTextBlock($content);
                    break;
                case 'image':
                    $html .= $this->renderImageBlock($content);
                    break;
                case 'code':
                    $html .= $this->renderCodeBlock($content);
                    break;
                case 'heading':
                    $html .= $this->renderHeadingBlock($content);
                    break;
                default:
                    // Unknown block type — render as text
                    $html .= '<div class="block block-unknown">' . htmlspecialchars(json_encode($content), ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        return $html;
    }

    private function renderTextBlock(array $content): string
    {
        $text = $content['text'] ?? '';
        return '<div class="block block-text"><p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p></div>';
    }

    private function renderImageBlock(array $content): string
    {
        $url = htmlspecialchars($content['url'] ?? '', ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($content['alt'] ?? '', ENT_QUOTES, 'UTF-8');
        return '<div class="block block-image"><img src="' . $url . '" alt="' . $alt . '" /></div>';
    }

    private function renderCodeBlock(array $content): string
    {
        $code = htmlspecialchars($content['code'] ?? '', ENT_QUOTES, 'UTF-8');
        $language = htmlspecialchars($content['language'] ?? 'plaintext', ENT_QUOTES, 'UTF-8');
        return '<div class="block block-code"><pre><code class="language-' . $language . '">' . $code . '</code></pre></div>';
    }

    private function renderHeadingBlock(array $content): string
    {
        $text = htmlspecialchars($content['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $level = (int)($content['level'] ?? 2);
        $level = max(1, min(6, $level)); // Clamp to h1-h6
        return '<div class="block block-heading"><h' . $level . '>' . $text . '</h' . $level . '></div>';
    }

    /**
     * Render public menu
     */
    private function renderMenu(): string
    {
        // Get published pages with show_in_menu = 1
        $menuPages = $this->pageRepository->findPublishedMenuPages();

        $menuHtml = '<ul class="menu">';
        foreach ($menuPages as $page) {
            $label = htmlspecialchars($page->getMenuTitle() ?? $page->getTitle(), ENT_QUOTES, 'UTF-8');
            $slug = htmlspecialchars($page->getSlug(), ENT_QUOTES, 'UTF-8');
            $menuHtml .= '<li><a href="/' . $slug . '">' . $label . '</a></li>';
        }
        $menuHtml .= '</ul>';

        return $menuHtml;
    }
}
```

**Примечания:**
- Метод `findPublishedMenuPages()` нужно добавить в `PageRepositoryInterface` и реализовать в `MySQLPageRepository` (см. дополнительные задачи ниже).
- Рендеринг блоков упрощён — можно улучшить (markdown, syntax highlighting для кода, etc.).
- CSS путь `/styles/editor-public.css` должен быть доступен на публичном сайте.

---

### Задача 2.2: Обновить `Application/UseCase/PublishPage.php`

**Файл:** `backend/src/Application/UseCase/PublishPage.php`

**Текущее состояние:**
```php
class PublishPage {
    public function execute(string $pageId): void {
        $page = $this->pageRepository->findById($pageId);
        if (!$page) throw new PageNotFoundException();
        
        $page->publish(); // sets status to 'published' and published_at
        $this->pageRepository->save($page);
    }
}
```

**Изменения:**

1. **Добавить зависимость `RenderPageHtml`:**
   ```php
   private RenderPageHtml $renderPageHtml;

   public function __construct(
       PageRepositoryInterface $pageRepository,
       BlockRepositoryInterface $blockRepository,
       RenderPageHtml $renderPageHtml
   ) {
       $this->pageRepository = $pageRepository;
       $this->blockRepository = $blockRepository;
       $this->renderPageHtml = $renderPageHtml;
   }
   ```

2. **После `$page->publish()` вызвать рендеринг и установить `rendered_html`:**
   ```php
   public function execute(string $pageId): void {
       $page = $this->pageRepository->findById($pageId);
       if (!$page) {
           throw new PageNotFoundException("Page with ID {$pageId} not found");
       }
       
       // Load blocks
       $blocks = $this->blockRepository->findByPageId($pageId);
       
       // Publish page (set status and published_at)
       $page->publish();
       
       // Generate static HTML
       $renderedHtml = $this->renderPageHtml->execute($page, $blocks);
       $page->setRenderedHtml($renderedHtml);
       
       // Save page with rendered HTML
       $this->pageRepository->save($page);
   }
   ```

**Обоснование:**
- Use-case `PublishPage` теперь оркеструет два действия: установить статус published + сгенерировать HTML.
- Это соответствует принципу Single Responsibility: PublishPage отвечает за "опубликовать страницу" (что включает генерацию HTML).

---

### Задача 2.3: Обновить `Application/UseCase/UpdatePage.php`

**Файл:** `backend/src/Application/UseCase/UpdatePage.php`

**Текущее состояние (примерно):**
```php
class UpdatePage {
    public function execute(string $pageId, array $data): void {
        $page = $this->pageRepository->findById($pageId);
        if (!$page) throw new PageNotFoundException();
        
        // Update fields
        $page->setTitle($data['title'] ?? $page->getTitle());
        $page->setSlug($data['slug'] ?? $page->getSlug());
        // ... other fields ...
        
        $this->pageRepository->save($page);
    }
}
```

**Изменения:**

1. **Добавить обработку `menu_title`:**
   ```php
   // Update page metadata
   if (isset($data['menu_title'])) {
       $page->setMenuTitle($data['menu_title']);
   }
   ```

2. **Полный обновлённый код:**
   ```php
   public function execute(string $pageId, array $data): void {
       $page = $this->pageRepository->findById($pageId);
       if (!$page) {
           throw new PageNotFoundException("Page with ID {$pageId} not found");
       }
       
       // Update basic fields
       if (isset($data['title'])) {
           $page->setTitle($data['title']);
       }
       
       if (isset($data['slug'])) {
           $page->setSlug($data['slug']);
       }
       
       // Update SEO fields
       if (isset($data['seo_title'])) {
           $page->setSeoTitle($data['seo_title']);
       }
       
       if (isset($data['seo_description'])) {
           $page->setSeoDescription($data['seo_description']);
       }
       
       if (isset($data['seo_keywords'])) {
           $page->setSeoKeywords($data['seo_keywords']);
       }
       
       // Update menu settings
       if (isset($data['show_in_menu'])) {
           $page->setShowInMenu((bool)$data['show_in_menu']);
       }
       
       if (isset($data['menu_title'])) {
           $page->setMenuTitle($data['menu_title']);
       }
       
       if (isset($data['menu_order'])) {
           $page->setMenuOrder((int)$data['menu_order']);
       }
       
       // Update blocks if provided
       if (isset($data['blocks'])) {
           // Delete old blocks
           $this->blockRepository->deleteByPageId($pageId);
           
           // Create new blocks
           foreach ($data['blocks'] as $blockData) {
               $block = Block::create(
                   pageId: $pageId,
                   type: $blockData['type'],
                   content: $blockData['content'],
                   orderPosition: $blockData['order_position'] ?? 0
               );
               $this->blockRepository->save($block);
           }
       }
       
       // Save page
       $this->pageRepository->save($page);
   }
   ```

**Обоснование:**
- `UpdatePage` обрабатывает все изменяемые поля, включая новое `menu_title`.
- Логика сохранения блоков уже может быть в use-case — если нет, добавить.

---

### Задача 2.4: Добавить метод `findPublishedMenuPages()` в репозиторий

**Интерфейс:** `backend/src/Domain/Repository/PageRepositoryInterface.php`

**Добавить метод:**
```php
/**
 * Find all published pages that should appear in menu
 * 
 * @return Page[]
 */
public function findPublishedMenuPages(): array;
```

**Реализация:** `backend/src/Infrastructure/Repository/MySQLPageRepository.php`

**Добавить метод:**
```php
public function findPublishedMenuPages(): array
{
    $sql = "
        SELECT * FROM pages
        WHERE status = 'published'
          AND show_in_menu = 1
          AND trashed_at IS NULL
        ORDER BY menu_order ASC, id ASC
    ";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $pages = [];
    foreach ($rows as $row) {
        $pages[] = $this->hydrate($row);
    }
    
    return $pages;
}
```

**Обоснование:**
- `RenderPageHtml` use-case нуждается в списке menu pages для генерации навигации.
- Этот метод инкапсулирует SQL запрос, соблюдая Clean Architecture (use-case не знает про SQL).

---

### Задача 2.5: Написать unit-тесты для use-cases

**Файл 1:** `backend/tests/Unit/Application/RenderPageHtmlTest.php`

**Тесты:**

```php
<?php

declare(strict_types=1);

namespace ExpatsHealth\CMS\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ExpatsHealth\CMS\Application\UseCase\RenderPageHtml;
use ExpatsHealth\CMS\Domain\Entity\Page;
use ExpatsHealth\CMS\Domain\Entity\Block;
use ExpatsHealth\CMS\Domain\Repository\PageRepositoryInterface;
use ExpatsHealth\CMS\Domain\Repository\BlockRepositoryInterface;

class RenderPageHtmlTest extends TestCase
{
    public function testRenderPageGeneratesValidHtml(): void
    {
        // Mock repositories
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        
        // Create test page
        $page = Page::create(
            title: 'Test Page',
            slug: 'test-page',
            createdBy: 'admin'
        );
        $page->publish();
        
        // Create test blocks
        $blocks = [
            Block::create('page-1', 'text', ['text' => 'Hello World'], 1),
            Block::create('page-1', 'heading', ['text' => 'Section 1', 'level' => 2], 2)
        ];
        
        // Mock findPublishedMenuPages
        $pageRepo->method('findPublishedMenuPages')->willReturn([]);
        
        // Execute use-case
        $useCase = new RenderPageHtml($pageRepo, $blockRepo);
        $html = $useCase->execute($page, $blocks);
        
        // Assertions
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Test Page</title>', $html);
        $this->assertStringContainsString('Hello World', $html);
        $this->assertStringContainsString('<h2>Section 1</h2>', $html);
    }
    
    public function testRenderPageEscapesHtmlInContent(): void
    {
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        
        $page = Page::create('Test', 'test', 'admin');
        $blocks = [
            Block::create('page-1', 'text', ['text' => '<script>alert("XSS")</script>'], 1)
        ];
        
        $pageRepo->method('findPublishedMenuPages')->willReturn([]);
        
        $useCase = new RenderPageHtml($pageRepo, $blockRepo);
        $html = $useCase->execute($page, $blocks);
        
        // Should escape script tag
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
    
    public function testRenderPageIncludesMenuWithCustomMenuTitle(): void
    {
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        
        $page = Page::create('Test', 'test', 'admin');
        
        // Create menu page with custom menu_title
        $menuPage = Page::create('About Us Full Title', 'about', 'admin');
        $menuPage->setMenuTitle('About');
        $menuPage->publish();
        
        $pageRepo->method('findPublishedMenuPages')->willReturn([$menuPage]);
        
        $useCase = new RenderPageHtml($pageRepo, $blockRepo);
        $html = $useCase->execute($page, []);
        
        // Should use custom menu_title instead of full title
        $this->assertStringContainsString('About', $html);
        $this->assertStringNotContainsString('About Us Full Title', $html);
    }
}
```

---

**Файл 2:** `backend/tests/Unit/Application/PublishPageTest.php`

**Тесты:**

```php
<?php

declare(strict_types=1);

namespace ExpatsHealth\CMS\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ExpatsHealth\CMS\Application\UseCase\PublishPage;
use ExpatsHealth\CMS\Application\UseCase\RenderPageHtml;
use ExpatsHealth\CMS\Domain\Entity\Page;
use ExpatsHealth\CMS\Domain\Repository\PageRepositoryInterface;
use ExpatsHealth\CMS\Domain\Repository\BlockRepositoryInterface;

class PublishPageTest extends TestCase
{
    public function testPublishPageSetsStatusAndRenderedHtml(): void
    {
        // Mock repositories
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        
        // Create test page (draft status)
        $page = Page::create('Test', 'test', 'admin');
        $this->assertEquals('draft', $page->getStatus()->getValue());
        
        // Mock findById
        $pageRepo->method('findById')->with('page-123')->willReturn($page);
        
        // Mock findByPageId (no blocks)
        $blockRepo->method('findByPageId')->with('page-123')->willReturn([]);
        
        // Mock RenderPageHtml
        $renderHtml = $this->createMock(RenderPageHtml::class);
        $renderHtml->method('execute')->willReturn('<html>Test Page</html>');
        
        // Expect save to be called
        $pageRepo->expects($this->once())->method('save')->with($this->callback(function ($savedPage) {
            return $savedPage->getStatus()->getValue() === 'published'
                && $savedPage->getRenderedHtml() !== null
                && $savedPage->getPublishedAt() !== null;
        }));
        
        // Execute use-case
        $useCase = new PublishPage($pageRepo, $blockRepo, $renderHtml);
        $useCase->execute('page-123');
    }
    
    public function testPublishPageThrowsExceptionIfPageNotFound(): void
    {
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        $renderHtml = $this->createMock(RenderPageHtml::class);
        
        $pageRepo->method('findById')->willReturn(null);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Page with ID not-exists not found');
        
        $useCase = new PublishPage($pageRepo, $blockRepo, $renderHtml);
        $useCase->execute('not-exists');
    }
}
```

---

**Файл 3:** `backend/tests/Unit/Application/UpdatePageTest.php`

**Тест для `menu_title`:**

```php
<?php

declare(strict_types=1);

namespace ExpatsHealth\CMS\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ExpatsHealth\CMS\Application\UseCase\UpdatePage;
use ExpatsHealth\CMS\Domain\Entity\Page;
use ExpatsHealth\CMS\Domain\Repository\PageRepositoryInterface;
use ExpatsHealth\CMS\Domain\Repository\BlockRepositoryInterface;

class UpdatePageTest extends TestCase
{
    public function testUpdatePageSetsMenuTitle(): void
    {
        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $blockRepo = $this->createMock(BlockRepositoryInterface::class);
        
        $page = Page::create('Original Title', 'test', 'admin');
        $pageRepo->method('findById')->with('page-123')->willReturn($page);
        
        // Expect save with updated menu_title
        $pageRepo->expects($this->once())->method('save')->with($this->callback(function ($savedPage) {
            return $savedPage->getMenuTitle() === 'Custom Menu Label';
        }));
        
        $useCase = new UpdatePage($pageRepo, $blockRepo);
        $useCase->execute('page-123', [
            'menu_title' => 'Custom Menu Label'
        ]);
    }
}
```

---

## Чеклист выполнения Stage 2

### Код (PHP)
- [ ] Создан файл `backend/src/Application/UseCase/RenderPageHtml.php` с методом `execute(Page, ?array): string`
- [ ] Реализованы методы рендеринга блоков: `renderTextBlock`, `renderImageBlock`, `renderCodeBlock`, `renderHeadingBlock`
- [ ] Реализован метод `renderMenu()` для генерации навигации
- [ ] Обновлён `backend/src/Application/UseCase/PublishPage.php`:
  - Добавлена зависимость `RenderPageHtml`
  - После `publish()` вызывается рендеринг и установка `rendered_html`
- [ ] Обновлён `backend/src/Application/UseCase/UpdatePage.php`:
  - Обработка `menu_title` из `$data` array
- [ ] Добавлен метод `findPublishedMenuPages()` в:
  - `backend/src/Domain/Repository/PageRepositoryInterface.php` (интерфейс)
  - `backend/src/Infrastructure/Repository/MySQLPageRepository.php` (реализация)

### Тесты (PHPUnit)
- [ ] Создан `backend/tests/Unit/Application/RenderPageHtmlTest.php`
  - Тест: генерация валидного HTML
  - Тест: экранирование HTML в контенте (XSS protection)
  - Тест: использование custom `menu_title` в меню
- [ ] Создан `backend/tests/Unit/Application/PublishPageTest.php`
  - Тест: публикация устанавливает статус и `rendered_html`
  - Тест: exception если страница не найдена
- [ ] Обновлён/создан `backend/tests/Unit/Application/UpdatePageTest.php`
  - Тест: обновление `menu_title`

### Верификация
- [ ] Все unit-тесты проходят (`php vendor/bin/phpunit tests/Unit/Application/`)
- [ ] Проверка PHP syntax (`php -l` для всех изменённых файлов)
- [ ] Code style соответствует PSR-12 (если используется code sniffer)

---

## Команды для выполнения

### 1. Создать файлы use-cases
```bash
# Создать RenderPageHtml.php
touch backend/src/Application/UseCase/RenderPageHtml.php

# Создать тесты
mkdir -p backend/tests/Unit/Application
touch backend/tests/Unit/Application/RenderPageHtmlTest.php
touch backend/tests/Unit/Application/PublishPageTest.php
touch backend/tests/Unit/Application/UpdatePageTest.php
```

### 2. Проверить синтаксис PHP
```bash
php -l backend/src/Application/UseCase/RenderPageHtml.php
php -l backend/src/Application/UseCase/PublishPage.php
php -l backend/src/Application/UseCase/UpdatePage.php
```

### 3. Запустить unit-тесты
```bash
# Все unit-тесты Application layer
php vendor/bin/phpunit tests/Unit/Application/

# Конкретный тест
php vendor/bin/phpunit tests/Unit/Application/RenderPageHtmlTest.php
```

### 4. Проверка coverage (опционально)
```bash
php vendor/bin/phpunit --coverage-html coverage/ tests/Unit/Application/
```

---

## Порядок работы (шаг за шагом)

### Шаг 1: Создать `RenderPageHtml` use-case
1. Создать файл `backend/src/Application/UseCase/RenderPageHtml.php`.
2. Реализовать конструктор с зависимостями (`PageRepositoryInterface`, `BlockRepositoryInterface`).
3. Реализовать метод `execute(Page $page, ?array $blocks = null): string`.
4. Реализовать приватные методы рендеринга блоков.
5. Реализовать метод `renderMenu()`.

### Шаг 2: Добавить метод в репозиторий
1. Добавить `findPublishedMenuPages(): array` в `PageRepositoryInterface`.
2. Реализовать SQL запрос в `MySQLPageRepository`.

### Шаг 3: Обновить `PublishPage`
1. Открыть `backend/src/Application/UseCase/PublishPage.php`.
2. Добавить `RenderPageHtml` в конструктор.
3. После `$page->publish()` вызвать `$this->renderPageHtml->execute()`.
4. Установить `rendered_html` через `$page->setRenderedHtml()`.

### Шаг 4: Обновить `UpdatePage`
1. Открыть `backend/src/Application/UseCase/UpdatePage.php`.
2. Добавить обработку `menu_title` из `$data` array.

### Шаг 5: Написать unit-тесты
1. Создать `RenderPageHtmlTest.php` с 3 тестами.
2. Создать `PublishPageTest.php` с 2 тестами.
3. Обновить `UpdatePageTest.php` (добавить тест для `menu_title`).

### Шаг 6: Запустить тесты и проверить
1. Выполнить все unit-тесты.
2. Убедиться, что все проходят (зелёный статус).
3. Проверить PHP syntax всех файлов.

---

## Ожидаемый результат Stage 2

После выполнения Stage 2:

✅ **Use-case `RenderPageHtml` создан** и генерирует полный HTML-документ из Page entity + блоки.

✅ **Use-case `PublishPage` обновлён**: при публикации генерируется `rendered_html` и сохраняется в БД.

✅ **Use-case `UpdatePage` обновлён**: обрабатывает `menu_title` из payload.

✅ **Unit-тесты покрывают новую логику**: рендеринг HTML, публикация с HTML, обновление `menu_title`.

✅ **Milestone достигнут**: публикация страницы генерирует и сохраняет `rendered_html` в database.

---

## Следующий этап

После Stage 2 переходим к **Stage 3: Presentation Layer** (контроллеры):
- Обновить `PageController::publish()` для возврата `publicUrl` в response.
- Обновить `PublicPageController::show()` для отдачи `rendered_html`.
- Обновить `MenuController::getPublicMenu()` для использования `menu_title`.

---

**Дата создания:** 2025-10-13  
**Автор:** Healthcare CMS Team  
**Статус:** готов к реализации  
**Зависимости:** Stage 1 ✅ (Domain + Infrastructure)
