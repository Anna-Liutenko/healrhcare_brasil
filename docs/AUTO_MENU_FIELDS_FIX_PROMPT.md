# Prompt: Menu Fields Persistence & Smoke Test Fix

## Goal
Обновить бэкенд и фронтенд так, чтобы поля меню (`show_in_menu`, `menu_position`/`menu_order`, `menu_label`) сохранялись при создании и обновлении страниц и корректно участвовали в автоматическом меню. Затем развернуть изменения в XAMPP и убедиться через смоук-тест, что опубликованная страница с флагом меню появляется в `GET /api/menu/public`.

## Контекст и предпосылки
- Текущая проблема: при создании/обновлении страницы через API значения `show_in_menu`, `menu_order` и `menu_label` не попадают в базу. Из-за этого автоматическое меню всегда пустое.
- Сущность `Page` уже содержит флаги `showInMenu`, `showInSitemap`, `menuOrder`, но не хранит `menuLabel`.
- Репозиторий `MySQLPageRepository` не читает/не записывает `menu_label` и не умеет обновлять `show_in_menu`/`menu_order` из входящих DTO.
- Фронтенд (Vue-редактор) отправляет данные в camelCase; API ожидает snake_case.
- Смоук-тест должен создавать страницу официальными REST-эндпойнтами без прямого доступа к SQL.

## Обязательные требования
1. **Backend**
   - Добавить поддержку `menu_label` в сущность `Domain\Entity\Page` (свойство, геттер, сеттер, конструктор, сериализация в массив).
   - Расширить `Application\UseCase\CreatePage` и `UpdatePage`:
     - Принимать новые поля из входного `array $data` (`show_in_menu`, `menu_position`/`menu_order`, `menu_label`).
     - Валидировать и приводить к корректным типам (`bool`, `int`, `string|null`).
     - Передавать значения в конструктор `Page` или устанавливать через сеттеры.
   - Обновить `Infrastructure\Repository\MySQLPageRepository`:
     - `insert()`/`update()` должны писать `show_in_menu`, `menu_order`, `menu_label` в таблицу `pages`.
     - `hydrate()` должен читать эти поля и инициализировать сущность (включая `menuLabel`).
     - Учесть fallback: если `menu_position` приходит с фронта, конвертировать в `menu_order`.
   - При необходимости обновить DTO/Валидаторы, чтобы новые поля не ломали существующую логику.
   - Покрыть новый функционал хотя бы одним unit-тестом или предусмотреть ручную проверку (например, убедиться, что `CreatePage` создаёт страницу с `show_in_menu=1` и `menu_label`).

2. **Frontend**
   - Проверить `frontend/editor.js` (`savePage`, `createNewPage`, `loadPageFromAPI`):
     - Убедиться, что при создании и обновлении страницы в `payload` уходят snake_case поля `show_in_menu`, `menu_position`, `menu_label`.
     - Привести типы (`menu_position` — число или `0`, `menu_label` — `null`/строка без лишних пробелов).
   - Актуализировать `frontend/api-client.js`:
     - При `createPage` и `updatePage` корректно сериализовать меню-поля.
     - (Опционально) централизовать маппинг camelCase ↔ snake_case, чтобы избежать дублирования.
   - Убедиться, что UI-логика (чекбокс «Показывать в меню», поле позиции, поле подписи) корректно срабатывает при смене статуса страницы и при повторной загрузке.

3. **Smoke-тест**
   - Обновить PowerShell-скрипт (или создать новый) так, чтобы он:
     1. Логинился через `/api/auth/login`.
     2. Создавал страницу через `POST /api/pages`, передавая `show_in_menu=1`, `menu_label` и позицию.
     3. (Если нужно) вызывал `PUT /api/pages/:id` для установки меню-полей перед публикацией.
     4. Публиковал страницу через `PUT /api/pages/:id/publish`.
     5. Вызывал `GET /api/menu/public` и проверял, что в ответе есть новый пункт.
   - Добавить вывод, фиксирующий результат проверки (`MENU_OK` / `MENU_FAIL`).

4. **Deployment / XAMPP**
   - После успешной локальной проверки скопировать изменённые файлы:
     - Backend → `C:\xampp\htdocs\healthcare-cms-backend\...`
     - Frontend → `C:\xampp\htdocs\healthcare-cms-frontend\...`
   - Перезапустить Apache/MySQL (через XAMPP Control Panel или `Stop-Process`), чтобы снять блокировку файлов.
   - Запустить обновлённый смоук-тест против XAMPP-сервера и сохранить лог результата.

## Детальные шаги реализации

### 1. Backend: поддержка меню-полей
1. **Page Entity (`backend/src/Domain/Entity/Page.php`)**
   - Добавить поле `private ?string $menuLabel;`.
   - Обновить конструктор: принимать `?string $menuLabel = null`.
   - Сохранить значение; добавить `getMenuLabel(): ?string` и `setMenuLabel(?string $label)` (с обрезкой пробелов и ограничением длины 255 символов).
   - При `makeUnlisted()` обнулять `menuLabel` или оставлять? (договориться; по умолчанию можно не менять).

