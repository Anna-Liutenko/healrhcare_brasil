# 🚀 Quick Start - Developer Cheat Sheet

## Before Starting Work Session

### 1. Start XAMPP Services
```
- Open XAMPP Control Panel
- Start Apache ✅
- Start MySQL ✅
```

### 2. Verify Services Running
```powershell
# Check MySQL
Get-Service *mysql*

# Check Apache
Get-Process -Name httpd -ErrorAction SilentlyContinue
```

---

## Making Code Changes

### ⚠️ CRITICAL: Always Sync to XAMPP After Changes

#### Backend Changes
```powershell
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0 /NFL /NDL
```

#### Frontend Changes
```powershell
robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /NFL /NDL
```

#### Full Sync (when unsure)
```powershell
# Backend
robocopy "backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /R:0 /W:0 /XD node_modules .git

# Frontend  
robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /XD node_modules .git
```

---

## Testing Your Changes

### 1. Verify Files Synced
```powershell
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\[YOUR_FILE_PATH]"
```

### 2. Check Autoloader Works
Open: `http://localhost/healthcare-cms-backend/public/test-autoload.php`

Expected: "OK: ... loaded"

### 3. Check Backend Logs
```powershell
Get-Content "C:\xampp\htdocs\healthcare-cms-backend\logs\errors.log" -Tail 10
```

### 4. Test in Browser
- Frontend: `http://localhost/healthcare-cms-frontend/editor.html`
- API Test: `http://localhost/healthcare-cms-backend/public/test-autoload.php`

---

## Common Commands

### Database
```powershell
# Run migration
mysql -u root healthcare_cms < database/migrations/[file].sql

# Backup database
mysqldump -u root healthcare_cms > database/backups/backup_$(Get-Date -Format 'yyyy-MM-dd').sql

# Check tables
mysql -u root -e "USE healthcare_cms; SHOW TABLES;"
```

### Restart Apache (if code not updating)
```powershell
net stop Apache2.4
net start Apache2.4

# Or use XAMPP Control Panel
```

### Check PHP Errors
```powershell
# Backend errors
Get-Content "C:\xampp\htdocs\healthcare-cms-backend\logs\errors.log" -Tail 20

# Apache error log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
```

---

## URLs Cheat Sheet

| Service | URL |
|---------|-----|
| Editor | `http://localhost/healthcare-cms-frontend/editor.html` |
| Media Library | `http://localhost/healthcare-cms-frontend/media-library.html` |
| API Base | `http://localhost/healthcare-cms-backend/public/` |
| Autoloader Test | `http://localhost/healthcare-cms-backend/public/test-autoload.php` |

---

## PowerShell: critical gotchas and safe patterns

When working in PowerShell you will often run PHP helper scripts and one-off commands. PowerShell quoting and escaping differ from bash and commonly cause subtle bugs. Follow these rules to avoid problems:

- Don't put multi-line PHP code directly inside a PowerShell one-liner — PowerShell will try to interpret `$` variables and backslashes.
- Instead, create a temporary PHP file and run it with PHP. Example (safe):

```powershell
Set-Content -Path .\temp_patch.php -Value "<?php\n// PHP code here\n" -Force
& 'C:\xampp\php\php.exe' .\temp_patch.php
Remove-Item .\temp_patch.php
```

- Prefer using a PHP heredoc (<<<'PHPCODE') inside the temporary PHP script to avoid escaping `$` and quotes.
- When editing files from scripts, check for an existing marker before applying changes (e.g. `strpos($content, 'marker')`) to avoid duplicate edits.
- Always lint modified PHP files with `php -l file.php` after editing to catch syntax errors early.
- Paths containing non-ASCII (Cyrillic) characters can be problematic for some tools; when possible, operate on files using PHP scripts (not complex PowerShell string manipulation).
- Make test fixtures and schema idempotent (e.g. `CREATE TABLE IF NOT EXISTS`) so repeated runs are safe.

Following these patterns reduces failures caused by quoting/escaping and makes test automation reliable.

---

## API Endpoints (Most Used)

### Auth
```
POST /api/auth/login
GET  /api/auth/me
POST /api/auth/logout
```

### Pages
```
GET    /api/pages          # List all pages
POST   /api/pages          # Create page + blocks
GET    /api/pages/:id      # Get page with blocks
PUT    /api/pages/:id      # Update page
DELETE /api/pages/:id      # Delete page
```

### Media
```
GET    /api/media          # List media files
POST   /api/media/upload   # Upload file
DELETE /api/media/:id      # Delete file
```

---

## Debugging Workflow

1. **See error in browser console** → Check browser Network tab
2. **API returns error** → Check `backend/logs/errors.log`
3. **Class not found** → Did you sync to XAMPP? Check autoloader
4. **Old code still runs** → Restart Apache
5. **File not found** → Check file exists in `C:\xampp\htdocs\...`

---

## Git Workflow

### Before Starting Work
```powershell
git status
git pull
```

### After Making Changes
```powershell
git status
git add .
git commit -m "Your message"
git push
```

---

## Running Tests

### PHP Unit + Integration Tests
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests
Set-Location ..
```

### PHP E2E Tests (API level)
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests\_bootstrap.php tests\E2E
Set-Location ..
```

### Playwright UI Tests (Browser level)
```powershell
# First time: install dependencies
Set-Location frontend\e2e
npm install
npx playwright install --with-deps

# Run tests
npm test

# Run with visible browser (helpful for debugging)
npm run test:headed

Set-Location ..\..
```

**See:** [E2E Quick Start](./E2E_QUICK_START.md) for full testing guide

---

## Remember: The 2 Golden Rules

1. **ALWAYS SYNC TO XAMPP** after code changes
2. **NEVER USE CYRILLIC** in code (only in docs/comments)

---

## Emergency Reset

If everything breaks:

```powershell
# 1. Stop services
# XAMPP Control Panel → Stop Apache & MySQL

# 2. Full sync
robocopy "backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /R:0 /W:0 /XD node_modules .git
robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /XD node_modules .git

# 3. Restart services
# XAMPP Control Panel → Start Apache & MySQL

# 4. Clear browser cache
# Browser → Ctrl+Shift+Delete → Clear cache

# 5. Test
# Open http://localhost/healthcare-cms-frontend/editor.html
```

---

## Documentation Links

- [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md) ⚠️
- [Sync Checklist](./SYNC_CHECKLIST.md)
- [API Endpoints](./API_ENDPOINTS_CHEATSHEET.md)
- [Project Status](./PROJECT_STATUS.md)
- [Troubleshooting](./TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md)
- [E2E Testing Quick Start](./E2E_QUICK_START.md) 🧪
- [E2E Testing Implementation Guide](./E2E_TESTING_IMPLEMENTATION_PROMPT.md) 🧪

---

**Updated:** October 6, 2025  
**Keep this file open while coding!**
