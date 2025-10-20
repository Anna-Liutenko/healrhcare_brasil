PowerShell deploy & smoke-test checklist
=====================================

This file contains step-by-step commands to deploy the Phase 1 XSS fixes to your local XAMPP webroot and run the smoke tests (Playwright) on Windows PowerShell.

1) Sync files to XAMPP (uses project script)

From repository root:

```powershell
# run the project's sync script which copies changed files to your XAMPP htdocs
powershell -NoProfile -ExecutionPolicy Bypass -File sync-to-xampp.ps1
```

2) Restart Apache (XAMPP) if needed

If you use XAMPP Control Panel: stop/start Apache there.
Or from PowerShell (run as Admin):

```powershell
net stop Apache2.4; Start-Sleep -Seconds 2; net start Apache2.4
```

3) Verify public page headers (quick curl)

```powershell
Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/p/your-page-slug' -Method GET -Headers @{} -UseBasicParsing | Select-Object -ExpandProperty Headers
```

4) Install e2e deps and Playwright browsers

```powershell
cd frontend/e2e
npm install
npx playwright install
```

5) Run the XSS smoke test only

```powershell
cd frontend/e2e
npx playwright test tests/xss-check.spec.js --project=chromium --reporter=list
```

6) Run full suite (optional)

```powershell
cd frontend/e2e
npx playwright test --project=chromium
```

7) Check server security log

```powershell
Get-Content -Path .\backend\logs\security-alerts.log -Tail 200
```

Notes
- If Playwright tests fail due to API response shape (page_id vs pageId), make tests tolerant — we've already added robustness to the XSS test.
- Ensure PHP process user has write permissions to `backend\logs` so detection entries can be created.
