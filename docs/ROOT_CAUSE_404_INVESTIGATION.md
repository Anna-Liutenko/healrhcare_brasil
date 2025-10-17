# Root Cause Investigation: Public Page 404 Error
**Date**: 2025-10-09  
**Problem**: Public visitor URLs like `/p/slug` return Apache 404 instead of routing to PHP backend

## Problem Statement
When a public (non-authenticated) user requests `http://localhost/healthcare-cms-backend/public/p/<slug>`, Apache returns a generic 404 HTML page ("404 - Страница не найдена — Healthcare Hacks Brazil") instead of routing the request to `backend/public/index.php` which would render the page via `PublicPageController::show()`.

**Critical Observation**: API endpoints like `/api/pages/:id/publish` work correctly, indicating PHP routing is functional for API requests but broken for public page routes.

## Evidence Collected

### What Works ✅
1. **API create/publish**: POST `/api/pages` returns 201 + page_id; PUT `/api/pages/:id/publish` returns success
2. **API GET page status**: GET `/api/pages/:id` returns `{"page": {"status": "published", ...}}`
3. **Backend logs confirm publish**: `api-responses.log` shows successful publish operations
4. **Apache config test**: `httpd -t` returns `Syntax OK` (reported by user)
5. **`.htaccess` exists**: File `backend/public/.htaccess` contains proper rewrite rules (mod_rewrite + fallback to index.php)

### What Fails ❌
1. **Public visitor GET**: `http://localhost/healthcare-cms-backend/public/p/e2e-playwright-test-slug` returns Apache 404 HTML
2. **Server-side fetch in test**: Playwright test's `fetch(publicUrl)` returns 404 status and Apache HTML body (not PHP-rendered)
3. **PowerShell Invoke-WebRequest**: Manual test returned Apache 404 HTML page

## Root Cause Hypotheses (Ordered by Likelihood)

### Hypothesis 1: `.htaccess` Not Being Applied (mod_rewrite Disabled or AllowOverride None) 🔴 **MOST LIKELY**
**Evidence**:
- User ran `httpd -k restart` and got error: `AH00436: No installed service named "Apache2.4"` — this indicates XAMPP Apache is NOT running as a Windows service, which means:
  - Apache may not be running at all after the restart attempt failed
  - Or Apache is running standalone (via XAMPP Control Panel) but config changes were never applied because the service restart command didn't work

**Why this causes 404**:
- If `AllowOverride None` is set in `httpd.conf` for the `C:/xampp/htdocs` directory, Apache ignores ALL `.htaccess` files including rewrite rules
- Without rewrite rules, requests to `/p/slug` are treated as literal filesystem paths
- Apache looks for `C:\xampp\htdocs\healthcare-cms-backend\public\p\e2e-playwright-test-slug` file/directory
- File doesn't exist → Apache returns its default 404 page

**How to verify**:
```powershell
# Check if mod_rewrite is loaded
& 'C:\xampp\apache\bin\httpd.exe' -M | Select-String -Pattern 'rewrite_module'
# Expected: "rewrite_module (shared)" — if missing, mod_rewrite is disabled

# Check AllowOverride setting for htdocs
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern 'AllowOverride' -Context 2,2
# Expected: AllowOverride All inside <Directory "C:/xampp/htdocs"> block
```

**Fix**:
1. Edit `C:\xampp\apache\conf\httpd.conf`:
   - Uncomment line: `LoadModule rewrite_module modules/mod_rewrite.so`
   - Find `<Directory "C:/xampp/htdocs">` block and change `AllowOverride None` → `AllowOverride All`
2. Restart Apache via XAMPP Control Panel (stop → start buttons)
3. Verify with `httpd -M` and re-test public URL

---

