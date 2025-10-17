# 🐛 Bug Fix: Infinite Loop in Editor.js (October 6, 2025)

## Executive Summary

**Fixed**: Critical infinite loop bug in `frontend/editor.js` that prevented pages from loading correctly.

**Impact**: HIGH - Complete failure of page editing functionality

**Status**: ✅ RESOLVED (October 6, 2025, 2:04 PM)

---

## The Bug

### Location
`frontend/editor.js`, `mounted()` lifecycle hook (lines 117-130)

### Broken Code
```javascript
async mounted() {
    const urlParams = new URLSearchParams(window.location.search);
    const pageId = urlParams.get('id');

    if (pageId) {
        // INFINITE LOOP STARTS HERE
        while (this.showLoginModal) {
            await new Promise(resolve => setTimeout(resolve), 100));
        }

        if (this.currentUser) {
            await this.loadPageFromAPI(pageId);
        }
    }
}
```

### Why It Failed

**Problem 1: Infinite Loop**
- If user opens `editor.html?id=xxx` without being logged in
- `showLoginModal = true` (set by `checkAuth()` in `created()`)
- `while (this.showLoginModal)` hangs **forever**
- Even after successful login, the loop continues waiting

**Problem 2: Double Load**
- `mounted()` calls `loadPageFromAPI()` after loop ends
- `login()` method **ALSO** calls `loadPageFromAPI()` after successful login
- Result: Page loads twice, second load overwrites first

**Problem 3: Race Condition**
- Vue reactive system updates asynchronously
- `isEditMode` flag may not update UI in time
- Blocks may render before data binds

---

## The Fix

### New Code
```javascript
async mounted() {
    const urlParams = new URLSearchParams(window.location.search);
    const pageId = urlParams.get('id');

    if (pageId) {
        this.debugMsg('Обнаружен параметр id в URL при монтировании', 'info', { pageId });
        
        // If user ALREADY logged in (checkAuth completed in created)
        if (this.currentUser && !this.showLoginModal) {
            this.debugMsg('Пользователь авторизован, загружаем страницу', 'info', { pageId });
            await this.loadPageFromAPI(pageId);
        } else {
            // If user NOT logged in, wait for login
            // loadPageFromAPI will be called INSIDE login() after successful login
            this.debugMsg('Пользователь не авторизован, ожидание входа. Страница будет загружена после логина.', 'info', { pageId });
        }
    }
}
```

### What Changed

1. **Removed infinite loop** - No more `while (this.showLoginModal)`
2. **Single responsibility** - `mounted()` only loads if ALREADY logged in
3. **Delegation** - If NOT logged in, `login()` handles page load
4. **Debug messages** - Clear logging for each code path

---

## How It Works Now

### Scenario 1: User Already Logged In
1. User opens `editor.html?id=xxx`
2. `created()` runs → `checkAuth()` → finds token → sets `currentUser`
3. `mounted()` runs → detects `pageId` → checks `currentUser` exists → loads page ✅

### Scenario 2: User NOT Logged In
1. User opens `editor.html?id=xxx`
2. `created()` runs → `checkAuth()` → no token → shows login modal
3. `mounted()` runs → detects `pageId` → sees no `currentUser` → logs "wait for login" → exits
4. User enters credentials → `login()` runs → successful login → `login()` loads page ✅

### Scenario 3: F5 Refresh
1. User refreshes page with `?id=xxx` in URL
2. Token still in localStorage
3. `created()` → `checkAuth()` → validates token → sets `currentUser`
4. `mounted()` → loads page immediately ✅

---

## Cache-Busting Strategy

To force browsers to reload the fixed file:

### Updated File
`frontend/editor.html` line 1912:
```html
<!-- Changed from v=1.1 to v=1.2 -->
<script type="module" src="editor.js?v=1.2"></script>
```

### Why This Works
- Browser sees `?v=1.2` as a **different URL** from `?v=1.1`
- Forces cache invalidation
- No need for users to manually clear cache

---

## Verification Tests

All tests passed ✅

### Test 1: Direct Repository
```bash
php backend/scripts/test_page_save.php
# Result: Page saved with 4 blocks ✅
```

### Test 2: API Endpoint
```bash
GET /api/pages/6db6f67b-50a4-44e2-9850-c9fb3a46336b
# Result: Returns page with blocks ✅
```

### Test 3: Frontend Load
```
Open: editor.html?id=6db6f67b-50a4-44e2-9850-c9fb3a46336b
Login: admin/admin
# Result: Page loads with all 4 blocks visible ✅
```

### Test 4: Edit Mode Detection
```javascript
console.log(this.isEditMode); // true ✅
console.log(this.currentPageId); // "6db6f67b-..." ✅
```

### Test 5: F5 Refresh
```
1. Load page with ?id=xxx
2. Press F5
# Result: Page reloads correctly, blocks remain ✅
```

---

## Lessons Learned

### Anti-Pattern: Polling Loops in Vue Lifecycle
❌ **Never use `while` loops waiting for state changes in `mounted()`**
- Blocks Vue's reactivity system
- Can cause infinite loops
- Hard to debug

✅ **Instead**: Use conditional logic + delegation
```javascript
if (conditionMet) {
    doAction();
} else {
    // Let other methods handle it
}
```

### Anti-Pattern: Duplicate Async Calls
❌ **Don't call the same async function from multiple lifecycle hooks**
- Causes race conditions
- Wastes API calls
- Unpredictable results

✅ **Instead**: Single source of truth
```javascript
// Only ONE place calls loadPageFromAPI for each scenario
if (scenario1) { loadPage(); }
else { /* delegated elsewhere */ }
```

### Anti-Pattern: Sync Issues Mistaken for Logic Issues
❌ **90% of debugging time spent on XAMPP sync**
- Old files cached in XAMPP
- Cyrillic paths break PowerShell scripts
- Manual verification needed every time

✅ **Solution**: Always verify sync with timestamps
```powershell
Get-Item workspace\file.js | Select LastWriteTime
Get-Item C:\xampp\htdocs\file.js | Select LastWriteTime
```

---

## Related Issues

- [TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md](./TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md) - Full debug history
- [XAMPP_SYNC_ANTIPATTERNS.md](./XAMPP_SYNC_ANTIPATTERNS.md) - Sync best practices
- [SYNC_CHECKLIST.md](./SYNC_CHECKLIST.md) - Verification steps

---

## Files Changed

1. `frontend/editor.js` - Fixed `mounted()` hook (lines 117-130)
2. `frontend/editor.html` - Updated cache-busting version `?v=1.2`
3. `docs/TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md` - Added resolution section
4. `docs/PROJECT_STATUS.md` - Updated status to RESOLVED

---

**Author**: GitHub Copilot  
**Date**: October 6, 2025  
**Time to Resolution**: ~3 hours (including XAMPP sync debugging)  
**Severity**: Critical  
**Impact**: All page editing functionality restored
