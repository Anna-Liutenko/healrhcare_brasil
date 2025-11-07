### 1. Tech Stack

*   **Backend:** PHP 8.2 (no frameworks).
    
*   **Frontend:** "Vanilla" Vue.js 3 (loaded via CDN, no `node_modules`).
    
*   **Server:** XAMPP with Apache and MySQL.
    
*   **Styling:** "Vanilla" CSS (no pre-processors).
    

* * *

### 2. Constraints (CRITICAL)

*   **AVOID** suggest installing new libraries via Composer or NPM unless explicitly asked.
    
*   **AVOID** use any PHP features newer than version 8.2.
    
*   **AVOID** propose over-engineered solutions (e.g., complex design patterns, factories, or DTOs for simple tasks). **ALWAYS** provide the simplest, "vanilla" solution that fits the current stack.
    
*   **AVOID** suggest refactoring when the request is to fix a bug.
    

* * *

### 3. Best Practices & Security

*   **Golden Security Rules:**
    
    1.  **Input Validation:** **ALWAYS** validate **ALL** user-provided data on the backend.
        
    2.  **Output Escaping:** **ALWAYS** escape all user-generated output rendered in HTML (use HTMLPurifier).
        
    3.  **Backend Authorization:** **ALWAYS** verify user permissions (role) on the backend before performing any sensitive action (e.g., save, delete, publish).
        
*   **Encoding:** All files and database connections **MUST** use `utf8mb4`.
    
*   **CSS:** Avoid global selectors (except for `body`, `h1`, etc.). Use BEM-like class naming conventions.
    

* * *

### 4. Environment Configuration

*   **XAMPP Deployment:** The code from the `frontend/` folder is deployed to **TWO** separate server directories:
    
    1.  `C:\xampp\htdocs\visual-editor-standalone\` (for the visual editor functionality)
        
    2.  `C:\xampp\htdocs\healthcare-cms-backend\public\` (for rendering the results of pages created/edited in the visual editor)
        
*   **Important:** When debugging CSS/JS issues in the editor, **ALWAYS** check **both** of these directories.
    

* * *

### 5. Architecture (Balanced Clean Architecture)

*   **We USE Clean Architecture (CA).** Continue placing code in the correct layers (Application, Infrastructure, Presentation). This is crucial for project stability.
    
*   **HOWEVER, maintain balance.** Avoid over-engineering. If a task is simple (e.g., "add 1 field"), do not create 5 new files. Carefully augment existing files.
    
*   **Priority:** Code simplicity, functionality

* * *

### 6. Git Merge & Conflict Resolution

**Source of Truth:**
*   **ALWAYS use locally verified code as the source of truth during merge operations.**
*   Local code has been tested on the local XAMPP server before commit and is guaranteed to be functional.
*   GitHub code may contain experimental changes or incomplete implementations and should NOT be trusted during conflicts.

**Merge Strategy:**
1.  **Create integration branch** from `main` when merging multiple feature branches.
2.  **Merge branches sequentially** to identify which branch causes conflicts.
3.  **Use `--no-commit --no-ff` flag** to preview changes before finalizing the merge.
4.  **Test locally** before committing the merge.

**Conflict Resolution Rules:**
1.  **Prefer local version (HEAD)** — it has been verified on the local server.
2.  **Choose simplicity over complexity** — avoid adding extra features during conflict resolution.
3.  **Maintain consistency** — use the same patterns as the rest of the codebase:
    - Positional parameters with default values (not named arguments) for consistency
    - Existing architecture patterns (no over-engineering)
    - Established naming conventions (snake_case for DB/API, camelCase for internal code)
4.  **Remove duplicate code** — conflicts often introduce duplicated properties/methods; clean them up.
5.  **Document decisions** in the merge commit message explaining why each choice was made.

**Example Conflict Resolution Pattern:**
```
If both branches add the same method:
- Choose the simpler, shorter version
- If quality differs, choose the better one but keep it simple
- AVOID: combining both versions with extra checks (method_exists, etc.)
- PREFER: single clean implementation that works

