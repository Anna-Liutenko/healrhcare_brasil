const { test, expect } = require('@playwright/test');

test('debug: dump toolbar and app state', async ({ page }) => {
  await page.goto('http://localhost/visual-editor-standalone/editor.html');
  await page.waitForLoadState('networkidle');

  // capture screenshot of page
  await page.screenshot({ path: 'test-results/debug-toolbar-before.png', fullPage: true });

  // Evaluate app and toolbar state
  const info = await page.evaluate(() => {
    const app = window.app || null;
    const toolbar = document.querySelector('.editor-toolbar');
    const toolbarActions = toolbar ? Array.from(toolbar.querySelectorAll('*')).map(el => ({tag: el.tagName, classes: el.className, text: (el.textContent||'').trim().slice(0,60)})) : null;
    const toolbarBtns = Array.from(document.querySelectorAll('.toolbar-btn')).map(el => ({tag: el.tagName, classes: el.className, text: (el.textContent||'').trim().slice(0,80), visible: !!(el.offsetWidth||el.offsetHeight)}));

    // find overlays and high z-index elements
    const overlays = Array.from(document.querySelectorAll('body *')).filter(el => {
      const cs = getComputedStyle(el);
      const pos = cs.position;
      if(!/fixed|absolute|sticky/.test(pos)) return false;
      const z = parseInt(cs.zIndex) || 0;
      return z >= 100 || el.className && /overlay|modal|backdrop|gallery|debug-panel/i.test(el.className);
    }).map(el => ({tag: el.tagName, classes: el.className, z: getComputedStyle(el).zIndex, rect: el.getBoundingClientRect()}));

    return {
      hasApp: !!app,
      appKeys: app ? Object.keys(app).slice(0,20) : null,
      currentUser: app && app.currentUser ? app.currentUser : null,
      showArticleEditor: app ? app.showArticleEditor : null,
      toolbarExists: !!toolbar,
      toolbarActions,
      toolbarBtns,
      overlays
    };
  });

  console.log('DIAGNOSTIC INFO:', JSON.stringify(info, null, 2));

  // also save page HTML snapshot
  const html = await page.content();
  const fs = require('fs');
  fs.writeFileSync('test-results/debug-toolbar-page.html', html);

  // final assert to avoid test passing silently
  expect(info).toBeTruthy();
});