# Журнал ошибок фронтенда — 5 октября 2025

## 1. Ошибка создания страницы (dashboard `index.html`)
- **Время замечено:** 5 октября 2025
- **URL:** `http://localhost/healthcare-cms-backend/frontend/index.html`
- **Сценарий:** Нажатие кнопки «Создать страницу» (или завершение диалога создания).
- **Консоль:**
  ```
  Error creating page: Error: Не удалось создать страницу
      at Proxy.createNewPage (index.html:758:12)
      createNewPage @ index.html:761
  ```
- **Комментарий:** запрос на создание страницы возвращает ошибку «Не удалось создать страницу». Нужно проверить сетевой запрос (API 4xx/5xx), корректность тела запроса, конфигурацию `api-client.js` и соответствие бэкенд-эндпойнта.

## 2. Ошибка загрузки визуального редактора (`editor.html`)
- **Время замечено:** 5 октября 2025
- **URL:** `http://localhost/healthcare-cms-backend/frontend/editor.html?id=e23479f6-2d2e-4031-b428-a995125a788a`
- **Сценарий:** Переход в визуальный редактор по кнопке «Редактировать».
- **Консоль (Vue warn + SyntaxError):**
  ```
  [Vue warn]: Template compilation error: Error parsing JavaScript expression: Unexpected identifier 'includes'
      <template snippet>
      250|     <label class="settings-label">{{ formatLabel(key) }}</label>
      251|     <input v-if="key.includes('height') || key.includes('width')" ...
      252|     <textarea v-else-if="key.includes('content') || key.includes('text') || key.includes('message') || key.includes('subtitle')" ...
      253|     <div v-else-if="key.includes('image') || key.includes('Image')" ...
  ```
  ```
  Uncaught SyntaxError: Unexpected identifier 'includes'
      at editor.html?id=...:718
  ```
- **Комментарий:** Vue компилятор не принимает использование `String.prototype.includes` в шаблонных выражениях. Требуется вынести проверки в вычисляемое свойство/метод (`hasSubstring(key, 'height')`) или иным образом упростить условия.

## Примечание
- Скриншоты с консолью сохранены в переписке (см. вложения «2025-10-05 frontend errors»). Возврат к разбору после команды пользователя.
