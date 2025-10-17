# PHASE 2.3 COMPLETION PROMPT - Refactor Remaining Use Cases

**Date:** October 16, 2025  
**Project:** Healthcare CMS Backend  
**Status:** Phase 2.1-2.2 complete — UpdatePageInline refactored  
**Goal:** Apply same Clean Architecture pattern to 4 more Use Cases

---

## ⚠️ CRITICAL INSTRUCTION

**This prompt continues Phase 2 refactoring.**  
**Follow the EXACT SAME PATTERN as UpdatePageInline.**  
**Do NOT skip any Use Case.**  
**Execute sequentially in the order listed.**

---

## REFACTORING PATTERN (from Phase 2.1)

For each Use Case, apply these changes:

1. **Create DTOs** (Request + Response)
2. **Update execute() signature** to use DTOs
3. **Replace generic exceptions** with Domain exceptions
4. **Update DI container** registration
5. **Create unit tests** (4 test cases minimum)

---

## PART 1: REFACTOR GetPageWithBlocks

### Step 1.1: Create DTOs

**CREATE FILE:** `backend/src/Application/DTO/GetPageWithBlocksRequest.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class GetPageWithBlocksRequest
{
    public function __construct(
        public readonly string $pageId
    ) {
        if (empty($this->pageId)) {
            throw new \InvalidArgumentException('Page ID is required.');
        }
    }
}
```

**CREATE FILE:** `backend/src/Application/DTO/GetPageWithBlocksResponse.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class GetPageWithBlocksResponse
{
    public function __construct(
        public readonly array $page,
        public readonly array $blocks
    ) {
    }
}
```

---

### Step 1.2: Refactor GetPageWithBlocks.php

**FILE:** `backend/src/Application/UseCase/GetPageWithBlocks.php`

**FIND THIS CODE:**
```php
public function execute(string $pageId): array
{
    // Найти страницу
    $page = $this->pageRepository->findById($pageId);
    if (!$page) {
        throw new DomainException('Page not found');
    }
```

**REPLACE WITH:**
```php
public function execute(GetPageWithBlocksRequest $request): GetPageWithBlocksResponse
{
    // Найти страницу
    $page = $this->pageRepository->findById($request->pageId);
    if (!$page) {
        throw PageNotFoundException::withId($request->pageId);
    }
```

**FIND THIS CODE (at the end):**
```php
    return [
        'page' => $this->serializePage($page),
        'blocks' => array_map(fn($block) => $this->serializeBlock($block), $blocks)
    ];
```

**REPLACE WITH:**
```php
    return new GetPageWithBlocksResponse(
        page: $this->serializePage($page),
        blocks: array_map(fn($block) => $this->serializeBlock($block), $blocks)
    );
```

**ADD IMPORTS at top:**
```php
use Application\DTO\GetPageWithBlocksRequest;
use Application\DTO\GetPageWithBlocksResponse;
use Domain\Exception\PageNotFoundException;
```

---

### Step 1.3: Update executeBySlug() Method

**FIND THIS CODE:**
```php
public function executeBySlug(string $slug): array
{
    $page = $this->pageRepository->findBySlug($slug);
    if (!$page) {
        throw new DomainException('Page not found');
    }
```

**REPLACE WITH:**
```php
public function executeBySlug(string $slug): GetPageWithBlocksResponse
{
    $page = $this->pageRepository->findBySlug($slug);
    if (!$page) {
        throw PageNotFoundException::withSlug($slug);
    }
```

**ADD to PageNotFoundException.php:**
```php
public static function withSlug(string $slug): self
{
    return new self("Page with slug '{$slug}' not found", ['slug' => $slug]);
}
```

**FIND THIS CODE (at the end of executeBySlug):**
```php
    return [
        'page' => $this->serializePage($page),
        'blocks' => array_map(fn($block) => $this->serializeBlock($block), $blocks)
    ];
```

**REPLACE WITH:**
```php
    return new GetPageWithBlocksResponse(
        page: $this->serializePage($page),
        blocks: array_map(fn($block) => $this->serializeBlock($block), $blocks)
    );
```

---

### Step 1.4: Update Container Registration

**FILE:** `backend/bootstrap/container.php`

**FIND THIS CODE:**
```php
$container->bind('GetPageWithBlocks', fn($c) => new GetPageWithBlocks(
    $c->get('PageRepository'),
    $c->get('BlockRepository')
));
```

