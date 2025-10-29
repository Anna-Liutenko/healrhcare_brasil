# 🧪 TESTING QUILL IMAGES - Step-by-Step

**Date:** October 24, 2025  
**Objective:** Verify Quill inline images work end-to-end

---

## ✅ WHAT WAS FIXED

### Problem
1. Quill images stored full URLs → broken paths in exported HTML
2. Article block content not visible in editor or exported HTML
3. No images in public pages

### Solution
1. Quill now stores relative paths (`/uploads/uuid.jpg`) instead of full URLs
2. Added `convertImagePathsInHtml()` to convert paths for display
3. Added detailed logging to track Article block content

---

## 🧪 TEST PROCEDURE

### Step 1: Clear Cache (2 minutes)

```
Ctrl + Shift + Delete
  → All time
  → Check: Cookies, Cache
  → Clear data
```

Then enable DevTools cache disable:
```
F12 → Settings → Network
  ✓ Disable cache (while DevTools is open)
```

Then hard refresh:
```
Ctrl + Shift + R
```

---

### Step 2: Create Test Article (5 minutes)

1. Open: `http://localhost/healthcare-cms-frontend/editor.html`
2. Click "Написать статью" button (orange button)
3. This opens Quill editor
4. Type some text in Quill
5. Insert image:
   - Click image icon in toolbar
   - Choose any image file
   - Image should appear in editor ✓
6. Add more text after image
7. Click "Сохранить и закрыть"

---

### Step 3: Monitor Console Output (IMPORTANT!)

**Open DevTools Console (F12 → Console) and watch for:**

```
[DEBUG ARTICLE BLOCK 0] Content length: XXX, First 200 chars: <img src="/uploads/...
```

**This tells you:**
- ✓ Article block has content
- ✓ Images have `/uploads/` relative paths (not full URLs!)
- ✓ Content is not empty

---

### Step 4: Save Page (2 minutes)

1. Set page type to "guide" or "article"
2. Set title, slug, etc.
3. Click green "✓ Сохранить и закрыть" button
4. Wait for notification

**Watch console for:**
```
[DEBUG ARTICLE BLOCK 0] Content length: XXX, First 200 chars: <img src="/uploads/...
```

---

### Step 5: Check Gallery (2 minutes)

1. Click "Медиатека" button
2. Your uploaded image should appear in list ✓
3. Image thumbnail should load ✓

---

### Step 6: Export HTML (3 minutes)

1. Click "Экспорт HTML" button
2. File should download
3. Open downloaded HTML file in browser
4. Check:
   - [ ] Header visible
   - [ ] Article content visible
   - [ ] Image displays
   - [ ] Footer visible
   - [ ] No console errors

**If image broken:**
- Open DevTools Network tab
- Look at image request
- Check URL path

---

### Step 7: Check Public Page (2 minutes)

1. Go back to editor
2. Click "Опубликовать" button
3. Visit public page at: `http://localhost/public/healthcare-cms-backend/public/guides/[slug]`
4. Check:
   - [ ] Article displays
   - [ ] Image visible
   - [ ] No broken image icons

---

## 🔍 DEBUGGING CONSOLE LOGS

### Good Output
```
[DEBUG ARTICLE BLOCK 0] Content length: 450, First 200 chars: <img src="/uploads/a1b2c3d4.jpg">...
```
✓ Content present, relative paths

### Bad Output 1
```
[DEBUG ARTICLE BLOCK 0] Content length: 0, First 200 chars:
```
❌ Content empty - block not saved properly

### Bad Output 2
```
[DEBUG ARTICLE BLOCK 0] Content length: 150, First 200 chars: <img src="http://localhost/healthcare-cms-backend/...
```
❌ Full URLs stored - means fix didn't apply

### Bad Output 3
```
No article blocks found
```
❌ No Article block in page - content not added

---

## 🧪 VERIFICATION CHECKLIST

| Test | Expected | Status |
|------|----------|--------|
| Article block has content | Content length > 0 | [ ] |
| Paths are relative | `/uploads/...` not full URL | [ ] |
| Editor displays image | Image visible in Quill | [ ] |
| Gallery shows image | Image in media library | [ ] |
| Export HTML works | File downloads | [ ] |
| Exported HTML has image | Image displays in exported file | [ ] |
| Public page shows image | Image visible on public site | [ ] |
| Console no 404 errors | No failed image loads | [ ] |

---

## 🆘 TROUBLESHOOTING

### Images still broken in export
**Check:**
1. DevTools console for `[DEBUG ARTICLE BLOCK]` logs
2. If `Content length: 0` → block is empty
3. If full URL shown → cache not cleared

**Solution:**
1. `Ctrl + Shift + Delete` → Clear ALL
2. `Ctrl + Shift + R` → Hard refresh
3. F12 → Application → Service Workers → Unregister all

### Image shows broken icon
**Check:**
1. Network tab → right-click image 404 → copy URL
2. Paste URL in browser address bar
3. Does image load?

**If yes:** Path conversion needed  
**If no:** Image file doesn't exist on disk

### Article content still empty
**Check:**
1. Quill editor had content? (typed text + image)?
2. Console shows `[DEBUG ARTICLE BLOCK]` at all?
3. Did you click "Сохранить и закрыть"?

**Solution:**
1. Make sure to click "Сохранить и закрыть" after editing in Quill
2. This calls `convertHtmlToBlocks()` which adds to `this.blocks`
3. Then click page save button

---

## 📝 WHAT TO REPORT IF BROKEN

If images still don't work, check and report:

1. **Console output** - paste the `[DEBUG ARTICLE BLOCK]` line
2. **Export HTML** - does it have `<img>` tags?
3. **Public page** - can you see article title?
4. **Network errors** - any 404s for images?
5. **Cache status** - when was `editor.js` loaded?

---

## 🎯 SUCCESS CRITERIA

✅ **All of these must pass:**
- [ ] Article content visible in editor after save
- [ ] Quill image visible in editor after insert
- [ ] Console shows `Content length: XXX` (not 0)
- [ ] Console shows `/uploads/` (not full URL)
- [ ] Gallery shows uploaded images
- [ ] Exported HTML contains `<img src="...">` tags
- [ ] Exported HTML images display when opened
- [ ] Public page shows article with image
- [ ] No 404 errors in Network tab for images

---

**Ready to test?** Follow steps 1-7 above!
