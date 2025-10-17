# PHASE 3 COMPLETION PROMPT - Refactor Controllers to Use DI Container

**Date:** October 16, 2025  
**Project:** Healthcare CMS Backend  
**Status:** Phase 2 complete — Use Cases refactored to DTOs and Domain exceptions  
**Goal:** Refactor PageController to use Dependency Injection and update index.php

---

## ⚠️ CRITICAL INSTRUCTION

**This prompt implements Clean Architecture Pattern for Controllers.**  
**Follow EACH STEP sequentially.**  
**Do NOT skip any step.**  
**Test after EVERY change.**

---

## WHAT YOU WILL DO

You will refactor the PageController to:
1. **Accept Use Cases through constructor** (Dependency Injection)
2. **Handle Domain exceptions** with correct HTTP status codes
3. **Remove all `new MySQLRepository()` calls**
4. **Use DTOs** for request/response data

Then you will update `index.php` to:
1. **Load the DI container**
2. **Create controllers via container**
3. **Add global exception handler**

---

## PREREQUISITE CHECK

Before starting, verify Phase 2 is complete:

```bash
cd backend
php -r "
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
echo 'UpdatePageInline: ' . get_class(\$container->get('UpdatePageInline')) . PHP_EOL;
echo 'GetPageWithBlocks: ' . get_class(\$container->get('GetPageWithBlocks')) . PHP_EOL;
echo 'PublishPage: ' . get_class(\$container->get('PublishPage')) . PHP_EOL;
echo 'Container is ready!' . PHP_EOL;
"
```

**Expected output:**
```
UpdatePageInline: Application\UseCase\UpdatePageInline
GetPageWithBlocks: Application\UseCase\GetPageWithBlocks
PublishPage: Application\UseCase\PublishPage
Container is ready!
```

**If this fails:** Phase 2 is not complete. Stop and complete Phase 2 first.

---

## PART 1: REFACTOR PageController

### Step 1.1: Read Current PageController

**FILE:** `backend/src/Presentation/Controller/PageController.php`

**CURRENT STATE (example method):**
```php
public function patchInline(string $id): void
{
    try {
        $pageRepository = new MySQLPageRepository(); // ❌ VIOLATION!
        $blockRepository = new MySQLBlockRepository(); // ❌ VIOLATION!
        
        $useCase = new UpdatePageInline($blockRepository, $pageRepository);
        // ... rest of code
    }
}
```

**PROBLEMS:**
- ❌ Creates repositories directly
- ❌ No constructor injection
- ❌ Throws generic exceptions (HTTP 500 instead of 404)

---

### Step 1.2: Add Constructor with Use Case Dependencies

**FIND THIS CODE (at top of class):**
```php
class PageController
{
    // No constructor or private constructor
```

**REPLACE WITH:**
```php
class PageController
{
    public function __construct(
        private UpdatePageInline $updatePageInline,
        private GetPageWithBlocks $getPageWithBlocks,
        private GetAllPages $getAllPages,
        private PublishPage $publishPage,
        private CreatePage $createPage,
        private DeletePage $deletePage
    ) {}
```

**ADD IMPORTS at top of file:**
```php
use Application\UseCase\UpdatePageInline;
use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\GetAllPages;
use Application\UseCase\PublishPage;
use Application\UseCase\CreatePage;
use Application\UseCase\DeletePage;
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\PublishPageRequest;
use Application\DTO\CreatePageRequest;
use Application\DTO\DeletePageRequest;
use Application\DTO\GetPageWithBlocksRequest;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;
```

---

### Step 1.3: Refactor patchInline() Method

