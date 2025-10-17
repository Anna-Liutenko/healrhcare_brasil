const { test, expect } = require('@playwright/test');

test('article button appears after simulating login and opens editor', async ({ page }) => {
  await page.goto('http://localhost/visual-editor-standalone/editor.html');
  await page.waitForLoadState('networkidle');

  // Simulate authentication by setting app.currentUser and hiding login modal
  await page.evaluate(() => {
    if (window.app) {
      try {
        window.app.currentUser = { username: 'playwright-test' };
        // If Vue is reactive, this should update UI; also hide login modal if shown
        window.app.showLoginModal = false;
      } catch (e) {
        console.warn('Failed to set app.currentUser', e);
      }
    }
    // As a fallback, hide modal overlays that might block clicks
    document.querySelectorAll('.modal-overlay').forEach(el => el.style.display = 'none');
  });

  // Give Vue a moment to update
  await page.waitForTimeout(300);

  // Now locate the button
  const btn = page.locator('button:has-text("Написать"), button:has-text("Написать статью"), button:has-text("✍")').first();

  await expect(btn).toBeVisible({ timeout: 5000 });
  await expect(btn).toBeEnabled();

  // Click and wait for editor
  await btn.click();
  await page.waitForSelector('#quill-editor, .ql-editor, .article-editor-mode', { timeout: 3000 });

  const quillPresent = await page.locator('#quill-editor, .ql-editor, .article-editor-mode').count();
  expect(quillPresent).toBeGreaterThan(0);
});