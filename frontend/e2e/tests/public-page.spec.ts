import { test, expect } from '@playwright/test';

test.describe('Public page preview parity', () => {
  test('public guides page includes preview css and key blocks', async ({ page }) => {
    await page.goto('http://localhost/healthcare-cms-backend/page/guides', { waitUntil: 'networkidle' });

    // Check that preview CSS is linked in the head
    const link = page.locator('head link[href="/healthcare-cms-frontend/editor-preview.css"]');
    await expect(link).toHaveCount(1);

    // Check presence of preview wrappers and header
    await expect(page.locator('.preview-wrapper')).toHaveCount(1);
    await expect(page.locator('.main-header')).toHaveCount(1);

  // Check the cards block and at least one card
  await expect(page.locator('.block-cards')).toHaveCount(1);
  const cardCount = await page.locator('.block-cards .card').count();
  expect(cardCount).toBeGreaterThan(0);
  });
});
