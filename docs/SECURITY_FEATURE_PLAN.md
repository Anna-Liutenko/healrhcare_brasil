# 📋 Детальный план разработки: Безопасная система управления пользователями

## 🎯 Цель фичи

Реализовать полноценную систему управления пользователями с максимальным уровнем безопасности:
- Email-подтверждение для новых администраторов
- Rate limiting против брутфорса
- CSRF защита
- Audit logging всех админских действий
- Сильная политика паролей
- Ограниченный CORS
- Security headers
- Блокировка аккаунтов после неудачных попыток

**БЕЗ 2FA** (оставляем на будущее).

---

## 📊 Этапы разработки

### **ЭТАП 1: Подготовка инфраструктуры (Foundation)**

#### 1.1 Миграции базы данных

**Создать:** `database/migrations/006_security_enhancements.sql`

```sql
-- Расширение таблицы users для безопасности
ALTER TABLE users
ADD COLUMN failed_login_attempts INT DEFAULT 0 AFTER last_login_at,
ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_login_attempts,
ADD COLUMN password_changed_at TIMESTAMP NULL AFTER locked_until,
ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER password_changed_at,
ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified,
ADD COLUMN email_verification_expires_at TIMESTAMP NULL AFTER email_verification_token;

-- Индексы для производительности
CREATE INDEX idx_email_verified ON users(email_verified);
CREATE INDEX idx_locked_until ON users(locked_until);

-- Таблица для аудита действий администраторов
CREATE TABLE admin_audit_log (
    id VARCHAR(36) PRIMARY KEY,
    admin_user_id VARCHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id VARCHAR(36) NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_user (admin_user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для rate limiting
CREATE TABLE rate_limits (
    id VARCHAR(36) PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL COMMENT 'IP:action или user_id:action',
    attempts INT DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_identifier (identifier),
    INDEX idx_locked_until (locked_until),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для CSRF токенов (опционально, можно хранить в sessions)
ALTER TABLE sessions
ADD COLUMN csrf_token VARCHAR(64) NULL AFTER last_activity;

-- Добавление индекса
CREATE INDEX idx_csrf_token ON sessions(csrf_token);

-- Таблица для хранения истории паролей (предотвращение повторного использования)
CREATE TABLE password_history (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для email-уведомлений (отслеживание отправленных писем)
CREATE TABLE email_notifications (
    id VARCHAR(36) PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'user_created, password_changed, role_changed, etc.',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.2 Скрипт для накатывания миграции

**Создать:** `database/migrations/apply_migration.php`

```php
<?php
// Скрипт для применения миграции 006
```

---

### **ЭТАП 2: Domain Layer (Бизнес-логика)**

#### 2.1 Value Objects

**Создать:** `backend/src/Domain/ValueObject/PasswordPolicy.php`
- Проверка сложности пароля
- Минимум 12 символов
- Требования: uppercase + lowercase + numbers + special chars
- Проверка на общие пароли

**Создать:** `backend/src/Domain/ValueObject/AuditAction.php`
- Enum для типов действий: USER_CREATED, USER_UPDATED, USER_DELETED, PASSWORD_CHANGED, ROLE_CHANGED, USER_ACTIVATED, USER_DEACTIVATED

**Создать:** `backend/src/Domain/ValueObject/EmailVerificationToken.php`
- Генерация безопасного токена
- Проверка срока действия (24 часа)

#### 2.2 Entities

**Обновить:** `backend/src/Domain/Entity/User.php`
- Добавить методы для работы с блокировкой
- `lockAccount(int $minutes): void`
- `unlockAccount(): void`
- `isLocked(): bool`
- `incrementFailedAttempts(): void`
- `resetFailedAttempts(): void`
- `generateEmailVerificationToken(): string`
- `verifyEmail(string $token): bool`

**Создать:** `backend/src/Domain/Entity/AuditLog.php`
```php
class AuditLog {
    private string $id;
    private string $adminUserId;
    private AuditAction $action;
    private string $targetType;
    private ?string $targetId;
    private ?array $details;
    private ?string $ipAddress;
    private ?string $userAgent;
    private DateTime $createdAt;
}
```

**Создать:** `backend/src/Domain/Entity/RateLimit.php`
```php
class RateLimit {
    private string $id;
    private string $identifier; // 'login:192.168.1.1' или 'api:user_123'
    private int $attempts;
    private DateTime $firstAttemptAt;
    private ?DateTime $lockedUntil;
}
```

#### 2.3 Repository Interfaces

**Создать:** `backend/src/Domain/Repository/AuditLogRepositoryInterface.php`
**Создать:** `backend/src/Domain/Repository/RateLimitRepositoryInterface.php`
**Создать:** `backend/src/Domain/Repository/EmailNotificationRepositoryInterface.php`

---

### **ЭТАП 3: Application Layer (Use Cases)**

#### 3.1 Новые Use Cases для безопасности

**Создать:** `backend/src/Application/UseCase/Security/CheckRateLimit.php`
```php
public function execute(string $identifier, int $maxAttempts = 5, int $windowMinutes = 15): bool
```

**Создать:** `backend/src/Application/UseCase/Security/RecordFailedAttempt.php`
```php
public function execute(string $identifier, int $lockoutMinutes = 15): void
```

**Создать:** `backend/src/Application/UseCase/Security/ValidatePasswordStrength.php`
```php
public function execute(string $password, ?string $username = null, ?string $email = null): void
```

**Создать:** `backend/src/Application/UseCase/Security/LogAuditEvent.php`
```php
public function execute(
    string $adminUserId,
    AuditAction $action,
    string $targetType,
    ?string $targetId,
    ?array $details,
    ?string $ipAddress,
    ?string $userAgent
): void
```

#### 3.2 Email Use Cases

**Создать:** `backend/src/Application/UseCase/Email/SendUserCreatedNotification.php`
```php
public function execute(User $user, string $verificationToken, string $tempPassword): void
```

**Создать:** `backend/src/Application/UseCase/Email/SendPasswordChangedNotification.php`
**Создать:** `backend/src/Application/UseCase/Email/SendRoleChangedNotification.php`
**Создать:** `backend/src/Application/UseCase/Email/SendAccountLockedNotification.php`

#### 3.3 Обновление существующих Use Cases

**Обновить:** `backend/src/Application/UseCase/CreateUser.php`
- Интегрировать ValidatePasswordStrength
- Генерировать email verification token
- Отправлять email с подтверждением
- Логировать в audit log
- Добавить в password_history

**Обновить:** `backend/src/Application/UseCase/UpdateUser.php`
- Проверять изменения роли → отправлять email
- Проверять изменения пароля → отправлять email
- Логировать все изменения в audit log

**Обновить:** `backend/src/Application/UseCase/Login.php`
- Проверять rate limit по IP
- Проверять блокировку аккаунта
- Проверять email_verified (опционально)
- Инкрементировать failed_attempts при ошибке
- Сбрасывать failed_attempts при успехе
- Генерировать CSRF токен

**Обновить:** `backend/src/Application/UseCase/DeleteUser.php`
- Логировать в audit log
- Отправлять email (опционально)

#### 3.4 Новые Use Cases для верификации email

**Создать:** `backend/src/Application/UseCase/VerifyAdminEmail.php`
```php
public function execute(string $token): User
```

**Создать:** `backend/src/Application/UseCase/ResendVerificationEmail.php`
```php
public function execute(string $userId): void
```

---

### **ЭТАП 4: Infrastructure Layer (Реализации)**

#### 4.1 Repository Implementations

**Создать:** `backend/src/Infrastructure/Repository/MySQLAuditLogRepository.php`
- `save(AuditLog $log): void`
- `findByAdminUser(string $adminUserId, int $limit = 100): array`
- `findByTarget(string $targetType, string $targetId): array`
- `findRecent(int $limit = 100): array`

**Создать:** `backend/src/Infrastructure/Repository/MySQLRateLimitRepository.php`
- `findByIdentifier(string $identifier): ?RateLimit`
- `save(RateLimit $rateLimit): void`
- `cleanExpired(): int`

**Создать:** `backend/src/Infrastructure/Repository/MySQLEmailNotificationRepository.php`
- `save(EmailNotification $notification): void`
- `markAsSent(string $id): void`
- `markAsFailed(string $id, string $error): void`

**Создать:** `backend/src/Infrastructure/Repository/MySQLPasswordHistoryRepository.php`
- `save(string $userId, string $passwordHash): void`
- `isPasswordUsedBefore(string $userId, string $password, int $historyLimit = 5): bool`

#### 4.2 Email Service

**Создать:** `backend/src/Infrastructure/Email/EmailService.php`
```php
class EmailService {
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    private function sendViaPHPMailer(...): bool  // или через SMTP
}
```

**Создать:** `backend/src/Infrastructure/Email/EmailTemplates.php`
- Шаблоны email-сообщений
- `userCreatedTemplate(User $user, string $verificationUrl, string $tempPassword): string`
- `passwordChangedTemplate(User $user): string`
- `roleChangedTemplate(User $user, string $oldRole, string $newRole): string`
- `accountLockedTemplate(User $user, int $lockMinutes): string`

#### 4.3 Security Middleware

**Создать:** `backend/src/Infrastructure/Middleware/RateLimitMiddleware.php`
- Проверяет rate limit перед выполнением запроса
- Возвращает 429 Too Many Requests

**Создать:** `backend/src/Infrastructure/Middleware/CsrfMiddleware.php`
- Проверяет CSRF токен на POST/PUT/DELETE запросах
- Генерирует новый токен при логине

**Создать:** `backend/src/Infrastructure/Middleware/SecurityHeadersMiddleware.php`
- Добавляет security headers:
  - X-Content-Type-Options: nosniff
  - X-Frame-Options: DENY
  - X-XSS-Protection: 1; mode=block
  - Strict-Transport-Security
  - Content-Security-Policy

**Обновить:** `backend/src/Infrastructure/Middleware/CorsMiddleware.php` (создать если нет)
- Заменить `Access-Control-Allow-Origin: *` на список разрешённых доменов
- Добавить проверку origin

---

### **ЭТАП 5: Presentation Layer (Controllers & Routes)**

#### 5.1 Новые endpoints

**Обновить:** `backend/src/Presentation/Controller/UserController.php`
- Интегрировать все новые use cases
- Добавить audit logging
- Добавить rate limiting проверки

**Создать:** `backend/src/Presentation/Controller/AdminAuditController.php`
```php
// GET /api/admin/audit-logs
public function index(): void

