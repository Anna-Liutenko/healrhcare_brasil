Цель: защитить PATCH `/api/pages/{id}/card-image` в `CollectionController::updateCardImage()` проверкой аутентификации, авторизации, валидацией URL, аудиту и простым rate-limiter'ом.

Ниже — шаги, точные файлы/места правок, примерный код и критерии приёмки.

Файлы, которые изменить/добавить
- `backend/src/Presentation/Controller/CollectionController.php` — добавить/вызвать:
  - `authenticate(): string` — возвращает userId (или выбрасывает/возвращает 401).
  - `authorize(string $userId, array $allowedRoles): void` — 403 при отказе.
  - `isValidImageUrl(string $url): bool` — SSRF/XSS проверки, возвращает false если невалидно.
  - `auditLog(array $entry): void` — запись событий в `logs/collection-changes.log` (JSONL).
  - `rateLimitCheck(string $key): bool` — простая проверка (при превышении возвращать 429).
  - Внутри `updateCardImage()` — вызывать authenticate -> authorize -> rateLimitCheck -> isValidImageUrl -> usecase -> auditLog -> return 200.

- Репозитории (если уже существуют — использовать их):
  - `backend/src/Infrastructure/Repository/MySQLSessionRepository.php` — метод поиска сессии по token (например `findByToken(string $token)`), проверить expiry.
  - `backend/src/Infrastructure/Repository/MySQLUserRepository.php` — `findById(string $id)` возвращает user с ролью.

- `logs/collection-changes.log` — файл логов (append-only). Убедиться, что папка `logs/` доступна для записи.

(Опционально) Миграция для таблицы rate limiting или простой `logs/collection-rate.json` для быстрого прототипа.

Подробный пример кода (вставлять в `CollectionController.php`)

PHP: извлечь токен и сессию
```
private function authenticate(): string
{
    $headers = getallheaders(); // или ваш request->getHeaderLine('Authorization')
    if (empty($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing Authorization header']);
        exit;
    }

    if (!preg_match('/^Bearer\s+(.+)$/', $headers['Authorization'], $m)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Authorization header']);
        exit;
    }

    $token = $m[1];

    // MySQLSessionRepository должен иметь метод findByToken
    $session = $this->sessionRepository->findByToken($token);
    if (!$session || $session->isExpired()) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }

    return $session->getUserId();
}
```

PHP: authorize по ролям
```
private function authorize(string $userId, array $allowedRoles = ['admin','editor','super_admin']): void
{
    $user = $this->userRepository->findById($userId);
    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    $role = $user->getRole(); // или $user['role']

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}
```

PHP: простая валидация imageUrl + SSRF check
```
private function isValidImageUrl(string $url): bool
{
    // basic structure
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    if (!in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)) {
        return false;
    }

    // optional: check extension
    $path = $parts['path'] ?? '';
    $allowedExt = ['jpg','jpeg','png','webp','gif'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== '' && !in_array($ext, $allowedExt, true)) {
        return false;
    }

    // resolve hostname -> ensure not private/reserved
    $host = $parts['host'] ?? null;
    if (!$host) return false;

    $records = dns_get_record($host, DNS_A + DNS_AAAA);
    if (empty($records)) {
        // fallback to gethostbyname (single)
        $ip = gethostbyname($host);
        if ($ip === $host) { // unresolved
            return false;
        }
        $ips = [$ip];
    } else {
        $ips = array_map(function($r){ return $r['ip'] ?? $r['ipv6'] ?? null; }, $records);
    }

    foreach ($ips as $ip) {
        if ($ip === null) continue;
        // reject private/reserved
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }

    return true;
}
```

PHP: аудит‑лог (JSON lines)
```
private function auditLog(array $entry): void
{
    $entry['timestamp'] = (new \DateTime())->format(\DateTime::ATOM);
    $logFile = __DIR__ . '/../../../logs/collection-changes.log';
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    // безопасная запись
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
```

PHP: rate limit (простая файловая реализация, prototype)
```
private function rateLimitCheck(string $key, int $limit = 30, int $windowSec = 60): bool
{
    $file = __DIR__ . '/../../../logs/collection-rate.json';
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    $now = time();
    if (!isset($data[$key])) {
        $data[$key] = ['count' => 1, 'start' => $now];
    } else {
        if ($now - $data[$key]['start'] > $windowSec) {
            $data[$key] = ['count' => 1, 'start' => $now];
        } else {
            $data[$key]['count'] += 1;
        }
    }
    file_put_contents($file, json_encode($data));
    return $data[$key]['count'] <= $limit;
}
```

