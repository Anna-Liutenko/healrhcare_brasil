const { test, expect } = require('@playwright/test');
const helper = require('./helpers/loginHelper');

test('article button visible and clickable (uses login helper)', async ({ page }) => {
  await page.goto('http://localhost/visual-editor-standalone/editor.html');
  await page.waitForLoadState('networkidle');

  // Simulate login using the helper (sets window.app.currentUser and hides overlays)
  await helper.loginAs(page, 'e2e-playwright');
  // Give Vue a moment to update DOM after login simulation
  await page.waitForTimeout(300);

  // Robust locator: prefer text 'Написать' but fall back to toolbar button
  const btn = page.locator('button:has-text("Написать"), button:has-text("Написать статью"), button:has-text("✍"), button.toolbar-btn').first();

  await expect(btn).toBeVisible({ timeout: 5000 });
  await expect(btn).toBeEnabled();

  // Diagnostic: ensure nothing overlays the button at its center
  const box = await btn.boundingBox();
  if (!box) throw new Error('Found button but bounding box is null');
  const center = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
  const topEl = await page.evaluate(({x,y}) => {
    const el = document.elementFromPoint(x,y);
    return el ? { tag: el.tagName, classes: el.className, id: el.id } : null;
  }, center);
  console.log('element at center:', topEl);

  // Click and wait for article editor (Quill) to appear
  await btn.click();
  await page.waitForSelector('#quill-editor, .ql-editor, .article-editor-mode', { timeout: 5000 });
});