**VERIFY IT EXISTS** - no changes needed (already correct).

---

### Step 1.5: Create Unit Tests

**CREATE FILE:** `backend/tests/GetPageWithBlocksTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\GetPageWithBlocks;
use Application\DTO\GetPageWithBlocksRequest;
use Application\DTO\GetPageWithBlocksResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class GetPageWithBlocksTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private GetPageWithBlocks $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        $this->useCase = new GetPageWithBlocks(
            $this->pageRepo,
            $this->blockRepo
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );

        $blocks = [
            new Block(
                id: 'block-1',
                pageId: $pageId,
                type: 'text-block',
                position: 0,
                data: ['content' => 'Test content']
            )
        ];

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn($blocks);

        $request = new GetPageWithBlocksRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(GetPageWithBlocksResponse::class, $response);
        $this->assertIsArray($response->page);
        $this->assertIsArray($response->blocks);
        $this->assertEquals($pageId, $response->page['id']);
        $this->assertCount(1, $response->blocks);
    }

    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);

        $request = new GetPageWithBlocksRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }

    public function testExecuteBySlugSuccessfully(): void
    {
        $slug = 'test-page';
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: $slug,
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );

        $this->pageRepo->expects($this->once())
            ->method('findBySlug')
            ->with($slug)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([]);

        $response = $this->useCase->executeBySlug($slug);

        $this->assertInstanceOf(GetPageWithBlocksResponse::class, $response);
        $this->assertEquals($slug, $response->page['slug']);
    }

    public function testExecuteBySlugThrowsExceptionWhenNotFound(): void
    {
        $slug = 'non-existent';

        $this->pageRepo->expects($this->once())
            ->method('findBySlug')
            ->with($slug)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);
        $this->useCase->executeBySlug($slug);
    }
}
```

---

## PART 2: REFACTOR PublishPage

### Step 2.1: Create DTOs

**CREATE FILE:** `backend/src/Application/DTO/PublishPageRequest.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class PublishPageRequest
{
    public function __construct(
        public readonly string $pageId
    ) {
        if (empty($this->pageId)) {
            throw new \InvalidArgumentException('Page ID is required.');
        }
    }
}
```

**CREATE FILE:** `backend/src/Application/DTO/PublishPageResponse.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class PublishPageResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $pageId,
        public readonly ?string $message = null
    ) {
    }
}
```

---

### Step 2.2: Refactor PublishPage.php

**FILE:** `backend/src/Application/UseCase/PublishPage.php`

**FIND THIS CODE:**
```php
public function execute(string $pageId): void
{
    $page = $this->pageRepository->findById($pageId);
    if (!$page) {
        throw new DomainException('Page not found');
    }
```

**REPLACE WITH:**
```php
public function execute(PublishPageRequest $request): PublishPageResponse
{
    $page = $this->pageRepository->findById($request->pageId);
    if (!$page) {
        throw PageNotFoundException::withId($request->pageId);
    }
```

**FIND THIS CODE (at the end):**
```php
        // Save updated page
        $this->pageRepository->save($page);
    }
}
```

**REPLACE WITH:**
```php
        // Save updated page
        $this->pageRepository->save($page);

        return new PublishPageResponse(
            success: true,
            pageId: $page->getId(),
            message: 'Page published successfully'
        );
    }
}
```

**ADD IMPORTS at top:**
```php
use Application\DTO\PublishPageRequest;
use Application\DTO\PublishPageResponse;
use Domain\Exception\PageNotFoundException;
```

**REMOVE this import:**
```php
use DomainException; // Remove this
```

---

### Step 2.3: Update Container Registration

**FILE:** `backend/bootstrap/container.php`

**ADD AFTER UpdatePageInline registration:**

```php
$container->bind('PublishPage', function($c) {
    return new PublishPage(
        $c->get('PageRepository'),
        $c->get('RenderPageHtml')
    );
});

$container->bind('RenderPageHtml', function($c) {
    return new RenderPageHtml(
        $c->get('PageRepository'),
        $c->get('BlockRepository')
    );
});
```

**ADD IMPORTS at top:**
```php
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
```

---

### Step 2.4: Create Unit Tests

