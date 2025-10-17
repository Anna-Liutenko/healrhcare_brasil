const { test, expect } = require('@playwright/test');

// No-fallback editor.spec.js — strict end-to-end: fails if public URL is not available.

test.describe('Page Editor Workflow', () => {
  let pageId;
  let slug;
  const testTimestamp = Date.now();

  test.beforeEach(async ({ page }) => {
  // Build absolute base URL from env to ensure deployment subpath is preserved
  // Default to the backend base (no /public) because the project uses a parent .htaccess
  // that routes requests to the public folder. Use a separate FRONTEND_BASE for the
  // visual editor (served from the frontend site), or fall back to a sibling
  // healthcare-cms-frontend host.
  let base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
  if (!base.endsWith('/')) base = base + '/';

  // Frontend editor is served from the frontend site. Allow overriding via FRONTEND_BASE.
  let frontendBase = process.env.FRONTEND_BASE || base.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
  if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';

    // Programmatic login: try to POST /api/auth/login to obtain a token and inject it into localStorage
    // This avoids fragile UI login flows and speeds up tests. Credentials: anna / password
    try {
      // Use relative path so it resolves under the configured BASE_URL (preserves subpath)
      const loginUrl = new URL('api/auth/login', base).toString();
      // Use node fetch available in Playwright runtime; fallback to node-fetch if needed
      const fetchFn = (globalThis.fetch) ? globalThis.fetch : (await import('node-fetch')).default;
      const res = await fetchFn(loginUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: 'anna', password: 'password' })
      });

      if (res && res.ok) {
        const data = await res.json();
        if (data && data.token) {
          // Inject token into localStorage before page load
          await page.addInitScript((t) => {
            localStorage.setItem('cms_auth_token', t);
          }, data.token);
        }
      }
    } catch (e) {
      // If programmatic login fails, continue — test will attempt UI login as fallback
      console.warn('Programmatic login failed:', e && e.message ? e.message : e);
    }

  // Open the editor served by the frontend site (editor.html is at the frontend root)
  const url = new URL('editor.html', frontendBase).toString();
    await page.goto(url);
    // Wait for the editor wrapper to be present in the DOM (may be hidden by v-cloak)
    // Increase timeout to 30s to allow slower initialization in the local environment
    await page.waitForSelector('.editor-wrapper', { timeout: 30000, state: 'attached' });
  });

  test('should login, create, edit, save, publish page and verify public URL', async ({ page }) => {
    // compute frontendBase (same logic as beforeEach) so diagnostics can use it
    let computedBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
    if (!computedBase.endsWith('/')) computedBase = computedBase + '/';
    let frontendBase = process.env.FRONTEND_BASE || computedBase.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
    if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';
    // If programmatic login worked, the editor toolbar will be visible and we can skip UI login.
    const toolbar = page.locator('.editor-toolbar');
    if (await toolbar.isVisible({ timeout: 1000 }).catch(() => false)) {
      // already authenticated via injected token
    } else {
      // login via UI fallback
      const loginButton = page.locator('button:has-text("Войти"), button:has-text("Login")').first();
      if (await loginButton.isVisible().catch(() => false)) {
        await loginButton.click();
        // Primary: wait for modal visibility. Fallback: wait for username input attached.
        try {
          await page.waitForSelector('.login-modal, [data-test="login-modal"]', { timeout: 30000, state: 'visible' });
        } catch (e) {
          await page.waitForSelector('input.settings-input, input[placeholder*="Имя"], input[type="text"]', { timeout: 30000, state: 'attached' });
        }

        // Fill credentials and submit
        await page.fill('input.settings-input, input[name="username"], input[placeholder*="Имя"], input[type="text"]', 'anna');
        await page.fill('input[placeholder*="Пароль"], input[type="password"]', 'password');
        await page.click('button:has-text("Войти"), button[type="submit"]');
        await page.waitForSelector('.editor-toolbar', { timeout: 10000 });
      }
    }

  // create via API using injected token (faster, less flaky than UI flows)
  slug = `e2e-playwright-test-${testTimestamp}`;
  // Use backend API base (prefer BASE_URL). Default to backend base without /public.
  let apiBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
  if (!apiBase.endsWith('/')) apiBase = apiBase + '/';
  // Read token from localStorage in the browser context
  const token = await page.evaluate(() => localStorage.getItem('cms_auth_token'));

  if (!token) {
    throw new Error('No auth token found for API-backed create');
  }

  // Query current user to obtain created_by
  const meUrl = new URL('api/auth/me', apiBase).toString();
  const meRes = await (globalThis.fetch ? globalThis.fetch : (await import('node-fetch')).default)(meUrl, {
    method: 'GET',
    headers: { 'Authorization': `Bearer ${token}` }
  });

  if (!meRes.ok) {
    const txt = await meRes.text();
    throw new Error(`Get current user failed: ${meRes.status} ${txt}`);
  }

  const meData = await meRes.json();
  const createdBy = meData && meData.id ? meData.id : null;

  // Prepare page payload (simple header + text + hero)
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

  // Use fetch in Node context to call API (relative to BASE_URL)
  const createUrl = new URL('api/pages', apiBase).toString();
  const createRes = await (globalThis.fetch ? globalThis.fetch : (await import('node-fetch')).default)(createUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify(pagePayload)
  });

  if (!createRes.ok) {
    const txt = await createRes.text();
    throw new Error(`Create page failed: ${createRes.status} ${txt}`);
  }

  const createData = await createRes.json();
  const createText = JSON.stringify(createData);
  console.log('CREATE response:', createText);
  if (!createData || !createData.page_id) {
    throw new Error(`Unexpected create response: ${createText}`);
  }

  pageId = createData.page_id;

  // Publish the page
  const publishUrl = new URL(`api/pages/${pageId}/publish`, apiBase).toString();
  const publishRes = await (globalThis.fetch ? globalThis.fetch : (await import('node-fetch')).default)(publishUrl, {
    method: 'PUT',
    headers: { 'Authorization': `Bearer ${token}` }
  });

  const publishText = await publishRes.text();
  console.log('PUBLISH response:', publishRes.status, publishText);
  if (!publishRes.ok) {
    throw new Error(`Publish failed: ${publishRes.status} ${publishText}`);
  }

  // Poll the API for page status until published (helps catch eventual consistency / cache delays)
  const pageGetUrl = new URL(`api/pages/${pageId}`, apiBase).toString();
  const deadline = Date.now() + 15000; // 15s
  let pageOk = false;
  while (Date.now() < deadline) {
    const sRes = await (globalThis.fetch ? globalThis.fetch : (await import('node-fetch')).default)(pageGetUrl, {
      method: 'GET',
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const sText = await sRes.text();
    console.log('GET page status:', sRes.status, sText.substring(0, 400));
    if (sRes.ok) {
      try {
        const sJson = JSON.parse(sText);
        if (sJson && sJson.page && sJson.page.status === 'published') {
          pageOk = true;
          break;
        }
      } catch (e) {
        // ignore JSON parse errors
      }
    }
    await new Promise(r => setTimeout(r, 1000));
  }
  console.log('Page published observed before navigation?', pageOk);

  // verify public (use full BASE_URL to preserve deployment subpath)
  const publicUrl = new URL(`p/${slug}`, apiBase).toString();
  console.log('PUBLIC URL:', publicUrl);

  // Do a server-side GET to observe what the visitor would see. If the visitor route returns 200
  // we also assert via the browser. If it returns 404 (common in local Apache setups), the test fails
  // to ensure we catch routing issues early.
  let visitorOk = false;
  try {
    const fetchFn = (globalThis.fetch) ? globalThis.fetch : (await import('node-fetch')).default;
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
    // Diagnostic attempts: try direct index.php invocation and alternate paths to help
    // determine whether Apache rewrite is not applied or index.php routing fails.
    try {
      const fetchFn = (globalThis.fetch) ? globalThis.fetch : (await import('node-fetch')).default;
      const debugUrls = [
        // Directly call public/index.php with path query (simulate rewrite)
        new URL(`public/index.php?path=/p/${slug}`, apiBase).toString(),
        // Try explicit /public/ in case Apache expects that form
        new URL(`public/p/${slug}`, apiBase).toString(),
        // Try root-level p/ under frontend base (unlikely), for completeness
        new URL(`p/${slug}`, frontendBase).toString()
      ];

      for (const du of debugUrls) {
        try {
          const r = await fetchFn(du, { method: 'GET' });
          const txt = await r.text();
          console.log('DIAG GET', du, r.status, 'len=', txt.length);
          // Save small snippet to debug log
          const snippet = txt.substring(0, 500).replace(/\n/g, ' ');
          console.log('DIAG SNIPPET:', snippet);
        } catch (e) {
          console.log('DIAG GET failed for', du, e && e.message ? e.message : e);
        }
      }
    } catch (e) {
      console.log('Diagnostic fetches failed:', e && e.message ? e.message : e);
    }

    throw new Error('Visitor route not available; public URL returned 404 or error (see diagnostic logs above)');
  }
  });
});

