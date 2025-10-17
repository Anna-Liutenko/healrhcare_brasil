# STAGE 1: Domain + Infrastructure Implementation

## Objective
Add support for `rendered_html` and `menu_title` fields to Page entity and MySQL repository. Enable saving/loading these fields from database.

## Context
- **Architecture:** PHP 8+ Clean Architecture (Domain/Application/Infrastructure/Presentation)
- **Database:** MySQL, table `pages`
- **Current state:** Page entity exists with ~17 properties (id, title, slug, status, type, seo fields, show_in_menu, menu_order, timestamps, etc.)
- **Goal:** Add 2 new nullable fields without breaking existing functionality

---

## Task 1: Update Domain Entity

**File:** `backend/src/Domain/Entity/Page.php`

### Changes Required:

1. **Add private properties** after existing properties:
```php
private ?string $renderedHtml = null;
private ?string $menuTitle = null;
```

2. **Update constructor** - add parameters at the end (use default values for backward compatibility):
```php
public function __construct(
    // ... existing 17+ parameters ...
    ?string $renderedHtml = null,
    ?string $menuTitle = null
) {
    // ... existing assignments ...
    $this->renderedHtml = $renderedHtml;
    $this->menuTitle = $menuTitle;
}
```

3. **Add getters** after existing getters:
```php
public function getRenderedHtml(): ?string
{
    return $this->renderedHtml;
}

public function getMenuTitle(): ?string
{
    return $this->menuTitle;
}
```

4. **Add setters** after existing setters:
```php
public function setRenderedHtml(?string $html): void
{
    $this->renderedHtml = $html;
    $this->touch();
}

public function setMenuTitle(?string $menuTitle): void
{
    $this->menuTitle = $menuTitle;
    $this->touch();
}
```

**Validation:** Entity must compile without errors, existing code must not break.

---

## Task 2: Create Database Migration

**File:** `database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql`

### SQL Script:
```sql
-- Migration: Add rendered_html and menu_title to pages table
-- Date: 2025-10-13
-- Description: Support for pre-rendered HTML caching and custom menu labels

-- Add columns (idempotent - use IF NOT EXISTS pattern)
ALTER TABLE pages
  ADD COLUMN IF NOT EXISTS rendered_html LONGTEXT NULL 
    COMMENT 'Pre-rendered static HTML (cached at publish time)' 
    AFTER page_specific_code,
  ADD COLUMN IF NOT EXISTS menu_title VARCHAR(255) NULL 
    COMMENT 'Custom menu item label (overrides title)' 
    AFTER show_in_menu;

-- Add unique index on slug (if not exists)
CREATE UNIQUE INDEX IF NOT EXISTS ux_pages_slug ON pages(slug);

-- Verification query
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pages'
  AND COLUMN_NAME IN ('rendered_html', 'menu_title');
```

**Execution steps:**
1. Backup database: `mysqldump -u root healthcare_cms > backup_before_stage1.sql`
2. Run migration: `mysql -u root healthcare_cms < migrations/2025_10_13_add_rendered_html_and_menu_title.sql`
3. Verify columns added: run verification query from migration file

**Rollback SQL (if needed):**
```sql
ALTER TABLE pages 
  DROP COLUMN IF EXISTS rendered_html,
  DROP COLUMN IF EXISTS menu_title;
```

---

## Task 3: Update Infrastructure Repository

**File:** `backend/src/Infrastructure/Repository/MySQLPageRepository.php`

### Changes Required:

#### 3.1. Update `save(Page $page)` method

**Locate:** SQL INSERT statement with column list (around line 50-100)

**Add to column list:**
```sql
INSERT INTO pages (
    id, title, slug, status, type,
    seo_title, seo_description, seo_keywords,
    show_in_menu, menu_title,        -- NEW: add menu_title here
    show_in_sitemap, menu_order,
    created_at, updated_at, published_at, trashed_at,
    created_by, collection_config, page_specific_code,
    rendered_html,                    -- NEW: add rendered_html here
    source_template_slug
) VALUES (
    :id, :title, :slug, :status, :type,
    :seo_title, :seo_description, :seo_keywords,
    :show_in_menu, :menu_title,       -- NEW
    :show_in_sitemap, :menu_order,
    :created_at, :updated_at, :published_at, :trashed_at,
    :created_by, :collection_config, :page_specific_code,
    :rendered_html,                   -- NEW
    :source_template_slug
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    slug = VALUES(slug),
    status = VALUES(status),
    -- ... existing fields ...
    menu_title = VALUES(menu_title),          -- NEW
    rendered_html = VALUES(rendered_html),    -- NEW
    updated_at = VALUES(updated_at)
```

