# PHASE 2 COMPLETION PROMPT - Clean Architecture Implementation

**Date:** October 16, 2025  
**Project:** Healthcare CMS Backend  
**Status:** 70% complete — Phases 0-1 foundation ready  
**Goal:** Complete Phases 1-2 with proper DI, DTOs, and Domain Exceptions

---

## ⚠️ CRITICAL INSTRUCTION

**This prompt must be executed EXACTLY as written.**  
**Do NOT skip steps.**  
**Do NOT modify the architecture.**  
**Do NOT combine steps.**

Each section is numbered and self-contained. Follow the **EXECUTION ORDER** strictly.

---

## PART 1: VERIFY EXISTING COMPONENTS (5 minutes)

### Step 1.1: Verify DTO Classes Exist

**File to check:** `backend/src/Application/DTO/UpdatePageInlineRequest.php`

```php
<?php
declare(strict_types=1);
namespace Application\DTO;

final class UpdatePageInlineRequest
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $blockId,
        public readonly string $fieldPath,
        public readonly string $newMarkdown
    ) {
        if (empty($this->pageId) || empty($this->blockId) || empty($this->fieldPath) || empty($this->newMarkdown)) {
            throw new \InvalidArgumentException('All fields are required.');
        }
    }
}
```

**File to check:** `backend/src/Application/DTO/UpdatePageInlineResponse.php`

```php
<?php
declare(strict_types=1);
namespace Application\DTO;

final class UpdatePageInlineResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null
    ) {
    }
}
```

✅ **If both files exist and match — PROCEED to PART 2**  
❌ **If missing — CREATE them with the code above**

---

### Step 1.2: Verify Domain Exceptions Exist

**File to check:** `backend/src/Domain/Exception/PageNotFoundException.php`

**Must contain:**
- `class PageNotFoundException extends DomainException`
- Private property `private string $pageId;`
- Method `public function getContext(): array`

**File to check:** `backend/src/Domain/Exception/BlockNotFoundException.php`

**Must contain:**
- `class BlockNotFoundException extends DomainException`
- Private property `private string $blockId;`
- Method `public function getContext(): array`

✅ **If both exist with methods — PROCEED**  
❌ **If missing methods — ADD them using code from CLEAN_ARCHITECTURE_VIOLATIONS_ANALYSIS.md, Section 2.2, Step 2**

---

### Step 1.3: Verify DI Container Exists

**File to check:** `backend/src/Infrastructure/Container/Container.php`

**Must contain THESE methods:**
```php
public function bind(string $abstract, callable $concrete): void
public function singleton(string $abstract, callable $concrete): void
public function get(string $id)
public function has(string $id): bool
public function make(string $abstract, array $parameters = [])
```

✅ **If all 5 methods exist — PROCEED**  
❌ **If missing — use code from CLEAN_ARCHITECTURE_VIOLATIONS_ANALYSIS.md, Section 2.2, Step 3**

---

### Step 1.4: Verify bootstrap/container.php Exists

**File to check:** `backend/bootstrap/container.php`

**Must contain:**
- `$container = new Container();`
- Registrations for 8 repositories (using string keys like 'BlockRepository')
- `return $container;` at the end

✅ **If exists — PROCEED**  
❌ **If missing or incomplete — see PART 3 for reconstruction**

---

## PART 2: REFACTOR UpdatePageInline USE CASE (CRITICAL)

### Step 2.1: Locate UpdatePageInline File

**File:** `backend/src/Application/UseCase/UpdatePageInline.php`

---

### Step 2.2: Replace the execute() Method Signature

**FIND THIS CODE:**
```php
    public function execute(string $pageId, string $blockId, string $fieldPath, string $newMarkdown): array
    {
        $page = $this->pageRepo->findById($pageId);
        if (!$page) {
            throw new \Exception('Page not found');
        }

        // Найти блок через репозиторий блоков
        $blocks = $this->blockRepo->findByPageId($pageId);
        $block = null;
        foreach ($blocks as $b) {
            if ($b->getId() === $blockId) {
                $block = $b;
                break;
            }
        }
        if (!$block) {
            throw new \Exception('Block not found');
        }
```