// GET /api/admin/audit-logs/user/{userId}
public function getUserLogs(string $userId): void
```

**Создать:** `backend/src/Presentation/Controller/EmailVerificationController.php`
```php
// GET /api/auth/verify-email/{token}
public function verify(string $token): void

// POST /api/auth/resend-verification
public function resend(): void
```

#### 5.2 Обновление роутинга

**Обновить:** `backend/public/index.php`

Добавить новые routes:
```php
// Email verification
elseif (preg_match('#^/api/auth/verify-email/([a-zA-Z0-9]+)$#', $uri, $matches) && $method === 'GET') {
    $controller = new \Presentation\Controller\EmailVerificationController();
    $controller->verify($matches[1]);
}
elseif (preg_match('#^/api/auth/resend-verification$#', $uri) && $method === 'POST') {
    $controller = new \Presentation\Controller\EmailVerificationController();
    $controller->resend();
}

// Audit logs (super_admin only)
elseif (preg_match('#^/api/admin/audit-logs$#', $uri) && $method === 'GET') {
    $controller = new \Presentation\Controller\AdminAuditController();
    $controller->index();
}
elseif (preg_match('#^/api/admin/audit-logs/user/([a-f0-9-]+)$#', $uri, $matches) && $method === 'GET') {
    $controller = new \Presentation\Controller\AdminAuditController();
    $controller->getUserLogs($matches[1]);
}
```

Добавить middleware:
```php
// Apply security headers to all responses
$securityHeaders = new \Infrastructure\Middleware\SecurityHeadersMiddleware();
$securityHeaders->apply();

