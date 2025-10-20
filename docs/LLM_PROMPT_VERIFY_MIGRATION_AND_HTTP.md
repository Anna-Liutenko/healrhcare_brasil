# LLM Prompt: Verify MySQL Migration and Test Public Pages

## Task Overview
After deploying the `client_id` migration and updated code to XAMPP, verify that:
1. The database migration was applied successfully (column and index exist)
2. The public pages are accessible and rendering correctly via HTTP

## Context
- **Migration file**: `database/migrations/2025_10_16_add_client_id_to_blocks.sql`
- **What it does**: Adds `client_id VARCHAR(255) NULL` column to `blocks` table and creates index `idx_blocks_client_id`
- **XAMPP MySQL**: Localhost on default port 3306, user `root`, empty password (default XAMPP setup)
- **XAMPP htdocs**: `C:\xampp\htdocs\healthcare-cms-backend` (backend), `C:\xampp\htdocs\visual-editor-standalone` (frontend)
- **Database name**: `healthcare_cms`

## Prerequisites
- XAMPP is running (Apache + MySQL services started)
- Migration SQL file exists in repository at `database/migrations/2025_10_16_add_client_id_to_blocks.sql`
- Code has been synced to XAMPP htdocs (via `sync.bat` or similar)

---

## Part 1: Verify MySQL Migration

### Goal
Confirm that the `client_id` column and index were successfully added to the `blocks` table in the `healthcare_cms` database.

### Steps

#### Step 1: Locate MySQL Client
The XAMPP MySQL client is located at:
```
C:\xampp\mysql\bin\mysql.exe
```

#### Step 2: Connect to MySQL and Check Column
Run the following PowerShell command to check if the `client_id` column exists:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "USE healthcare_cms; SHOW COLUMNS FROM blocks LIKE 'client_id';"
```

**Expected output** (if migration applied successfully):
```
+------------+--------------+------+-----+---------+-------+
| Field      | Type         | Null | Key | Default | Extra |
+------------+--------------+------+-----+---------+-------+
| client_id  | varchar(255) | YES  | MUL | NULL    |       |
+------------+--------------+------+-----+---------+-------+
```

**Key validation points:**
- `Field` = `client_id`
- `Type` = `varchar(255)`
- `Null` = `YES` (nullable)
- `Key` = `MUL` (indicates index exists)
- `Default` = `NULL`

#### Step 3: Check Index
Run the following PowerShell command to verify the index was created:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "USE healthcare_cms; SHOW INDEX FROM blocks WHERE Key_name='idx_blocks_client_id';"
```

**Expected output** (if index created successfully):
```
+--------+------------+----------------------+--------------+-------------+-----------+-------------+----------+--------+------+------------+---------+---------------+
| Table  | Non_unique | Key_name             | Seq_in_index | Column_name | Collation | Cardinality | Sub_part | Packed | Null | Index_type | Comment | Index_comment |
+--------+------------+----------------------+--------------+-------------+-----------+-------------+----------+--------+------+------------+---------+---------------+
| blocks |          1 | idx_blocks_client_id |            1 | client_id   | A         |           0 |     NULL | NULL   | YES  | BTREE      |         |               |
+--------+------------+----------------------+--------------+-------------+-----------+-------------+----------+--------+------+------------+---------+---------------+
```

**Key validation points:**
- `Table` = `blocks`
- `Key_name` = `idx_blocks_client_id`
- `Column_name` = `client_id`
- `Index_type` = `BTREE`

#### Step 4: If Migration Not Applied
If the column or index is missing, apply the migration:

```powershell
Get-Content "database\migrations\2025_10_16_add_client_id_to_blocks.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root healthcare_cms
```

Then re-run Steps 2 and 3 to verify.

### Troubleshooting
- **Error "Access denied for user 'root'"**: Add `-p` flag and enter password when prompted (default XAMPP has empty password, so omit `-p`)
- **Error "Unknown database 'healthcare_cms'"**: Database doesn't exist; check database name or restore from backup
- **Column exists but no index**: Run only the index creation part of the migration SQL manually

---

## Part 2: Test Public Pages via HTTP

### Goal
Verify that public pages are accessible via HTTP and rendering correctly after the code deployment.

### Prerequisites
- XAMPP Apache is running (check via XAMPP Control Panel or `http://localhost`)
- Backend is deployed to `C:\xampp\htdocs\healthcare-cms-backend`
- Frontend is deployed to `C:\xampp\htdocs\visual-editor-standalone`

