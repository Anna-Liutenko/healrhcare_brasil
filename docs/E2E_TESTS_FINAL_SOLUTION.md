# E2E Tests: Final Solution (October 8, 2025)

## Problem Summary

E2E tests were repeatedly **freezing/hanging** when running the test suite. The issue was caused by `proc_open()` trying to auto-start the PHP built-in server on Windows with a path containing Cyrillic characters.

### Root Cause

- **Windows + Cyrillic path + `proc_open()`** = silent hang
- Path: `C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\...`
- `proc_open()` with Cyrillic paths causes the process to freeze indefinitely
- No error messages, no exceptions — just a silent hang

## Solution Implemented

### Approach: Manual Server Start (No Auto-Start)

Instead of trying to auto-start the server via `proc_open()`, the E2E tests now:

1. **Check if server is running** using `fsockopen()`
2. **Skip gracefully** with clear instructions if server is not running
3. **No background processes** — no freezing, no cleanup issues

### Files Modified

#### 1. `backend/tests/E2E/HttpApiE2ETest.php`

**Before**: Complex `proc_open()` logic with PowerShell command strings (70+ lines)

**After**: Simple fsockopen check (10 lines)

```php
protected function setUp(): void
{
    $fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 1);
    if (!$fp) {
        $this->markTestSkipped(
            "E2E test skipped: PHP built-in server is not running...\n" .
            "See backend/tests/E2E/README_RUN_E2E_TESTS.md for setup instructions."
        );
        return;
    }
    fclose($fp);
}

protected function tearDown(): void
{
    // No process to clean up (server is manually managed)
}
```

#### 2. `backend/tests/E2E/HttpImportE2ETest.php`

Same simplification as above.

#### 3. `backend/tests/E2E/README_RUN_E2E_TESTS.md` (NEW)

Step-by-step instructions for manually starting the server.

## How to Run E2E Tests

### Step 1: Start PHP Server Manually

Open a **separate PowerShell window** and run:

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

$env:DB_DEFAULT='sqlite'
$env:DB_DATABASE=(Resolve-Path '.\tests\tmp\e2e.sqlite').Path

& 'C:\xampp\php\php.exe' -d auto_prepend_file=tests\E2E\server_bootstrap.php -S 127.0.0.1:8089 -t public
```

**Keep this window open** while running tests.

### Step 2: Run Tests

In a **different PowerShell window**:

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

.\vendor\bin\phpunit --bootstrap tests\_bootstrap.php tests
```

### Step 3: Verify Results

Expected output:

```
PHPUnit 10.5.58 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12

..........                                                        10 / 10 (100%)

Time: 00:00.287, Memory: 10.00 MB

OK (10 tests, 31 assertions)
```

If server is **not running**, E2E tests will skip:

```
OK, but some tests were skipped!
Tests: 10, Assertions: 22, Skipped: 3.
```

## Test Results

### Without Server (Skipped)

```
✅ Tests: 10
✅ Assertions: 22
⚠️  Skipped: 3 (E2E tests)
✅ NO FREEZING!
```

### With Server (All Pass)

```
✅ Tests: 10
✅ Assertions: 31
✅ Skipped: 0
✅ All E2E tests pass
```

## Benefits of This Approach

1. **No Freezing**: Tests never hang — they skip gracefully
2. **Clear Messages**: Developers see exact commands to start server
3. **Reliable**: Works regardless of path encoding issues
4. **Simple**: No complex process management, no cleanup needed
5. **Debuggable**: Server runs in visible terminal window with logs

## Alternative: Permanent Fix

If you want **automatic server startup** to work, relocate the project to a path **without Cyrillic characters**:

```powershell
# Example
Move-Item 'C:\Users\annal\Documents\Мои сайты\...' 'C:\projects\healthcare-cms'
```

Then `proc_open()` will work correctly, and auto-start can be re-enabled.

## Related Documentation

- **E2E_INVESTIGATION_REPORT.md** — Original investigation of this issue
- **E2E_DEBUGGING_AND_FIXES.md** — Debugging journey and fixes applied
- **README_RUN_E2E_TESTS.md** — Detailed manual server startup instructions
- **BACKEND_CURRENT_STATE.md** — Current backend status and test coverage

## Conclusion

The **manual server approach** is a pragmatic solution that:

- ✅ Eliminates all freezing issues
- ✅ Works reliably on Windows with Cyrillic paths
- ✅ Provides clear guidance to developers
- ✅ Maintains full test coverage when server is running

This issue was caused by a **Windows + Cyrillic + proc_open() incompatibility**, not by test logic errors.
