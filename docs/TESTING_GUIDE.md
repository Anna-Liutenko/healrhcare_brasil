# Как протестировать проект после синхронизации слоя

## 🚀 Быстрый старт

После завершения Phase 2 (синхронизация слоя), вы можете протестировать проект тремя способами:

### Способ 1: E2E тесты через PHPUnit (Рекомендуется)

#### Шаг 1: Запустите тестовый сервер в отдельном окне PowerShell

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

# Установите переменные окружения для SQLite
$env:DB_DEFAULT = 'sqlite'
$env:DB_DATABASE = (Resolve-Path '.\tests\tmp\e2e.sqlite').Path

# Запустите сервер с server_bootstrap.php
& 'C:\xampp\php\php.exe' -d auto_prepend_file=tests\E2E\server_bootstrap.php -S 127.0.0.1:8089 -t public
```

**Оставьте это окно открытым!**

#### Шаг 2: Запустите E2E тесты в другом окне PowerShell

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

# Запустите ResponseFormatTest
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests/_bootstrap.php tests/E2E/ResponseFormatTest.php

# Или запустите все E2E тесты
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --bootstrap tests/_bootstrap.php tests/E2E/HttpApiE2ETest.php
```

**Ожидаемый результат:**
```
PHPUnit 10.5.58 by Sebastian Bergmann and contributors.

.......                                                             7 / 7 (100%)

Time: 00:05.123, Memory: 8.00 MB

OK (7 tests, 42 assertions)
```

---

### Способ 2: Тестирование на XAMPP (Production-like)

#### Шаг 1: Убедитесь что XAMPP запущен

```powershell
# Проверьте статус Apache
netstat -ano | findstr :80

# Если порт 80 не занят, запустите Apache через XAMPP Control Panel
```

#### Шаг 2: Используйте production базу данных MySQL

```powershell
cd 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'

# Создайте пользователя и залогиньтесь через API
curl http://localhost/backend/public/api/auth/login `
  -Method POST `
  -Headers @{'Content-Type'='application/json'} `
  -Body '{"username":"admin","password":"your_password"}'
```

#### Шаг 3: Проверьте camelCase responses

```powershell
# Получите список страниц
curl http://localhost/backend/public/api/pages `
  -Method GET `
  -Headers @{'Authorization'='Bearer YOUR_TOKEN_HERE'}
```

**Ожидаемый response (camelCase):**
```json
{
  "pages": [
    {
      "id": "uuid-here",
      "title": "Home Page",
      "slug": "home",
      "createdAt": "2025-10-18 12:00:00",
      "updatedAt": "2025-10-18 12:00:00",
      "createdBy": "admin-uuid",
      "showInMenu": true
    }
  ]
}
```

❌ **Неправильный response (snake_case) - не должен быть:**
```json
{
  "pages": [
    {
      "id": "uuid-here",
      "created_at": "...",    // ❌ snake_case
      "show_in_menu": true    // ❌ snake_case
    }
  ]
}
```

---

### Способ 3: Ручное тестирование через Postman/Insomnia

#### Шаг 1: Импортируйте коллекцию API

Создайте новый request в Postman:

**1. Login:**
```
POST http://localhost:8089/api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin"
}
```

**2. Get Pages:**
```
GET http://localhost:8089/api/pages
Authorization: Bearer {{token}}
```

**3. Create Page:**
```
POST http://localhost:8089/api/pages
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "title": "Test Page",
  "slug": "test-page",
  "type": "regular",
  "status": "draft",
  "createdBy": "admin-uuid",
  "blocks": [
    {
      "type": "text",
      "position": 0,
      "content": {"text": "Test content"}
    }
  ]
}
```

#### Шаг 2: Проверьте responses

✅ **Правильно (camelCase):**
- `pageId`, `createdAt`, `updatedAt`, `createdBy`
- `showInMenu`, `menuOrder`, `menuTitle`
- `customName`, `pageId` (в блоках)

❌ **Неправильно (snake_case):**
- `page_id`, `created_at`, `updated_at`, `created_by`
- `show_in_menu`, `menu_order`, `menu_title`
- `custom_name`, `page_id` (в блоках)

---

## 🔍 Что проверять

### 1. Authentication Endpoints
- ✅ `POST /api/auth/login` → возвращает `token` и `user` object (camelCase)
- ✅ `GET /api/auth/me` → возвращает user data (camelCase)

### 2. Pages Endpoints
- ✅ `GET /api/pages` → список страниц (все ключи camelCase)
- ✅ `POST /api/pages` → возвращает `pageId` (НЕ `page_id`)
- ✅ `GET /api/pages/:id` → страница с блоками (все ключи camelCase)
- ✅ `PUT /api/pages/:id` → обновление страницы
- ✅ `PUT /api/pages/:id/publish` → публикация

### 3. Menu Endpoints
- ✅ `GET /api/menu` → меню со всеми пунктами (camelCase)
- ✅ `POST /api/menu` → создание пункта меню, возвращает `menuItemId`

### 4. Media Endpoints
- ✅ `GET /api/media` → список файлов (camelCase: `uploadedBy`, `uploadedAt`)

---

## 🐛 Troubleshooting

### Проблема: "Unable to connect to the remote server"

**Решение:**
1. Убедитесь что тестовый сервер запущен:
```powershell
netstat -ano | findstr ":8089"
```

2. Если порт не занят, перезапустите сервер:
```powershell
cd backend
$env:DB_DEFAULT='sqlite'
$env:DB_DATABASE=(Resolve-Path '.\tests\tmp\e2e.sqlite').Path
& 'C:\xampp\php\php.exe' -d auto_prepend_file=tests\E2E\server_bootstrap.php -S 127.0.0.1:8089 -t public
```

### Проблема: "E2E tests skipped"

**Причина:** Тесты не видят запущенный сервер на порту 8089.

**Решение:**
1. Запустите сервер в отдельном терминале (см. Способ 1)
2. Дождитесь сообщения "PHP Development Server started"
3. Запустите тесты в другом терминале

### Проблема: Получаю snake_case в responses

**Причина:** EntityToArrayTransformer не используется в контроллере.

**Решение:**
1. Проверьте что контроллер импортирует `EntityToArrayTransformer`
2. Убедитесь что методы используют transformer:
```php
$result = EntityToArrayTransformer::pageToArray($page);
```
3. Проверьте что в контроллере нет локального метода `jsonResponse()` который переопределяет trait

---

## ✅ Критерии успеха

После тестирования вы должны убедиться что:

1. ✅ **Все E2E тесты проходят** (7/7 тестов в ResponseFormatTest)
2. ✅ **Все API responses используют camelCase** (нет ключей с underscore)
3. ✅ **Frontend может корректно читать данные** (не требуется дополнительная конвертация)
4. ✅ **CRUD операции работают** (create, read, update, delete для страниц)
5. ✅ **Публикация страниц работает** (status меняется на published)

---

## 📚 Дополнительные ресурсы

- **API Contract:** `docs/API_CONTRACT.md` - полная документация endpoints
- **Response Standards:** `docs/RESPONSE_FORMAT_STANDARDS.md` - стандарты форматирования
- **Phase 2 Report:** `docs/PHASE_2_COMPLETION_REPORT.md` - отчет о выполненных изменениях
- **E2E Tests:** `backend/tests/E2E/ResponseFormatTest.php` - тесты форматирования

---

**Готово к production!** 🎉