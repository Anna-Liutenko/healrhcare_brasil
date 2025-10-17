DEBUG: Public page rendering — история отладки
=================================================

Дата: 9 октября 2025
Автор: автоматизированный ассистент (редактировал код и окружение по запросу пользователя)

Краткая суть
------------
Публикуемые страницы API успешно создавались и переводились в статус "published". Однако при обращении к публичному URL (например /p/<slug>) браузер получал 404 или не видел блока контента — в результате E2E тесты завершались неуспехом. В процессе отладки выявлены несколько проблем: конфигурация Apache/htaccess, наложение путей, и критическая проблема в методе рендеринга PublicPageController::renderPage() в бэкенде — отсутствие правильного HTTP-статуса и несовпадение имен типов блоков с теми, что хранились в базе.

Цели отладки
------------
- Отобразить публичную страницу локально по URL вида /healthcare-cms-backend/p/<slug>
- Сделать так, чтобы Playwright E2E тест проходил полностью (programmatic login → create page → publish → verify public page content)

Хронология и шаги
------------------
1) Первичные симптомы
- Playwright создавал страницу через API и успешно публиковал её (API ответы: 200, success).
- Переход на публичный URL возвращал 404 или телом возвращался небольшой текст (вплоть до пустого тела).
- Логи приложения показывали, что маршрут распознан и PublicPageController::renderPage() вызывался — то есть маршрутизация работала, но ответ клиента был 404.

2) Диагностические проверки
- Добавлен детализированный debug-лог в `backend/public/index.php` для записи исходного REQUEST_URI, затем нормализованного URI после всех str_replace() — это подтвердило, что uri сопадает с `/p/<slug>`.
- Проверялись Apache access/error логи и вывод `Invoke-WebRequest` в PowerShell для тестов запросов.
- Проверялось содержимое таблицы `pages` в базе данных — страницы действительно существуют и имеют `status='published'`.

3) Почему 404, если renderPage() вызывается?
- После вызова `renderPage()` Apache всё ещё возвращал 404. Это указывало на то, что в PHP-скрипте до вывода явно не был выставлен корректный HTTP-код или была ранняя ошибка, приводящая Apache к 404.
- В `renderPage()` обнаружили echo и exit без явной установки HTTP-статуса — Apache мог интерпретировать это как отсутствие контента или внутреннюю ошибку.

4) Первое исправление (статус ответа)
- В `backend/src/Presentation/Controller/PublicPageController.php` в `renderPage()` добавлено:
  - `http_response_code(200);`
  - `header('Content-Type: text/html; charset=utf-8');`
- После этого публичный GET перестал возвращать 404 и стал отдавать тело с текстом "SUCCESS: Page found - <title>" — значит маршрут и вывод выполнялись, но содержимое было заглушкой.

5) Далее — E2E тесты всё ещё падали из-за ожиданий контента
- Playwright ожидал в body текстов, которые находятся в блоках (hero heading, hero subtitle, text content), но видел только заглушку "SUCCESS: Page found".

6) Исправление рендеринга: полная реализация renderPage()
- Метод `renderPage()` был переписан, чтобы генерировать полноценный HTML5-документ:
  - DOCTYPE, head (meta charset, viewport, title), inline CSS
  - body с h1 (title) и main, в котором итерация по массиву `$blocks` и рендер каждого блока
  - Поддержка типов блоков и безопасная обработка data (json_decode если строка, поддержка массива)
  - Для hero/text добавлена логика рендера (h2, <p>, nl2br для plain-text)
- После внесения этого изменения страница демонстрировала структуру HTML, но блоки не отображались — причина оказалась в несовпадении имён типов блоков.

7) Проверка таблицы `blocks`
- Запрос в базу `blocks` для конкретной страницы показал реальные типы:
  - `main-screen` (hero)
  - `text-block` (текст)
  - `page-header` и другие секции (`service-cards`, `about-section`, ...)
- Код рендера ожидал `hero`/`text` — поэтому блоки не попадали под условия и не рендерились.

8) Полный фикс: поддержка реальных типов
- В `renderPage()` расширена логика определения типа:
  - `if ($type === 'hero' || $type === 'main-screen')` — рендер hero
  - `elseif ($type === 'text' || $type === 'text-block')` — рендер текстовых блоков
  - `page-header` пропускается (т.к. уже есть h1) либо опционально рендерится