Пример использования в начале `updateCardImage()`:
```
$userId = $this->authenticate();
$this->authorize($userId);
// rate limit by userId
if (!$this->rateLimitCheck('collection_patch_user_' . $userId)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$imageUrl = $body['imageUrl'] ?? '';
$targetPageId = $body['targetPageId'] ?? '';
if (!Uuid::isValid($targetPageId) || !$this->isValidImageUrl($imageUrl)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid input']);
    $this->auditLog([
        'action' => 'update_card_image_attempt',
        'userId' => $userId,
        'targetPageId' => $targetPageId,
        'imageUrl' => $imageUrl,
        'outcome' => 'validation_failed',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    exit;
}

// prior to update: fetch old image (if any)
$collectionPage = $this->pageRepository->findById($collectionPageId);
$oldImage = $collectionPage->getCollectionConfig()['cardImages'][$targetPageId] ?? null;

// call use case
$this->updateCardImageUseCase->execute($collectionPageId, $targetPageId, $imageUrl);

// audit success
$this->auditLog([
    'action' => 'update_card_image',
    'userId' => $userId,
    'targetPageId' => $targetPageId,
    'oldImage' => $oldImage,
    'newImage' => $imageUrl,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'outcome' => 'success',
]);

http_response_code(200);
echo json_encode(['status' => 'ok', 'targetPageId' => $targetPageId, 'imageUrl' => $imageUrl]);
```

CSRF и cookie‑sessions (опционально)
- Если проект использует cookie‑based sessions, дополнительно:
  - Требовать заголовок `Authorization: Bearer <token>` для API (лучше) — тогда CSRF риск низкий.
  - Если обязаны поддерживать cookie: проверять `X-CSRF-Token` (с токеном в сессии) и выставлять SameSite=Lax/Strict у cookie.
  - Для простоты и безопасности: отказаться от cookie на этом endpoint и требовать Authorization header.

Логирование и мониторинг
- Лог в `logs/collection-changes.log`: JSON строки, включающие timestamp, userId, client IP, targetPageId, oldImage, newImage, outcome.
- Альтернатива: если есть `SecurityMonitor` в проекте — вызывать его метод: `$this->securityMonitor->alertEvent(...)`.

Тесты: что добавить
- Happy path: валидный токен, пользователь роль `editor`, валидный URL → 200 и auditLog запись.
- Unauthorized: нет заголовка Authorization → 401.
- Invalid token / expired → 401.
- Forbidden: роль `viewer` → 403.
- Invalid image URL → 422 and audit record with validation_failed.
- Rate limit exceeded → 429.

Пример запуска тестов (PowerShell):
```
php vendor/bin/phpunit --testsuite unit
php vendor/bin/phpunit tests/CollectionControllerTest.php -v
```

Контракт/приёмочные критерии (inputs/outputs)
- Вход: PATCH /api/pages/{collectionId}/card-image with JSON body { "targetPageId": "<uuid>", "imageUrl": "<url>" } и заголовок `Authorization: Bearer <token>`.
- Успех: 200 JSON { status: "ok", targetPageId, imageUrl }.
- Ошибки:
  - 401 — missing/invalid/expired token
  - 403 — user exists but role not allowed
  - 422 — invalid input (uuid/url)
  - 429 — rate limit exceeded
  - 500 — internal error (catch и лог)
- Side effects: запись в `logs/collection-changes.log` (успех/попытки/ошибки).

Edge cases и защитные меры
- token present but session expired → 401
- user removed/disabled after session issued → `findById()` → 403
- invalid/malicious `imageUrl` (javascript:, data:) → 422
- hostname resolves to private IP (SSRF) → 422
- race condition при одновременных обновлениях — usecase должен атомарно сохранять collectionConfig (DB transaction).
- file system not writable → fallback в SecurityMonitor и возвращать 500.

Быстрая миграция/инфраструктура (опционально)
- Если хотите устойчивый rate limiter — создать таблицу `rate_limits (key VARCHAR PRIMARY, count INT, window_start INT)` и использовать UPDATE/INSERT атомарно.

Краткая инструкция по проверке/приёмке
1. Юнит‑тесты: все тесты в `tests/CollectionControllerTest.php` проходят (см. выше).
2. Ручной тест:
   - Получить валидную сессию/токен (или мок).
   - Сделать PATCH с валидными данными → 200.
   - Проверить `logs/collection-changes.log` содержит корректную JSON‑запись.
   - Повторить с неправильным токеном → 401.
   - Повторить с пользователем без прав → 403.
   - Повторить с "http://169.254.169.254/latest/meta-data/..." → 422.
3. Проверить, что frontend (editor) получает 401/403/422 и показывает соответствующее сообщение пользователю.