**FIND THIS CODE:**
```php
public function patchInline(string $id): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        
        $useCase = new UpdatePageInline($blockRepository, $pageRepository);
        
        // Get request body
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // Execute
        $result = $useCase->execute($id, $data['blockId'], $data['fieldPath'], $data['newMarkdown']);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'data' => $result]);
        
    } catch (\Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

**REPLACE WITH:**
```php
public function patchInline(string $id): void
{
    try {
        // 1. Get and validate request payload
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new \InvalidArgumentException('Invalid JSON payload');
        }
        
        // Add pageId from URL
        $data['pageId'] = $id;
        
        // 2. Create Request DTO (validates automatically)
        $request = UpdatePageInlineRequest::fromArray($data);
        
        // 3. Execute Use Case (injected via constructor)
        $response = $this->updatePageInline->execute($request);
        
        // 4. Return success response
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response->toArray());
        
    } catch (\InvalidArgumentException $e) {
        // 400 Bad Request - invalid input data
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'INVALID_INPUT'
        ]);
        
    } catch (PageNotFoundException $e) {
        // 404 Not Found - page not found
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext(),
            'code' => 'PAGE_NOT_FOUND'
        ]);
        
    } catch (BlockNotFoundException $e) {
        // 404 Not Found - block not found (MAIN FIX for "Block not found" issue!)
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext(), // Includes block_id, page_id for debugging
            'code' => 'BLOCK_NOT_FOUND'
        ]);
        
    } catch (\Exception $e) {
        // 500 Internal Server Error - unexpected error
        error_log("Unexpected error in patchInline: " . $e->getMessage());
        error_log($e->getTraceAsString());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

**KEY CHANGES:**
- ✅ Uses `$this->updatePageInline` (injected via constructor)
- ✅ Creates `UpdatePageInlineRequest` DTO (type-safe validation)
- ✅ Handles Domain exceptions with correct HTTP codes:
  - 400 for invalid input
  - 404 for PageNotFoundException
  - 404 for BlockNotFoundException (with context!)
  - 500 for unexpected errors
- ✅ Returns `$response->toArray()` (structured response)
- ✅ Logs errors for debugging

---

### Step 1.4: Refactor index() Method

**FIND THIS CODE:**
```php
public function index(): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $useCase = new GetAllPages($pageRepository);
        $pages = $useCase->execute();
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'data' => $pages]);
    } catch (\Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

**REPLACE WITH:**
```php
public function index(): void
{
    try {
        // Execute Use Case (injected via constructor)
        $pages = $this->getAllPages->execute();
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'data' => $pages
        ]);
        
    } catch (\Exception $e) {
        error_log("Error in PageController::index: " . $e->getMessage());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve pages',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

---

### Step 1.5: Refactor show() Method

**FIND THIS CODE:**
```php
public function show(string $id): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);
        $result = $useCase->execute($id);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
        // ...
    }
}
```

**REPLACE WITH:**
```php
public function show(string $id): void
{
    try {
        // Create Request DTO
        $request = new GetPageWithBlocksRequest(pageId: $id);
        
        // Execute Use Case
        $response = $this->getPageWithBlocks->execute($request);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'page' => $response->page,
            'blocks' => $response->blocks
        ]);
        
    } catch (PageNotFoundException $e) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext(),
            'code' => 'PAGE_NOT_FOUND'
        ]);
        
    } catch (\Exception $e) {
        error_log("Error in PageController::show: " . $e->getMessage());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve page',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

---

### Step 1.6: Refactor publish() Method

**FIND THIS CODE:**
```php
public function publish(string $id): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new PublishPage($pageRepository, $blockRepository);
        $useCase->execute($id);
        
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        // ...
    }
}
```

**REPLACE WITH:**
```php
public function publish(string $id): void
{
    try {
        // Create Request DTO
        $request = new PublishPageRequest(pageId: $id);
        
        // Execute Use Case
        $response = $this->publishPage->execute($request);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $response->success,
            'pageId' => $response->pageId,
            'message' => $response->message
        ]);
        
    } catch (PageNotFoundException $e) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'PAGE_NOT_FOUND'
        ]);
        
    } catch (\Exception $e) {
        error_log("Error in PageController::publish: " . $e->getMessage());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to publish page',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

---

### Step 1.7: Refactor store() Method (Create Page)

**FIND THIS CODE:**
```php
public function store(): void
{
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $pageRepository = new MySQLPageRepository();
        $useCase = new CreatePage($pageRepository);
        $page = $useCase->execute($data);
        
        echo json_encode(['success' => true, 'data' => $page]);
    } catch (\Exception $e) {
        // ...
    }
}
```

**REPLACE WITH:**
```php
public function store(): void
{
    try {
        // Get request payload
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new \InvalidArgumentException('Invalid JSON payload');
        }
        
        // Create Request DTO
        $request = new CreatePageRequest(data: $data);
        
        // Execute Use Case
        $response = $this->createPage->execute($request);
        
        header('HTTP/1.1 201 Created');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $response->success,
            'pageId' => $response->pageId,
            'message' => $response->message
        ]);
        
    } catch (\InvalidArgumentException $e) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'INVALID_INPUT'
        ]);
        
    } catch (\Exception $e) {
        error_log("Error in PageController::store: " . $e->getMessage());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create page',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

---

### Step 1.8: Refactor delete() Method

**FIND THIS CODE:**
```php
public function delete(string $id): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new DeletePage($pageRepository, $blockRepository);
        $useCase->execute($id);
        
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        // ...
    }
}
```

**REPLACE WITH:**
```php
public function delete(string $id): void
{
    try {
        // Create Request DTO
        $request = new DeletePageRequest(pageId: $id);
        
        // Execute Use Case
        $response = $this->deletePage->execute($request);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $response->success,
            'pageId' => $response->pageId,
            'message' => $response->message
        ]);
        
    } catch (PageNotFoundException $e) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'PAGE_NOT_FOUND'
        ]);
        
    } catch (\Exception $e) {
        error_log("Error in PageController::delete: " . $e->getMessage());
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to delete page',
            'code' => 'INTERNAL_ERROR'
        ]);
    }
}
```

---

### Step 1.9: Remove Old Imports

**FIND AND DELETE these imports:**
```php
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLUserRepository;
```

**These should NOT be in PageController anymore!**

---

### Step 1.10: Verify PageController Changes

**Run syntax check:**
```bash
cd backend
php -l src/Presentation/Controller/PageController.php
```

**Expected:** `No syntax errors detected`

---

## PART 2: UPDATE DI CONTAINER FOR USE CASES

### Step 2.1: Add Use Case Bindings to Container

**FILE:** `backend/bootstrap/container.php`

**FIND THIS CODE (near the end, after service registrations):**
```php
$container->bind('UpdatePageInline', function($c) {
    return new UpdatePageInline(
        $c->get('PageRepository'),
        $c->get('BlockRepository'),
        $c->get('MarkdownConverter'),
        $c->get('HTMLSanitizer')
    );
});
```

**ADD AFTER IT:**
```php
$container->bind('GetAllPages', function($c) {
    return new GetAllPages(
        $c->get('PageRepository')
    );
});

