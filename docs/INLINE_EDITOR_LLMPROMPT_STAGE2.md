Цель: дать простую, пошаговую инструкцию для LLM с низкой "квалификацией" чтобы он выполнил три задачи в репозитории фронтенда
1) добавить минимальный unit-тест для undo-stack и автосейва
2) подключить Turndown.js статически в `frontend/editor.html`, если его там нет
3) заменить `alert(...)` на некогда блокирующие уведомления `inlineNotify(message, type)` и внедрить простую реализацию

Требования к LLM: НЕ ЗАДАВАТЬ ВОПРОСОВ, РАБОТАТЬ С ФАЙЛАМИ РЕПОЗИТОРИЯ, СОБЛЮДАТЬ ИМЕНА ФАЙЛОВ И ПУТИ.

Файлы, в которых вносим изменения:
- frontend/js/InlineEditorManager.js
- frontend/css/inline-editor.css
- frontend/editor.html
- docs/ (создаём тестовый файл и/или вспомогательные файлы)

Часть A — Подключение Turndown.js
1. Открой `frontend/editor.html`.
2. Ищи строку, содержащую `turndown` или `TurndownService` или `<script` с CDN для turndown. Если строка найдена — ничего не менять.
3. Если не найдена, вставить в секцию `<head>` ДО любых скриптов, использующих Turndown, следующую строку точно (CDN):

   <script src="https://cdn.jsdelivr.net/npm/turndown@8.1.0/dist/turndown.js"></script>

   - Добавь однострочный HTML-комментарий над строкой: <!-- Turndown.js: needed for HTML->Markdown conversion in inline editor -->

4. Сохранить файл.

Часть B — Замена alert() на inlineNotify
1. Создать файл `frontend/js/inline-notify.js` с минимальной функцией `window.inlineNotify(message, type)` где `type` может быть `'info'|'success'|'error'`. Функция должна:
   - Создавать контейнер div#inline-notify-container в body при первом вызове.
   - Внутри контейнера добавлять элемент уведомления с классом `inline-notify inline-notify-{type}`.
   - Автоматически убирать уведомление через 3000ms.
   - Не использовать сторонние библиотеки.
2. Добавить стили в `frontend/css/inline-editor.css` для `.inline-notify`, `.inline-notify-success`, `.inline-notify-error`.
3. В `frontend/editor.html` подключить `frontend/js/inline-notify.js` перед `editor.js` или перед `InlineEditorManager.js` так, чтобы `inlineNotify` было доступно.
4. В `frontend/js/InlineEditorManager.js` заменить все `alert(...)` вызовы на `inlineNotify(message, 'error')` или соответствующий тип. Якщо alert используется для успеха — заменить на inlineNotify(..., 'success').

Часть C — Минимальный unit-test для undo-stack / автосейва
1. Создать простой test harness без дополнительных фреймворков: файл `frontend/tests/inline-editor-test.html`.
2. В `inline-editor-test.html` положить минимальную страницу, которая подключает:
   - `frontend/js/InlineEditorManager.js`
   - `frontend/js/inline-notify.js` (чтобы не было alert)
   - небольшой скрипт, который создаёт в body элемент `<div id="preview"><p data-inline-editable="true" data-block-id="test-block" data-field-path="data.text">Initial</p></div>`
   - инициализирует `new InlineEditorManager(document.getElementById('preview'), 'test-page')`.
3. Написать скрипт в этом HTML, который:
   - Находит элемент editable
   - Вызывает manager.startEdit(el)
   - Меняет innerHTML el.innerHTML = 'First change'
   - Ждёт с помощью setTimeout 2200ms и проверяет, что был попытка сохранения — для этого можно заменить `fetch` глобально временно в тесте: переопределить `window.fetch` в тестовом хосте на функцию, которая будет логировать вызов в `window.__lastFetch`.
   - Тест должен проверить: после 2200ms `window.__lastFetch` содержит объект с url, и body содержит JSON с `blockId: 'test-block'` и markdown/ html с 'First change'.
   - Аналогично проверить Undo: вызвать manager.undo() после pushUndoState и сверить, что innerHTML вернулся к предыдущему.
4. Сделать тест самопроверяющимся: выводит PASS/FAIL в DOM и в консоль.

Примеры конкретной кода-замены и подсказки для LLM (делай точно как ниже):
- Для вставки Turndown.js используй точную строку, указанную выше.
- Для inline-notify.js используй следующий минимальный код (пример):

```javascript
// файл: frontend/js/inline-notify.js
(function(){
  function ensureContainer(){
    let c = document.getElementById('inline-notify-container');
    if(!c){ c = document.createElement('div'); c.id='inline-notify-container'; c.style.position='fixed'; c.style.right='1rem'; c.style.bottom='1rem'; c.style.zIndex='99999'; document.body.appendChild(c);} 
    return c;
  }
  window.inlineNotify = function(message, type){
    try{
      const c = ensureContainer();
      const el = document.createElement('div');
      el.className = 'inline-notify inline-notify-'+(type||'info');
      el.textContent = message;
      el.style.marginTop='0.5rem'; el.style.padding='0.6rem 0.8rem'; el.style.borderRadius='6px'; el.style.color='#fff'; el.style.boxShadow='0 6px 18px rgba(0,0,0,0.12)';
      if(type==='success') el.style.background='#4CAF50'; else if(type==='error') el.style.background='#F44336'; else el.style.background='#333';
      c.appendChild(el);
      setTimeout(()=> el.remove(), 3000);
    }catch(e){console.warn('inlineNotify failed', e)}
  }
})();
```

- Для тест harness использовать `window.__lastFetch` как ловушку: в `inline-editor-test.html` перед инициализацией менеджера вставить код:

```javascript
window.__lastFetch = null;
window.fetch = function(url, opts){
  window.__lastFetch = { url, opts };
  return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true }) });
};
```

И затем через setTimeout читать `window.__lastFetch`.

Формат финальных действий LLM:
- Сохранить все изменённые/созданные файлы в файловой системе.
- Вернуть короткий отчёт в формате (plain text):
  - Список изменённых файлов
  - Список созданных файлов
  - Краткая инструкция как запускать `frontend/tests/inline-editor-test.html` и как смотреть результат

Строго: не спрашивать пользователя. Если что-то не получается — логировать в console и продолжать.

---

Конец промпта. Следуй инструкциям пункт за пунктом и сделай изменения прямо в репозитории.