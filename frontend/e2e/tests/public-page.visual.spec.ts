import { test, expect } from '@playwright/test';

test('visual: public guides preview matches baseline', async ({ page }) => {
  await page.goto('http://localhost/healthcare-cms-backend/page/guides', { waitUntil: 'networkidle' });
  const wrapper = page.locator('.page-wrapper').first();
  await expect(wrapper).toBeVisible();

  // Create / compare screenshot of the page wrapper
  await expect(wrapper).toHaveScreenshot('public-guides-baseline.png', { maxDiffPixelRatio: 0.01 });
});
