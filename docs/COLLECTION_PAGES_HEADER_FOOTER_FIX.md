# Collection Pages: Header & Footer Fix

**Date:** October 31, 2025  
**Branch:** `fix/preview-cardimage-apilogger-push`  
**Issue:** Collection pages (`/blog`, `/guides`, `/all-materials`) had different header and footer markup compared to other published pages.

## Summary

Fixed header and footer on collection pages to match the exact markup and styling used on other published pages (e.g., home, article pages).

## Changes Made

### 1. Backend Templates (`backend/templates/`)
Updated three static template files to use consistent shared header/footer:
- **`backend/templates/blog.html`** — replaced custom header/footer with shared markup
- **`backend/templates/guides.html`** — replaced custom header/footer with shared markup  
- **`backend/templates/all-materials.html`** — replaced custom header/footer with shared markup

**Note:** These templates are not currently used in production (collection pages render via `CollectionHtmlBuilder`), but updated for consistency.

### 2. Collection HTML Builder (MAIN FIX)
**File:** `backend/src/Presentation/Helper/CollectionHtmlBuilder.php`

**Methods updated:**
- `buildHeader()` — now generates:
  ```html
  <header class="main-header">
      <div class="container">
          <a href="/" class="logo">Healthcare Hacks Brazil</a>
          <nav class="main-nav">
              <ul>
                  <li><a href="/">Главная</a></li>
                  <li><a href="/guides">Гайды</a></li>
                  <li><a href="/blog">Блог</a></li>
                  <li><a href="/bot">Бот</a></li>
              </ul>
          </nav>
      </div>
  </header>
  ```

- `buildFooter()` — now generates:
  ```html
  <footer class="main-footer">
      <div class="container">
          <a href="/" class="logo">Healthcare Hacks Brazil</a>
          <p>&copy; 2025 Анна Лютенко (Anna Liutenko). Все права защищены.</p>
          <p><a href="#privacy">Политика конфиденциальности</a></p>
      </div>
  </footer>
  ```

## How It Works

1. **Collection pages** (`/blog`, `/guides`, `/all-materials?section=guides&page=1` etc.) are dynamically rendered by `PublicPageController::renderCollectionPage()`.
2. This controller uses `CollectionHtmlBuilder::build()` to construct the full HTML page.
3. The builder now uses the **same header and footer CSS classes** (`main-header`, `main-footer`) that are styled in `frontend/editor-public.css`.
4. CSS styling is automatically inherited from the global stylesheet (`/healthcare-cms-frontend/styles.css` and `/healthcare-cms-frontend/editor-preview.css`).

## Compliance

✅ **No new dependencies added** (PHP 8.2 compliant, vanilla HTML generation)  
✅ **Follows project constraints** (`copilot-instructions.md`)  
✅ **Minimal, focused changes** — only header/footer markup modified  
✅ **UTF-8 encoded** (database and file charset)  
✅ **Follows DRY principle** — uses same CSS classes as other pages  

## Verification

### Local Testing
1. Start XAMPP and navigate to: `http://localhost/healthcare-cms-backend/`
2. Open collection pages:
   - `http://localhost/healthcare-cms-backend/all-materials?section=guides&page=1`
   - `http://localhost/healthcare-cms-backend/blog?section=articles&page=1`
   - `http://localhost/healthcare-cms-backend/guides`
3. Compare header and footer with published pages (e.g., home page).
4. Verify CSS styling is consistent (sticky header, footer background, logo styling, etc.).

### DevTools Check
- Open browser DevTools (F12)
- Inspect `.main-header` and `.main-footer` classes
- Verify computed styles match between collection pages and other published pages

## Troubleshooting: Pre-rendered HTML Cache

### Problem
If changes to `CollectionHtmlBuilder.php` are not visible after syncing to XAMPP and clearing browser cache, the issue is likely **pre-rendered HTML cache in the database**.

**Symptom:** View page source and see `<!-- SERVED=pre-rendered | length=XXXX | ts=TIMESTAMP -->` at the top.

### Root Cause
1. Collection pages (like other pages) can be published and have their HTML cached in the `rendered_html` field in the `pages` table.
2. `PublicPageController::show()` checks if `rendered_html` is not empty for published pages and serves it **directly from the database**, bypassing `CollectionHtmlBuilder`.
3. This means changes to `CollectionHtmlBuilder.php` won't be visible until the page is re-rendered and re-saved.

### Solution
Clear the `rendered_html` cache for collection pages to force dynamic rendering:

```powershell
# Clear cache for specific collection page
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "UPDATE healthcare_cms.pages SET rendered_html = NULL WHERE slug = 'all-materials';"

# Verify it's cleared
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SELECT slug, IFNULL(LENGTH(rendered_html), 0) as html_length FROM healthcare_cms.pages WHERE slug = 'all-materials';"
```

After clearing the cache:
1. Refresh the page in browser (Ctrl+Shift+R)
2. Verify source shows `<!-- SERVED=runtime | ...` instead of `pre-rendered`
3. Changes to `CollectionHtmlBuilder.php` should now be visible

### Alternative: Force Re-render
Instead of clearing cache, you can re-publish the page in the CMS admin panel to regenerate `rendered_html` with the updated `CollectionHtmlBuilder` code.

### Prevention
- Always check page source when debugging template changes
- Look for `<!-- SERVED=pre-rendered` vs `<!-- SERVED=runtime` comment
- For collection pages specifically, prefer dynamic rendering during development (keep `rendered_html` NULL)

## Files Modified

| File | Change | Type |
|------|--------|------|
| `backend/templates/blog.html` | Replace header/footer with shared markup | Template |
| `backend/templates/guides.html` | Replace header/footer with shared markup | Template |
| `backend/templates/all-materials.html` | Replace header/footer with shared markup | Template |
| `backend/src/Presentation/Helper/CollectionHtmlBuilder.php` | Update `buildHeader()` and `buildFooter()` methods | PHP |

## Next Steps

- [ ] Manual QA on XAMPP (test on mobile and desktop viewports)
- [ ] Review page rendering in browser
- [ ] Commit and push to feature branch
- [ ] Create pull request with this summary

---

**Author:** AI Assistant  
**Status:** ✅ Complete