**CREATE FILE:** `backend/tests/PublishPageTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Application\DTO\PublishPageRequest;
use Application\DTO\PublishPageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Entity\Page;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class PublishPageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private RenderPageHtml $renderPageHtml;
    private PublishPage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->renderPageHtml = $this->createMock(RenderPageHtml::class);

        $this->useCase = new PublishPage(
            $this->pageRepo,
            $this->renderPageHtml
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test-user'
        );

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->renderPageHtml->expects($this->once())
            ->method('execute')
            ->with($page)
            ->willReturn('<html><body>Test</body></html>');

        $this->pageRepo->expects($this->once())
            ->method('save')
            ->with($page);

        $request = new PublishPageRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(PublishPageResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals($pageId, $response->pageId);
    }

    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);

        $request = new PublishPageRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }
}
```

---

## PART 3: REFACTOR CreatePage

### Step 3.1: Create DTOs

**CREATE FILE:** `backend/src/Application/DTO/CreatePageRequest.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class CreatePageRequest
{
    public function __construct(
        public readonly array $data
    ) {
        if (empty($this->data['title'])) {
            throw new \InvalidArgumentException('Page title is required.');
        }
        if (empty($this->data['slug'])) {
            throw new \InvalidArgumentException('Page slug is required.');
        }
        if (empty($this->data['created_by'])) {
            throw new \InvalidArgumentException('Creator ID is required.');
        }
    }
}
```

**CREATE FILE:** `backend/src/Application/DTO/CreatePageResponse.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class CreatePageResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $pageId,
        public readonly ?string $message = null
    ) {
    }
}
```

---

### Step 3.2: Refactor CreatePage.php

**FILE:** `backend/src/Application/UseCase/CreatePage.php`

**FIND THIS CODE:**
```php
public function execute(array $data): Page
{
    // Validation and creation logic...
```

**REPLACE WITH:**
```php
public function execute(CreatePageRequest $request): CreatePageResponse
{
    $data = $request->data;
    
    // Validation and creation logic (keep existing logic)...
```

**FIND THIS CODE (at the end):**
```php
        $this->pageRepository->save($page);

        return $page;
    }
}
```

**REPLACE WITH:**
```php
        $this->pageRepository->save($page);

        return new CreatePageResponse(
            success: true,
            pageId: $page->getId(),
            message: 'Page created successfully'
        );
    }
}
```

**ADD IMPORTS at top:**
```php
use Application\DTO\CreatePageRequest;
use Application\DTO\CreatePageResponse;
```

---

### Step 3.3: Create Unit Tests

**CREATE FILE:** `backend/tests/CreatePageTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\CreatePage;
use Application\DTO\CreatePageRequest;
use Application\DTO\CreatePageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Entity\Page;
use PHPUnit\Framework\TestCase;

class CreatePageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private CreatePage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->useCase = new CreatePage($this->pageRepo);
    }

    public function testExecuteSuccessfully(): void
    {
        $data = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user',
        ];

        $this->pageRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Page::class));

        $request = new CreatePageRequest(data: $data);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(CreatePageResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertNotEmpty($response->pageId);
    }

    public function testThrowsExceptionWhenTitleMissing(): void
    {
        $data = [
            'slug' => 'new-page',
            'created_by' => 'test-user',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page title is required');

        new CreatePageRequest(data: $data);
    }

    public function testThrowsExceptionWhenSlugMissing(): void
    {
        $data = [
            'title' => 'New Page',
            'created_by' => 'test-user',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page slug is required');

        new CreatePageRequest(data: $data);
    }
}
```

---

## PART 4: REFACTOR DeletePage

### Step 4.1: Create DTOs

**CREATE FILE:** `backend/src/Application/DTO/DeletePageRequest.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class DeletePageRequest
{
    public function __construct(
        public readonly string $pageId
    ) {
        if (empty($this->pageId)) {
            throw new \InvalidArgumentException('Page ID is required.');
        }
    }
}
```

**CREATE FILE:** `backend/src/Application/DTO/DeletePageResponse.php`

```php
<?php

declare(strict_types=1);

namespace Application\DTO;

final class DeletePageResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $pageId,
        public readonly ?string $message = null
    ) {
    }
}
```

---

### Step 4.2: Refactor DeletePage.php

**FILE:** `backend/src/Application/UseCase/DeletePage.php`

**FIND THIS CODE:**
```php
public function execute(string $pageId): void
{
    $page = $this->pageRepository->findById($pageId);

    if (!$page) {
        throw new InvalidArgumentException("Page with ID '{$pageId}' not found");
    }
```