**Add to parameter binding array** (find `$stmt->execute([...])` or similar):
```php
$params = [
    'id' => $page->getId(),
    'title' => $page->getTitle(),
    // ... existing parameters ...
    'menu_title' => $page->getMenuTitle(),          // NEW
    'rendered_html' => $page->getRenderedHtml(),    // NEW
    // ... rest of parameters ...
];
```

#### 3.2. Update `hydrate(array $row): Page` method

**Locate:** Method that creates Page entity from database row (around line 200-300)

**Update constructor call:**
```php
private function hydrate(array $row): Page
{
    return new Page(
        id: $row['id'],
        title: $row['title'],
        slug: $row['slug'],
        // ... existing 15+ parameters ...
        sourceTemplateSlug: $row['source_template_slug'] ?? null,
        renderedHtml: $row['rendered_html'] ?? null,    // NEW: add at end
        menuTitle: $row['menu_title'] ?? null           // NEW: add at end
    );
}
```

**Important:** Add new parameters at the END to maintain backward compatibility with existing code that may call hydrate indirectly.

#### 3.3. Update all SELECT queries

**Find all SQL SELECT statements** in repository (typically in methods like `findById`, `findBySlug`, `findAll`, etc.)

**Add to SELECT column list:**
```sql
SELECT 
    p.id, p.title, p.slug, p.status, p.type,
    p.seo_title, p.seo_description, p.seo_keywords,
    p.show_in_menu, p.menu_title,           -- NEW
    p.show_in_sitemap, p.menu_order,
    p.created_at, p.updated_at, p.published_at, p.trashed_at,
    p.created_by, p.collection_config, p.page_specific_code,
    p.rendered_html,                         -- NEW
    p.source_template_slug
FROM pages p
WHERE ...
```

**Tip:** Use search/replace to add `p.menu_title, ` and `p.rendered_html, ` to all SELECT statements systematically.

---

## Task 4: Write Unit Tests

**File:** `backend/tests/Unit/Infrastructure/MySQLPageRepositoryTest.php`

### Test Cases:

```php
<?php

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use App\Domain\Entity\Page;
use App\Infrastructure\Repository\MySQLPageRepository;

class MySQLPageRepositoryTest extends TestCase
{
    private MySQLPageRepository $repository;
    
    protected function setUp(): void
    {
        // Setup test database connection
        $this->repository = new MySQLPageRepository(
            $this->getTestDatabaseConnection()
        );
    }
    
    public function testSaveAndLoadPageWithRenderedHtml(): void
    {
        // Create page with rendered_html
        $page = new Page(
            id: 'test-page-001',
            title: 'Test Page',
            slug: 'test-page',
            status: 'published',
            type: 'dynamic',
            seoTitle: 'Test SEO',
            seoDescription: 'Test Description',
            seoKeywords: 'test,keywords',
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 1,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'admin-001',
            collectionConfig: null,
            pageSpecificCode: null,
            sourceTemplateSlug: null,
            renderedHtml: '<html><body>Test HTML</body></html>',  // NEW
            menuTitle: null
        );
        
        // Save to database
        $this->repository->save($page);
        
        // Retrieve from database
        $loadedPage = $this->repository->findById('test-page-001');
        
        // Assert rendered_html is persisted
        $this->assertNotNull($loadedPage);
        $this->assertEquals('<html><body>Test HTML</body></html>', $loadedPage->getRenderedHtml());
    }
    
    public function testSaveAndLoadPageWithMenuTitle(): void
    {
        // Create page with menu_title
        $page = new Page(
            id: 'test-page-002',
            title: 'Very Long Page Title That Should Not Appear In Menu',
            slug: 'test-page-2',
            status: 'published',
            type: 'dynamic',
            // ... (use same parameters as above) ...
            renderedHtml: null,
            menuTitle: 'Short Menu Label'  // NEW
        );
        
        // Save and retrieve
        $this->repository->save($page);
        $loadedPage = $this->repository->findById('test-page-002');
        
        // Assert menu_title is persisted
        $this->assertNotNull($loadedPage);
        $this->assertEquals('Short Menu Label', $loadedPage->getMenuTitle());
    }
    
    public function testSavePageWithNullRenderedHtmlAndMenuTitle(): void
    {
        // Create page without new fields (backward compatibility test)
        $page = new Page(
            id: 'test-page-003',
            title: 'Legacy Page',
            slug: 'legacy-page',
            // ... standard parameters ...
            renderedHtml: null,  // NULL
            menuTitle: null      // NULL
        );
        
        // Should save without errors
        $this->repository->save($page);
        $loadedPage = $this->repository->findById('test-page-003');
        
        $this->assertNotNull($loadedPage);
        $this->assertNull($loadedPage->getRenderedHtml());
        $this->assertNull($loadedPage->getMenuTitle());
    }
    
    protected function tearDown(): void
    {
        // Cleanup: delete test pages
        $this->repository->delete('test-page-001');
        $this->repository->delete('test-page-002');
        $this->repository->delete('test-page-003');
    }
}
```

