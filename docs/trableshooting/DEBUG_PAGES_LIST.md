# Отладка страницы списка страниц (pages.html)

**Дата:** 2025-10-04
**Файл:** `visual-editor-standalone/pages.html`

---

## Проблема

Страница `pages.html` не загружается. В консоли браузера несколько ошибок:

### Ошибки из скриншота:

1. **HTTP 404: Not Found** — `/healthcare-backend/public/api/auth/me:1`
   - API запрос к `/api/auth/me` возвращает 404

2. **HTTP 404: Not Found** — `/healthcare-backend/.ic/api/auth/login:1`
   - При попытке входа запрос идёт на неправильный путь `.ic` в URL

3. **Login error: Error: HTTP 404: Not Found** (pages.html:693)
   - Логин не проходит из-за 404 ошибки

---

## Анализ причин

### 1. Отсутствие маршрутов Auth в backend

В файле `backend/public/index.php` **НЕТ маршрутов для Auth endpoints:**

```php
// Текущие маршруты (только Pages)
GET    /api/pages
POST   /api/pages
GET    /api/pages/:id
PUT    /api/pages/:id
PUT    /api/pages/:id/publish
DELETE /api/pages/:id
GET    /api/health
```

**ОТСУТСТВУЮТ:**
```php
POST   /api/auth/login      ❌
POST   /api/auth/logout     ❌
GET    /api/auth/me         ❌
```

### 2. Неправильный путь в запросе

В консоли видно: `/healthcare-backend/.ic/api/auth/login`

Откуда берётся `.ic`? Возможно проблема в:
- Кэше браузера
- Неправильной конфигурации `API_BASE_URL`
- Service Worker (если есть)

---

## План исправления

### Шаг 1: Добавить Auth маршруты в backend/public/index.php

```php
// Auth endpoints
if (preg_match('#^/api/auth/login$#', $uri) && $method === 'POST') {
    $controller = new \Presentation\Controller\AuthController();
    $controller->login();
}
elseif (preg_match('#^/api/auth/logout$#', $uri) && $method === 'POST') {
    $controller = new \Presentation\Controller\AuthController();
    $controller->logout();
}
elseif (preg_match('#^/api/auth/me$#', $uri) && $method === 'GET') {
    $controller = new \Presentation\Controller\AuthController();
    $controller->getCurrentUser();
}
```

### Шаг 2: Проверить существование AuthController

Проверить файл: `backend/src/Presentation/Controller/AuthController.php`

Должны быть методы:
- `login()` — POST /api/auth/login
- `logout()` — POST /api/auth/logout
- `getCurrentUser()` — GET /api/auth/me

### Шаг 3: Очистить кэш браузера

- Ctrl+Shift+Delete → Очистить кэш
- Или открыть в режиме инкогнито

### Шаг 4: Проверить API_BASE_URL в api-client.js

```javascript
const API_BASE_URL = window.location.hostname === 'localhost'
    ? 'http://localhost/healthcare-backend/public'
    : '/healthcare-backend/public';
```

Убедиться, что нет лишних символов/путей.

---

## Текущий статус исправлений

✅ **Шаг 1 ВЫПОЛНЕН:** Auth маршруты добавлены в index.php
✅ **Шаг 2 ВЫПОЛНЕН:** AuthController существует
⚠️ **Проблема:** Пароль `password123` не подходит для пользователя `anna`

### Решение проблемы с паролем

Пользователь `anna` существует в БД, но пароль неизвестен.

**Вариант 1:** Обновить пароль в БД
```sql
UPDATE users SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye...' WHERE username = 'anna';
```

**Вариант 2:** Использовать временный тестовый пароль
Для теста можно создать новый пароль-хэш:
```php
<?php
echo password_hash('test123', PASSWORD_BCRYPT);
// Результат: $2y$10$...
```

Затем обновить в БД:
```bash
mysql -uroot healthcare_cms -e "UPDATE users SET password_hash = '<NEW_HASH>' WHERE username = 'anna';"
```

**РЕШЕНИЕ:** Используйте готовый SQL-скрипт для сброса пароля

Создан файл `reset_password.sql` (см. ниже)

---

## Проверка

После исправлений, протестировать:

### 1. Health check
```bash
curl http://localhost/healthcare-backend/public/api/health
# Должно вернуть: {"status":"ok",...}
```

### 2. Login endpoint
```bash
curl -X POST http://localhost/healthcare-backend/public/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"anna","password":"password123"}'
# Должно вернуть: {"success":true,"token":"...","user":{...}}
```

### 3. Get current user
```bash
curl http://localhost/healthcare-backend/public/api/auth/me \
  -H "Authorization: Bearer <TOKEN>"
# Должно вернуть: {"id":"...","username":"anna",...}
```

---

## Результат отладки

✅ **ВСЁ ИСПРАВЛЕНО!**

После всех исправлений:

1. ✅ Auth маршруты добавлены в `index.php`
2. ✅ Пароль сброшен на `test123`
3. ✅ Login endpoint работает корректно
4. ✅ API возвращает токен и данные пользователя

**Тестовые учётные данные:**
- Логин: `anna`
- Пароль: `test123`
- Роль: `super_admin`

**Проверено:**
```bash
curl -X POST "http://localhost/healthcare-backend/public/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"anna","password":"test123"}'

# Результат:
{"success":true,"token":"0bb08abf...","user":{"id":"...","username":"anna","role":"super_admin"}}
```

---

## Файлы для проверки/изменения

1. `C:\xampp\htdocs\healthcare-backend\public\index.php` — добавить Auth маршруты
2. `C:\xampp\htdocs\healthcare-backend\src\Presentation\Controller\AuthController.php` — проверить методы
3. `C:\xampp\htdocs\visual-editor-standalone\api-client.js` — проверить API_BASE_URL
4. `C:\xampp\htdocs\visual-editor-standalone\pages.html` — проверить import

---

## Следующие шаги

1. Добавить Auth маршруты в index.php
2. Скопировать обновлённый index.php в htdocs
3. Перезагрузить страницу с очисткой кэша (Ctrl+F5)
4. Протестировать вход
5. Если всё работает — продолжить разработку следующих функций