$container->bind('CreatePage', function($c) {
    return new CreatePage(
        $c->get('PageRepository')
    );
});

$container->bind('DeletePage', function($c) {
    return new DeletePage(
        $c->get('PageRepository'),
        $c->get('BlockRepository')
    );
});
```

**ADD IMPORTS at top:**
```php
use Application\UseCase\GetAllPages;
use Application\UseCase\CreatePage;
use Application\UseCase\DeletePage;
```

---

### Step 2.2: Verify Container

```bash
cd backend
php -r "
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
echo 'GetAllPages: ' . get_class(\$container->get('GetAllPages')) . PHP_EOL;
echo 'CreatePage: ' . get_class(\$container->get('CreatePage')) . PHP_EOL;
echo 'DeletePage: ' . get_class(\$container->get('DeletePage')) . PHP_EOL;
echo 'All Use Cases registered!' . PHP_EOL;
"
```

**Expected output:**
```
GetAllPages: Application\UseCase\GetAllPages
CreatePage: Application\UseCase\CreatePage
DeletePage: Application\UseCase\DeletePage
All Use Cases registered!
```

---

## PART 3: UPDATE index.php TO USE DI CONTAINER

### Step 3.1: Add Container Loading

**FILE:** `backend/public/index.php`

**FIND THIS CODE (near the top, after autoload):**
```php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
```

**ADD AFTER `require_once database.php`:**
```php
// Load DI Container
$container = require __DIR__ . '/../bootstrap/container.php';
```

---

### Step 3.2: Update Routing to Use Container

**FIND THIS ROUTING CODE:**
```php
// Route: GET /api/pages
if (preg_match('#^GET /api/pages$#', $route)) {
    $controller = new PageController(); // ❌ OLD WAY
    $controller->index();
```

**REPLACE WITH:**
```php
// Route: GET /api/pages
if (preg_match('#^GET /api/pages$#', $route)) {
    $controller = $container->make(PageController::class);
    $controller->index();
```

**DO THIS FOR ALL PAGE ROUTES:**

```php
// GET /api/pages
if (preg_match('#^GET /api/pages$#', $route)) {
    $controller = $container->make(PageController::class);
    $controller->index();

// GET /api/pages/{id}
} elseif (preg_match('#^GET /api/pages/([a-f0-9\-]+)$#', $route, $matches)) {
    $controller = $container->make(PageController::class);
    $controller->show($matches[1]);

// PATCH /api/pages/{id}/inline
} elseif (preg_match('#^PATCH /api/pages/([a-f0-9\-]+)/inline$#', $route, $matches)) {
    $controller = $container->make(PageController::class);
    $controller->patchInline($matches[1]);

// POST /api/pages/{id}/publish
} elseif (preg_match('#^POST /api/pages/([a-f0-9\-]+)/publish$#', $route, $matches)) {
    $controller = $container->make(PageController::class);
    $controller->publish($matches[1]);

// POST /api/pages
} elseif (preg_match('#^POST /api/pages$#', $route)) {
    $controller = $container->make(PageController::class);
    $controller->store();

// DELETE /api/pages/{id}
} elseif (preg_match('#^DELETE /api/pages/([a-f0-9\-]+)$#', $route, $matches)) {
    $controller = $container->make(PageController::class);
    $controller->delete($matches[1]);
```

**ADD IMPORT at top of index.php:**
```php
use Presentation\Controller\PageController;
```

---

### Step 3.3: Add Global Exception Handler

**FIND THE CLOSING of the routing block:**
```php
} else {
    // 404 Not Found
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['success' => false, 'error' => 'Route not found']);
}
```

**WRAP ALL ROUTING CODE in try-catch:**
```php
try {
    // ===== ALL ROUTING CODE HERE =====
    if (preg_match('#^GET /api/pages$#', $route)) {
        $controller = $container->make(PageController::class);
        $controller->index();
    
    } elseif (preg_match('#^GET /api/pages/([a-f0-9\-]+)$#', $route, $matches)) {
        // ... all other routes
    
    } else {
        // 404 Not Found
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Route not found',
            'route' => $route
        ]);
    }

} catch (\Exception $e) {
    // Global exception handler
    error_log("Unhandled exception in index.php: " . $e->getMessage());
    error_log($e->getTraceAsString());
    
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'code' => 'INTERNAL_ERROR'
    ]);
}
```

---

## PART 4: TESTING

### Step 4.1: Syntax Check All Files

```bash
cd backend
php -l src/Presentation/Controller/PageController.php
php -l public/index.php
php -l bootstrap/container.php
```

**Expected:** `No syntax errors` for all files.

---

### Step 4.2: Test Container Instantiation

```bash
cd backend
php -r "
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
\$controller = \$container->make('Presentation\Controller\PageController');
echo 'Controller class: ' . get_class(\$controller) . PHP_EOL;
echo 'PageController successfully created via DI!' . PHP_EOL;
"
```

**Expected output:**
```
Controller class: Presentation\Controller\PageController
PageController successfully created via DI!
```

---

### Step 4.3: Manual E2E Test (in browser)

**Start server:**
```bash
# If using XAMPP, ensure Apache is running
# Otherwise:
cd backend/public
php -S localhost:8000
```

**Test endpoints:**

1. **GET /api/pages**
   ```
   curl http://localhost/healthcare-cms-backend/api/pages
   ```
   Expected: JSON with list of pages

2. **GET /api/pages/{id}**
   ```
   curl http://localhost/healthcare-cms-backend/api/pages/9c23c3ff-...
   ```
   Expected: JSON with page and blocks

3. **PATCH /api/pages/{id}/inline** (Block not found test!)
   ```bash
   curl -X PATCH http://localhost/healthcare-cms-backend/api/pages/9c23c3ff-.../inline \
     -H "Content-Type: application/json" \
     -d '{"blockId":"invalid-block-id","fieldPath":"content","newMarkdown":"Test"}'
   ```
   
   **Expected response:**
   ```json
   {
     "success": false,
     "error": "Block invalid-block-id not found for page 9c23c3ff-...",
     "context": {
       "block_id": "invalid-block-id",
       "page_id": "9c23c3ff-...",
       "timestamp": 1729123456
     },
     "code": "BLOCK_NOT_FOUND"
   }
   ```
   
   **Expected HTTP code:** `404 Not Found` (NOT 500!)

4. **Test with valid block:**
   ```bash
   # Use a real blockId from the database
   curl -X PATCH http://localhost/healthcare-cms-backend/api/pages/9c23c3ff-.../inline \
     -H "Content-Type: application/json" \
     -d '{"blockId":"1537c131-...","fieldPath":"content","newMarkdown":"Updated content"}'
   ```
   
   Expected: `200 OK` with success response

---

### Step 4.4: Check Error Logs

```bash
# Windows/XAMPP:
tail -f C:\xampp\apache\logs\error.log

