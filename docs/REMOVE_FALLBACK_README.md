REMOVE API-FALLBACK from Playwright E2E test

Purpose
- This document explains the safe patch to remove the API-based fallback from `frontend/e2e/tests/editor.spec.js` and replace it with a stricter check that the public URL responds with 200 and contains the expected content.

When to apply
- Only apply this patch after you have confirmed that the local Apache rewrite configuration is fully working and that the visitor GET to the public URL returns HTTP 200 consistently.

What the patch does
- Removes the branch that, on visitor 404, would check the published page via the API (GET `/api/pages/:id`).
- Instead, it polls the public URL for up to 15 seconds and fails if it doesn't return 200 and expected content.
- Keeps programmatic login, API create/publish, and polling the page resource to observe `status: published` before visiting.

Why this is safer
- Prevents tests from passing silently when the public route is broken but the API shows the page as published.
- Ensures true end-to-end verification using the same route a real visitor would use.

Edge-cases & anti-patterns to watch for
1. Timing / eventual consistency
   - After publishing, the public URL may take a moment to be available (caching, indexing, or write-through delays). The patch includes a 15s polling loop for the public URL; if your environment needs more time, increase this to 30s.

2. PATH / subpath handling
   - Ensure `BASE_URL` includes the deployment subpath (for this project: `http://localhost/healthcare-cms-backend/public`) and that the test constructs public URL using that base.

3. Token / Auth
   - Test uses programmatic login and injects token into localStorage. If auth schema changes, update token injection logic.

4. Content matching
   - The test checks for specific strings (hero heading, hero subtitle, test content). If templates or sanitization change, update the expected strings accordingly.

5. .htaccess / RewriteBase
   - Make sure `.htaccess` exists in `backend/public` and has correct `RewriteBase` for local subpath, or that `AllowOverride All` and `mod_rewrite` are enabled in Apache.

Verification steps after applying the patch
1. Run the provided PowerShell script to enable rewrite and restart Apache (if you haven't already):
   - `.	ools\enable-rewrite-and-restart-apache.ps1` (or the `scripts` copy in this repo)
2. From project `frontend` run the specific test:
   - `npx playwright test e2e/tests/editor.spec.no-fallback.js -c e2e/playwright.config.js --project=chromium -g "Page Editor Workflow"`
3. If the test fails because the visitor GET didn't return 200 within timeout, inspect Apache error.log, request-debug.log, and ensure `.htaccess` rules are correct.

Rollback
- If you want to keep the fallback behavior, simply keep `frontend/e2e/tests/editor.spec.js` unchanged and do not replace it with the no-fallback variant.

Notes
- I created `frontend/e2e/tests/editor.spec.no-fallback.js` as a review copy. When you confirm visitor GET returns 200 reliably, I can apply a small patch to replace the original file with the no-fallback version.
