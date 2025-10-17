# XAMPP Sync Checklist ✅

## Use this checklist BEFORE testing any feature

---

## 📁 Backend Changes

Did you modify any PHP files?

- [ ] Controllers (`backend/src/Presentation/Controller/`)
- [ ] Use Cases (`backend/src/Application/UseCase/`)
- [ ] Repositories (`backend/src/Infrastructure/Repository/`)
- [ ] Entities (`backend/src/Domain/Entity/`)
- [ ] Routes (`backend/public/index.php`)
- [ ] Config (`backend/config/`)

### If YES → Sync Backend

```powershell
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0 /NFL /NDL
```

**Verify**:
```powershell
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\[YOUR_FILE_PATH]"
```

---

## 🎨 Frontend Changes

Did you modify any frontend files?

- [ ] JavaScript (`frontend/*.js`)
- [ ] HTML (`frontend/*.html`)
- [ ] CSS (`frontend/*.css`)
- [ ] Components (`frontend/components/`)
- [ ] Utils (`frontend/utils/`)

### If YES → Sync Frontend

```powershell
robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /NFL /NDL
```

**Verify**:
```powershell
Test-Path "C:\xampp\htdocs\healthcare-cms-frontend\[YOUR_FILE_NAME]"
```

---

## 🗄️ Database Changes

Did you modify migrations or schema?

- [ ] Migrations (`database/migrations/`)
- [ ] Seeds (`database/seeds/`)

### If YES → Run Migrations

```bash
mysql -u root healthcare_cms < database/migrations/[YOUR_MIGRATION].sql
```

---

## 🔧 Vendor/Dependencies Changes

Did you modify composer or vendor?

- [ ] `backend/composer.json`
- [ ] `backend/vendor/autoload.php`
- [ ] `backend/vendor/composer/*`

### If YES → Sync Vendor

```powershell
robocopy "backend\vendor" "C:\xampp\htdocs\healthcare-cms-backend\vendor" /MIR /R:0 /W:0
```

---

## 🌐 Apache Configuration Changes

Did you modify .htaccess or Apache config?

- [ ] `backend/.htaccess`
- [ ] `backend/public/.htaccess`

### If YES → Sync & Restart Apache

```powershell
Copy-Item "backend\.htaccess" -Destination "C:\xampp\htdocs\healthcare-cms-backend\" -Force

# Restart Apache
net stop Apache2.4
net start Apache2.4
```

---

## 🧪 After Sync: Verification

### 1. Check Files Exist
```powershell
# Backend
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\Infrastructure\Repository\MySQLPageRepository.php"

# Frontend
Test-Path "C:\xampp\htdocs\healthcare-cms-frontend\editor.js"
```

### 2. Check Autoloader Works
Open in browser: `http://localhost/healthcare-cms-backend/public/test-autoload.php`

Expected output:
```
OK: Infrastructure\Database\Connection loaded
OK: Presentation\Controller\PageController loaded
Test complete!
```

### 3. Check API Response
```powershell
Invoke-WebRequest -Uri "http://localhost/healthcare-cms-backend/public/index.php" -UseBasicParsing | Select-Object -ExpandProperty Content
```

Expected: `{"error":"Endpoint not found"}` (means routing works)

### 4. Check Frontend Loads
Open in browser: `http://localhost/healthcare-cms-frontend/editor.html`

Expected: Editor loads without console errors

---

## ⚠️ Common Sync Mistakes

### Mistake #1: Forgot to Sync
**Symptom**: Changes don't appear  
**Solution**: Run robocopy again

### Mistake #2: Wrong Path
**Symptom**: Files not found  
**Solution**: Use absolute paths with `C:\xampp\htdocs\`

### Mistake #3: Cyrillic in Script
**Symptom**: PowerShell parse errors  
**Solution**: Use robocopy directly, not custom scripts

### Mistake #4: Old PHP Cache
**Symptom**: Old code still runs  
**Solution**: Restart Apache or clear opcache

---

## 🚀 Quick Sync All (Full Reset)

Use this when you're not sure what changed:

```powershell
# Backend
robocopy "backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /R:0 /W:0 /XD node_modules .git

# Frontend
robocopy "frontend" "C:\xampp\htdocs\healthcare-cms-frontend" /MIR /R:0 /W:0 /XD node_modules .git

# Restart Apache
net stop Apache2.4
net start Apache2.4
```

**Warning**: `/MIR` will delete files in destination that don't exist in source.

---

## 📊 Sync Status Log Template

Use this to track sync operations:

```
Date: 2025-10-06
Time: 00:50
Changed: backend/src/Infrastructure/Repository/MySQLPageRepository.php
Synced: ✅ Yes
Command: robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR
Result: 4 files copied
Verified: ✅ Test-Path returned True
Tested: ✅ Page save works
```

---

## 🎯 Remember

**DON'T TRUST YOUR MEMORY**

Always sync explicitly, always verify, always test.

The 2 minutes spent syncing properly saves 2 hours of debugging.
