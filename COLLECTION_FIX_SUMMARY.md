# Исправление рендеринга страницы-коллекции

## Проблемы, которые были исправлены

### 1. ✅ Заголовок отображается как "????"
**Причина:** Данные в БД были изначально сохранены с неправильной кодировкой (вероятно, при миграции или первичном импорте).

**Решение:**
- Добавлен PDO init command `SET NAMES 'utf8mb4'` в `Connection.php` для гарантии правильной кодировки всех соединений
- Создан и выполнен скрипт `fix-collection-title.php` для исправления уже повреждённых данных в БД
- Страница переиндексирована через `republish-collections.php`

**Файлы изменены:**
- `backend/src/Infrastructure/Database/Connection.php` — добавлена опция `PDO::MYSQL_ATTR_INIT_COMMAND`

### 2. ✅ Все карточки показывают SVG-заглушки вместо реальных изображений
**Причина:** Функция `sanitizeImageUrl` была слишком строгой и отклоняла валидные URL-пути (например, `http://...`, относительные пути `uploads/...`).

**Решение:**
- Обновлена логика `sanitizeImageUrl` в `CollectionCardRenderer.php` (backend) — теперь разрешает:
  - Абсолютные пути с ведущим слешем (`/uploads/...`)
  - Относительные пути (`uploads/...`)
  - HTTP/HTTPS URLs
  - Протокол-относительные URLs (`//host/...`)
  - По-прежнему блокирует `javascript:` и `data:` схемы
- Аналогичное изменение в `frontend/modules/card-templates.js` для консистентности
- Путь к заглушке изменён с `/healthcare-cms-frontend/uploads/default-card.svg` на `/uploads/default-card.svg`

**Файлы изменены:**
- `backend/src/Presentation/Helper/CollectionCardRenderer.php`
- `frontend/modules/card-templates.js`

### 3. ✅ Пути к uploads не работают на XAMPP
**Причина:** Сохранённый HTML содержит пути вида `/uploads/...` или `http://localhost/.../uploads/...`, которые не соответствуют структуре XAMPP.

**Решение:**
- Добавлен вызов `$this->fixUploadsUrls($html)` в `PublicPageController::renderCollectionPage()` перед `echo $html`
- Это преобразует все пути к `/healthcare-cms-backend/public/uploads/...` для корректной работы на XAMPP

**Файлы изменены:**
- `backend/src/Presentation/Controller/PublicPageController.php`

### 4. ✅ Шапка и футер (опционально, для будущего)
**Замечание:** `CollectionHtmlBuilder` строит собственную упрощённую шапку/футер. Для полного соответствия макету можно дополнительно синхронизировать их с публичным шаблоном. Пока оставлено как есть, так как функциональность работает.

## Результаты

После внесённых изменений:
1. ✅ Заголовок отображается правильно: **"Полезные материалы"** (не "????")
2. ✅ Карточки с загруженными изображениями показывают реальные картинки
3. ✅ Все пути к uploads корректно маппятся на `/healthcare-cms-backend/public/uploads/...`
4. ✅ PDO соединение гарантированно использует `utf8mb4`

## Файлы, которые были изменены

1. `backend/src/Infrastructure/Database/Connection.php`
2. `backend/src/Presentation/Helper/CollectionCardRenderer.php`
3. `backend/src/Presentation/Controller/PublicPageController.php`
4. `frontend/modules/card-templates.js`

## Утилиты для диагностики (созданы)

- `fix-collection-title.php` — исправление повреждённых заголовков в БД
- `dump-page-title.php` — дамп заголовка страницы (HEX + длина) для диагностики кодировки
- `test-collection-render.php` — проверка рендеринга коллекции
- `test-fix-uploads.php` — проверка трансформации URL путей

## Команды для валидации

```powershell
# Переиндексация коллекций после изменений
php republish-collections.php

# Проверка сохранённого HTML
php show-collection-html.php

# Синхронизация с XAMPP
powershell -NoProfile -ExecutionPolicy Bypass -File sync-to-xampp.ps1
```

## Дальнейшие шаги (опционально)

1. Проверить другие страницы на предмет повреждённой кодировки
2. Синхронизировать шапку/футер `CollectionHtmlBuilder` с публичным шаблоном
3. Добавить unit-тесты для `sanitizeImageUrl`
