### 1\. Tech Stack

*   **Backend:** PHP 8.2 (no frameworks).
    
*   **Frontend:** "Vanilla" Vue.js 3 (loaded via CDN, no `node_modules`).
    
*   **Server:** XAMPP with Apache and MySQL.
    
*   **Styling:** "Vanilla" CSS (no pre-processors).
    

* * *

### 2\. Constraints (CRITICAL)

*   **AVOID** suggest installing new libraries via Composer or NPM unless explicitly asked.
    
*   **AVOID** use any PHP features newer than version 8.2.
    
*   **AVOID** propose over-engineered solutions (e.g., complex design patterns, factories, or DTOs for simple tasks). **ALWAYS** provide the simplest, "vanilla" solution that fits the current stack.
    
*   **AVOID** suggest refactoring when the request is to fix a bug.
    

* * *

### 3\. Best Practices & Security

*   **Golden Security Rules:**
    
    1.  **Input Validation:** **ALWAYS** validate **ALL** user-provided data on the backend.
        
    2.  **Output Escaping:** **ALWAYS** escape all user-generated output rendered in HTML (use HTMLPurifier).
        
    3.  **Backend Authorization:** **ALWAYS** verify user permissions (role) on the backend before performing any sensitive action (e.g., save, delete, publish).
        
*   **Encoding:** All files and database connections **MUST** use `utf8mb4`.
    
*   **CSS:** Avoid global selectors (except for `body`, `h1`, etc.). Use BEM-like class naming conventions.
    

* * *

### 4\. Environment Configuration

*   **XAMPP Deployment:** The code from the `frontend/` folder is deployed to **TWO** separate server directories:
    
    1.  `C:\xampp\htdocs\visual-editor-standalone\` (for the visual editor functionality)
        
    2.  `C:\xampp\htdocs\healthcare-cms-backend\public\` (for rendering the results of pages created/edited in the visual editor)
        
*   **Important:** When debugging CSS/JS issues in the editor, **ALWAYS** check **both** of these directories.
    

* * *

### 5\. Architecture (Balanced Clean Architecture)

*   **We USE Clean Architecture (CA).** Continue placing code in the correct layers (Application, Infrastructure, Presentation). This is crucial for project stability.
    
*   **HOWEVER, maintain balance.** Avoid over-engineering. If a task is simple (e.g., "add 1 field"), do not create 5 new files. Carefully augment existing files.
    
*   **Priority:** Code simplicity, functionality

* * *

### 6\. Git Merge & Conflict Resolution

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