If both branches modify constructor parameters:
- Use positional parameters with defaults (project standard)
- AVOID: switching between positional and named arguments
- MAINTAIN: consistency with existing Entity classes
```

**After Merge:**
1.  Run full test suite locally to verify no regressions
2.  Deploy to local XAMPP to smoke test
3.  Force-push to GitHub only after local verification
4.  Use `--force-with-lease` instead of `--force` for safety

* * *

### 7. Deployment & Pre-rendered Cache (CRITICAL)

*   Some pages are "pre-rendered" and stored in the database in the `pages.rendered_html` column. When `rendered_html` is present for a published page, the PublicPageController will serve that HTML directly and bypass any runtime builders or PHP templates. This can make template/code changes invisible until the cache is cleared or the page is re-published.

*   Checklist when changes aren't visible after syncing files and clearing browser cache:
    1.  Open page source and look for the diagnostic comment at the top:
        - `<!-- SERVED=pre-rendered | length=XXXX | ts=TIMESTAMP -->` means DB cache is being served.
        - `<!-- SERVED=runtime | length=XXXX | ts=TIMESTAMP -->` means runtime builder/template was used.
    2.  If pre-rendered, either:
        - Clear `rendered_html` for the page (SQL UPDATE to set NULL) during development, or
        - Re-publish the page via admin UI so the system re-generates `rendered_html` from the current code.
    3.  Verify with a fresh request (Ctrl+Shift+R) and re-check the source comment.

*   Do NOT assume file sync alone is sufficient to make template changes visible for published pages. Always check `rendered_html` when debugging.

### 8. XAMPP Sync & Robocopy notes

*   The repository includes `sync-to-xampp.ps1` which uses `robocopy /MIR` to mirror files to XAMPP. Robocopy is reliable but can silently skip files in edge-cases (timestamp/ACL differences or when files appear unchanged).

*   Recommended practice when deploying critical PHP changes locally:
    1.  Run `sync-to-xampp.ps1` as usual.
    2.  If a change is not visible, **manually copy** the file to the XAMPP path with `Copy-Item -Force` (PowerShell) as a deterministic fallback.
    3.  After copying, restart Apache (or run `touch` on `apache`-visible files) and re-request the page.

*   Logging & Verification:
    - Keep a short checklist in your debugging notes: `1) sync-to-xampp ran`, `2) file exists in XAMPP path`, `3) page source checked for SERVED=runtime/pre-rendered`, `4) rendered_html cleared or republished`.

* * *

### 9. ES6 Modules & Global Scope Access (Discovered: November 2025)

**CRITICAL RULE:** When using ES6 modules (`<script type="module">`), code in that module has **isolated scope** — variables/classes are NOT automatically available as `window.ClassName`. This breaks legacy code expecting global access.

**Rule: Use Dual-Export Pattern for Compatibility**
```javascript
// GOOD: Works with both import AND window access
export default ClassName;
if (typeof window !== 'undefined') {
  window.ClassName = ClassName;  // Explicit export to global
}

// BAD: Only works with import (breaks when instantiating from non-module context)
export default ClassName;  // No window export!

// BAD: Only works with global (defeats purpose of modules)
window.ClassName = ClassName;  // No export!
```

**When to Apply:**
- ANY class/function that needs to be instantiated from non-module context (e.g., from HTML onclick handlers)
- ANY utility that legacy code expects as a global (e.g., window.InlineEditorManager)
- When gradually migrating from globals to ES6 modules

**Example (from this project):**
```html
<!-- HTML: Non-module context -->
<script type="module" src="editor.js"></script>
<button onclick="new window.InlineEditorManager(...)">Enable Editing</button>
```

```javascript
// InlineEditorManager.js
export default InlineEditorManager;
if (typeof window !== 'undefined') {
  window.InlineEditorManager = InlineEditorManager;  // Make accessible from HTML
}
```

* * *

### 10. Markdown & Contenteditable: Nested Block-Level Tags (Discovered: November 2025)

**CRITICAL RULE:** When rendering markdown inside a contenteditable element, **NEVER include block-level tags** in the sanitizer's ALLOWED_TAGS. This causes nested tags that break DOM structure.

**Root Cause:** Markdown renderer (marked.js) adds `<p>` tags. If ALLOWED_TAGS includes `<p>`, and the template wraps markdown in a `<p>`, you get:
```html
<!-- Original structure -->
<p data-inline-editable="true">[markdown output]</p>

<!-- After marked.js + DOMPurify with 'p' in ALLOWED_TAGS -->
<p data-inline-editable="true">
  <p>paragraph 1</p>
  <p>paragraph 2</p>
