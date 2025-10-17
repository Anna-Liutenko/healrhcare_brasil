# ПРОМТ: Централизация авторизации через AuthHelper

**Дата:** 8 октября 2025  
**Задача:** Вынести повторяющуюся логику проверки Authorization Bearer токена в переиспользуемый helper  
**Приоритет:** 🟡 Средний (рефакторинг, улучшение кода)

---

## 📋 КОНТЕКСТ

### Текущая проблема

**Дублирование кода авторизации** в контроллерах:
- `AuthController::me()` - парсит Authorization, валидирует токен, находит user
- `TemplateController::import()` - та же логика, скопирована полностью
- Потенциально другие защищённые endpoints повторят этот код

**Что дублируется:**
```php
// В каждом контроллере повторяется:
$headers = ApiLogger::getRequestHeaders();
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);

if (!$authHeader) {
    $this->jsonResponse(['error' => 'Token required'], 401);
}

$matches = [];
if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
    $this->jsonResponse(['error' => 'Invalid auth header'], 401);
}

$token = $matches[1];

$sessionRepo = new MySQLSessionRepository();
if (!$sessionRepo->isValid($token)) {
    $this->jsonResponse(['error' => 'Invalid token'], 401);
}

$session = $sessionRepo->findByToken($token);
$userRepo = new MySQLUserRepository();
$user = $userRepo->findById($session['user_id']);

if (!$user) {
    $this->jsonResponse(['error' => 'User not found'], 404);
}

// Наконец-то можем использовать $user
```

**Последствия:**
- ~20 строк дублируются в каждом защищённом endpoint
- Изменения в логике авторизации требуют правки всех контроллеров
- Риск несогласованности (в одном месте забыли обновить)
- Сложнее тестировать

---

## 🎯 ЦЕЛЬ ЗАДАЧИ

Создать **централизованный helper** для авторизации:
- Один метод `AuthHelper::requireAuth(): User`
- Используется во всех контроллерах, требующих авторизацию
- При ошибке бросает исключение `UnauthorizedException`
- Контроллеры ловят исключение и возвращают корректный HTTP ответ

---

## 🏗️ АРХИТЕКТУРНЫЕ РЕШЕНИЯ

### Где разместить helper?

**Вариант 1: `Infrastructure\Auth\AuthHelper` (РЕКОМЕНДУЕТСЯ)**
- Обоснование: авторизация — это инфраструктурная ответственность (работа с HTTP заголовками, сессиями, БД)
- Путь: `backend/src/Infrastructure/Auth/AuthHelper.php`
- Использует: `ApiLogger`, `MySQLSessionRepository`, `MySQLUserRepository`

**Вариант 2: `Presentation\Middleware\AuthMiddleware`**
- Подходит, если в будущем перейдём на PSR-15 middleware stack
- Сейчас у нас нет middleware pipeline, поэтому helper проще

**Выбор:** Вариант 1 - `Infrastructure\Auth\AuthHelper`

### Какие методы создать?

```php
namespace Infrastructure\Auth;

use Domain\Entity\User;

class AuthHelper
{
    /**
     * Извлечь текущего пользователя из Authorization header
     * 
     * @throws UnauthorizedException если токен отсутствует/невалиден
     * @return User
     */
    public static function requireAuth(): User;
    
    /**
     * Попытаться получить пользователя (null если не авторизован)
     * Не бросает исключение
     * 
     * @return User|null
     */
    public static function getCurrentUser(): ?User;
}
```

### Исключение для авторизации

```php
namespace Infrastructure\Auth;

class UnauthorizedException extends \Exception
{
    private int $httpCode;
    
    public function __construct(string $message, int $httpCode = 401)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
    
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
```

---

## 📝 ПОШАГОВАЯ РЕАЛИЗАЦИЯ

### Шаг 1: Создать исключение `UnauthorizedException`

**Файл:** `backend/src/Infrastructure/Auth/UnauthorizedException.php`

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Auth;

/**
 * Исключение для ошибок авторизации
 * Содержит HTTP код ответа (401, 403, 404)
 */
class UnauthorizedException extends \Exception
{
    private int $httpCode;
    