**REPLACE WITH THIS CODE:**
```php
    public function execute(UpdatePageInlineRequest $request): UpdatePageInlineResponse
    {
        // Import at top: use Application\DTO\UpdatePageInlineRequest;
        // Import at top: use Application\DTO\UpdatePageInlineResponse;
        // Import at top: use Domain\Exception\PageNotFoundException;
        // Import at top: use Domain\Exception\BlockNotFoundException;

        $page = $this->pageRepo->findById($request->pageId);
        if (!$page) {
            throw PageNotFoundException::withId($request->pageId);
        }

        // Найти блок через репозиторий блоков
        $blocks = $this->blockRepo->findByPageId($request->pageId);
        $block = null;
        foreach ($blocks as $b) {
            if ($b->getId() === $request->blockId) {
                $block = $b;
                break;
            }
        }
        if (!$block) {
            throw BlockNotFoundException::withId($request->blockId);
        }
```

---

### Step 2.3: Update the Rest of the Method

**FIND THIS CODE (line ~65-85):**
```php
        // Валидация Markdown (roundtrip: Markdown → HTML → sanitize → Markdown)
        $html = $this->markdownConverter->toHTML($newMarkdown);
```

**REPLACE WITH:**
```php
        // Валидация Markdown (roundtrip: Markdown → HTML → sanitize → Markdown)
        $html = $this->markdownConverter->toHTML($request->newMarkdown);
```

**FIND THIS CODE (line ~95-105):**
```php
        // Обновить поле в data блока
        $data = $block->getData();
        $pathParts = explode('.', $fieldPath);

        if ($pathParts[0] === 'data') {
            array_shift($pathParts);
        }

        $ref = &$data;
        foreach ($pathParts as $i => $key) {
            if ($i === count($pathParts) - 1) {
                $ref[$key] = $sanitizedMarkdown; // Сохраняем Markdown, НЕ HTML
            } else {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
```

**REPLACE WITH:**
```php
        // Обновить поле в data блока
        $data = $block->getData();
        $pathParts = explode('.', $request->fieldPath);

        if (empty($pathParts) || ($pathParts[0] !== 'data' && count($pathParts) < 2)) {
            throw new \InvalidArgumentException('Invalid fieldPath format. Expected "data.key" or "data.nested.key".');
        }

        if ($pathParts[0] === 'data') {
            array_shift($pathParts);
        }

        $ref = &$data;
        foreach ($pathParts as $i => $key) {
            if ($i === count($pathParts) - 1) {
                $ref[$key] = $sanitizedMarkdown; // Сохраняем Markdown, НЕ HTML
            } else {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
```

---

### Step 2.4: Update Return Statement

**FIND THIS CODE (line ~115-120):**
```php
        $block->setData($data);
        $this->blockRepo->update($block);

        return [
            'success' => true,
            'blockId' => $block->getId(),
            'updatedField' => $fieldPath
        ];
```

**REPLACE WITH:**
```php
        $block->setData($data);
        $this->blockRepo->update($block);

        return new UpdatePageInlineResponse(
            success: true,
            message: 'Block updated successfully'
        );
```

---

### Step 2.5: Add Imports at Top of File

**FILE:** `backend/src/Application/UseCase/UpdatePageInline.php`

**ADD THESE LINES after `namespace Application\UseCase;`:**

```php
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;
```

**MUST BE BEFORE** the existing use statements for repositories.

---

### Step 2.6: Verify Final Structure

**The complete execute() method should now be:**