### Hypothesis 2: Apache DocumentRoot or Alias Not Pointing to Project ⚠️ **POSSIBLE**
**Evidence**:
- User's URL pattern: `http://localhost/healthcare-cms-backend/public/`
- This suggests Apache serves from `C:\xampp\htdocs\` and the project is in a subdirectory `healthcare-cms-backend\public`

**Why this causes 404**:
- If Apache's DocumentRoot is `C:\xampp\htdocs`, requests go to htdocs first
- Request `/healthcare-cms-backend/public/p/slug` looks for file `C:\xampp\htdocs\healthcare-cms-backend\public\p\slug`
- `.htaccess` in `backend/public/` should rewrite → but only if AllowOverride is enabled AND the directory path matches

**Potential issue**: If `.htaccess` is at `backend/public/.htaccess` but Apache is looking in `C:\xampp\htdocs\healthcare-cms-backend\public`, the paths must match exactly

**How to verify**:
```powershell
# Check DocumentRoot
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern 'DocumentRoot'

# Check if project is symlinked or physically copied to htdocs
Test-Path 'C:\xampp\htdocs\healthcare-cms-backend'
# Expected: True (directory exists)

# Check .htaccess presence at the exact location Apache sees
Test-Path 'C:\xampp\htdocs\healthcare-cms-backend\public\.htaccess'
# Expected: True
```

**Fix if mismatch**:
- Ensure project is either:
  - **Option A**: Symlinked: `C:\xampp\htdocs\healthcare-cms-backend` → workspace `backend/`
  - **Option B**: Physically copied to `C:\xampp\htdocs\healthcare-cms-backend`
- Ensure `.htaccess` is present at `C:\xampp\htdocs\healthcare-cms-backend\public\.htaccess`

---

### Hypothesis 3: `.htaccess` RewriteBase Incorrect ⚠️ **POSSIBLE**
**Evidence**:
- Current `.htaccess` does NOT have a `RewriteBase` directive
- Default behavior: Apache uses the directory where `.htaccess` lives as the base

**Why this might cause 404**:
- If Apache's interpretation of the request path doesn't match the RewriteRule pattern `^(.*)$`, the rule won't match
- Example: request `/healthcare-cms-backend/public/p/slug` but `.htaccess` only sees `/p/slug` relative to the directory

**How to verify**:
- Enable Apache rewrite log (if available) or check `access.log` to see what path Apache receives

**Fix**:
Add `RewriteBase` to `.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /healthcare-cms-backend/public
    
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

---

### Hypothesis 4: Apache Not Running or Wrong Port 🟡 **UNLIKELY BUT CHECK**
**Evidence**:
- User got service error when trying `httpd -k restart`
- This could mean Apache isn't running at all

**How to verify**:
```powershell
# Check if httpd.exe processes are running
Get-Process -Name httpd -ErrorAction SilentlyContinue
# Expected: 1-2 httpd.exe processes (parent + worker)

# Test if Apache responds at all
Invoke-WebRequest -Uri 'http://localhost/' -UseBasicParsing
# Expected: XAMPP dashboard page or similar
```

**Fix**:
- Open XAMPP Control Panel
- Stop Apache (if running)
- Start Apache
- Verify green "Running" indicator

---

### Hypothesis 5: PHP Routing in index.php Broken for /p/ Pattern 🟢 **UNLIKELY**
**Evidence**:
- API routes work → router is functional
- `backend/public/index.php` has been updated to handle `/p/{slug}` pattern

**Why unlikely**:
- If request reached PHP, we'd see logs in `e2e-publicpage.log` or `request-debug.log`
- User's test showed Apache 404 HTML, not PHP 404 JSON/HTML

**How to verify**:
- Tail logs during a public GET request:
```powershell
Get-Content 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend\logs\request-debug.log' -Tail 10 -Wait
```
- Make request: `Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/public/p/test'`
- Expected: Log entry appears if PHP router received request
- Actual (current): No log entry → request never reached PHP

---

## Diagnostic Plan (Execute in Order)