</p>

<!-- Browser auto-corrects by moving inner <p> outside (DOM CORRUPTED) -->
<p data-inline-editable="true"></p>
<p>paragraph 1</p>
<p>paragraph 2</p>
```

**Rule: Block-Level Tags to EXCLUDE from Contenteditable Markdown**
```javascript
// renderMarkdown() for contenteditable blocks
ALLOWED_TAGS: ['strong', 'em', 'u', 's', 'a', 'br', 'b', 'i']
// EXCLUDE: 'p', 'div', 'blockquote', 'li', 'ol', 'ul', 'h1-h6'
```

**Exceptions:** If you NEED block-level structure in contenteditable:
- Use `renderInlineMarkdown()` variant with `inline: true` flag
- Or render markdown OUTSIDE contenteditable wrapper and make only inline parts editable

**Audit Checklist:**
- [ ] Search all renderBlock functions for `renderMarkdown()`
- [ ] Check each one: does markdown output wrap in `<p>`? Check template
- [ ] Check renderMarkdown() ALLOWED_TAGS — remove block-level tags
- [ ] Test: edit a block → should be fully editable (no partial text)

* * *

### 11. Contenteditable & Event Listeners: Top-Level Elements Only (Discovered: November 2025)

**CRITICAL RULE:** The HTML contenteditable spec does NOT support nested contenteditable elements well. When querying for editable elements, **ALWAYS filter to top-level only**.

**Problem:** Using querySelectorAll('[contenteditable]') finds ALL elements, including nested ones. This causes:
- Event listener conflicts
- Mixed editability (some text editable, some not)
- Unexpected behavior when switching between blocks

**Rule: Filter to Top-Level Editables**
```javascript
// BAD: Gets ALL editable elements, including nested ones
const editables = this.preview.querySelectorAll('[data-inline-editable="true"]');
editables.forEach(el => {
  // Attach listeners to ALL, including nested ones — CONFLICTS!
});

// GOOD: Filter to only top-level
function _getTopLevelEditables() {
  const allEditables = this.preview.querySelectorAll('[data-inline-editable="true"]');
  const topLevel = [];
  
  allEditables.forEach(el => {
    const parentEditable = el.parentElement?.closest('[data-inline-editable="true"]');
    if (!parentEditable) {  // No parent editable = top-level
      topLevel.push(el);
    }
  });
  
  return topLevel;
}