```php
public function execute(UpdatePageInlineRequest $request): UpdatePageInlineResponse
{
    $page = $this->pageRepo->findById($request->pageId);
    if (!$page) {
        throw PageNotFoundException::withId($request->pageId);
    }

    $blocks = $this->blockRepo->findByPageId($request->pageId);
    $block = null;
    foreach ($blocks as $b) {
        if ($b->getId() === $request->blockId) {
            $block = $b;
            break;
        }
    }
    if (!$block) {
        throw BlockNotFoundException::withId($request->blockId);
    }

    $html = $this->markdownConverter->toHTML($request->newMarkdown);
    $sanitizedHTML = $this->sanitizer->sanitize($html, [
        'allowedTags' => ['p', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'img', 'br'],
        'allowedAttributes' => [
            'a' => ['href', 'title', 'target'],
            'img' => ['src', 'alt', 'width', 'height', 'class']
        ],
        'allowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true]
    ]);
    $sanitizedMarkdown = $this->markdownConverter->toMarkdown($sanitizedHTML);

    $data = $block->getData();
    $pathParts = explode('.', $request->fieldPath);

    if (empty($pathParts) || ($pathParts[0] !== 'data' && count($pathParts) < 2)) {
        throw new \InvalidArgumentException('Invalid fieldPath format.');
    }

    if ($pathParts[0] === 'data') {
        array_shift($pathParts);
    }

    $ref = &$data;
    foreach ($pathParts as $i => $key) {
        if ($i === count($pathParts) - 1) {
            $ref[$key] = $sanitizedMarkdown;
        } else {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
    }

    $block->setData($data);
    $this->blockRepo->update($block);

    return new UpdatePageInlineResponse(
        success: true,
        message: 'Block updated successfully'
    );
}
```

---

## PART 3: UPDATE BOOTSTRAP CONTAINER (CRITICAL)

### Step 3.1: Verify File Structure

**FILE:** `backend/bootstrap/container.php`

**Must start with:**
```php
<?php

declare(strict_types=1);

use Infrastructure\Container\Container;
```

---

### Step 3.2: Update Use Case Registration

**FIND THIS CODE:**
```php
$container->bind('UpdatePageInline', fn($c) => new UpdatePageInline(
    $c->get('PageRepository'),
    $c->get('BlockRepository')
));
```

**REPLACE WITH THIS CODE:**
```php
// Register use cases
$container->bind('UpdatePageInline', function($c) {
    return new UpdatePageInline(
        $c->get('PageRepository'),
        $c->get('BlockRepository'),
        $c->get('MarkdownConverter'),
        $c->get('HTMLSanitizer')
    );
});
```

**ADD these imports at the top if missing:**
```php
use Application\UseCase\UpdatePageInline;
use Infrastructure\Service\MarkdownConverter;
use Infrastructure\Service\HTMLSanitizer;
```

---

### Step 3.3: Add Service Registrations

**FIND THIS SECTION:**
```php
// Register repositories
$container->singleton('BlockRepository', fn() => new MySQLBlockRepository());
```

**ADD BEFORE IT (if not present):**
```php
// ========================================
// SERVICES (Singleton - shared instance)
// ========================================

if (!$container->has('MarkdownConverter')) {
    $container->singleton('MarkdownConverter', function() {
        return new MarkdownConverter();
    });
}

if (!$container->has('HTMLSanitizer')) {
    $container->singleton('HTMLSanitizer', function() {
        return new HTMLSanitizer();
    });
}
```

---

### Step 3.4: Verify Final Structure

**bootstrap/container.php should have this structure:**

