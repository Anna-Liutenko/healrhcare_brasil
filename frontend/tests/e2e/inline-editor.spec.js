const { test, expect } = require('@playwright/test');

const path = require('path');
const PAGE_ID = process.env.PAGE_ID || 'a1b2c3d4-e5f6-7890-abcd-ef1234567891';
const testHarnessPath = path.resolve(__dirname, '../inline-editor-test.html');
const TEST_FILE = `file://${testHarnessPath}`;

test('inline edit autosave', async ({ page }) => {
  await page.goto(TEST_FILE);
  
  // Wait for test harness to complete (it runs automatically)
  await page.waitForFunction(() => {
    const result = document.getElementById('result');
    return result && (result.textContent === 'PASS' || result.textContent === 'FAIL' || result.textContent.startsWith('ERROR'));
  }, { timeout: 10000 });
  
  const result = await page.textContent('#result');
  
  // Log console messages from test page
  const logs = await page.evaluate(() => {
    return window.__testLogs || [];
  });
  
  console.log('Test harness result:', result);
  console.log('Test logs:', logs);
  
  expect(result).toBe('PASS');
});