**Run tests:**
```bash
cd backend
vendor/bin/phpunit tests/Unit/Infrastructure/MySQLPageRepositoryTest.php
```

---

## Verification Checklist

- [ ] Page entity compiles without errors
- [ ] Page entity has `getRenderedHtml()`, `setRenderedHtml()`, `getMenuTitle()`, `setMenuTitle()` methods
- [ ] Database migration executed successfully (verify with `SHOW COLUMNS FROM pages`)
- [ ] Columns `rendered_html` (LONGTEXT) and `menu_title` (VARCHAR 255) exist in database
- [ ] MySQLPageRepository saves new fields (check with `SELECT * FROM pages WHERE id = 'test-page-id'`)
- [ ] MySQLPageRepository loads new fields into Page entity
- [ ] Unit tests pass (all 3 test cases green)
- [ ] No existing functionality broken (run full test suite: `vendor/bin/phpunit`)

---

## Expected Output

After completing Stage 1:
1. **Domain layer:** Page entity supports `rendered_html` and `menu_title` properties
2. **Database:** `pages` table has 2 new nullable columns
3. **Infrastructure layer:** Repository can persist and hydrate new fields
4. **Tests:** Unit tests confirm save/load works correctly
5. **Backward compatibility:** Existing pages load without errors (NULL for new fields)

**Next stage:** Application layer (PublishPage use-case will use these fields to store rendered HTML).

---

## Common Issues & Solutions

**Issue:** Migration fails with "column already exists"
- **Solution:** Migration SQL uses `IF NOT EXISTS` - safe to re-run

**Issue:** Page constructor parameter count mismatch
- **Solution:** Add new parameters at END with default values: `?string $renderedHtml = null`

**Issue:** Unit tests fail with "Column not found: rendered_html"
- **Solution:** Verify migration executed on TEST database (not just production)

**Issue:** `rendered_html` truncated or corrupted
- **Solution:** Verify column type is LONGTEXT (not TEXT or VARCHAR)

**Issue:** Repository save() doesn't include new fields
- **Solution:** Check BOTH INSERT and UPDATE parts of `ON DUPLICATE KEY UPDATE` query

---

## Files Modified Summary

```
backend/src/Domain/Entity/Page.php                    [MODIFIED: +20 lines]
database/migrations/2025_10_13_add_*.sql              [CREATED: new file]
backend/src/Infrastructure/Repository/MySQLPageRepository.php  [MODIFIED: ~50 lines]
backend/tests/Unit/Infrastructure/MySQLPageRepositoryTest.php  [CREATED: new file, ~100 lines]
```

**Total changes:** ~170 lines of code across 4 files.

---

**Execution time estimate:** 1.5–2 hours (including testing and verification).
