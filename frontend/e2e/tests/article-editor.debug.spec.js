const { test, expect } = require('@playwright/test');

test('debug open article editor', async ({ page }) => {
  const logs = [];
  page.on('console', msg => logs.push(`[console] ${msg.type()}: ${msg.text()}`));
  page.on('pageerror', err => logs.push(`[pageerror] ${err.message}`));

  await page.goto('http://localhost/visual-editor-standalone/editor.html');

  // Wait for app to mount
  await page.waitForFunction(() => !!window.app, { timeout: 10000 });

  const hasMethod = await page.evaluate(() => typeof window.app.openArticleEditor === 'function');

  // Call the method if it exists
  if (hasMethod) {
    await page.evaluate(() => {
      try {
        window.app.openArticleEditor();
      } catch (e) {
        console.error('call openArticleEditor failed', e);
        throw e;
      }
    });
  }

  // Wait for Quill editor or its container
  let quillFound = false;
  try {
    await page.waitForSelector('#quill-editor, .ql-editor', { timeout: 10000 });
    quillFound = true;
  } catch (e) {
    quillFound = false;
  }

  // Dump logs to test output
  console.log('hasMethod=', hasMethod);
  console.log('quillFound=', quillFound);
  if (logs.length) {
    console.log('--- Console logs ---');
    for (const l of logs) console.log(l);
    console.log('--- End logs ---');
  }

  expect(hasMethod).toBe(true);
  expect(quillFound).toBe(true);
});