    public function __construct(string $message, int $httpCode = 401)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
    
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
```

---

### Шаг 2: Создать `AuthHelper`

**Файл:** `backend/src/Infrastructure/Auth/AuthHelper.php`

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Auth;

use Domain\Entity\User;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;

/**
 * Helper для централизованной авторизации
 * 
 * Извлекает Authorization Bearer токен из заголовков,
 * валидирует через SessionRepository,
 * возвращает User entity из UserRepository
 */
class AuthHelper
{
    /**
     * Получить текущего авторизованного пользователя
     * 
     * @throws UnauthorizedException если токен отсутствует/невалиден
     * @return User
     */
    public static function requireAuth(): User
    {
        $user = self::getCurrentUser();
        
        if ($user === null) {
            throw new UnauthorizedException('Authentication required', 401);
        }
        
        return $user;
    }
    
    /**
     * Попытаться получить текущего пользователя
     * Возвращает null если не авторизован (не бросает исключение)
     * 
     * @return User|null
     */
    public static function getCurrentUser(): ?User
    {
        // 1. Извлечь заголовок Authorization
        $token = self::extractBearerToken();
        
        if ($token === null) {
            return null;
        }
        
        // 2. Проверить валидность токена через SessionRepository
        $sessionRepo = new MySQLSessionRepository();
        
        if (!$sessionRepo->isValid($token)) {
            return null;
        }
        
        // 3. Получить сессию и найти пользователя
        $session = $sessionRepo->findByToken($token);
        
        if (!$session) {
            return null;
        }
        
        $userRepo = new MySQLUserRepository();
        $user = $userRepo->findById($session['user_id']);
        
        return $user; // может быть null, если пользователь удалён
    }
    
    /**
     * Извлечь Bearer токен из HTTP заголовков
     * 
     * @return string|null
     */
    private static function extractBearerToken(): ?string
    {
        $headers = ApiLogger::getRequestHeaders();
        
        // Проверяем оба варианта (Authorization и authorization)
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
        
        if (!$authHeader) {
            return null;
        }
        
        // Парсим "Bearer {token}"
        $matches = [];
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return null;
        }
        
        return $matches[1];
    }
}
```

---

### Шаг 3: Обновить `AuthController::me()`

**Файл:** `backend/src/Presentation/Controller/AuthController.php`

**БЫЛО:**
```php
public function me(): void
{
    $startTime = ApiLogger::logRequest();

    try {
        $token = $this->getBearerToken();

        if (!$token) {
            $error = ['error' => 'Token required'];
            ApiLogger::logResponse(401, $error, $startTime);
            $this->jsonResponse($error, 401);
        }

        $sessionRepository = new MySQLSessionRepository();

        if (!$sessionRepository->isValid($token)) {
            $error = ['error' => 'Invalid or expired token'];
            ApiLogger::logResponse(401, $error, $startTime);
            $this->jsonResponse($error, 401);
        }

        $session = $sessionRepository->findByToken($token);
        $userRepository = new MySQLUserRepository();
        $user = $userRepository->findById($session['user_id']);

        if (!$user) {
            $error = ['error' => 'User not found'];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        }

        $response = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()->value
        ];

        ApiLogger::logResponse(200, $response, $startTime);
        $this->jsonResponse($response, 200);
    } catch (\Exception $e) {
        $error = ['error' => 'Internal server error'];
        ApiLogger::logError('AuthController::me() error', $e);
        ApiLogger::logResponse(500, $error, $startTime);
        $this->jsonResponse($error, 500);
    }
}
```

**СТАЛО:**
```php
public function me(): void
{
    $startTime = ApiLogger::logRequest();

    try {
        // Централизованная авторизация через helper
        $user = AuthHelper::requireAuth();

        $response = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()->value
        ];

        ApiLogger::logResponse(200, $response, $startTime);
        $this->jsonResponse($response, 200);
        
    } catch (UnauthorizedException $e) {
        // Обработка ошибок авторизации
        $error = ['error' => $e->getMessage()];
        ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
        $this->jsonResponse($error, $e->getHttpCode());
        
    } catch (\Exception $e) {
        $error = ['error' => 'Internal server error'];
        ApiLogger::logError('AuthController::me() error', $e);
        ApiLogger::logResponse(500, $error, $startTime);
        $this->jsonResponse($error, 500);
    }
}
```

**Что изменилось:**
- ❌ Удалено: ~30 строк парсинга заголовков и валидации
- ✅ Добавлено: 1 строка `$user = AuthHelper::requireAuth()`
- ✅ Добавлен: отдельный catch для `UnauthorizedException`
- ✅ Добавлен: импорт `use Infrastructure\Auth\AuthHelper;` и `use Infrastructure\Auth\UnauthorizedException;`

---

### Шаг 4: Обновить `TemplateController::import()`

**Файл:** `backend/src/Presentation/Controller/TemplateController.php`

**БЫЛО:**
```php
public function import(string $slug): void
{
    try {
        // ... создание репозиториев ...

        $upsert = isset($_GET['upsert']) && ($_GET['upsert'] === '1' || $_GET['upsert'] === 'true');

        // Resolve current user from Bearer token (same logic as AuthController::me)
        $headers = ApiLogger::getRequestHeaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);

        if (!$authHeader) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'UNAUTHORIZED','message' => 'Authorization token required']], 401);
        }

        $matches = [];
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'UNAUTHORIZED','message' => 'Invalid Authorization header']], 401);
        }

        $token = $matches[1];

        $sessionRepo = new MySQLSessionRepository();
        if (!$sessionRepo->isValid($token)) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'UNAUTHORIZED','message' => 'Invalid or expired token']], 401);
        }

        $session = $sessionRepo->findByToken($token);
        $userRepo = new MySQLUserRepository();
        $user = $userRepo->findById($session['user_id']);

        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'NOT_FOUND','message' => 'User not found']], 404);
        }

        $pageId = $useCase->execute($slug, $user->getId(), $upsert);
        
        // ...
    } catch (\Exception $e) {
        // ...
    }
}
```

**СТАЛО:**
```php
public function import(string $slug): void
{
    try {
        // ... создание репозиториев ...

        $upsert = isset($_GET['upsert']) && ($_GET['upsert'] === '1' || $_GET['upsert'] === 'true');

        // Централизованная авторизация
        $user = AuthHelper::requireAuth();

        $pageId = $useCase->execute($slug, $user->getId(), $upsert);
        
        // ... остальная логика ...
        
    } catch (UnauthorizedException $e) {
        $this->jsonResponse([
            'success' => false, 
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $e->getMessage()
            ]
        ], $e->getHttpCode());
        
    } catch (\InvalidArgumentException $e) {
        $this->jsonResponse(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR','message' => $e->getMessage()]], 400);
        
    } catch (\Exception $e) {
        $this->jsonResponse(['success' => false, 'error' => ['code' => 'SERVER_ERROR','message' => $e->getMessage()]], 500);
    }
}
```

**Что изменилось:**
- ❌ Удалено: ~25 строк парсинга токена
- ✅ Добавлено: 1 строка `$user = AuthHelper::requireAuth()`
- ✅ Добавлен: отдельный catch для `UnauthorizedException`
- ✅ Добавлены импорты: `use Infrastructure\Auth\AuthHelper;` и `use Infrastructure\Auth\UnauthorizedException;`

---

### Шаг 5: Удалить helper метод `getBearerToken()` из `AuthController`

**Файл:** `backend/src/Presentation/Controller/AuthController.php`

**Удалить:**
```php
/**
 * Получить Bearer токен из заголовка Authorization
 */
private function getBearerToken(): ?string
{
    $headers = ApiLogger::getRequestHeaders();

    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s+(.+)/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    return null;
}
```

**Обоснование:** Эта логика теперь в `AuthHelper::extractBearerToken()`

---

### Шаг 6: Обновить импорты

**В `AuthController.php` добавить:**
```php
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
```

**В `TemplateController.php` добавить:**
```php
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
```

**Удалить неиспользуемые импорты:**
- `use Infrastructure\Repository\MySQLSessionRepository;` (из контроллеров, которые теперь используют AuthHelper)
- `use Infrastructure\Repository\MySQLUserRepository;` (если больше нигде не используется в контроллере)

---

## 🧪 ТЕСТИРОВАНИЕ

### Тест 1: Unit тест для `AuthHelper`

**Файл:** `backend/tests/Unit/AuthHelperTest.php`

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;

class AuthHelperTest extends TestCase
{
    public function testRequireAuthThrowsWhenNoToken(): void
    {
        $this->expectException(UnauthorizedException::class);
        
        // Симулируем отсутствие заголовка
        // (потребуется мокировать ApiLogger::getRequestHeaders())
        AuthHelper::requireAuth();
    }
    