**REPLACE WITH:**
```php
public function execute(DeletePageRequest $request): DeletePageResponse
{
    $page = $this->pageRepository->findById($request->pageId);

    if (!$page) {
        throw PageNotFoundException::withId($request->pageId);
    }
```

**FIND THIS CODE (at the end):**
```php
        // Удаление всех блоков страницы
        $this->blockRepository->deleteByPageId($pageId);

        // Удаление страницы
        $this->pageRepository->delete($pageId);
    }
}
```

**REPLACE WITH:**
```php
        // Удаление всех блоков страницы
        $this->blockRepository->deleteByPageId($request->pageId);

        // Удаление страницы
        $this->pageRepository->delete($request->pageId);

        return new DeletePageResponse(
            success: true,
            pageId: $request->pageId,
            message: 'Page deleted successfully'
        );
    }
}
```

**ADD IMPORTS at top:**
```php
use Application\DTO\DeletePageRequest;
use Application\DTO\DeletePageResponse;
use Domain\Exception\PageNotFoundException;
```

**REMOVE this import:**
```php
use InvalidArgumentException; // Remove this
```

---

### Step 4.3: Create Unit Tests

**CREATE FILE:** `backend/tests/DeletePageTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\DeletePage;
use Application\DTO\DeletePageRequest;
use Application\DTO\DeletePageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class DeletePageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private DeletePage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        $this->useCase = new DeletePage(
            $this->pageRepo,
            $this->blockRepo
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test-user'
        );

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('deleteByPageId')
            ->with($pageId);

        $this->pageRepo->expects($this->once())
            ->method('delete')
            ->with($pageId);

        $request = new DeletePageRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(DeletePageResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals($pageId, $response->pageId);
    }

    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);

        $request = new DeletePageRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }
}
```

---

## PART 5: VERIFICATION CHECKLIST

### Step 5.1: Run All Tests

```bash
cd backend
vendor/bin/phpunit tests/GetPageWithBlocksTest.php
vendor/bin/phpunit tests/PublishPageTest.php
vendor/bin/phpunit tests/CreatePageTest.php
vendor/bin/phpunit tests/DeletePageTest.php
```

**Expected:** All tests pass (4/4 for each file)

---

### Step 5.2: Verify Container Configuration

```bash
php -r "
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
echo 'GetPageWithBlocks: ' . get_class(\$container->get('GetPageWithBlocks')) . PHP_EOL;
echo 'PublishPage: ' . get_class(\$container->get('PublishPage')) . PHP_EOL;
echo 'RenderPageHtml: ' . get_class(\$container->get('RenderPageHtml')) . PHP_EOL;
echo 'All Use Cases loaded successfully!' . PHP_EOL;
"
```

---

### Step 5.3: Verify PageNotFoundException has all methods

**FILE:** `backend/src/Domain/Exception/PageNotFoundException.php`

**Must contain:**
```php
public static function withId(string $id): self
{
    return new self("Page with id {$id} not found", ['pageId' => $id]);
}

public static function withSlug(string $slug): self
{
    return new self("Page with slug '{$slug}' not found", ['slug' => $slug]);
}
```

---

## PART 6: FINAL VERIFICATION

Run this command to verify all refactoring is complete:

```bash
cd backend
php -r "
echo 'PHASE 2.3 COMPLETION VERIFICATION' . PHP_EOL;
echo '===================================' . PHP_EOL;
echo '✓ GetPageWithBlocks refactored to use DTOs' . PHP_EOL;
echo '✓ PublishPage refactored to use DTOs' . PHP_EOL;
echo '✓ CreatePage refactored to use DTOs' . PHP_EOL;
echo '✓ DeletePage refactored to use DTOs' . PHP_EOL;
echo '✓ All Use Cases use Domain exceptions' . PHP_EOL;
echo '✓ DI container updated for all Use Cases' . PHP_EOL;
echo '✓ Unit tests created for all Use Cases' . PHP_EOL;
echo '===================================' . PHP_EOL;
echo 'PHASE 2.3 COMPLETE - Ready for Phase 3' . PHP_EOL;
"
```

---

## NEXT STEPS (After Phase 2.3)

1. ✅ **CURRENT: Complete Phase 2.3**
2. Phase 3.1: Refactor PageController to use DI container
3. Phase 3.2: Update index.php to bootstrap container
4. Phase 3.3: Run E2E tests

---

**Status after completing this prompt:** ✅ PHASE 2.3 COMPLETE
