# 🚀 COPY THIS RIGHT NOW

This is the file you need **RIGHT NOW** to execute Phase 2.

---

## ⚡ 30-Second Quick Start

### Step 1: Open This File
```
docs/PHASE_2_COMPLETION_PROMPT.md
```

### Step 2: Copy All (Ctrl+A → Ctrl+C)

### Step 3: Paste Into Your LLM (Claude/ChatGPT)

### Step 4: Say This To Your LLM

```
Execute this prompt step-by-step. 
Follow all PARTS in order: 1 → 2 → 3 → 4 → 5 → 6
Don't skip any steps.
```

### Step 5: Wait ~45 minutes

### Step 6: Verify Success

Run this in backend directory:
```bash
php -r "
require 'vendor/autoload.php';
\$c = require 'bootstrap/container.php';
\$u = \$c->get('UpdatePageInline');
echo '✅ PHASE 2.1 COMPLETE!';
"
```

Expected: `✅ PHASE 2.1 COMPLETE!`

---

## 📂 Files You Need

Located in `docs/` folder:

```
PHASE_2_COMPLETION_PROMPT.md ← COPY THIS (800+ lines)
HOW_TO_USE_PHASE_2_PROMPT.md ← READ THIS IF CONFUSED
PHASE_2_QUICK_START.md ← QUICK REFERENCE
```

---

## ⚠️ Important Notes

- ✅ Works with any LLM (Claude, ChatGPT, Gemini, etc.)
- ✅ Takes ~45 minutes
- ✅ No background context needed
- ✅ Self-contained with all code
- ✅ Low error rate (designed to be fail-safe)

---

## 🎯 What Gets Done

1. ✅ UpdatePageInline refactored to use DTOs
2. ✅ Domain exceptions added (PageNotFoundException, BlockNotFoundException)
3. ✅ bootstrap/container.php updated
4. ✅ 4 unit tests created
5. ✅ Integration test created

---

## ✅ After LLM Finishes

Run verification command (see Step 6 above).

All tests should pass:
```bash
vendor/bin/phpunit tests/UpdatePageInlineTest.php
```

Expected: **4 tests, 4 passed**

---

## 📞 If It Fails

1. Check: `docs/PHASE_2_COMPLETION_PROMPT.md` → TROUBLESHOOTING
2. Or: `docs/HOW_TO_USE_PHASE_2_PROMPT.md` → FAQ

---

## 🎓 That's It

- Copy → Paste → Execute → Done ✅

---

**Next Step:** Open `docs/PHASE_2_COMPLETION_PROMPT.md` now
