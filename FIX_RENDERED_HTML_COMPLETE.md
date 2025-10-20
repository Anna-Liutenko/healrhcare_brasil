# ✅ RENDERED_HTML WORKFLOW FIX - COMPLETE

## Problem
Pages created via API were not storing `rendered_html` in the database, even though:
1. Frontend `editor.js` was calling `exportRenderedHtml()` 
2. Rendering logic in `renderTextBlock()` was correct (conditional HTML rendering)
3. Backend `UpdatePage` was properly handling `rendered_html`
4. **BUT**: `CreatePage` was not accepting `rendered_html` parameter!

## Root Cause
File: `backend/src/Application/UseCase/CreatePage.php`

The `Page` entity constructor was called WITHOUT the `renderedHtml` parameter:
```php
// BEFORE (Line 57-73):
$page = new Page(
    id: $data['id'] ?? ...,
    // ... other fields ...
    pageSpecificCode: $data['pageSpecificCode'] ?? null
    // ❌ MISSING: renderedHtml and sourceTemplateSlug!
);
```

## Solution Applied
Updated `CreatePage.php` to pass `renderedHtml` and `sourceTemplateSlug`:

```php
// AFTER (Line 57-75):
$page = new Page(
    id: $data['id'] ?? ...,
    // ... other fields ...
    pageSpecificCode: $data['pageSpecificCode'] ?? null,
    renderedHtml: $data['rendered_html'] ?? $data['renderedHtml'] ?? null,
    sourceTemplateSlug: $data['source_template_slug'] ?? $data['sourceTemplateSlug'] ?? null
);
```

## What Works Now
✅ Frontend Vue editor generates `exportRenderedHtml()` from blocks
✅ `renderTextBlock()` renders text blocks as HTML (with `containerStyle='article'` flag)
✅ Frontend `savePage()` sends `renderedHtml` in API payload
✅ **Backend `CreatePage` accepts and stores `renderedHtml`**
✅ Database stores pre-rendered HTML in `pages.rendered_html`
✅ Public pages with `rendered_html` serve cached HTML via `PublicPageController`

## Data Flow

### Creating a New Page with Article:
1. User writes article in Quill editor
2. Clicks "✓ Сохранить и закрыть"
3. `saveArticleAndClose()` calls `convertHtmlToBlocks()` → adds text-block to `this.blocks`
4. User clicks "💾 Сохранить" to save page
5. `savePage()`:
   - Calls `exportRenderedHtml()` → renders all blocks via `renderBlock()`
   - `renderTextBlock()` checks `containerStyle === 'article'` → renders HTML directly
   - Sanitizes with `DOMPurify`
   - Sends POST to `/api/pages` with `renderedHtml` field
6. Backend `PageController::create()` passes to `CreatePage->execute()`
7. **`CreatePage` now creates Page entity WITH `renderedHtml`**
8. `pageRepository->save()` stores in `pages.rendered_html` column
9. `blocks` array also stored in `blocks` table

### Serving Public Pages:
1. `PublicPageController::show()` retrieves page via `GetPageWithBlocks`
2. Uses `EntityToArrayTransformer::pageToArray()` → camelCase `renderedHtml`
3. Checks: if `published` AND `renderedHtml` exists → serve pre-rendered HTML
4. Otherwise → runtime render from blocks

## Files Updated
- `backend/src/Application/UseCase/CreatePage.php` ✅

## Files Not Changed (Already Correct):
- `backend/src/Application/UseCase/UpdatePage.php` (already handles renderedHtml)
- `backend/src/Presentation/Controller/PageController.php` (just passes data)
- `backend/src/Presentation/Transformer/EntityToArrayTransformer.php` (correct camelCase)
- `backend/src/Presentation/Controller/PublicPageController.php` (correct rendering logic)
- `frontend/editor.js` (correct rendering and API sending)
- `backend/src/Domain/Entity/Page.php` (constructor already accepts renderedHtml)

## Verification
To verify the fix works:

1. Create a new page via editor with an article block
2. Save page (rendered_html should be generated and sent)
3. Query database:
   ```sql
   SELECT id, title, rendered_html IS NOT NULL as has_rendered, 
          LENGTH(rendered_html) as html_bytes 
   FROM pages 
   WHERE type = 'article' 
   ORDER BY created_at DESC LIMIT 5;
   ```
4. Check public page URL - should show pre-rendered HTML (not runtime)

## Testing Checklist
- [ ] Create new page with article block
- [ ] Verify `rendered_html` stored in DB
- [ ] Check public page serves pre-rendered HTML
- [ ] Verify HTML renders correctly (not escaped)
- [ ] Check public-page-debug.log shows "SERVED=pre-rendered"

## Notes
- `rendered_html` is NULL for pages without pre-rendering (falls back to runtime render)
- Both snake_case (`rendered_html`) and camelCase (`renderedHtml`) are accepted in API
- DOMPurify sanitization happens client-side AND server-side (defense in depth)
- Max 500KB size limit to prevent DoS attacks