```php
<?php
declare(strict_types=1);

use Infrastructure\Container\Container;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLMenuRepository;
use Infrastructure\Repository\MySQLMediaRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLStaticTemplateRepository;
use Infrastructure\Repository\MySQLSettingsRepository;
use Application\UseCase\UpdatePageInline;
use Infrastructure\Service\MarkdownConverter;
use Infrastructure\Service\HTMLSanitizer;

$container = new Container();

// SERVICES
$container->singleton('MarkdownConverter', fn() => new MarkdownConverter());
$container->singleton('HTMLSanitizer', fn() => new HTMLSanitizer());

// REPOSITORIES
$container->singleton('PageRepository', fn() => new MySQLPageRepository());
$container->singleton('BlockRepository', fn() => new MySQLBlockRepository());
$container->singleton('UserRepository', fn() => new MySQLUserRepository());
$container->singleton('MenuRepository', fn() => new MySQLMenuRepository());
$container->singleton('MediaRepository', fn() => new MySQLMediaRepository());
$container->singleton('SessionRepository', fn() => new MySQLSessionRepository());
$container->singleton('StaticTemplateRepository', fn() => new MySQLStaticTemplateRepository());
$container->singleton('SettingsRepository', fn() => new MySQLSettingsRepository());

// USE CASES
$container->bind('UpdatePageInline', function($c) {
    return new UpdatePageInline(
        $c->get('PageRepository'),
        $c->get('BlockRepository'),
        $c->get('MarkdownConverter'),
        $c->get('HTMLSanitizer')
    );
});

return $container;
```

---

## PART 4: CREATE UNIT TEST (IMPORTANT)

### Step 4.1: Create Test File

**CREATE FILE:** `backend/tests/UpdatePageInlineTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\UpdatePageInline;
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;
use PHPUnit\Framework\TestCase;

class UpdatePageInlineTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private UpdatePageInline $useCase;

    protected function setUp(): void
    {
        // Create mock repositories
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        // Mock services (use real implementations if available)
        $markdownConverter = new \Infrastructure\Service\MarkdownConverter();
        $htmlSanitizer = new \Infrastructure\Service\HTMLSanitizer();

        $this->useCase = new UpdatePageInline(
            $this->pageRepo,
            $this->blockRepo,
            $markdownConverter,
            $htmlSanitizer
        );
    }

    /**
     * TEST 1: Happy Path - Successfully update block
     */
    public function testExecuteSuccessfully(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: 'published'
        );

        // Setup mock block
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Old content']
        );

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([$block]);

        $this->blockRepo->expects($this->once())
            ->method('update')
            ->with($block);

        // Create request
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: '**Updated content**'
        );

        // Execute
        $response = $this->useCase->execute($request);

        // Assert
        $this->assertInstanceOf(UpdatePageInlineResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertNotNull($response->message);
    }

    /**
     * TEST 2: Page Not Found
     */
    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Configure mock to return null (page not found)
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        // Expect exception
        $this->expectException(PageNotFoundException::class);

        // Create request and execute
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }

    /**
     * TEST 3: Block Not Found (CRITICAL CASE)
     */
    public function testThrowsBlockNotFoundExceptionWhenBlockDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: 'published'
        );

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        // Return empty array (block doesn't exist)
        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([]);

        // Expect exception
        $this->expectException(BlockNotFoundException::class);

        // Create request and execute
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }

    /**
     * TEST 4: Invalid FieldPath
     */
    public function testThrowsInvalidArgumentExceptionForBadFieldPath(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: 'published'
        );

        // Setup mock block
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: []
        );

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([$block]);

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);

        // Create request with invalid fieldPath
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'invalid', // No "data." prefix
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }
}
```

---

## PART 5: CREATE INTEGRATION TEST (OPTIONAL BUT RECOMMENDED)

### Step 5.1: Create Integration Test

