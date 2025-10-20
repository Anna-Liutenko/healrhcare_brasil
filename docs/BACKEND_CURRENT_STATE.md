# Backend Current State

## Implementation Progress

### Phase 0-1: Infrastructure (✅ COMPLETE 100%)
- ✅ DI Container (`bootstrap/container.php`)
- ✅ Domain Exceptions (PageNotFoundException, BlockNotFoundException)
- ✅ DTOs (10 Request/Response pairs created)

### Phase 2: Use Cases Refactoring (✅ 70-80% COMPLETE)
**Completed:**
- ✅ UpdatePageInline — uses DTO + Domain Exceptions
- ✅ GetPageWithBlocks — uses DTO
- ✅ CreatePage — uses DTO
- ✅ DeletePage — uses DTO
- ✅ PublishPage — uses DTO

**Remaining:**
- ⏳ UpdatePage — partially refactored (needs full DTO adoption)
- ⏳ GetAllPages — returns array, needs DTO wrapper
- ⏳ RenderPageHtml — needs review

### Phase 3: Controllers Refactoring (✅ 40-50% COMPLETE)
**Completed:**
- ✅ PageController — uses constructor injection (7 use cases)
- ✅ index.php — uses `$container->make(PageController::class)`

**Remaining:**
- ⏳ AuthController — still instantiated directly (needs DI)
- ⏳ MenuController — needs DI
- ⏳ MediaController — needs DI
- ⏳ UserController — needs DI
- ⏳ SettingsController — needs DI

### Phase 4: Response Format Standardization (✅ COMPLETE)
**Problem discovered:** Backend returns mixed snake_case/camelCase in responses.

**Solution implemented:**
- ✅ Phase 1: JsonSerializer hotfix (automatic camelCase conversion)
- ✅ Phase 2: EntityToArrayTransformer (proper architecture)
- ✅ Phase 3: Documentation updates

**Status:** All phases complete. Sync layer problem resolved.

See: `docs/SYNC_LAYER_PROBLEM_ANALYSIS.md`

---

Текущее состояние бэкенда (кратко)

1) Общая картина
- Ядро написано на PHP 8.2, тесты — PHPUnit 10.
- В архитектуре есть разделение: Domain entities, ValueObjects, Repositories (Infrastructure), Use Cases (Application), Presentation (Controllers) и простой front controller (`backend/public/index.php`).
- Тестовая среда использует файл-базу SQLite: `backend/tests/tmp/e2e.sqlite`.

2) Что исправлено и что работает сейчас
- E2E-сценарий редактирования страницы (create → update → publish → public page) выполняется успешно — локальный E2E тест `testPageEditWorkflow` проходит (OK).
- CRUD для страниц работает: эндпоинты `/api/pages` (POST), `/api/pages/:id` (PUT), `/api/pages/:id/publish` (PUT) отвечают корректно для тестового сценария.
- Публичная страница доступна по короткому маршруту `/p/{slug}` и рендерит шаблон, включая заголовок страницы.

3) Что ещё остаётся/ограничения
- Я добавил временные отладочные логи (`backend/logs/e2e-*.log`) — они полезны, но рекомендуем перевести в нормальный логгер или отключить для прод/CI.
- Набор тестов не прогонялся полностью — только конкретный E2E тест. Возможны другие незамеченные регрессии.
- API контракт по ключам блоков (data vs content) нормализован на контроллере, но стоит выбрать единый контракт и задокументировать его.

4) Технические детали: важные файлы и их статус
- ValueObjects/Enums
  - `backend/src/Domain/ValueObject/PageStatus.php` — класс со статическими фабриками (`draft()`, `published()`), теперь используется корректно (через `getValue()` при сериализации).
  - `backend/src/Domain/ValueObject/PageType.php` — PHP enum, используется как `$page->getType()->value`.

- Репозитории
  - `backend/src/Infrastructure/Repository/MySQLPageRepository.php` — исправлена работа со статусом, добавлен небольшой лог в `findBySlug`.
  - `backend/src/Infrastructure/Repository/MySQLBlockRepository.php` — сохраняет `data` как JSON (`json_encode($block->getData())`), корректно гидратирует через `json_decode(..., true)`.

- UseCases / Controllers
  - `backend/src/Application/UseCase/CreatePage.php` — создаёт страницу и возвращает ID; контроллер отвечает 201.
  - `backend/src/Application/UseCase/UpdatePage.php` — теперь при замене блоков поддерживает как `data`, так и `content` в источнике.
  - `backend/src/Presentation/Controller/PageController.php` — при создании страниц теперь учитывает `content` как payload блока; добавлены диагностические error_log в местах сохранения блоков (временно).
  - `backend/src/Presentation/Controller/PublicPageController.php` — путь до шаблонов исправлен; добавлен fallback-рендеринг простых блоков если шаблон не поддерживает динамическую вставку.

- Тесты и утилиты
  - `backend/tests/E2E/HttpApiE2ETest.php` — тест `testPageEditWorkflow` проверяет 3 вещи на публичной странице: title, текст в text-блоке, heading в hero-блоке.
  - `backend/tools/inspect_e2e_db.php` — маленький скрипт для вывода текущего содержимого sqlite DB (pages и blocks).

5) Рекомендации по дальнейшим шагам
- Прогнать весь тестовый набор в CI или локально, чтобы выявить возможные другие ошибки.
- Добавить регрессионный тест на обработку `content`.
- Перевести debug-логи в использующийся в проекте логгер и сделать их отключаемыми в CI/production.
- Документировать контракт API для блоков (например, `blocks[].data` или `blocks[].content` — выбрать одно).

6) Как поднять локально для ручной проверки
- Запуск сервера (в `backend`):
```powershell
$env:DB_DEFAULT = 'sqlite'
$env:DB_DATABASE = (Resolve-Path .\tests\tmp\e2e.sqlite).Path
& 'C:\xampp\php\php.exe' -d auto_prepend_file=tests\server_bootstrap.php -S 127.0.0.1:8089 -t public
```
- Открыть страницу в браузере: `http://127.0.0.1:8089/p/<slug>` (slug можно посмотреть через `tools/inspect_e2e_db.php`).

---
(файл сгенерирован автоматически как краткая сводка состояния бэкенда в момент отладки)
