Цель: дать простую, пошаговую инструкцию для LLM с низкой "квалификацией" чтобы он выполнил серию задач по автоматизации E2E и улучшению UI уведомлений.

Задачи (выполняй по порядку, без вопросов):
1) Автоматизировать тест в Playwright / добавить package.json script для запуска E2E.
2) Заменить confirm() на non-blocking UI modal.
3) Перенести visual notification в header/toolbar вместо правого-нижнего угла.
4) Запустить quick manual E2E (если backend запущен и вы откроете editor.html?id=...), или
5) Обновить template-manager confirm -> inline modal.

Файлы, с которыми будете работать (точные пути):
- frontend/editor.html
- frontend/js/InlineEditorManager.js
- frontend/js/inline-notify.js
- frontend/js/inline-modal.js  (создать)
- frontend/css/inline-editor.css
- frontend/template-manager.html
- frontend/tests/inline-editor-test.html
- package.json (создать/обновить в корне frontend)
- playwright.config.js (создать в корне frontend)
- tests/e2e/inline-editor.spec.js (создать тест для Playwright)

Общие указания для LLM с низкой квалификацией:
- Не спрашивай пользователя. Делай изменения в файлах прямо в репозитории и сохраняй.
- Держи изменения минимальными и локальными; используй plain JS/CSS/HTML.
- После каждой операции сохраняй файлы и продолжай.
- Вставляй код ровно как в примерах ниже, не добавляй сложных абстракций.

Часть 1 — Playwright E2E (минимальная настройка)
A. package.json
1. В корне `frontend/` либо в корне репозитория (если нет package.json в frontend), создать/обновить `package.json` с минимальным содержимым:

{
  "name": "healthcare-cms-frontend-tests",
  "version": "0.0.1",
  "private": true,
  "devDependencies": {
    "@playwright/test": "^1.46.0"
  },
  "scripts": {
    "test:e2e": "npx playwright test --project=chromium"
  }
}

2. Не выполняй npm install — просто добавь файл. (Если есть доступ к install later, пользователь запустит `npm install` самостоятельно.)

B. playwright.config.js (в `frontend/`)
Создать файл с минимальной конфигурацией:

const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: { headless: true, viewport: { width: 1280, height: 800 } },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }]
});

C. Тест для inline editor (tests/e2e/inline-editor.spec.js)
Создать тест, который:
- Открывает `file:///<path-to-repo>/frontend/editor.html?id=<page-id>` (используй process.env.PAGE_ID или  hardcode 'a1b2c3d4-e5f6-7890-abcd-ef1234567891' если env undefined).
- Нажимает кнопку Enable Inline Editing
- Находит первый элемент с attribute data-inline-editable="true"
- Кликает и изменяет текст
- Ждёт появление network PATCH или визуальной подсказки

Пример теста (пиши ровно как здесь):

const { test, expect } = require('@playwright/test');

const PAGE_ID = process.env.PAGE_ID || 'a1b2c3d4-e5f6-7890-abcd-ef1234567891';
const EDITOR_FILE = `file://${process.cwd()}/frontend/editor.html?id=${PAGE_ID}`;

test('inline edit autosave', async ({ page }) => {
  await page.goto(EDITOR_FILE);
  // wait for editor scripts
  await page.waitForSelector('#toggleInlineMode');
  await page.click('#toggleInlineMode');
  // find an editable element
  const editable = await page.waitForSelector('[data-inline-editable="true"]', { timeout: 5000 });
  await editable.click();
  await editable.fill('Playwright change');
  // wait for 3s to allow autosave
  await page.waitForTimeout(3000);
  // check for notification element or other hint
  const saved = await page.evaluate(() => !!document.querySelector('.inline-saved'));
  expect(saved).toBeTruthy();
});

D. Комментарий: тест использует file:// scheme; если backend must be running for PATCH, either run server or mock network. For a basic smoke-run, presence of inline-saved element is sufficient.

Часть 2 — Replace confirm() with non-blocking modal
A. Создать `frontend/js/inline-modal.js` — минимальная реализация, возвращающая Promise<boolean>:

// frontend/js/inline-modal.js
(function(){
  function ensure(){
    let c = document.getElementById('inline-modal-root');
    if(!c){ c = document.createElement('div'); c.id='inline-modal-root'; document.body.appendChild(c);} return c;
  }
  window.inlineConfirm = function(message){
    return new Promise(resolve => {
      const root = ensure();
      root.innerHTML = `
        <div class="inline-modal-backdrop">
          <div class="inline-modal">
            <div class="inline-modal-body">${message}</div>
            <div class="inline-modal-actions">
              <button id="inline-confirm-yes">Да</button>
              <button id="inline-confirm-no">Нет</button>
            </div>
          </div>
        </div>
      `;
      document.getElementById('inline-confirm-yes').addEventListener('click', ()=>{ root.innerHTML=''; resolve(true); });
      document.getElementById('inline-confirm-no').addEventListener('click', ()=>{ root.innerHTML=''; resolve(false); });
    });
  }
})();

B. Добавить CSS стили для modal в `frontend/css/inline-editor.css` (простые стили: backdrop, modal box, buttons).

C. В `frontend/template-manager.html` заменить usages of `confirm(...)` with `await inlineConfirm(...)` — because template is plain script, modify it to use async function for importTemplate and call: `const ok = await inlineConfirm(...); if (!ok) return;`.
- Hint: wrap existing code of importTemplate into `async function importTemplate(slug, title) { ... }` (already is async), so you can `const ok = await inlineConfirm(...)` at top.

Часть 3 — Move visual notifications to header/toolbar
A. In `frontend/editor.html` add a small notifications container in toolbar, e.g. inside `.toolbar-actions` add `<div id="header-notify" class="header-notify"></div>` next to buttons.
B. Modify `inline-notify.js` to prefer header container if present: if `document.getElementById('header-notify')` exists, append message there and style accordingly; otherwise fallback to bottom-right container.
C. Update CSS in `frontend/css/inline-editor.css` to style `#header-notify` messages (small badge stack, right-aligned inside toolbar).

Часть 4 — Quick manual E2E run (instructions for the user/LLM)
- If backend is running and editor.html is accessible, run the Playwright test locally (after npm install). Otherwise, open `frontend/editor.html?id=<page-id>` manually in browser and perform the manual test:
  1. Click Enable Inline Editing
  2. Edit a field
  3. Observe autosave (green flash) and/or header notification

Часть 5 — Update template-manager confirm -> inline modal
- After implementing `inlineConfirm` and including `inline-modal.js` in `template-manager.html`, replace `confirm(...)` calls with `const ok = await inlineConfirm(...); if (!ok) return;` and show success/error using `inlineNotify` instead of alert.

What to return when done (plain text):
- List of created files
- List of modified files
- How to run Playwright test locally (one-liner commands)
- Any assumptions or blockers encountered

---

Конец промпта. Следуй инструкциям шаг за шагом и делай изменения прямо в репозитории.