**CREATE FILE:** `backend/tests/Integration/UpdatePageInlineIntegrationTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use Application\UseCase\UpdatePageInline;
use Application\DTO\UpdatePageInlineRequest;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Service\MarkdownConverter;
use Infrastructure\Service\HTMLSanitizer;
use Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

class UpdatePageInlineIntegrationTest extends TestCase
{
    private UpdatePageInline $useCase;
    private MySQLPageRepository $pageRepo;
    private MySQLBlockRepository $blockRepo;

    protected function setUp(): void
    {
        // Use real repositories and database
        $this->pageRepo = new MySQLPageRepository();
        $this->blockRepo = new MySQLBlockRepository();

        $markdownConverter = new MarkdownConverter();
        $htmlSanitizer = new HTMLSanitizer();

        $this->useCase = new UpdatePageInline(
            $this->pageRepo,
            $this->blockRepo,
            $markdownConverter,
            $htmlSanitizer
        );
    }

    /**
     * TEST: Real database - verify Block not found returns proper exception
     */
    public function testBlockNotFoundWithRealDatabase(): void
    {
        // Note: This requires a test page and block to exist in database
        // Adjust IDs based on your test data

        $request = new UpdatePageInlineRequest(
            pageId: 'existing-page-id',
            blockId: 'non-existent-block-id',
            fieldPath: 'data.content',
            newMarkdown: 'Test'
        );

        $this->expectException(\Domain\Exception\BlockNotFoundException::class);
        $this->useCase->execute($request);
    }
}
```

---

## PART 6: VERIFICATION CHECKLIST

### Step 6.1: Code Review

- [ ] UpdatePageInline.php has new imports (DTO, exceptions)
- [ ] execute() signature is `execute(UpdatePageInlineRequest): UpdatePageInlineResponse`
- [ ] PageNotFoundException is thrown with `PageNotFoundException::withId()`
- [ ] BlockNotFoundException is thrown with `BlockNotFoundException::withId()`
- [ ] InvalidArgumentException is thrown for bad fieldPath
- [ ] Method returns `new UpdatePageInlineResponse(success: true, message: ...)`

### Step 6.2: Container Configuration

- [ ] bootstrap/container.php imports UpdatePageInline
- [ ] bootstrap/container.php imports MarkdownConverter
- [ ] bootstrap/container.php imports HTMLSanitizer
- [ ] UpdatePageInline is registered with all 4 dependencies
- [ ] MarkdownConverter is registered as singleton
- [ ] HTMLSanitizer is registered as singleton

### Step 6.3: Testing

- [ ] Run unit test: `vendor/bin/phpunit tests/UpdatePageInlineTest.php`
- [ ] All 4 test cases pass
- [ ] Code coverage >= 80%

### Step 6.4: No Errors

```bash
# Check for syntax errors
php -l backend/src/Application/UseCase/UpdatePageInline.php
php -l backend/bootstrap/container.php

# Run tests
cd backend
vendor/bin/phpunit tests/UpdatePageInlineTest.php --coverage-html=coverage
```

---

## NEXT STEPS (After This Prompt is Complete)

1. ✅ **CURRENT: Complete this prompt fully**
2. Update PageController to use DI container
3. Update index.php to bootstrap the container
4. Run E2E tests to verify PATCH /api/pages/{id}/inline returns 404 for BlockNotFound
5. Proceed with Phase 2.3 (other Use Cases)

---

## TROUBLESHOOTING

### Error: "Class not found: UpdatePageInlineRequest"
**Solution:** Verify import statement at top of UpdatePageInline.php:
```php
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;
```

### Error: "Method withId() not found"
**Solution:** Verify PageNotFoundException has static method:
```php
public static function withId(string $id): self
{
    return new self("Page with id {$id} not found");
}
```

### Error: "Container not found for 'MarkdownConverter'"
**Solution:** Ensure bootstrap/container.php has:
```php
$container->singleton('MarkdownConverter', fn() => new MarkdownConverter());
```

### Error: "Undefined method findByPageId()"
**Solution:** Verify BlockRepositoryInterface has this method and MySQLBlockRepository implements it.

---

## FINAL VERIFICATION COMMAND

After completing all steps, run this command:

```bash
cd backend
php -r "
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
\$useCase = \$container->get('UpdatePageInline');
echo 'UpdatePageInline USE CASE loaded successfully!' . PHP_EOL;
echo 'Class: ' . get_class(\$useCase) . PHP_EOL;
"
```

**Expected output:**
```
UpdatePageInline USE CASE loaded successfully!
Class: Application\UseCase\UpdatePageInline
```

---

**Status after completing this prompt:** ✅ PHASE 2.1 COMPLETE
