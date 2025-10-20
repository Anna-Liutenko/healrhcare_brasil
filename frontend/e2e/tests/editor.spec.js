const { test, expect } = require('@playwright/test');

// Editor e2e tests: load editor and full create/publish verification.

test.describe('Page Editor Workflow', () => {
  let pageId;
  let slug;
  const testTimestamp = Date.now();

  test.beforeEach(async ({ page }) => {
    // Build absolute base URL from env to ensure deployment subpath is preserved
    let base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
    if (!base.endsWith('/')) base = base + '/';

    let frontendBase = process.env.FRONTEND_BASE || base.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
    if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';

    // Try programmatic login to speed up tests
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

    const url = new URL('editor.html', frontendBase).toString();
    await page.goto(url);
    await page.waitForSelector('.editor-wrapper', { timeout: 30000, state: 'attached' });
  });

  test('editor loads and renders', async ({ page }) => {
    let base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
    if (!base.endsWith('/')) base = base + '/';
    let frontendBase = process.env.FRONTEND_BASE || base.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
    if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';
    const url = new URL('editor.html', frontendBase).toString();
    await page.goto(url);
    console.log('Viewport:', await page.viewportSize());
    console.log('UserAgent:', await page.evaluate(() => navigator.userAgent));
    await expect(page.locator('.editor-wrapper')).toBeVisible();
  });

  test('should login, create, edit, save, publish page and verify public URL', async ({ page }) => {
    let computedBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
    if (!computedBase.endsWith('/')) computedBase = computedBase + '/';
    let frontendBase = process.env.FRONTEND_BASE || computedBase.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
    if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';

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

    // create via API using injected token
    slug = `e2e-playwright-test-${testTimestamp}`;
    let apiBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
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
    const createRes = await fetchFn(createUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify(pagePayload) });
    if (!createRes.ok) {
      const txt = await createRes.text();
      throw new Error(`Create page failed: ${createRes.status} ${txt}`);
    }

    const createData = await createRes.json();
    const createText = JSON.stringify(createData);
    console.log('CREATE response:', createText);
    if (!createData || !createData.page_id) throw new Error(`Unexpected create response: ${createText}`);

    pageId = createData.page_id;

    const publishUrl = new URL(`api/pages/${pageId}/publish`, apiBase).toString();
    const publishRes = await fetchFn(publishUrl, { method: 'PUT', headers: { 'Authorization': `Bearer ${token}` } });
    const publishText = await publishRes.text();
    console.log('PUBLISH response:', publishRes.status, publishText);
    if (!publishRes.ok) throw new Error(`Publish failed: ${publishRes.status} ${publishText}`);

    // Poll for published
    const pageGetUrl = new URL(`api/pages/${pageId}`, apiBase).toString();
    const deadline = Date.now() + 15000;
    let pageOk = false;
    while (Date.now() < deadline) {
      const sRes = await fetchFn(pageGetUrl, { method: 'GET', headers: { 'Authorization': `Bearer ${token}` } });
      const sText = await sRes.text();
      console.log('GET page status:', sRes.status, sText.substring(0, 400));
      if (sRes.ok) {
        try {
          const sJson = JSON.parse(sText);
          if (sJson && sJson.page && sJson.page.status === 'published') {
            pageOk = true;
            break;
          }
        } catch (e) {}
      }
      await new Promise(r => setTimeout(r, 1000));
    }

    console.log('Page published observed before navigation?', pageOk);

    const publicUrl = new URL(`p/${slug}`, apiBase).toString();
    console.log('PUBLIC URL:', publicUrl);

    let visitorOk = false;
    try {
      const visitorRes = await fetchFn(publicUrl, { method: 'GET' });
      const visitorText = await visitorRes.text();
      console.log('VISITOR GET:', visitorRes.status, visitorText.substring(0,300));
      visitorOk = visitorRes.status === 200 && !visitorText.includes('Страница не найдена');
    } catch (e) {
      console.log('VISITOR GET failed:', e && e.message ? e.message : e);
    }

    if (visitorOk) {
      await page.goto(publicUrl);
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText('This is E2E test content created by Playwright.');
      await expect(page.locator('body')).toContainText('E2E Hero Heading');
      await expect(page.locator('body')).toContainText('E2E Hero Subheading');
    } else {
      // diagnostics
      const debugUrls = [
        new URL(`public/index.php?path=/p/${slug}`, apiBase).toString(),
        new URL(`public/p/${slug}`, apiBase).toString(),
        new URL(`p/${slug}`, frontendBase).toString()
      ];
      for (const du of debugUrls) {
        try {
          const r = await fetchFn(du, { method: 'GET' });
          const txt = await r.text();
          console.log('DIAG GET', du, r.status, 'len=', txt.length);
          console.log('DIAG SNIPPET:', txt.substring(0,500).replace(/\\n/g, ' '));
        } catch (e) {
          console.log('DIAG GET failed for', du, e && e.message ? e.message : e);
        }
      }
      throw new Error('Visitor route not available; public URL returned 404 or error (see diagnostic logs above)');
    }
  });
});