# Linux:
tail -f /var/log/apache2/error.log
```

**Look for:**
- ✅ No PHP errors
- ✅ Custom log messages like `[REPO] BlockRepository::findById(...)` (if logging enabled)
- ✅ Exception traces only for unexpected errors (not for 404s)

---

## PART 5: VERIFICATION CHECKLIST

### Code Quality Checks

- [ ] **No `new MySQLRepository()` in PageController**
  ```bash
  grep -n "new MySQL" backend/src/Presentation/Controller/PageController.php
  ```
  Expected: No matches

- [ ] **Constructor has Use Case dependencies**
  ```bash
  grep -A10 "public function __construct" backend/src/Presentation/Controller/PageController.php
  ```
  Expected: Shows UpdatePageInline, GetPageWithBlocks, etc.

- [ ] **All methods use `$this->useCaseName`**
  ```bash
  grep -n "\$this->updatePageInline" backend/src/Presentation/Controller/PageController.php
  ```
  Expected: Found in patchInline() method

- [ ] **Domain exceptions imported**
  ```bash
  grep "use Domain\\\\Exception" backend/src/Presentation/Controller/PageController.php
  ```
  Expected: Shows PageNotFoundException, BlockNotFoundException

---

### Functional Checks

- [ ] **Block not found returns 404 (not 500)**
  - Test with invalid blockId
  - Check HTTP response code
  - Check response includes context

- [ ] **Valid inline update works**
  - Test with real blockId from database
  - Check updated block content
  - Verify page.updated_at changed

- [ ] **GET /api/pages returns all pages**
  - No PHP errors
  - Returns JSON array

- [ ] **Create page works**
  - POST with valid data
  - Returns 201 Created
  - New page appears in database

---

## PART 6: FINAL VERIFICATION SCRIPT

Run this to confirm Phase 3 is complete:

```bash
cd backend
php -r "
echo '========================================' . PHP_EOL;
echo 'PHASE 3 COMPLETION VERIFICATION' . PHP_EOL;
echo '========================================' . PHP_EOL;