// Apply CORS restrictions
$cors = new \Infrastructure\Middleware\CorsMiddleware();
$cors->apply();
```

---

### **ЭТАП 6: Frontend Updates**

#### 6.1 Обновления UI для управления пользователями

**Обновить:** `frontend/index.html` (admin panel)

Добавить в модальное окно управления пользователями:
- Индикатор статуса email-верификации
- Кнопка "Переслать письмо подтверждения"
- Индикатор блокировки аккаунта
- Кнопка "Разблокировать вручную"
- Требования к паролю в реальном времени (strength meter)

#### 6.2 Новая страница для audit logs

**Создать:** `frontend/audit-logs.html` (отдельная страница или вкладка в user management)
- Таблица с логами
- Фильтры: по действию, по пользователю, по дате
- Пагинация
- Экспорт в CSV

#### 6.3 Email verification page

**Создать:** `frontend/verify-email.html`
- Простая страница с сообщением "Проверяем ваш email..."
- Автоматический редирект после верификации

#### 6.4 Password strength indicator

**Обновить:** `frontend/index.html`

Добавить компонент для визуализации силы пароля:
```javascript
// В userForm секции добавить
<div v-if="userForm.password" class="password-strength">
    <div class="strength-bar" :class="passwordStrengthClass"></div>
    <span class="strength-text">{{ passwordStrengthText }}</span>
