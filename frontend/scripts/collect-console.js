const { chromium } = require('@playwright/test');

(async () => {
  const results = { console: [], errors: [], requests: [] };
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('console', msg => {
    results.console.push({ type: msg.type(), text: msg.text() });
  });
  page.on('pageerror', err => {
    results.errors.push(String(err));
  });
  page.on('requestfailed', req => {
    results.requests.push({ url: req.url(), failure: req.failure()?.errorText || 'unknown' });
  });

  const base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend/public';
  const url = base + '/frontend/editor.html';
  console.log('Navigating to', url);
  try {
    const resp = await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
    console.log('Response status:', resp && resp.status());
  } catch (e) {
    console.error('Navigation error:', e.message);
  }

  // wait a little for scripts to run
  await page.waitForTimeout(3000);

  console.log('--- Console ---');
  console.log(JSON.stringify(results.console, null, 2));
  console.log('--- Page errors ---');
  console.log(JSON.stringify(results.errors, null, 2));
  console.log('--- Request failures ---');
  console.log(JSON.stringify(results.requests, null, 2));

  await browser.close();

  // exit with code 1 if any page errors
  process.exit(results.errors.length > 0 ? 1 : 0);
})();