# История дебага: импорт статических шаблонов в CMS (октябрь 2025)

## Введение

В октябре 2025 была реализована и отлажена функция импорта статических HTML-шаблонов (frontend/templates) в CMS. Документ описывает шаги реализации, найденные баги и ход отладки.

---

## Краткое резюме изменений

- Добавлено API:
  - `GET /api/templates` — возвращает список доступных файлов-шаблонов
  - `POST /api/templates/:slug/import` — импорт шаблона в CMS
- Реализован FileSystemStaticTemplateRepository (чтение `backend/templates/*`, `.imported_templates.json`)
- Реализован HtmlTemplateParser (DOMDocument + XPath) — извлекает title/SEO и блоки
- Use case `ImportStaticTemplate`:
  - Парсит HTML, создаёт Page + Block сущности, сохраняет в БД
  - Помечает шаблон как импортированный в кэше `.imported_templates.json`
  - Добавлена логика генерации уникального slug при конфликте
  - Добавлен режим `upsert` — обновление существующей импортированной страницы
- Добавлены unit tests: `backend/tests/ImportStaticTemplateTest.php` (2 теста)

---

## Баги, найденные во время имплементации и их фикс

1) Duplicate slug (SQL 1062)
- Симптом: при попытке импортировать шаблон `home` в базе уже была страница с slug `home` — INSERT падал с ошибкой Duplicate entry.
- Диагностика: репозиторий страниц проверялся, slugExists() возвращал true.
- Решение: в `ImportStaticTemplate` реализована генерация уникального slug: `home-2`, `home-3` и т.д. при конфликтах.

2) Foreign key constraint (created_by отсутствует)
- Симптом: при создании страницы импорт выдавал ошибку FK (created_by referencing users.id не найден).
- Причина: для тестов был использован временный id пользователя, который отсутствовал в тестовой БД на машине разработчика.
- Временное решение: временный seeder / use of existing user id (скрипт `backend/scripts/check_db.php` помог найти подходящий user id).
- Рекомендация: заменить временное решение реальной интеграцией с auth (в контроллере использовать текущего аутентифицированного пользователя).

3) Template file missing in runtime
- Симптом: API возвращал пустой список или выполнялся import с ошибкой "file not found"
- Причина: шаблоны не были скопированы в `backend/templates/` (порядок синхронизации XAMPP)
- Решение: добавлена инструкция в docs про синхронизацию и скрипты sync; в процессе копирования шаблоны были добавлены и API начал возвращать список.

4) Upsert behavior and blocks replacement
- Симптом: при попытке повторного импорта хотелось обновить существующую страницу, но предыдущая логика запрещала повторный импорт.
- Изменение: добавлен параметр `upsert` в `ImportStaticTemplate::execute(..., bool $upsert = false)`.
- Поведение: если `upsert=true` и шаблон уже импортирован — use case найдёт существующую страницу (по pageId), заменит блоки (deleteByPageId) и сохранит новые блоки; при отсутствии страницы — создаст новую.

5) Tests and CI readiness
- Добавлены unit tests, которые используют in-memory fake repositories и реальный `HtmlTemplateParser`.
- Tests located: `backend/tests/ImportStaticTemplateTest.php` (fixtures in `backend/tests/fixtures/test-template.html`).

---

## Локальные команды для воспроизведения

- Список шаблонов:
  - `GET http://localhost/healthcare-cms-backend/public/api/templates`
- Импорт шаблона:
  - `POST http://localhost/healthcare-cms-backend/public/api/templates/home/import`
  - Для upsert: `POST http://.../api/templates/home/import?upsert=1`

---

## Рекомендации по дальнейшим улучшениям

1. Интегрировать real auth в TemplateController — использовать текущего пользователя вместо временного id.
2. Добавить endpoint/админку для просмотра истории импортов и возможности отката (re-import / rollback).
3. Добавить интеграционные тесты с sqlite или тестовой БД, покрывающие реальное сохранение Page + Blocks + FK.
4. Перенести шаблоны в единый, надёжно синхронизируемый источник (avoid manual copy drift).

---

## Приложения
- `backend/src/Application/UseCase/ImportStaticTemplate.php` — main use case
- `backend/src/Infrastructure/Repository/FileSystemStaticTemplateRepository.php`
- `backend/src/Infrastructure/Parser/HtmlTemplateParser.php`
- `backend/tests/ImportStaticTemplateTest.php`
- `backend/tests/fixtures/test-template.html`


_Document prepared automatically from development session._
