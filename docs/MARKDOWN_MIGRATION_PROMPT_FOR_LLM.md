# LLM Implementation Prompt: Markdown Rendering Migration

**Task ID:** MARKDOWN-MIGRATION-001  
**Priority:** HIGH  
**Estimated Time:** 3-4 hours  
**Context Document:** `docs/MARKDOWN_MIGRATION_PLAN.md`

---

## OBJECTIVE

Migrate the CMS from `htmlspecialchars()`-based text rendering to Markdown-based rendering using `league/commonmark` library. This will fix the issue where HTML tags (e.g., `<br>`) are displayed as text instead of being rendered as markup on public pages.

**Critical Requirements:**
1. Visual editor UI must remain unchanged
2. Existing published pages must render correctly
3. XSS protection must be maintained
4. Rollback must be possible via single `git revert`

---

## CURRENT STATE ANALYSIS

### Problem Statement
- **Frontend (Vue):** Renders blocks directly, allowing HTML tags to work
- **Backend (PHP):** Uses `htmlspecialchars()` on all text fields, escaping HTML tags
- **Result:** User sees `<br>` as line break in editor, but as text `&lt;br&gt;` on public page

### Files Affected
1. `backend/src/Presentation/Controller/PublicPageController.php` — public page rendering
2. `backend/src/Application/UseCase/RenderPageHtml.php` — use case for HTML generation
3. `backend/composer.json` — dependencies (need to add league/commonmark)
4. `backend/tests/Unit/RenderPageHtmlTest.php` — existing tests (need updates)

### Block Types (DO NOT CHANGE)
The following block types exist and their structure must remain unchanged:
- `main-screen`, `page-header`, `service-cards`, `article-cards`, `text`, `button`, etc.
- Defined in: `frontend/blocks.js`

---

## IMPLEMENTATION STEPS

### Step 1: Install league/commonmark

**Command:**
```bash
cd backend
php composer.phar require league/commonmark
```

**Expected result:**
- `backend/composer.json` updated with `"league/commonmark": "^2.4"`
- `backend/vendor/league/commonmark/` directory created

**Verification:**
```bash
php composer.phar show league/commonmark
```

---

### Step 2: Create Markdown Configuration

**File:** `backend/config/markdown.php`

**Content:**
```php
<?php
/**
 * Markdown Renderer Configuration
 * Used by league/commonmark for safe HTML rendering
 */
return [
    // Strip dangerous HTML tags
    'html_input' => 'strip',
    
    // Block javascript: and data: URLs
    'allow_unsafe_links' => false,
    
    // Prevent DoS via deeply nested structures
    'max_nesting_level' => 10,
    
    // Renderer options
    'renderer' => [
        // Convert \n to <br>
        'soft_break' => "<br>\n",
    ],
];
```

**Verification:** File created, syntax valid (no PHP errors).

---

### Step 3: Create MarkdownRenderer Service

**File:** `backend/src/Infrastructure/Service/MarkdownRenderer.php`

**Content:**
```php
<?php
declare(strict_types=1);

namespace Infrastructure\Service;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown Renderer Service
 * 
 * Converts text (with optional HTML tags) to safe HTML using Markdown.
 * Provides backward compatibility by auto-converting HTML tags to Markdown.
 */
class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/markdown.php';
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        
        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Render text to HTML
     * 
     * Auto-converts common HTML tags to Markdown for backward compatibility:
     * - <br> → \n\n
     * - <strong> → **
     * - <em> → _
     * 
     * Then converts Markdown to safe HTML.
     * 
     * @param string $text Input text (plain text, Markdown, or HTML)
     * @return string Safe HTML output
     */
    public function render(string $text): string
    {
        // Step 1: Convert HTML tags to Markdown (backward compatibility)
        $text = $this->htmlToMarkdown($text);
        
        // Step 2: Render Markdown to HTML
        $html = $this->converter->convert($text)->getContent();
        
        // Step 3: Remove wrapping <p> tags if present (to avoid double-wrapping)
        $html = trim($html);
        if (str_starts_with($html, '<p>') && str_ends_with($html, '</p>')) {
            $html = substr($html, 3, -4);
        }
        
        return $html;
    }

    /**
     * Convert common HTML tags to Markdown
     * 
     * @param string $text Text with HTML tags
     * @return string Text with Markdown syntax
     */
    private function htmlToMarkdown(string $text): string
    {
        $replacements = [
            // Line breaks
            '<br>' => "\n\n",
            '<br/>' => "\n\n",
            '<br />' => "\n\n",
            
            // Bold
            '<strong>' => '**',
            '</strong>' => '**',
            '<b>' => '**',
            '</b>' => '**',
            
            // Italic
            '<em>' => '_',
            '</em>' => '_',
            '<i>' => '_',
            '</i>' => '_',
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }
}
```

