const { test, expect } = require('@playwright/test');

test('frontend XSS smoke check (renderedHtml + public page)', async ({ page, request }) => {
  // Build API and frontend bases (preserve existing config pattern)
  let apiBase = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend';
  if (!apiBase.endsWith('/')) apiBase = apiBase + '/';
  let frontendBase = process.env.FRONTEND_BASE || apiBase.replace('healthcare-cms-backend', 'healthcare-cms-frontend');
  if (!frontendBase.endsWith('/')) frontendBase = frontendBase + '/';

  // Programmatic login to obtain token
  const loginUrl = new URL('api/auth/login', apiBase).toString();
  let token = null;
  try {
    const res = await request.post(loginUrl, {
      data: { username: 'anna', password: 'password' }
    });
    const json = await res.json();
    if (json && (json.token || json.access_token)) token = json.token || json.access_token;
    // Prefer authenticated user's id as the creator when available
    var creatorId = (json && json.user && (json.user.id || json.user.username)) ? (json.user.id || json.user.username) : null;
  } catch (e) {
    console.log('Login failed:', e && e.message ? e.message : e);
  }

  test.skip(!token, 'No auth token available for API operations');

  // Create a page via API
  const ts = Date.now();
  const slug = `e2e-xss-${ts}`;
  const createUrl = new URL('api/pages', apiBase).toString();
  const pagePayload = {
    title: `E2E XSS Test ${ts}`,
    slug: slug,
    type: 'regular',
    status: 'draft',
    // API requires createdBy/created_by. Use authenticated user id when available, else fallback to e2e-script.
    createdBy: creatorId || 'e2e-script'
  };
  const createRes = await request.post(createUrl, {
    data: pagePayload,
    headers: { 'Authorization': `Bearer ${token}` }
  });
  let createJson = null;
  try {
    createJson = await createRes.json();
    console.log('create response (json):', createJson);
  } catch (err) {
    const bodyText = await createRes.text();
    console.log('create response status:', createRes.status());
    console.log('create response body (truncated):', bodyText.substring(0, 2000));
    throw new Error(`Create page failed: non-JSON response (status=${createRes.status()})`);
  }

  // Extract page id from common response shapes
  let pageId = createJson.page_id || createJson.pageId || createJson.pageId || createJson.pageId;
  if (!pageId && createJson.success && (createJson.pageId || createJson.page_id)) {
    pageId = createJson.pageId || createJson.page_id;
  }
  if (!pageId) {
    // sometimes API returns nested body or different keys; provide clearer diagnostics
    throw new Error('Create page failed: no pageId in response: ' + JSON.stringify(createJson).substring(0, 1000));
  }

  // Prepare malicious renderedHtml payload
  const payloadHtml = `<!doctype html><html><body><div>TEST XSS</div><img src="x" onerror="console.log('ONERROR-${ts}')" /><script>console.log('INLINE_SCRIPT-${ts}');</script></body></html>`;

  // PUT update with renderedHtml
  const putUrl = new URL(`api/pages/${pageId}`, apiBase).toString();
  const putRes = await request.put(putUrl, {
    data: { renderedHtml: payloadHtml },
    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
  });
  const putJson = await putRes.json();
  console.log('put response:', putJson);

  // Publish the page
  const publishUrl = new URL(`api/pages/${pageId}/publish`, apiBase).toString();
  const publishRes = await request.put(publishUrl, { headers: { 'Authorization': `Bearer ${token}` } });
  console.log('publish status:', publishRes.status());

  // Capture console messages when visiting public URL
  const publicUrl = new URL(`p/${slug}`, apiBase).toString();
  console.log('publicUrl ->', publicUrl);

  const messages = [];
  page.on('console', msg => {
    try { messages.push({ type: msg.type(), text: msg.text() }); } catch (e) { messages.push({ type: 'unknown', text: String(msg) }); }
  });

  // Also fetch headers directly to check CSP presence
  const visitorRes = await request.get(publicUrl);
  const visitorText = await visitorRes.text();
  console.log('visitor status:', visitorRes.status());

  // Navigate with Playwright and wait a bit for any JS to run
  await page.goto(publicUrl, { waitUntil: 'networkidle' });
  // Give inline onerror some time
  await page.waitForTimeout(1500);

  console.log('collected console messages:', messages);

  // Check headers for CSP
  const csp = visitorRes.headers()['content-security-policy'] || visitorRes.headers()['Content-Security-Policy'];
  console.log('CSP header:', csp);

  // Determine whether our inline script or onerror executed
  const inlineRan = messages.some(m => m.text && m.text.includes(`INLINE_SCRIPT-${ts}`));
  const onerrorRan = messages.some(m => m.text && m.text.includes(`ONERROR-${ts}`));

  // Report results
  console.log('INLINE_SCRIPT executed?', inlineRan);
  console.log('ONERROR executed?', onerrorRan);

  // Expectations: CSP and no inline execution
  expect(visitorRes.status()).toBe(200);
  // CSP should exist (Phase1 adds it)
  expect(csp).toBeTruthy();
  // Inline script or onerror should NOT have produced console messages
  expect(inlineRan).toBeFalsy();
  expect(onerrorRan).toBeFalsy();
});
