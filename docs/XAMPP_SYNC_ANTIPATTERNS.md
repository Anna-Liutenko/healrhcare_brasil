# XAMPP Synchronization Antipatterns ⚠️

## Critical Issues to Remember

This document outlines the **bottleneck issues** that consistently cause problems during development with XAMPP.

---

## 🔴 ANTIPATTERN #1: Poor XAMPP Synchronization

### The Problem

**Development code** in `backend/` and `frontend/` folders **DOES NOT automatically sync** to XAMPP runtime environment at `C:\xampp\htdocs\healthcare-cms-backend\`.

### Symptoms

- ✅ Code changes look correct in workspace
- ❌ Changes don't work when testing in browser
- ❌ Old code keeps running
- ❌ New files don't exist in XAMPP
- ❌ Fatal errors: "Class not found" even though file exists in workspace

### Why It Happens

XAMPP serves files from `C:\xampp\htdocs\`, **NOT** from your workspace folder. Any changes you make in your workspace must be **manually copied** to XAMPP.

### Solution: Always Sync After Changes

After **ANY** code modification, run:

```powershell
# Full sync (recommended)
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0 /NFL /NDL

# Or use sync script
.\sync-to-xampp.ps1
```

### Verification Checklist

Before testing ANY feature:

- [ ] Did I modify backend code?
- [ ] Did I sync to XAMPP?
- [ ] Did robocopy report success (Exit Code 1 or 0)?
- [ ] Did I verify the file exists in `C:\xampp\htdocs\...`?

---

## 🔴 ANTIPATTERN #2: Cyrillic Characters in Paths

### The Problem

**Cyrillic (Russian) characters** in file paths cause:
- Encoding errors
- Sync failures
- File access errors
- Unpredictable behavior

### Examples of Problematic Paths

❌ **BAD**:
```
C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\...
```

✅ **GOOD**:
```
C:\Projects\healthcare-cms\...
C:\dev\cms-brazil\...
```

### Symptoms

```powershell
# Robocopy failure
ERROR: Source path contains invalid characters

# PowerShell script errors
Parse error: unexpected token

# PHP errors
Warning: failed to open stream: No such file or directory
```

### Real Example from This Project

```powershell
# This FAILS due to Cyrillic:
.\sync-to-xampp.ps1
# Error: "Unexpected token '}' in expression or statement"
# Error: "The string is missing the terminator"

# This WORKS:
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR
```

### Current Workaround

Since project path contains Cyrillic, **always use**:

1. **Robocopy** (handles Cyrillic better):
   ```powershell
   robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0
   ```

2. **Copy-Item** with `-Force`:
   ```powershell
   Copy-Item "backend\public\index.php" -Destination "C:\xampp\htdocs\healthcare-cms-backend\public\" -Force
   ```

3. **Avoid** Cyrillic in:
   - Variable names
   - File names
   - Function names
   - Comments in code (use English or transliteration)
   - Script paths

### Best Practice

**Rule**: Never use Cyrillic characters in any code or paths, only in user-facing text and documentation for the developer.

---

## 🛠️ Recommended Sync Workflow

### Before Every Test Session

1. **Check what changed**:
   ```powershell
   git status
   ```

2. **Sync changed files**:
   ```powershell
   # If changed backend
   robocopy "backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /R:0 /W:0 /XD node_modules .git
   
   # If changed frontend
   robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /XD node_modules .git
   ```

3. **Verify sync**:
   ```powershell
   # Check file exists
   Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\Infrastructure\Repository\MySQLPageRepository.php"
   
   # Check file content (last modified)
   Get-Item "C:\xampp\htdocs\healthcare-cms-backend\src\Infrastructure\Repository\MySQLPageRepository.php" | Select-Object LastWriteTime
   ```

4. **Clear PHP opcache** (if enabled):
   ```powershell
   # Restart Apache
   net stop Apache2.4
   net start Apache2.4
   
   # Or use XAMPP Control Panel
   ```

---

## 📋 Quick Diagnostic Commands

### Check if file synced
```powershell
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\Domain\Entity\Page.php"
```

### Compare file timestamps
```powershell
# Workspace file
Get-Item "backend\src\Domain\Entity\Page.php" | Select-Object LastWriteTime

# XAMPP file
Get-Item "C:\xampp\htdocs\healthcare-cms-backend\src\Domain\Entity\Page.php" | Select-Object LastWriteTime
```

### Verify autoloader in XAMPP
```php
// Create: C:\xampp\htdocs\healthcare-cms-backend\public\test-autoload.php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

echo class_exists('Infrastructure\Repository\MySQLPageRepository') ? 'OK' : 'FAIL';
```

Then open: `http://localhost/healthcare-cms-backend/public/test-autoload.php`

---

## 🎯 Remember: The Golden Rule

**NEVER ASSUME CODE IS SYNCED**

Always:
1. Make changes in workspace
2. Sync to XAMPP explicitly
3. Verify sync succeeded
4. Test in browser

This bottleneck has caused 90% of debugging time. **Always be conscious of sync state.**

---

## Related Issues Solved

- ✅ October 6, 2025: Missing `MySQLPageRepository` - not synced to XAMPP
- ✅ October 5, 2025: Blocks not saving - autoloader not synced
- ✅ October 4, 2025: Menu editor errors - frontend not synced

## Prevention Checklist

Before reporting "it doesn't work":

- [ ] Did I sync backend to XAMPP?
- [ ] Did I sync frontend to XAMPP?
- [ ] Did I check XAMPP error logs?
- [ ] Did I verify file exists in `C:\xampp\htdocs\`?
- [ ] Did I restart Apache if needed?

**If all checked and still failing** → Then investigate deeper.