// 1. Container loads
require 'vendor/autoload.php';
\$container = require 'bootstrap/container.php';
echo '✓ DI Container loaded' . PHP_EOL;

// 2. PageController can be created via DI
\$controller = \$container->make('Presentation\Controller\PageController');
echo '✓ PageController created via DI' . PHP_EOL;

// 3. Use Cases are injected
\$reflection = new ReflectionClass(\$controller);
\$constructor = \$reflection->getConstructor();
\$params = \$constructor->getParameters();
echo '✓ PageController has ' . count(\$params) . ' constructor dependencies' . PHP_EOL;

// 4. No MySQLRepository in PageController source
\$source = file_get_contents('src/Presentation/Controller/PageController.php');
\$hasMySQLRepo = strpos(\$source, 'new MySQLPageRepository') !== false 
             || strpos(\$source, 'new MySQLBlockRepository') !== false;

if (\$hasMySQLRepo) {
    echo '✗ ERROR: PageController still creates repositories directly!' . PHP_EOL;
    exit(1);
} else {
    echo '✓ No direct repository instantiation in PageController' . PHP_EOL;
}

echo '========================================' . PHP_EOL;
echo 'PHASE 3 COMPLETE!' . PHP_EOL;
echo '========================================' . PHP_EOL;
echo '' . PHP_EOL;
echo 'Next steps:' . PHP_EOL;
echo '1. Test inline editing in browser' . PHP_EOL;
echo '2. Verify Block not found returns HTTP 404' . PHP_EOL;
echo '3. Optional: Refactor other controllers (AuthController, etc.)' . PHP_EOL;
"
```

**Expected output:**
```
========================================
PHASE 3 COMPLETION VERIFICATION
========================================
✓ DI Container loaded
✓ PageController created via DI
✓ PageController has 6 constructor dependencies
✓ No direct repository instantiation in PageController
========================================
PHASE 3 COMPLETE!
========================================