### Test Scenarios

#### Test 1: Backend Health Check
Check if the backend API is responding:

```powershell
Invoke-WebRequest -Uri "http://localhost/healthcare-cms-backend/public/index.php" -Method GET -UseBasicParsing | Select-Object StatusCode, StatusDescription
```

**Expected output:**
```
StatusCode StatusDescription
---------- -----------------
       200 OK
```

**Alternative using curl** (if available):
```powershell
curl -I http://localhost/healthcare-cms-backend/public/index.php
```

#### Test 2: Fetch Public Pages List
Test the public pages API endpoint:

```powershell
$response = Invoke-RestMethod -Uri "http://localhost/healthcare-cms-backend/public/index.php?action=public_pages" -Method GET
$response | ConvertTo-Json -Depth 3
```

**Expected output:**
- JSON array with published pages
- Each page should have: `id`, `slug`, `title`, `page_type`, `status`, etc.
- `status` should be `published` for public pages

**Validation:**
- Response is valid JSON
- At least one page exists
- Pages have required fields

#### Test 3: Fetch a Specific Public Page by Slug
Choose a known published page slug (e.g., `guides`, `home`, or check from Test 2 output) and fetch it:

```powershell
$slug = "guides"  # Replace with actual slug from your database
$response = Invoke-RestMethod -Uri "http://localhost/healthcare-cms-backend/public/index.php?action=public_page&slug=$slug" -Method GET
$response | ConvertTo-Json -Depth 5
```

**Expected output:**
- JSON object with page data
- `page` object containing: `id`, `title`, `slug`, `page_type`, `status`, `rendered_html`
- `blocks` array (if applicable) with block content
- `rendered_html` field should contain sanitized HTML (no debug logs, safe attributes preserved)

**Validation checks:**
- `status` = `published`
- `rendered_html` is not empty
- HTML is properly sanitized (no `javascript:` or `data:` in href/src attributes)
- Allowed attributes like `target="_blank"` are preserved
- No PHP errors or warnings in response

#### Test 4: Visual Check in Browser
Open the following URLs in a browser:

1. **Backend API root:**
   ```
   http://localhost/healthcare-cms-backend/public/
   ```

2. **Public page (direct):**
   ```
   http://localhost/healthcare-cms-backend/public/index.php?action=public_page&slug=guides
   ```
   *(Replace `guides` with actual slug)*

3. **Frontend visual editor:**
   ```
   http://localhost/visual-editor-standalone/
   ```

**What to check:**
- No PHP errors/warnings displayed
- Pages load without 404/500 errors
- HTML content renders correctly (images, links, formatting)
- No JavaScript console errors (open browser DevTools → Console)

#### Test 5: Check Sanitizer Behavior (Optional Deep Test)
Create a test request to verify HTMLSanitizer is working correctly after changes:

**Manual test via database:**
1. Insert a test page/block with potentially unsafe HTML:
   ```sql
   -- (Run via mysql.exe or phpMyAdmin)
   UPDATE blocks SET content = '<p>Test <a href="javascript:alert(1)">click</a> and <a href="https://example.com" target="_blank">safe link</a></p>' WHERE id = 1;
   ```

2. Fetch the page via API (Test 3 command) and check `rendered_html`:
   - `javascript:` href should be neutralized (replaced with `#`)
   - `target="_blank"` should be preserved
   - No debug log files created in temp directory

**Cleanup after test:**
```sql
UPDATE blocks SET content = '<original content>' WHERE id = 1;
```

---

## Expected Results Summary

### Part 1: Migration Verification
✅ Column `client_id` exists in `blocks` table  
✅ Column type is `VARCHAR(255)` and nullable  
✅ Index `idx_blocks_client_id` exists  
✅ Index is of type BTREE  

### Part 2: HTTP Testing
✅ Backend API responds with HTTP 200  
✅ Public pages list endpoint returns valid JSON  
✅ Individual public pages return valid JSON with `rendered_html`  
✅ HTML is sanitized (no unsafe schemes, allowed attributes preserved)  
✅ No PHP errors/warnings in responses  
✅ Pages render correctly in browser  

---

## Troubleshooting Common Issues

### Issue: "Connection refused" or "Unable to connect"
**Cause:** Apache or MySQL not running  
**Solution:** Start services via XAMPP Control Panel