2. **CreatePage Use Case**
   - Обновить phpDoc входного массива: добавить `showInMenu?`, `menuPosition?`, `menuLabel?`.
   - После валидации подготовить значения:
     ```php
     $showInMenu = !empty($data['show_in_menu']);
     $menuOrder = isset($data['menu_order']) ? (int)$data['menu_order'] : 0;
     $menuLabel = isset($data['menu_label']) ? trim((string)$data['menu_label']) : null;
     ```
   - Передать в конструктор `Page` или вызвать сеттеры после создания.

3. **UpdatePage Use Case**
   - После загрузки сущности `Page` и перед сохранением:
     - Обновлять `showInMenu`/`menuOrder`/`menuLabel`, если соответствующие ключи есть в `$data`.
     - Учитывать алиас `menu_position` → `menu_order`.

4. **MySQLPageRepository**
   - `extractData(Page $page)` должен добавлять `'menu_label' => $page->getMenuLabel()`.
   - SQL в `insert()`/`update()` расширить колонками `menu_label`.
   - `hydrate()` передавать `menuLabel` в сущность.
   - Если в таблице нет `menu_label`, убедиться, что миграция `011_add_menu_fields_to_pages.sql` выполнена (есть). При необходимости добавить защиту (`$row['menu_label'] ?? null`).

5. **Тестирование**
   - При наличии автотестов добавить unit-тест для `CreatePage`/`UpdatePage`, либо описать ручную проверку: создать страницу через Postman/скрипт и проверить запись в таблице `pages`.

### 2. Frontend: отправка меню-полей
1. **`frontend/editor.js`**
   - В `savePage()` убедиться, что отправляется объект вида:
     ```js
     const payload = {
       ...,
       show_in_menu: this.pageSettings.showInMenu && this.pageData.status === 'published' ? 1 : 0,
       menu_position: Number(this.pageSettings.menuPosition) || 0,
       menu_label: this.pageSettings.menuLabel?.trim() || null
     };
     ```
   - При `createNewPage()` / `saveWithStatus('draft')` обеспечить, что значения также уходят (даже если страница ещё в черновике, тогда `show_in_menu` можно выставить в 0, чтобы не попасть в меню).
   - При загрузке (`loadPageFromAPI`) читать эти поля из ответа (`show_in_menu`, `menu_order`/`menu_position`, `menu_label`).

2. **`frontend/api-client.js`**
   - В `createPage` и `updatePage` гарантировать правильную сериализацию (использовать `toPlainObject` + ensure snake_case).
   - Возможно, добавить helper-метод `normalizePagePayload(pageData)` для единообразия.

3. **UI-поведение**
   - Проверить, что чекбокс «Показывать в меню» автоматически отключается при переводе страницы в статус отличный от `published`.
   - Убедиться, что поля позиции и подписи доступны/блокируются по UX-требованиям.

### 3. Smoke-тест (PowerShell)
1. Обновить `scripts/smoke_menu_test.ps1` (или создать `smoke_menu_test_v2.ps1`):
   - Считать базовый URL бэкенда из переменной.
   - Логин (`/api/auth/login`).
   - Сформировать JSON для `POST /api/pages`, включив:
     ```json
     {
       "title": "Smoke Menu Test",
       "slug": "smoke-menu-<timestamp>",
       "type": "regular",
       "createdBy": "<admin-id>",
       "show_in_menu": 1,
       "menu_label": "Smoke Test",
       "menu_order": 10
     }
     ```
   - Если API ожидает `createdBy` в snake_case (`created_by`), адаптировать.
   - При необходимости вызвать `PUT /api/pages/:id` с теми же полями (если `POST` не принимает).
   - `PUT /api/pages/:id/publish`.
   - `GET /api/menu/public` → проверить, что среди элементов есть slug новой страницы.
   - Сохранить ответ в `scripts/output/menu_result.json` и выводить `MENU_OK`/`MENU_FAIL`.

### 4. Развёртывание в XAMPP и проверка
1. Остановить Apache через XAMPP Control Panel или `Stop-Process -Name httpd`.
2. Скопировать изменённые файлы:
   - Backend: Entity, UseCase, Repository, Controller (если затронут) → `C:\xampp\htdocs\healthcare-cms-backend\...`
   - Frontend: `editor.js`, `api-client.js`, при необходимости стили.
   - Smoke-тест: скрипты → (оставить в workspace, но запускать из PowerShell).
3. Запустить Apache и MySQL.
4. Выполнить смоук-тест против `http://localhost/healthcare-cms-backend/public`.
5. Зафиксировать результат (включить в отчёт/лог: ID созданной страницы, ответ меню, статус).

## Acceptance Criteria
- Созданная или обновлённая страница с `show_in_menu=1` и `menu_label` отображается в `GET /api/menu/public` без ручного SQL.
- Поля меню сохраняются в таблице `pages` и восстанавливаются при повторной загрузке страницы в редактор.
- Смоук-тест проходит автоматически и фиксирует результат.
- Развёрнутый билд в XAMPP отдаёт корректное меню.

## Дополнительные заметки
- Если `menu_position` используется на фронте, а в БД хранится `menu_order`, в маппинге нужно привести именование (например, `menu_position` → `menu_order`).
- При валидации желательно ограничить `menu_label` 255 символами и сбрасывать в `NULL`, если строка пустая.
- При сохранении черновиков можно всегда сбрасывать `show_in_menu` в 0, чтобы черновые страницы не попадали в меню.
- Убедиться, что существующие страницы/данные не ломаются (например, выполнить SELECT перед миграцией, проверить несколько старых страниц).