- Добавлена обработка разных ключей данных: `title`/`heading`, `subtitle`/`subheading`, `content`/`text`.

9) Синхронизация с XAMPP и проверка
- Обновлённый файл контроллера был скопирован в `C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php`.
- Выполнены ручные GET-запросы к публичным URL — результирующая страница содержала:
  - h1 с заголовком страницы
  - текстовый блок с "This is E2E test content created by Playwright." (HTML)
  - hero-блок с H2 и подзаголовком

10) Запуск Playwright E2E теста
- Тест запускался из `frontend` через `npx playwright test e2e/tests/editor.spec.js`.
- Результат: тест проходил (1 passed) после правок.

Что мешало решению (препятствия)
---------------------------------
- Несоответствие ожидаемых имён блоков в коде и реальных типов из БД — это основная логическая ошибка, которая скрывала контент от рендера.
- Начальная путаница с rewrite/base path и .htaccess: Apache и .htaccess переписка требовала небольшого уточнения `RewriteBase`, чтобы локальный путь совпадал с тем, как XAMPP размещал backend в `htdocs`.
- Некоторая путаница с рабочими директориями при копировании файлов (временный провал Copy-Item из-за нахождения в `frontend/`), что заставляло повторять синхронизацию.
- Переходный костыль (echo "SUCCESS: Page found") маскировал проблему — приходилось дополнительно проверять DB и логи.

Что было сделано в коде (точные правки)
----------------------------------------
- `backend/public/index.php` — добавлен/перемещён debug-лог, который записывает нормализованный URI после всех замен.
- `backend/src/Presentation/Controller/PublicPageController.php`:
  - В `renderPage()` добавлены `http_response_code(200)` и `Content-Type` header
  - `renderPage()` переписан для генерации полного HTML документа с блоками
  - Добавлена совместимость типов: `main-screen`, `text-block`, `page-header` и др.; обработка полей `content`, `text`, `title`, `heading`, `subtitle`, `subheading`.

Важные команды и проверки, которые использовались
-------------------------------------------------
- Проверка страницы напрямую из PowerShell:
    Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/p/<slug>' -UseBasicParsing
- Просмотр БД (MySQL в XAMPP):
    C:\xampp\mysql\bin\mysql.exe -u root healthcare_cms -e "SELECT ... FROM pages WHERE slug='...'"
    C:\xampp\mysql\bin\mysql.exe -u root healthcare_cms -e "SELECT ... FROM blocks WHERE page_id='...' ORDER BY position"
- Запуск Playwright теста:
    cd frontend
    npx playwright test e2e/tests/editor.spec.js
- Копирование файла в XAMPP htdocs (PowerShell):
    Copy-Item -Path 'backend\src\Presentation\Controller\PublicPageController.php' -Destination 'C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php' -Force

Итог
-----
- Проблема 404 устранена. PublicPageController корректно устанавливает HTTP статус 200.
- Полная рендер-логика добавлена и теперь поддерживает реальные типы блоков из базы.
- Playwright E2E тест проходит успешно (создание → публикация → проверка публичной страницы).

Рекомендации и дальнейшие шаги
------------------------------
1. Убрать временные debug-логи из `backend/public/index.php` после окончательной проверки, чтобы не захламлять лог-файлы.
2. Вынести рендеринг блоков в отдельный класс/шаблонизатор (например, простой ViewRenderer), чтобы упростить поддержку новых типов блоков и тестируемость.
3. Добавить автоматизированные unit/integration тесты для `PublicPageController` (или для нового renderer), чтобы избежать регрессий с типами блоков и форматами данных.
4. Обновить документацию схемы `blocks` в `docs/` (какие типы блоков есть и какие поля у data для каждого типа).

Приложения
-----------
- Логи запросов и последние успешные запросы были сохранены в `C:\xampp\htdocs\healthcare-cms-backend\logs\` (request-debug.log, e2e-publicpage.log).
- Файлы изменены:
  - `backend/src/Presentation/Controller/PublicPageController.php` (ререндеринг)
  - `backend/public/.htaccess` (добавлен RewriteBase для локальной среды)

Если нужно, могу:
- Убрать debug-логи и закоммитить изменения
- Вынести renderer в отдельный класс и добавить пару unit-тестов
- Сгенерировать краткую инструкцию для devs о том, как синхронизировать изменения в XAMPP при разработке (PowerShell команда, рекомендованная структура)