### Step 1: Verify Apache is Running
```powershell
Get-Process -Name httpd -ErrorAction SilentlyContinue
```
- **If no processes**: Start Apache via XAMPP Control Panel
- **If processes exist**: Proceed to Step 2

### Step 2: Verify mod_rewrite is Loaded
```powershell
& 'C:\xampp\apache\bin\httpd.exe' -M 2>&1 | Select-String -Pattern 'rewrite_module'
```
- **Expected**: `rewrite_module (shared)`
- **If missing**: Uncomment `LoadModule rewrite_module modules/mod_rewrite.so` in `httpd.conf`, restart Apache

### Step 3: Verify AllowOverride Setting
```powershell
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern 'AllowOverride' -Context 3,3
```
- **Expected**: `AllowOverride All` inside `<Directory "C:/xampp/htdocs">` block
- **If `AllowOverride None`**: Change to `All`, restart Apache

### Step 4: Verify Project Path and .htaccess
```powershell
Test-Path 'C:\xampp\htdocs\healthcare-cms-backend\public\.htaccess'
Get-Content 'C:\xampp\htdocs\healthcare-cms-backend\public\.htaccess'
```
- **Expected**: File exists and contains `RewriteEngine On` and `RewriteRule ^(.*)$ index.php`
- **If missing or wrong**: Copy/fix `.htaccess` file

### Step 5: Test with Direct index.php Request
```powershell
Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/public/index.php?path=/p/test-slug' -UseBasicParsing
```
- **Expected**: PHP responds (200 or 404 rendered by PHP, not Apache)
- **If works**: Problem is rewrite config; if fails: PHP/Apache integration broken

### Step 6: Enable Apache Rewrite Logging (Advanced)
Edit `httpd.conf` and add:
```apache
LogLevel alert rewrite:trace3
```
Restart Apache and check `error.log` for rewrite trace during requests.

### Step 7: Re-run PowerShell Helper Script
```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
.\scripts\enable-rewrite-and-restart-apache.ps1 2>&1 | Tee-Object -FilePath .\enable_rewrite_output.txt
```
- Script will apply all fixes automatically
- Review output for errors

---

## Next Actions

1. **Run the fixed PowerShell script** (it now has clean syntax, no smart quotes, proper encoding)
2. **Manually restart Apache via XAMPP Control Panel** (not via `httpd -k restart` since service is not installed)
3. **Verify with diagnostic commands above**
4. **Share outputs**: `enable_rewrite_output.txt`, `httpd -M` output, `temp_public_test.html` content

## Anti-Patterns to Avoid

1. ❌ **Don't assume `httpd -k restart` works** if XAMPP Apache is not installed as a service
   - ✅ Use XAMPP Control Panel stop/start buttons instead

2. ❌ **Don't edit files with smart quotes or UTF-8 BOM**
   - ✅ Use plain ASCII quotes `'` and UTF-8 without BOM encoding

3. ❌ **Don't skip verification steps** (assume config applied when it didn't)
   - ✅ Always check `httpd -M`, `AllowOverride` value, and test with curl/Invoke-WebRequest after changes

4. ❌ **Don't trust frontend/test-only diagnostics**
   - ✅ Test server-side first: direct curl/PowerShell GET to public URL before running Playwright

5. ❌ **Don't mix development paths** (workspace vs htdocs)
   - ✅ Ensure project is properly synced/symlinked to `C:\xampp\htdocs\healthcare-cms-backend`

---

## Expected Final State

After applying fixes:
1. `httpd -M` shows `rewrite_module (shared)`
2. `httpd.conf` has `AllowOverride All` for htdocs
3. Apache running (XAMPP Control Panel shows green "Running")
4. `Invoke-WebRequest 'http://localhost/healthcare-cms-backend/public/p/test-slug'` returns:
   - **200 OK** with PHP-rendered HTML (page content or PHP 404 message)
   - NOT Apache's default 404 HTML
5. Playwright E2E test passes: creates page, publishes, navigates to public URL, asserts content visible
