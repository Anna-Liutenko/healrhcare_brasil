# HOW TO USE PHASE_2_COMPLETION_PROMPT.md

## Quick Start

This file contains a **step-by-step instruction set** that any LLM can follow to complete Phase 2 of the Clean Architecture refactoring.

### 📋 Files Created

1. **PHASE_2_COMPLETION_PROMPT.md** — Main instruction file (executable for any LLM)
2. **THIS FILE** — How to use the prompt effectively

---

## ✅ For Your LLM Assistant

### Method 1: Direct Copy-Paste (RECOMMENDED)

1. Open `docs/PHASE_2_COMPLETION_PROMPT.md`
2. Copy the **entire content** (Ctrl+A → Ctrl+C)
3. Paste into your LLM chat window (Claude, ChatGPT, etc.)
4. Say: **"Execute this prompt step-by-step"**

### Method 2: File Reference

If your LLM supports file references, say:

```
Please execute the prompt in: docs/PHASE_2_COMPLETION_PROMPT.md

Execute it section by section:
- PART 1: Verification
- PART 2: UpdatePageInline refactoring
- PART 3: Bootstrap container update
- PART 4: Unit tests
- PART 5: Integration tests
```

---

## 📌 Key Features of This Prompt

### ✅ Self-Contained
- Does NOT require previous context
- All necessary code snippets included
- All import statements specified

### ✅ Foolproof
- Step-by-step numbering
- **FIND THIS** / **REPLACE WITH** patterns
- Before/after code examples
- Exact line numbers mentioned

### ✅ Verifiable
- Checklist in PART 6
- Final verification command
- Troubleshooting section

### ✅ Low-Error
- Does NOT skip steps
- Does NOT modify architecture
- Maintains consistency

---

## 🚀 Expected Time

- PART 1 (Verification): 5 minutes
- PART 2 (UpdatePageInline): 10 minutes
- PART 3 (Container): 5 minutes
- PART 4 (Unit Tests): 15 minutes
- PART 5 (Integration Tests): 5 minutes
- PART 6 (Verification): 5 minutes

**Total: ~45 minutes**

---

## ⚠️ Important Notes

### Before Sending to LLM

1. **Ensure LLM can edit files**
   - Use tools like: `replace_string_in_file`, `create_file`, `read_file`
   - Not: running PowerShell scripts (except at end for tests)

2. **Mention your tech stack**
   - Backend: PHP 8.1+
   - Package manager: Composer
   - Test framework: PHPUnit
   - Architecture: Clean Architecture + DI

3. **Provide workspace context** (if needed)
   - Backend path: `backend/`
   - Tests path: `backend/tests/`
   - Bootstrap path: `backend/bootstrap/`

### After LLM Completes

1. Run the **FINAL VERIFICATION COMMAND** (in PART 6)
2. Check the **VERIFICATION CHECKLIST** (all items should be ✅)
3. Run: `vendor/bin/phpunit tests/UpdatePageInlineTest.php`

---

## 📂 Files Modified During Execution

| File | Action | Priority |
|------|--------|----------|
| `backend/src/Application/UseCase/UpdatePageInline.php` | Replace method | HIGH |
| `backend/bootstrap/container.php` | Update registration | HIGH |
| `backend/tests/UpdatePageInlineTest.php` | Create new | HIGH |
| `backend/tests/Integration/UpdatePageInlineIntegrationTest.php` | Create new | MEDIUM |

---

## 🔍 Verification After Execution

### Quick Check (5 seconds)

```bash
cd backend
php -l src/Application/UseCase/UpdatePageInline.php
php -l bootstrap/container.php
```

Both should show: `No syntax errors detected`

### Full Verification (30 seconds)

```bash
cd backend
vendor/bin/phpunit tests/UpdatePageInlineTest.php
```

Expected: **4 tests, 4 passed**

### Coverage Report (2 minutes)

```bash
cd backend
vendor/bin/phpunit tests/UpdatePageInlineTest.php --coverage-html=coverage
# Then open: backend/coverage/index.html
```

Expected: >= 80% coverage

---

## 🤔 FAQ

### Q: Can I skip PART 5 (Integration Test)?
**A:** Yes, but NOT recommended. Unit tests are sufficient for this prompt, but integration tests ensure real database works.

### Q: What if the LLM makes mistakes?
**A:** 
1. The prompt is designed to be fail-safe
2. Each step has FIND/REPLACE patterns
3. Checklist helps catch issues early
4. Verification command confirms success

### Q: Can I modify the prompt?
**A:** No. The prompt is tuned for clarity and correctness. Modifications may introduce errors.

### Q: What's next after this prompt?
**A:** After success, proceed with:
- PHASE 3.1: Refactor PageController
- PHASE 3.2: Update index.php
- PHASE 3.3: E2E testing

---

## 📝 Recommended LLM Instructions

**Copy-paste this into your LLM chat:**

```
You are a PHP/Clean Architecture expert. I will provide a step-by-step prompt 
to refactor a Healthcare CMS backend from Generic Architecture to Clean Architecture.

The prompt is in markdown format with numbered sections and FIND/REPLACE patterns.

RULES:
1. Execute EACH PART in order (1, 2, 3, 4, 5, 6)
2. For PART 2-3, use the FIND/REPLACE patterns EXACTLY as shown
3. Do NOT skip any step
4. Do NOT modify the architecture
5. Do NOT combine steps
6. When creating files, use full file paths from the workspace root
7. When replacing code, include 3-5 lines of context

After completing all parts, run the FINAL VERIFICATION COMMAND from PART 6.

Here's the prompt:

[PASTE ENTIRE CONTENT OF PHASE_2_COMPLETION_PROMPT.md HERE]
```

---

## 🎯 Success Criteria

After LLM execution, the following must be true:

✅ UpdatePageInline accepts `UpdatePageInlineRequest` DTO  
✅ UpdatePageInline returns `UpdatePageInlineResponse` DTO  
✅ UpdatePageInline throws `PageNotFoundException`  
✅ UpdatePageInline throws `BlockNotFoundException` (CRITICAL!)  
✅ UpdatePageInline throws `InvalidArgumentException`  
✅ bootstrap/container.php registers MarkdownConverter + HTMLSanitizer  
✅ 4 unit tests pass  
✅ No PHP syntax errors  
✅ Final verification command succeeds  

If ALL ✅ — **PHASE 2.1 IS COMPLETE**

---

## 📞 Support

If LLM encounters errors:

1. **Check the TROUBLESHOOTING section** in PHASE_2_COMPLETION_PROMPT.md
2. **Re-read PART 1** (Verification) to ensure components exist
3. **Compare your code** against the examples in PART 2-3
4. **Run the final verification command** to see exact errors

---

**Document Version:** 1.0  
**Last Updated:** October 16, 2025  
**Status:** Ready for LLM execution