</div>
```

#### 6.5 CSRF Token Integration

**Обновить:** `frontend/api-client.js`

```javascript
class ApiClient {
    constructor() {
        this.csrfToken = null;
    }

    setCsrfToken(token) {
        this.csrfToken = token;
    }

    async request(endpoint, options = {}) {
        // Добавлять X-CSRF-Token header к POST/PUT/DELETE
        if (['POST', 'PUT', 'DELETE'].includes(options.method) && this.csrfToken) {
            options.headers = options.headers || {};
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }
        // ... rest of request logic
    }
}
```

---

### **ЭТАП 7: Configuration & Environment**

#### 7.1 Email Configuration

**Создать:** `backend/config/email.php`

```php
<?php
return [
    'driver' => getenv('MAIL_DRIVER') ?: 'smtp', // smtp, sendmail, mailgun
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => getenv('MAIL_PORT') ?: 587,
    'username' => getenv('MAIL_USERNAME'),
    'password' => getenv('MAIL_PASSWORD'),
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@healthcarebrasil.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Healthcare Brasil CMS',
];
```

**Создать:** `backend/.env.example`

```env
# Email Configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@healthcarebrasil.com
MAIL_FROM_NAME=Healthcare Brasil CMS

# Security
ALLOWED_ORIGINS=https://yourdomain.com,http://localhost:3000
RATE_LIMIT_MAX_ATTEMPTS=5
RATE_LIMIT_WINDOW_MINUTES=15
ACCOUNT_LOCKOUT_MINUTES=30
SESSION_LIFETIME=3600
PASSWORD_MIN_LENGTH=12
REQUIRE_EMAIL_VERIFICATION=true
```

#### 7.2 Security Configuration

**Создать:** `backend/config/security.php`

```php
<?php
return [
    'allowed_origins' => explode(',', getenv('ALLOWED_ORIGINS') ?: '*'),
    'rate_limit' => [
        'max_attempts' => (int) (getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 5),
        'window_minutes' => (int) (getenv('RATE_LIMIT_WINDOW_MINUTES') ?: 15),
        'lockout_minutes' => (int) (getenv('ACCOUNT_LOCKOUT_MINUTES') ?: 30),
    ],
    'session' => [
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 3600), // 1 hour
    ],
    'password' => [
        'min_length' => (int) (getenv('PASSWORD_MIN_LENGTH') ?: 12),
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => true,
        'prevent_common' => true,
        'history_limit' => 5, // не использовать последние 5 паролей
    ],
    'email_verification' => [
        'required' => filter_var(getenv('REQUIRE_EMAIL_VERIFICATION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'token_lifetime' => 86400, // 24 hours
    ],
];
```

---

### **ЭТАП 8: Testing**

#### 8.1 Unit Tests

**Создать:** `backend/tests/Unit/Security/PasswordPolicyTest.php`
**Создать:** `backend/tests/Unit/Security/RateLimiterTest.php`
**Создать:** `backend/tests/Unit/UseCase/ValidatePasswordStrengthTest.php`

#### 8.2 Integration Tests

**Создать:** `backend/tests/Integration/UserManagement/CreateUserWithEmailTest.php`
- Тест создания пользователя
- Проверка отправки email
- Проверка записи в audit log

**Создать:** `backend/tests/Integration/Security/RateLimitingTest.php`
- Тест блокировки после 5 попыток
- Тест разблокировки по времени

**Создать:** `backend/tests/Integration/Security/CsrfProtectionTest.php`
- Тест отклонения запроса без CSRF токена
- Тест успешного запроса с токеном

#### 8.3 E2E Tests

**Создать:** `backend/tests/e2e/UserManagementSecurityE2ETest.php`
- Полный flow: создание → email → верификация → вход

---

### **ЭТАП 9: Documentation**

#### 9.1 API Documentation

**Обновить:** `docs/API_CONTRACT.md`

Добавить документацию для новых endpoints:
- POST /api/users (с обновлённой валидацией)
- GET /api/auth/verify-email/{token}
- POST /api/auth/resend-verification
- GET /api/admin/audit-logs
- GET /api/admin/audit-logs/user/{userId}

#### 9.2 Security Documentation

**Создать:** `docs/SECURITY.md`

```markdown
# 🔒 Security Features

## Password Policy
- Minimum 12 characters
- Must contain: uppercase, lowercase, numbers, special characters
- Cannot reuse last 5 passwords
- Common passwords are blocked

## Rate Limiting
- Login: 5 attempts per 15 minutes
- Account locked for 30 minutes after 5 failures

## CSRF Protection
- All POST/PUT/DELETE requests require CSRF token
- Token generated at login

## Email Verification
- New admins must verify email before first login
- Verification token expires in 24 hours

## Audit Logging
- All admin actions are logged
- Includes: IP address, user agent, timestamp

## Session Security
- 1 hour lifetime (configurable)
- Automatic expiration
- Token rotation on privilege escalation
```

#### 9.3 Admin Guide

**Создать:** `docs/ADMIN_USER_MANAGEMENT_GUIDE.md`

Пошаговая инструкция для администраторов:
- Как создать нового пользователя
- Как работает email-верификация
- Как разблокировать аккаунт
- Как просмотреть audit logs
- Как сменить роль пользователя

---

### **ЭТАП 10: Deployment & Migration**

#### 10.1 Migration Script

**Создать:** `backend/scripts/migrate_security_features.php`

```php
<?php
// Применяет миграцию 006
// Проверяет существование таблиц
// Создаёт недостающие индексы
```

#### 10.2 Rollback Script

**Создать:** `backend/scripts/rollback_security_features.php`

```php
<?php
// Откатывает миграцию 006 (на случай проблем)
```

#### 10.3 Deployment Checklist

**Создать:** `docs/DEPLOYMENT_SECURITY_CHECKLIST.md`

```markdown
## Pre-Deployment
- [ ] Run migration 006
- [ ] Configure .env with email credentials
- [ ] Set ALLOWED_ORIGINS
- [ ] Test email sending
- [ ] Run all tests

## Deployment
- [ ] Deploy backend code
- [ ] Deploy frontend code
- [ ] Apply database migrations
- [ ] Clear cache if any
- [ ] Test in staging

## Post-Deployment
- [ ] Verify email sending works
- [ ] Test login rate limiting
- [ ] Test CSRF protection
- [ ] Check audit logs
- [ ] Monitor error logs

## Rollback Plan
- [ ] Keep rollback script ready
- [ ] Database backup before migration
- [ ] Quick rollback procedure documented
```

---

## 📁 Полный список файлов

### **Новые файлы (создать):**

#### Database
1. `database/migrations/006_security_enhancements.sql`
2. `database/migrations/apply_migration.php`

#### Domain Layer
3. `backend/src/Domain/ValueObject/PasswordPolicy.php`
4. `backend/src/Domain/ValueObject/AuditAction.php`
5. `backend/src/Domain/ValueObject/EmailVerificationToken.php`
6. `backend/src/Domain/Entity/AuditLog.php`
7. `backend/src/Domain/Entity/RateLimit.php`
8. `backend/src/Domain/Entity/EmailNotification.php`
9. `backend/src/Domain/Repository/AuditLogRepositoryInterface.php`
10. `backend/src/Domain/Repository/RateLimitRepositoryInterface.php`
11. `backend/src/Domain/Repository/EmailNotificationRepositoryInterface.php`
12. `backend/src/Domain/Repository/PasswordHistoryRepositoryInterface.php`

#### Application Layer
13. `backend/src/Application/UseCase/Security/CheckRateLimit.php`
14. `backend/src/Application/UseCase/Security/RecordFailedAttempt.php`
15. `backend/src/Application/UseCase/Security/ValidatePasswordStrength.php`
16. `backend/src/Application/UseCase/Security/LogAuditEvent.php`
17. `backend/src/Application/UseCase/Email/SendUserCreatedNotification.php`
18. `backend/src/Application/UseCase/Email/SendPasswordChangedNotification.php`
19. `backend/src/Application/UseCase/Email/SendRoleChangedNotification.php`
20. `backend/src/Application/UseCase/Email/SendAccountLockedNotification.php`
21. `backend/src/Application/UseCase/VerifyAdminEmail.php`
22. `backend/src/Application/UseCase/ResendVerificationEmail.php`

#### Infrastructure Layer
23. `backend/src/Infrastructure/Repository/MySQLAuditLogRepository.php`
24. `backend/src/Infrastructure/Repository/MySQLRateLimitRepository.php`
25. `backend/src/Infrastructure/Repository/MySQLEmailNotificationRepository.php`
26. `backend/src/Infrastructure/Repository/MySQLPasswordHistoryRepository.php`
27. `backend/src/Infrastructure/Email/EmailService.php`
28. `backend/src/Infrastructure/Email/EmailTemplates.php`
29. `backend/src/Infrastructure/Middleware/RateLimitMiddleware.php`
30. `backend/src/Infrastructure/Middleware/CsrfMiddleware.php`
31. `backend/src/Infrastructure/Middleware/SecurityHeadersMiddleware.php`
32. `backend/src/Infrastructure/Middleware/CorsMiddleware.php`

#### Presentation Layer
33. `backend/src/Presentation/Controller/AdminAuditController.php`
34. `backend/src/Presentation/Controller/EmailVerificationController.php`

#### Configuration
35. `backend/config/email.php`
36. `backend/config/security.php`
37. `backend/.env.example`

#### Frontend
38. `frontend/audit-logs.html`
39. `frontend/verify-email.html`

#### Scripts
40. `backend/scripts/migrate_security_features.php`
41. `backend/scripts/rollback_security_features.php`
42. `backend/scripts/cleanup_rate_limits.php` (cron job)
43. `backend/scripts/send_pending_emails.php` (cron job)

#### Tests
44. `backend/tests/Unit/Security/PasswordPolicyTest.php`
45. `backend/tests/Unit/Security/RateLimiterTest.php`
46. `backend/tests/Unit/UseCase/ValidatePasswordStrengthTest.php`
47. `backend/tests/Integration/UserManagement/CreateUserWithEmailTest.php`
48. `backend/tests/Integration/Security/RateLimitingTest.php`
49. `backend/tests/Integration/Security/CsrfProtectionTest.php`
50. `backend/tests/e2e/UserManagementSecurityE2ETest.php`

#### Documentation
51. `docs/SECURITY.md`
52. `docs/ADMIN_USER_MANAGEMENT_GUIDE.md`
53. `docs/DEPLOYMENT_SECURITY_CHECKLIST.md`
54. `docs/SECURITY_FEATURE_PLAN.md` (этот документ)

### **Файлы для изменения:**

55. `backend/src/Domain/Entity/User.php` - добавить методы для блокировки и верификации
56. `backend/src/Application/UseCase/CreateUser.php` - интегрировать валидацию и email
57. `backend/src/Application/UseCase/UpdateUser.php` - добавить audit logging
58. `backend/src/Application/UseCase/Login.php` - добавить rate limiting
59. `backend/src/Application/UseCase/DeleteUser.php` - добавить audit logging
60. `backend/src/Presentation/Controller/UserController.php` - интегрировать новые use cases
61. `backend/public/index.php` - добавить routes и middleware
62. `frontend/index.html` - обновить UI для user management
63. `frontend/api-client.js` - добавить CSRF token support
64. `docs/API_CONTRACT.md` - документировать новые endpoints
65. `database/DATABASE_SCHEMA.md` - обновить схему

---

## 📅 Временная оценка (по этапам)

| Этап | Описание | Время |
|------|----------|-------|
| 1 | Подготовка инфраструктуры (БД миграции) | 2 часа |
| 2 | Domain Layer (entities, value objects) | 4 часа |
| 3 | Application Layer (use cases) | 8 часов |
| 4 | Infrastructure Layer (repositories, email, middleware) | 12 часов |
| 5 | Presentation Layer (controllers, routes) | 4 часа |
| 6 | Frontend Updates (UI, CSRF, password strength) | 6 часов |
| 7 | Configuration & Environment | 2 часа |
| 8 | Testing (unit, integration, e2e) | 8 часов |
| 9 | Documentation | 3 часа |
| 10 | Deployment & Migration | 3 часа |
| **ИТОГО** | | **~52 часа** (6-7 рабочих дней) |

---

## 🎯 Приоритеты реализации

### **Критично (должно быть в MVP):**
1. ✅ Rate Limiting на login
2. ✅ Ограничение CORS
3. ✅ Сильная политика паролей
4. ✅ CSRF защита
5. ✅ Email-подтверждение для новых админов
6. ✅ Audit logging

### **Важно (можно добавить в следующей итерации):**
7. ⏸️ Блокировка аккаунта после неудачных попыток
8. ⏸️ Password history (предотвращение повторного использования)
9. ⏸️ Security headers
10. ⏸️ Email уведомления о всех действиях

### **Желательно (для будущих версий):**
11. 🔮 Проверка паролей на утечки (haveibeenpwned API)
12. 🔮 IP whitelist для admin panel
13. 🔮 Детальная статистика по audit logs
14. 🔮 Export audit logs в CSV/Excel

---

## ✅ Критерии готовности (Definition of Done)

Фича считается завершённой, когда:

- [ ] Все миграции применены и работают
- [ ] Все новые файлы созданы согласно плану
- [ ] Все существующие файлы обновлены
- [ ] Email-уведомления отправляются корректно
- [ ] Rate limiting работает (проверено вручную)
- [ ] CSRF защита работает
- [ ] Audit logs записываются для всех действий
- [ ] Все тесты (unit, integration, e2e) проходят
- [ ] Документация написана
- [ ] Code review пройден
- [ ] Протестировано в staging окружении
- [ ] Deployment checklist выполнен

---

## 🚀 Порядок разработки (рекомендуемый)

**Итерация 1: Foundation (Дни 1-2)**
- Миграции БД
- Domain entities и value objects
- Repository interfaces

**Итерация 2: Core Security (Дни 3-4)**
- Rate limiting
- Password validation
- Audit logging
- Repositories implementations

**Итерация 3: Email Integration (День 5)**
- Email service
- Email templates
- Email verification use cases
- Email notification repository

**Итерация 4: Controllers & Routes (День 6)**
- Обновление controllers
- Новые endpoints
- Middleware integration
- CSRF protection

**Итерация 5: Frontend & Testing (День 7)**
- UI updates
- CSRF token integration
- Password strength indicator
- All tests
- Documentation

**Итерация 6: Deployment (День 8)**
- Staging deployment
- Production deployment
- Monitoring
- Bug fixes

---

## 📌 Важные замечания

### Соблюдение правил проекта:
1. ✅ **НЕ устанавливаем новые библиотеки** - используем только встроенные возможности PHP
2. ✅ **Clean Architecture** - соблюдаем слои Domain → Application → Infrastructure → Presentation
3. ✅ **Vanilla Vue.js** - используем Vue через CDN, никаких npm пакетов
4. ✅ **UTF-8mb4** - везде используем правильную кодировку
5. ✅ **Валидация на backend** - всегда проверяем на сервере
6. ✅ **Проверка прав на backend** - никогда не доверяем frontend

### Безопасность:
- Все пароли хэшируются с bcrypt (cost=10)
- Все токены генерируются криптографически безопасным способом
- Все SQL запросы используют prepared statements
- Все выходные данные экранируются
- Все входные данные валидируются

### Email:
- Используем PHPMailer (уже должен быть в проекте) или встроенный mail()
- Шаблоны email простые, без сложной вёрстки
- Fallback на логирование, если email не отправлен

### Производительность:
- Индексы на всех новых таблицах
- Rate limits чистятся по cron (cleanup_rate_limits.php)
- Audit logs можно архивировать (хранить только за последние 90 дней)

---

## 📊 Диаграмма архитектуры безопасности

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (Vue.js)                        │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  User Management Modal                              │    │
│  │  - Create/Edit/Delete Users                         │    │
│  │  - Password Strength Indicator                      │    │
│  │  - Email Verification Status                        │    │
│  │  - Audit Logs Viewer                                │    │
│  └─────────────────────┬───────────────────────────────┘    │
└────────────────────────┼────────────────────────────────────┘
                         │
                         │ HTTPS + CSRF Token
                         ↓
┌─────────────────────────────────────────────────────────────┐
│              MIDDLEWARE (Security Layer)                    │
│  ┌─────────────┬─────────────┬──────────────┬─────────┐    │
│  │ CORS Check  │ Rate Limit  │ CSRF Check   │ Headers │    │
│  │ Middleware  │ Middleware  │ Middleware   │ MW      │    │
│  └─────────────┴─────────────┴──────────────┴─────────┘    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ↓
┌─────────────────────────────────────────────────────────────┐
│              CONTROLLERS (Presentation)                     │
│  ┌───────────────┬──────────────────┬────────────────┐      │
│  │ UserController│ AuditController  │ EmailVerif     │      │
│  │               │                  │ Controller     │      │
│  └───────┬───────┴────────┬─────────┴────────┬───────┘      │
└──────────┼────────────────┼──────────────────┼──────────────┘
           │                │                  │
           ↓                ↓                  ↓
┌─────────────────────────────────────────────────────────────┐
│                 USE CASES (Application)                     │
│  ┌────────────────┬─────────────────┬──────────────────┐    │
│  │ CreateUser     │ CheckRateLimit  │ VerifyEmail      │    │
│  │ UpdateUser     │ ValidatePassword│ SendEmail        │    │
│  │ DeleteUser     │ LogAuditEvent   │ RecordAttempt    │    │
│  └────────┬───────┴────────┬────────┴──────────┬───────┘    │
└───────────┼────────────────┼───────────────────┼────────────┘
            │                │                   │
            ↓                ↓                   ↓
┌─────────────────────────────────────────────────────────────┐
│              REPOSITORIES (Infrastructure)                  │
│  ┌──────────────┬──────────────┬─────────────────────┐      │
│  │ User Repo    │ RateLimit    │ AuditLog Repo       │      │
│  │ Email Repo   │ Repo         │ PasswordHistory     │      │
│  └──────┬───────┴──────┬───────┴──────────┬──────────┘      │
└─────────┼──────────────┼──────────────────┼─────────────────┘
          │              │                  │
          ↓              ↓                  ↓
┌─────────────────────────────────────────────────────────────┐
│                    MySQL DATABASE                           │
│  ┌──────────┬──────────────┬─────────────┬────────────┐     │
│  │ users    │ rate_limits  │ audit_log   │ password   │     │
│  │ sessions │              │             │ _history   │     │
│  └──────────┴──────────────┴─────────────┴────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

---

Этот план полностью соответствует архитектуре проекта, правилам из документации и обеспечивает максимальную безопасность без ущерба функциональности!