### Issue: HTTP 404 on backend endpoints
**Cause:** .htaccess not working or mod_rewrite disabled  
**Solution:** 
```powershell
# Check if mod_rewrite is enabled in httpd.conf
Get-Content "C:\xampp\apache\conf\httpd.conf" | Select-String "LoadModule rewrite_module"
```
Should show uncommented line. If commented, uncomment and restart Apache.

### Issue: HTTP 500 or PHP errors in response
**Cause:** PHP syntax error, missing dependencies, or database connection issue  
**Solution:**
1. Check Apache error log:
   ```powershell
   Get-Content "C:\xampp\apache\logs\error.log" -Tail 50
   ```
2. Check PHP error log (if configured)
3. Verify database credentials in backend `.env` or config files

### Issue: Empty or missing `rendered_html` in API response
**Cause:** Page not published, or render logic failing  
**Solution:**
1. Check page status in database:
   ```sql
   SELECT id, title, slug, status FROM pages WHERE slug='guides';
   ```
2. Ensure status is `published`
3. Check if `rendered_html` column has content

### Issue: Migration shows column exists but queries fail
**Cause:** MySQL cache or stale connection  
**Solution:** Restart MySQL service via XAMPP Control Panel

---

## Automation Script (Optional)

You can run all checks automatically with this PowerShell script:

```powershell
# verify-migration-and-http.ps1

Write-Host "=== Part 1: MySQL Migration Verification ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "Checking client_id column..." -ForegroundColor Yellow
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "USE healthcare_cms; SHOW COLUMNS FROM blocks LIKE 'client_id';"

Write-Host ""
Write-Host "Checking idx_blocks_client_id index..." -ForegroundColor Yellow
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "USE healthcare_cms; SHOW INDEX FROM blocks WHERE Key_name='idx_blocks_client_id';"

Write-Host ""
Write-Host "=== Part 2: HTTP Testing ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "Test 1: Backend health check..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost/healthcare-cms-backend/public/index.php" -Method GET -UseBasicParsing
    Write-Host "✓ Status: $($response.StatusCode) $($response.StatusDescription)" -ForegroundColor Green
} catch {
    Write-Host "✗ Failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Test 2: Fetch public pages list..." -ForegroundColor Yellow
try {
    $pages = Invoke-RestMethod -Uri "http://localhost/healthcare-cms-backend/public/index.php?action=public_pages" -Method GET
    Write-Host "✓ Retrieved $($pages.Count) public pages" -ForegroundColor Green
    $pages | Select-Object -First 3 | Format-Table id, slug, title, status
} catch {
    Write-Host "✗ Failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Test 3: Fetch specific public page (slug=guides)..." -ForegroundColor Yellow
try {
    $page = Invoke-RestMethod -Uri "http://localhost/healthcare-cms-backend/public/index.php?action=public_page&slug=guides" -Method GET
    Write-Host "✓ Page fetched: $($page.page.title)" -ForegroundColor Green
    Write-Host "  - Status: $($page.page.status)"
    Write-Host "  - Rendered HTML length: $($page.page.rendered_html.Length) characters"
    Write-Host "  - Blocks count: $($page.blocks.Count)"
} catch {
    Write-Host "✗ Failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== Summary ===" -ForegroundColor Cyan
Write-Host "✓ If all tests passed, deployment is successful!" -ForegroundColor Green
Write-Host "✓ Migration verified and HTTP endpoints are working." -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  - Open browser and visually check pages"
Write-Host "  - Run PHPUnit tests if not already done"
Write-Host "  - Consider opening a PR when ready"
```

Save as `scripts\verify-migration-and-http.ps1` and run:
```powershell
.\scripts\verify-migration-and-http.ps1
```

---

## Success Criteria

When all checks pass:
- ✅ Migration is confirmed in database
- ✅ Backend API responds correctly
- ✅ Public pages return valid JSON with sanitized HTML
- ✅ No errors in Apache logs
- ✅ Browser rendering works as expected

You can then proceed to:
1. Run full PHPUnit test suite (if not done)
2. Open a Pull Request with migration and code changes
3. Deploy to staging/production (after code review)

---

## Notes

- This prompt assumes Windows/PowerShell environment with XAMPP
- Adjust paths if XAMPP is installed in a non-default location
- For production deployments, use proper database credentials (not root with empty password)
- Always backup the database before applying migrations in production
- Test on staging environment before production deployment