**Verification:**
```php
// Quick test (can run in backend/test_markdown.php)
$renderer = new \Infrastructure\Service\MarkdownRenderer();
echo $renderer->render('Hello<br>World'); // Should output: Hello<br>\nWorld (with <br> tag)
echo $renderer->render('**Bold** text'); // Should output: <strong>Bold</strong> text
```

---

### Step 4: Update PublicPageController

**File:** `backend/src/Presentation/Controller/PublicPageController.php`

**Changes:**

#### 4.1. Add MarkdownRenderer dependency

**Location:** Top of class, after properties declaration

**FIND:**
```php
class PublicPageController
{
    /**
```

**REPLACE WITH:**
```php
class PublicPageController
{
    private \Infrastructure\Service\MarkdownRenderer $markdownRenderer;

    public function __construct()
    {
        $this->markdownRenderer = new \Infrastructure\Service\MarkdownRenderer();
    }

    /**
```

#### 4.2. Add renderText helper method

**Location:** End of class, before closing brace

**ADD:**
```php
    /**
     * Render text with Markdown support (safe HTML output)
     */
    private function renderText(string $text): string
    {
        return $this->markdownRenderer->render($text);
    }
```

#### 4.3. Replace htmlspecialchars() calls

**CRITICAL:** Replace ALL occurrences of `htmlspecialchars($data[...])` with `$this->renderText($data[...])`

**Example transformations:**

**FIND:**
```php
$html .= '<h2>' . htmlspecialchars($data['heading']) . '</h2>';
```

**REPLACE WITH:**
```php
$html .= '<h2>' . $this->renderText($data['heading']) . '</h2>';
```

**FIND:**
```php
$html .= '<p>' . nl2br(htmlspecialchars($data['text'])) . '</p>';
```

**REPLACE WITH:**
```php
$html .= '<div>' . $this->renderText($data['text']) . '</div>';
```

**FIND (card rendering):**
```php
$html .= '<h3>' . htmlspecialchars($cardTitle) . '</h3>';
$html .= '<p>' . nl2br(htmlspecialchars($cardText)) . '</p>';
```

**REPLACE WITH:**
```php
$html .= '<h3>' . $this->renderText($cardTitle) . '</h3>';
$html .= '<div>' . $this->renderText($cardText) . '</div>';
```

**Complete list of fields to update:**
- `$data['heading']`
- `$data['title']`
- `$data['subtitle']`
- `$data['subheading']`
- `$data['text']`
- `$data['description']`
- `$cardTitle`
- `$cardText`
- `$page['title']` (in renderPage method, line ~110)

**DO NOT CHANGE:**
- `htmlspecialchars($btnUrl)` — URLs must stay escaped
- `htmlspecialchars($cardImage)` — image URLs must stay escaped
- Any `htmlspecialchars()` inside attributes (href, src, alt)

---

### Step 5: Update RenderPageHtml Use Case

**File:** `backend/src/Application/UseCase/RenderPageHtml.php`

**Changes:**

#### 5.1. Add MarkdownRenderer dependency

**FIND:**
```php
public function __construct(
    private BlockRepositoryInterface $blockRepository
) {}
```

**REPLACE WITH:**
```php
public function __construct(
    private BlockRepositoryInterface $blockRepository,
    private ?\Infrastructure\Service\MarkdownRenderer $markdownRenderer = null
) {
    $this->markdownRenderer = $markdownRenderer ?? new \Infrastructure\Service\MarkdownRenderer();
}
```

#### 5.2. Update render logic