    // TODO: добавить тесты с валидным токеном (требуется мокирование)
}
```

### Тест 2: Проверка через API (curl)

**Проверить `/api/auth/me` после рефакторинга:**

```powershell
# 1. Получить токен
$body = '{"username":"anna","password":"anna123"}'
$response = Invoke-RestMethod -Method Post -Uri "http://localhost/healthcare-cms-backend/public/api/auth/login" -ContentType "application/json" -Body $body
$token = $response.token

# 2. Проверить /api/auth/me (должен работать как раньше)
$headers = @{ 'Authorization' = "Bearer $token" }
Invoke-RestMethod -Method Get -Uri "http://localhost/healthcare-cms-backend/public/api/auth/me" -Headers $headers
```

**Ожидаемый результат:** Тот же JSON с user данными, что и до рефакторинга

**Проверить `/api/templates/{slug}/import`:**

```powershell
$headers = @{ 'Authorization' = "Bearer $token" }
Invoke-RestMethod -Method Post -Uri "http://localhost/healthcare-cms-backend/public/api/templates/home/import" -Headers $headers
```

**Ожидаемый результат:** Успешный импорт (или ошибка, если шаблон уже импортирован)

### Тест 3: Проверка ошибок авторизации

```powershell
# Без токена
Invoke-RestMethod -Method Get -Uri "http://localhost/healthcare-cms-backend/public/api/auth/me"
# Ожидается: 401 Unauthorized

# С невалидным токеном
$headers = @{ 'Authorization' = "Bearer invalid-token-12345" }
Invoke-RestMethod -Method Get -Uri "http://localhost/healthcare-cms-backend/public/api/auth/me" -Headers $headers
# Ожидается: 401 Unauthorized
```

---

## ✅ КРИТЕРИИ УСПЕХА

### Функциональные требования
- ✅ `/api/auth/me` работает как раньше
- ✅ `/api/templates/{slug}/import` работает как раньше
- ✅ Ошибки авторизации возвращают корректные HTTP коды (401, 404)
- ✅ Сообщения об ошибках понятные

### Нефункциональные требования
- ✅ Код авторизации не дублируется
- ✅ Все контроллеры используют `AuthHelper::requireAuth()`
- ✅ Новые защищённые endpoints могут легко добавить авторизацию (1 строка кода)
- ✅ Легко изменить политику авторизации в будущем (например, добавить refresh token)

### Тесты
- ✅ Все существующие тесты проходят
- ✅ API endpoints возвращают те же результаты, что до рефакторинга

---

## 📊 МЕТРИКИ УЛУЧШЕНИЯ

### До рефакторинга:
```
AuthController::me() - 45 строк
TemplateController::import() - 60 строк
Дублирование кода авторизации: ~25 строк × 2 = 50 строк

Итого: 105 строк
```

### После рефакторинга:
```
AuthHelper.php - 70 строк
UnauthorizedException.php - 15 строк
AuthController::me() - 20 строк (-25)
TemplateController::import() - 35 строк (-25)

Итого: 140 строк
```

**Но:**
- ✅ При добавлении 3-го защищённого endpoint: экономия 25 строк
- ✅ При добавлении 10 endpoints: экономия 250 строк
- ✅ Изменение логики авторизации: 1 файл вместо N файлов

---

## 🚨 ВОЗМОЖНЫЕ ПРОБЛЕМЫ И РЕШЕНИЯ

### Проблема 1: Как тестировать AuthHelper (зависит от глобального состояния)?

**Решение:**
- Сделать `extractBearerToken()` публичным методом и передавать заголовки как параметр
- Или использовать dependency injection для SessionRepository и UserRepository
- Для текущего этапа: интеграционные тесты достаточны

### Проблема 2: А если нужен опциональный auth (endpoint работает и с авторизацией, и без)?

**Решение:**
Использовать `AuthHelper::getCurrentUser()` вместо `requireAuth()`:
```php
$user = AuthHelper::getCurrentUser();

if ($user) {
    // Авторизованный пользователь - показать персонализированный контент
} else {
    // Гость - показать публичный контент
}
```

### Проблема 3: Нужна проверка прав (permissions/roles)?

**Решение:**
Добавить в `AuthHelper`:
```php
public static function requireRole(string $role): User
{
    $user = self::requireAuth();
    
    if ($user->getRole()->value !== $role) {
        throw new UnauthorizedException('Access denied', 403);
    }
    
    return $user;
}
```

Использование:
```php
// Только super_admin может импортировать шаблоны
$user = AuthHelper::requireRole('super_admin');
```

---

## 📚 ДОПОЛНИТЕЛЬНЫЕ УЛУЧШЕНИЯ (опционально)

### 1. Логирование попыток авторизации

В `AuthHelper::getCurrentUser()` добавить:
```php
if (!$sessionRepo->isValid($token)) {
    ApiLogger::logError('Invalid token attempt', null, ['token' => substr($token, 0, 8) . '...']);
    return null;
}
```

### 2. Rate limiting

Добавить подсчёт неудачных попыток:
```php
// В случае многих 401 с одного IP - блокировать на 15 минут
```

### 3. Token refresh

Добавить endpoint `/api/auth/refresh`:
```php
public function refresh(): void
{
    $user = AuthHelper::requireAuth();
    
    // Создать новый токен
    $sessionRepo = new MySQLSessionRepository();
    $newToken = $sessionRepo->create($user->getId());
    
    // Вернуть новый токен
    $this->jsonResponse(['token' => $newToken], 200);
}
```

---

## 🎯 СЛЕДУЮЩИЕ ШАГИ ПОСЛЕ РЕАЛИЗАЦИИ

1. **Обновить документацию API:**
   - Добавить раздел "Authentication" в `docs/API_CONTRACT.md`
   - Описать формат Bearer токена
   - Описать коды ошибок (401, 403)

2. **Применить к остальным endpoints:**
   - `PageController::create()` - требует авторизацию?
   - `PageController::update()` - требует авторизацию?
   - `PageController::delete()` - требует авторизацию?
   - `MediaController::upload()` - требует авторизацию?

3. **Добавить проверку прав:**
   - super_admin может всё
   - admin может создавать/редактировать страницы
   - editor может только редактировать

4. **Мониторинг:**
   - Логировать все 401 ошибки
   - Алерты при подозрительной активности

---

## 📝 CHECKLIST РЕАЛИЗАЦИИ

**Создание файлов:**
- [ ] Создать `backend/src/Infrastructure/Auth/UnauthorizedException.php`
- [ ] Создать `backend/src/Infrastructure/Auth/AuthHelper.php`

**Рефакторинг контроллеров:**
- [ ] Обновить `AuthController::me()` - использовать `AuthHelper::requireAuth()`
- [ ] Удалить `AuthController::getBearerToken()` - метод больше не нужен
- [ ] Обновить `TemplateController::import()` - использовать `AuthHelper::requireAuth()`
- [ ] Обновить импорты в обоих контроллерах

**Тестирование:**
- [ ] Запустить PHPUnit тесты - все должны проходить
- [ ] Проверить `/api/auth/me` через curl/Invoke-RestMethod
- [ ] Проверить `/api/templates/{slug}/import` через curl
- [ ] Проверить ошибки авторизации (401, 404)

**Синхронизация:**
- [ ] Синхронизировать изменения в XAMPP (`robocopy` или symlink)
- [ ] Перезапустить Apache (если файлы были заблокированы)

**Документация:**
- [ ] Обновить `docs/PROJECT_STATUS.md` - отметить централизацию авторизации
- [ ] Добавить запись в `docs/RESOLVED_ISSUES.md`
- [ ] Обновить `docs/API_CONTRACT.md` - раздел Authentication

---

## 🎓 АРХИТЕКТУРНЫЕ ВЫВОДЫ

### Почему это хорошая практика?

1. **DRY (Don't Repeat Yourself):** Код авторизации написан один раз
2. **Single Responsibility:** Каждый контроллер делает свою бизнес-логику, авторизация вынесена
3. **Testability:** Легко протестировать авторизацию отдельно от контроллеров
4. **Maintainability:** Изменения в политике авторизации - один файл
5. **Consistency:** Все endpoints ведут себя одинаково при ошибках авторизации

### Почему это НЕ нарушает Clean Architecture?

- `Infrastructure\Auth\AuthHelper` — инфраструктурный слой ✅
- Использует инфраструктурные сервисы (Session/User repositories) ✅
- Не содержит бизнес-логику ✅
- Presentation слой (контроллеры) может использовать Infrastructure ✅
- Domain слой не затронут ✅

### Что дальше?

После успешной реализации можно:
- Добавить middleware pipeline (PSR-15)
- Добавить JWT токены вместо random hex
- Добавить OAuth2 / OpenID Connect
- Добавить двухфакторную аутентификацию

Но всё это будет легко сделать, потому что логика авторизации централизована!

---

**Автор промта:** Claude + Anna  
**Дата создания:** 8 октября 2025  
**Статус:** ✅ Готов к реализации
