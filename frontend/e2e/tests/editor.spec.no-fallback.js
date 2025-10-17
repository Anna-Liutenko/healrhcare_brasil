const { test, expect } = require('@playwright/test');

// Variant of editor.spec.js with API-fallback removed.
// Use this file to review the changes before replacing the original test.
// Important: apply only after you've confirmed visitor GET returns 200 reliably.

test.describe('Page Editor Workflow (no API-fallback)', () => {
  let pageId;
  let slug;
  const testTimestamp = Date.now();

  test.beforeEach(async ({ page }) => {
    let base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend/public';
    if (!base.endsWith('/')) base = base + '/';

    try {
      const loginUrl = new URL('api/auth/login', base).toString();
      const fetchFn = (globalThis.fetch) ? globalThis.fetch : (await import('node-fetch')).default;
      const res = await fetchFn(loginUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: 'anna', password: 'password' })
      });

      if (res && res.ok) {
        const data = await res.json();
        if (data && data.token) {
          await page.addInitScript((t) => {
            localStorage.setItem('cms_auth_token', t);
          }, data.token);
        }
      }
    } catch (e) {
      console.warn('Programmatic login failed:', e && e.message ? e.message : e);
    }

    const url = new URL('frontend/editor.html', base).toString();
    await page.goto(url);
    await page.waitForSelector('.editor-wrapper', { timeout: 30000, state: 'attached' });
  });

  test('should login, create, publish page and verify public URL (no fallback)', async ({ page }) => {
    const toolbar = page.locator('.editor-toolbar');
    if (await toolbar.isVisible({ timeout: 1000 }).catch(() => false)) {
      // already authenticated
    } else {
      const loginButton = page.locator('button:has-text("Войти"), button:has-text("Login")').first();
      if (await loginButton.isVisible().catch(() => false)) {
        await loginButton.click();
        try {
          await page.waitForSelector('.login-modal, [data-test="login-modal"]', { timeout: 30000, state: 'visible' });
        } catch (e) {
          await page.waitForSelector('input.settings-input, input[placeholder*="Имя"], input[type="text"]', { timeout: 30000, state: 'attached' });
        }

        await page.fill('input.settings-input, input[name="username"], input[placeholder*="Имя"], input[type="text"]', 'anna');
        await page.fill('input[placeholder*="Пароль"], input[type="password"]', 'password');
        await page.click('button:has-text("Войти"), button[type="submit"]');
        await page.waitForSelector('.editor-toolbar', { timeout: 10000 });
      }
    }

    // create via API
    slug = `e2e-playwright-test-${testTimestamp}`;
    let apiBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend/public';
    if (!apiBase.endsWith('/')) apiBase = apiBase + '/';
    const token = await page.evaluate(() => localStorage.getItem('cms_auth_token'));
    if (!token) throw new Error('No auth token found for API-backed create');

    const meUrl = new URL('api/auth/me', apiBase).toString();
    const fetchFn = (globalThis.fetch) ? globalThis.fetch : (await import('node-fetch')).default;
    const meRes = await fetchFn(meUrl, { method: 'GET', headers: { 'Authorization': `Bearer ${token}` } });
    if (!meRes.ok) {
      const txt = await meRes.text();
      throw new Error(`Get current user failed: ${meRes.status} ${txt}`);
    }
    const meData = await meRes.json();
    const createdBy = meData && meData.id ? meData.id : null;

    const pagePayload = {
      title: `E2E Playwright Test ${testTimestamp}`,
      slug: slug,
      type: 'regular',
      status: 'draft',
      createdBy: createdBy,
      seo: { meta_title: '', meta_description: '', meta_keywords: '' },
      tracking: { page_specific_code: '' },
      blocks: [
        { type: 'page-header', position: 0, custom_name: 'Page Header', data: { title: `E2E Playwright Test ${testTimestamp}` } },
        { type: 'text-block', position: 1, custom_name: null, data: { content: '<p>This is E2E test content created by Playwright.</p>' } },
        { type: 'main-screen', position: 2, custom_name: 'Hero', data: { title: 'E2E Hero Heading', subtitle: 'E2E Hero Subheading' } }
      ]
    };

    const createUrl = new URL('api/pages', apiBase).toString();
    const createRes = await fetchFn(createUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify(pagePayload)
    });
    if (!createRes.ok) {
      const txt = await createRes.text();
      throw new Error(`Create page failed: ${createRes.status} ${txt}`);
    }

    const createData = await createRes.json();
    console.log('CREATE response:', JSON.stringify(createData));
    if (!createData || !createData.page_id) throw new Error('Unexpected create response');
    pageId = createData.page_id;

    const publishUrl = new URL(`api/pages/${pageId}/publish`, apiBase).toString();
    const publishRes = await fetchFn(publishUrl, { method: 'PUT', headers: { 'Authorization': `Bearer ${token}` } });
    const publishText = await publishRes.text();
    console.log('PUBLISH response:', publishRes.status, publishText);
    if (!publishRes.ok) throw new Error(`Publish failed: ${publishRes.status} ${publishText}`);

    // Poll the API for page status
    const pageGetUrl = new URL(`api/pages/${pageId}`, apiBase).toString();
    const deadline = Date.now() + 15000; // 15s
    let pageOk = false;
    while (Date.now() < deadline) {
      const sRes = await fetchFn(pageGetUrl, { method: 'GET', headers: { 'Authorization': `Bearer ${token}` } });
      const sText = await sRes.text();
      console.log('GET page status:', sRes.status, sText.substring(0, 400));
      if (sRes.ok) {
        try { const sJson = JSON.parse(sText); if (sJson && sJson.page && sJson.page.status === 'published') { pageOk = true; break; } } catch (e) {}
      }
      await new Promise(r => setTimeout(r, 1000));
    }
    console.log('Page published observed before navigation?', pageOk);

    const publicUrl = new URL(`p/${slug}`, apiBase).toString();
    console.log('PUBLIC URL:', publicUrl);

    // Robust check: poll the public URL (server-side) for up to 15s until it returns 200 and doesn't contain 404-text
    let visitorOk = false;
    const visitorDeadline = Date.now() + 15000;
    let lastVisitorText = '';
    while (Date.now() < visitorDeadline) {
      try {
        const vRes = await fetchFn(publicUrl, { method: 'GET' });
        lastVisitorText = await vRes.text();
        console.log('VISITOR GET:', vRes.status, lastVisitorText.substring(0,300));
        if (vRes.status === 200 && !lastVisitorText.includes('Страница не найдена')) { visitorOk = true; break; }
      } catch (e) {
        console.log('VISITOR GET failed:', e && e.message ? e.message : e);
      }
      await new Promise(r => setTimeout(r, 1000));
    }

    if (!visitorOk) {
      throw new Error(`Visitor GET did not return 200 within timeout. Last snippet: ${lastVisitorText.substring(0,200)}`);
    }

    // Navigate the browser to the public URL and assert page content
    await page.goto(publicUrl);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('This is E2E test content created by Playwright.');
    await expect(page.locator('body')).toContainText('E2E Hero Heading');
    await expect(page.locator('body')).toContainText('E2E Hero Subheading');
  });
});