**FIND:**
```php
$title = htmlspecialchars($page->getTitle() ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

**REPLACE WITH:**
```php
$title = $this->markdownRenderer->render($page->getTitle() ?? '');
```

**FIND:**
```php
} elseif (isset($data['text'])) {
    $bodyParts[] = '<p>' . htmlspecialchars((string)$data['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}
```

**REPLACE WITH:**
```php
} elseif (isset($data['text'])) {
    $bodyParts[] = '<div>' . $this->markdownRenderer->render((string)$data['text']) . '</div>';
}
```

**Keep unchanged:**
- `htmlspecialchars(json_encode(...))` in fallback rendering

---

### Step 6: Create Unit Tests for MarkdownRenderer

**File:** `backend/tests/Unit/MarkdownRendererTest.php`

**Content:**
```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Infrastructure\Service\MarkdownRenderer;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    public function testRenderPlainText(): void
    {
        $result = $this->renderer->render('Hello world');
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testRenderBrTag(): void
    {
        $input = 'Line 1<br>Line 2';
        $result = $this->renderer->render($input);
        
        // Should convert <br> to line break
        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 2', $result);
        
        // Should NOT escape <br> as &lt;br&gt;
        $this->assertStringNotContainsString('&lt;br&gt;', $result);
    }

    public function testRenderMarkdownBold(): void
    {
        $result = $this->renderer->render('**bold text**');
        $this->assertStringContainsString('<strong>bold text</strong>', $result);
    }

    public function testRenderMarkdownItalic(): void
    {
        $result = $this->renderer->render('_italic text_');
        $this->assertStringContainsString('<em>italic text</em>', $result);
    }

    public function testXssProtection(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $result = $this->renderer->render($malicious);
        
        // Should strip <script> tags
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testUnsafeLinks(): void
    {
        $malicious = '[link](javascript:alert("xss"))';
        $result = $this->renderer->render($malicious);
        
        // Should block javascript: URLs
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testHtmlToMarkdownConversion(): void
    {
        // Test automatic HTML → Markdown conversion
        $input = 'Text with <strong>bold</strong> and <em>italic</em>';
        $result = $this->renderer->render($input);
        
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
    }
}
```

**Run tests:**
```bash
cd backend
php vendor/bin/phpunit tests/Unit/MarkdownRendererTest.php --bootstrap tests/_bootstrap.php
```

**Expected:** All tests pass (green).

---

### Step 7: Update Existing RenderPageHtml Tests

**File:** `backend/tests/Unit/RenderPageHtmlTest.php`

**Changes needed:**

#### 7.1. Update test expectations

Tests that check for `htmlspecialchars()` behavior need to be updated to expect Markdown rendering.

**FIND (in test assertions):**
```php
$this->assertStringContainsString('&lt;', $html); // Expecting escaped HTML
```

**REPLACE WITH:**
```php
$this->assertStringContainsString('<', $html); // Expecting rendered HTML (not escaped)
```

#### 7.2. Add Markdown-specific tests

**ADD at the end of the file:**
```php
public function testRenderPageHtmlWithBrTag(): void
{
    $page = $this->createMockPage('Test Page');
    
    $block = $this->createMock(\Domain\Entity\Block::class);
    $block->method('getData')->willReturn([
        'text' => 'Line 1<br>Line 2'
    ]);
    
    $this->blockRepository->method('findByPageId')->willReturn([$block]);
    
    $html = $this->useCase->execute($page);
    
    // <br> should work as line break, not be escaped
    $this->assertStringContainsString('Line 1', $html);
    $this->assertStringContainsString('Line 2', $html);
    $this->assertStringNotContainsString('&lt;br&gt;', $html);
}
```

**Run tests:**
```bash
php vendor/bin/phpunit tests/Unit/RenderPageHtmlTest.php --bootstrap tests/_bootstrap.php
```

**Expected:** All tests pass.

---

### Step 8: Copy uploads folder (for testing)

**Purpose:** Ensure images are available in test environment.

**Commands (PowerShell):**
```powershell
# From production/current htdocs to test htdocs
$srcBackend = "C:\xampp\htdocs\healthcare-cms-backend\uploads"
$srcFrontend = "C:\xampp\htdocs\healthcare-cms-frontend\uploads"
$dst = "C:\xampp\htdocs\visual-editor-standalone-test\uploads"

# Create destination if doesn't exist
if (!(Test-Path $dst)) { New-Item -Path $dst -ItemType Directory }

# Copy from backend
if (Test-Path $srcBackend) { robocopy $srcBackend $dst /E }

# Copy from frontend (may overwrite some files)
if (Test-Path $srcFrontend) { robocopy $srcFrontend $dst /E }
```

**Alternative (from workspace):**
```powershell
$srcBackend = "backend\uploads"
$srcFrontend = "frontend\uploads"
$dst = "C:\xampp\htdocs\visual-editor-standalone-test\uploads"

if (!(Test-Path $dst)) { New-Item -Path $dst -ItemType Directory }
if (Test-Path $srcBackend) { robocopy $srcBackend $dst /E }
if (Test-Path $srcFrontend) { robocopy $srcFrontend $dst /E }
```

**Verification:**
```powershell
# Check if files copied
Get-ChildItem $dst | Measure-Object
# Should show files count > 0
```

---

## VERIFICATION CHECKLIST

After completing all steps, verify:

### Code Quality
- [ ] All PHP files have `declare(strict_types=1);`
- [ ] No syntax errors (`php -l <file>` for each changed file)
- [ ] PSR-4 autoloading works (class names match file paths)
- [ ] No `var_dump()` or debug code left in files

### Tests
- [ ] `MarkdownRendererTest.php` — all tests pass
- [ ] `RenderPageHtmlTest.php` — all tests pass
- [ ] Run full unit test suite: `php vendor/bin/phpunit tests/Unit/ --bootstrap tests/_bootstrap.php`
- [ ] No test regressions (all previously passing tests still pass)

### Functional
- [ ] Test page renders correctly: `http://localhost/visual-editor-standalone-test/backend/public/index.php?path=/p/test`
- [ ] `<br>` works as line break (not as text)
- [ ] Images display (not 404)
- [ ] No XSS vulnerabilities (test with `<script>alert(1)</script>`)
- [ ] Bold/italic Markdown works (`**text**`, `_text_`)

### Git
- [ ] Changes committed on branch `feature/markdown-migration`
- [ ] Commit message: `feat: migrate to Markdown rendering with league/commonmark`
- [ ] No uncommitted changes (`git status` clean)
- [ ] `.gitignore` includes `vendor/` (don't commit dependencies)

---

## ROLLBACK PROCEDURE

If something goes wrong:

```bash
# Rollback code changes
git revert HEAD

# Or reset to previous commit
git reset --hard HEAD~1

# Restore composer dependencies
cd backend
php composer.phar install
```

---

## SUCCESS CRITERIA

Migration is considered successful when:

1. ✅ All unit tests pass
2. ✅ Test page `/p/test` renders correctly with:
   - Line breaks working (`<br>` → actual line break)
   - Images displaying (no 404)
   - No escaped HTML entities visible (`&lt;`, `&gt;`)
3. ✅ XSS protection verified (malicious input stripped)
4. ✅ No console errors in browser DevTools
5. ✅ Code committed to git with proper message

---

## CONSTRAINTS AND RULES

### DO NOT CHANGE
- Block type definitions in `frontend/blocks.js`
- Visual editor UI or behavior
- Database schema
- API endpoints or contracts
- Existing test files (except `RenderPageHtmlTest.php`)

### MUST PRESERVE
- XSS protection (all user input must be sanitized)
- Backward compatibility (existing pages must render)
- Rollback capability (via `git revert`)

### CODING STANDARDS
- PHP 8.0+ syntax
- PSR-4 autoloading
- Strict types (`declare(strict_types=1);`)
- PHPUnit 9+ for tests

---

## EXPECTED OUTPUT

After execution, provide:

1. **Summary report:**
   - List of files created/modified
   - Test results (pass/fail counts)
   - Screenshots or HTML snippets of test page

2. **Git commit:**
   - Branch name: `feature/markdown-migration`
   - Commit message: `feat: migrate to Markdown rendering with league/commonmark`
   - Files changed count

3. **Verification:**
   - Unit test output
   - Functional test results (test page URL + screenshot)
   - XSS test results

---

## CONTEXT FILES (READ BEFORE STARTING)

1. `docs/MARKDOWN_MIGRATION_PLAN.md` — detailed explanation (this is for humans, not required for LLM)
2. `backend/src/Presentation/Controller/PublicPageController.php` — main file to modify
3. `backend/src/Application/UseCase/RenderPageHtml.php` — use case to modify
4. `frontend/blocks.js` — block definitions (DO NOT MODIFY, read for context)

---

## FINAL NOTES

- This is a **critical migration** — test thoroughly before marking complete
- If unsure about any step, ask for clarification before proceeding
- Document any deviations from this plan in commit message
- Keep backups of original files before modifying

**GOOD LUCK!** 🚀