const editables = _getTopLevelEditables();
editables.forEach(el => {
  // Attach listeners only to top-level — NO CONFLICTS!
});
```

**When Debugging Editability Issues:**
1. Open Inspector
2. Search for nested `[data-inline-editable]` elements
3. If nested found → apply this filter
4. Test: all text should now be editable

* * *

### 12. Vue Reactivity & Event Listeners: Refresh After DOM Updates (Discovered: November 2025)

**CRITICAL RULE:** When Vue re-renders components (after data changes, block edits, etc.), it creates **new DOM elements**. Event listeners attached to old elements are **lost**. This is not a Vue bug — it's how DOM works. When you need to maintain state across DOM updates, **explicitly refresh listeners**.

**Problem: Mode Stickiness**
```
User workflow:
1. Click "Enable Inline Editing" → listeners attached to DOM elements
2. Edit block 1 → Vue re-renders block (creates NEW DOM element)
3. Listeners on OLD element lost
4. Try to edit block 2 → mode variable says "enabled" but no listeners
5. User clicks "Enable" again (shouldn't need to!)
```

**Rule: Refresh Listeners After Known DOM Changes**
```javascript
// In Vue component
async updateBlockField(blockId, fieldPath, newValue) {
  // Update Vue model
  this.updateNestedProperty(this.blocks[blockId], fieldPath, newValue);
  
  // Vue will re-render on next cycle
  await this.$nextTick();
  
  // RE-ATTACH listeners to NEW elements
  if (this._inlineManager && this._inlineModeEnabled) {
    this._inlineManager.refreshEditableElements();
  }
}

// InlineEditorManager.refreshEditableElements()
refreshEditableElements() {
  if (!this.isInlineMode) return;
  
  const editables = this._getTopLevelEditables();
  editables.forEach(el => {
    // Remove old listeners (if any)
    el.removeEventListener('mouseenter', this._onMouseEnter);
    el.removeEventListener('mouseleave', this._onMouseLeave);
    el.removeEventListener('click', this._onClickElement);
    
    // Attach listeners to NEW elements
    el.addEventListener('mouseenter', this._onMouseEnter);
    el.addEventListener('mouseleave', this._onMouseLeave);
    el.addEventListener('click', this._onClickElement);
    el.classList.add('inline-editable-ready');
  });
}
```

**When to Call refreshEditableElements():**
- After Vue model updates (in `$nextTick`)
- After API calls that load/update page structure
- After block deletion/insertion
- After mode toggle

**Performance:** Typically ~2ms per refresh — acceptable even on frequent updates.

**Testing:**
```
1. Enable inline editing
2. Edit text in block 1
3. Edit text in block 2 (without clicking Enable again)
4. Edit text in block 3 (without clicking Enable again)
✓ PASS: Mode stays active across multiple blocks
```

* * *

### 13. Vue Model Synchronization: Use Callbacks for Non-Reactive Updates (Discovered: November 2025)

**RULE:** When external code (non-Vue) modifies data that Vue should track, **use callbacks to sync the Vue model**. Do NOT modify the DOM and expect Vue to notice.

**Problem:**
```javascript
// InlineEditorManager saves to API, DOM is updated
// But Vue model is still stale
// Result: Save succeeds, but UI shows old data
```

**Rule: Callback Pattern for Sync**
```javascript
// In Vue component: Pass callback when creating external manager
this._inlineManager = new window.InlineEditorManager(
  previewEl,
  pageId,
  this.updateBlockField.bind(this)  // ← Callback to sync Vue
);

// In InlineEditorManager: After API save, call callback
async saveFieldToAPI(blockId, fieldPath, markdown) {
  const response = await fetch(`/api/blocks/${blockId}`, {
    method: 'PATCH',
    body: JSON.stringify({ [fieldPath]: markdown })
  });
  
  // API save succeeded — now sync Vue model
  if (response.ok && this.updateCallback) {
    this.updateCallback(blockId, fieldPath, markdown);
  }
}

// In Vue component: updateBlockField updates model AND refreshes listeners
updateBlockField(blockId, fieldPath, newValue) {
  // Support nested properties (e.g., "data.title")
  this.updateNestedProperty(this.blocks[blockId], fieldPath, newValue);
  
  // Refresh DOM listeners
  await this.$nextTick();
  if (this._inlineManager && this._inlineModeEnabled) {
    this._inlineManager.refreshEditableElements();
  }
}
```

**Key Points:**
- Callback must use `.bind(this)` to preserve `this` context
- Call callback AFTER API operation succeeds (not before)
- Callback should update model AND handle side effects (listener refresh)
- Do NOT mix Vue reactivity with direct DOM manipulation

* * *

### 14. Block Rendering Rules: Audit All render*Block() Functions (Discovered: November 2025)

**RULE:** Whenever adding inline editing support to a block type, **audit the render function** for potential issues:

1. **Nested tags in markdown** — Does it call renderMarkdown? Check if that function is sanitizing correctly
2. **Contenteditable nesting** — Does the block have multiple [data-inline-editable] elements? Are they top-level?
3. **Event listener attachment** — After edits that cause Vue re-render, are listeners being refreshed?

**Audit Checklist for Each Block Type:**
```javascript
// renderBlockType() audit steps:
1. Find renderMarkdown() calls
   - If found: Ensure renderMarkdown() excludes block-level tags
   - Alternative: Use renderInlineMarkdown(html, { inline: true })

2. Find [contenteditable] or [data-inline-editable] elements
   - If multiple: Use _getTopLevelEditables() filter

3. Check if field is nested (e.g., "data.title")
   - If nested: updateBlockField() must support nested property paths

4. Test manually:
   - Enable inline editing
   - Edit every field in the block
   - Save (Ctrl+S) and refresh (F5)
   - Verify formatting persists and all fields are editable
```

**Block Types to Verify (As of Nov 2025):**
- main-screen ✅
- page-header ✅
- service-cards ✅
- article-cards ✅
- about-section ✅
- text-block ✅ (has renderMarkdown)
- image-block ✅
- blockquote ✅
- button ✅
- section-title ✅
- chat-bot ✅
- spacer ✅

* * *
