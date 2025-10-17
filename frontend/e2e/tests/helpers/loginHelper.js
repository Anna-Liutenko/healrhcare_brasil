/**
 * Test helper: simulate login for the Vue app in the visual editor.
 * Exposed function: loginAs(page, username)
 */
module.exports = {
  async loginAs(page, username = 'e2e-user') {
    // Set currentUser in the app and hide login modal/overlays
    await page.evaluate((username) => {
      try {
        if (window.app) {
          window.app.currentUser = { username };
          // reactive props - try to update Vue managed modals
          window.app.showLoginModal = false;
          window.app.showGalleryModal = false;
          window.app.showTemplatesModal = false;
        }
      } catch (e) {
        // ignore
      }
      // Also hide any DOM modal overlays that might block clicks
      document.querySelectorAll('.modal-overlay').forEach(el => el.style.display = 'none');
      // remove intrusive debug panel if present
      const dbg = document.querySelector('.debug-panel');
      if (dbg) dbg.style.display = 'none';
    }, username);

    // Wait until toolbar button(s) appear
    const btn = page.locator('button:has-text("Написать"), button:has-text("Написать статью"), button:has-text("✍")').first();
    await btn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => null);
    return btn;
  }
};