Next steps:
1. Test inline editing in browser
2. Verify Block not found returns HTTP 404
3. Optional: Refactor other controllers (AuthController, etc.)
```

---

## TROUBLESHOOTING

### Error: "No binding found for UpdatePageInline"

**Cause:** Container registration missing.

**Fix:** Add to `bootstrap/container.php`:
```php
$container->bind('UpdatePageInline', function($c) {
    return new UpdatePageInline(
        $c->get('PageRepository'),
        $c->get('BlockRepository'),
        $c->get('MarkdownConverter'),
        $c->get('HTMLSanitizer')
    );
});
```

---

### Error: "Class 'UpdatePageInline' not found"

**Cause:** Missing import in PageController.

**Fix:** Add to top of PageController.php:
```php
use Application\UseCase\UpdatePageInline;
```

---

### Error: "Cannot auto-resolve parameter"

**Cause:** Container's `make()` cannot auto-wire constructor.

**Fix:** Verify all Use Cases are registered in bootstrap/container.php with correct dependencies.

---

### Still getting HTTP 500 for Block not found

**Cause:** Exception not caught correctly.

**Fix:** Verify PageController has:
```php
} catch (BlockNotFoundException $e) {
    header('HTTP/1.1 404 Not Found');
    // ... return JSON with context
}
```

And check that UpdatePageInline throws `BlockNotFoundException` (not `InvalidArgumentException`).

---

## SUCCESS CRITERIA

Phase 3 is complete when:

- [x] PageController has constructor with Use Case dependencies
- [x] No `new MySQLRepository()` in PageController
- [x] All methods use `$this->useCaseName->execute()`
- [x] Domain exceptions (PageNotFoundException, BlockNotFoundException) are caught and return correct HTTP codes
- [x] `index.php` loads DI container and creates controllers via `$container->make()`
- [x] Manual testing shows:
  - GET /api/pages works
  - PATCH /api/pages/{id}/inline with invalid blockId returns **404** (not 500)
  - PATCH /api/pages/{id}/inline with valid blockId works
  - Response includes context for debugging

---

## WHAT'S NEXT (Optional - Phase 3.5)

After completing Phase 3, you can optionally refactor other controllers:

1. **AuthController** (high priority for security)
2. **MenuController**
3. **MediaController**
4. **UserController**
5. **SettingsController**
6. **TemplateController**
7. **PublicPageController**

Each controller follows the SAME pattern as PageController:
- Constructor injection of Use Cases
- DTO for requests
- Domain exceptions with HTTP codes
- No `new MySQLRepository()`

---

**STATUS AFTER PHASE 3:** ✅ Clean Architecture implemented for PageController and routing infrastructure.
