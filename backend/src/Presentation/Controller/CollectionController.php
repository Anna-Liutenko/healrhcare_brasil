<?php
declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetCollectionItems;
use Application\UseCase\UpdateCollectionCardImage;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Audit\FileAuditLogger;

/**
 * Controller: Управление коллекциями
 */
class CollectionController
{
    private $pageRepository;
    private $blockRepository;
    private $sessionRepository;
    private $userRepository;
    private $updateCardImageUseCase;
    private $securityMonitor;
    private $auditLogger;
    private $requireCsrfForCookieSessions = false;
    private $lastSession = null;

    public function __construct(
        $pageRepository = null,
        $blockRepository = null,
        $sessionRepository = null,
        $userRepository = null,
        $updateCardImageUseCase = null,
        $securityMonitor = null,
        $auditLogger = null,
        bool $requireCsrfForCookieSessions = false
    ) {
        $this->pageRepository = $pageRepository ?? new MySQLPageRepository();
        $this->blockRepository = $blockRepository ?? new MySQLBlockRepository();
        $this->sessionRepository = $sessionRepository ?? new MySQLSessionRepository();
        $this->userRepository = $userRepository ?? new MySQLUserRepository();
        $this->updateCardImageUseCase = $updateCardImageUseCase;
        $this->securityMonitor = $securityMonitor;
        if ($auditLogger !== null) {
            $this->auditLogger = $auditLogger;
        } else {
            $this->auditLogger = new FileAuditLogger();
        }
        $this->requireCsrfForCookieSessions = $requireCsrfForCookieSessions;
    }
    /**
     * GET /api/pages/:id/collection-items
     * 
     * Получить элементы коллекции (карточки статей/гайдов)
     */
    public function getItems(string $pageId): void
    {
        try {
            // Basic validation of UUID-ish id
            if (!preg_match('/^[a-z0-9-]{36}$/i', $pageId)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid page id']);
                return;
            }

            // Read pagination params from query string
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;

            $pageRepo = $this->pageRepository;
            $blockRepo = $this->blockRepository;

            // Read optional section parameter
            $section = $_GET['section'] ?? null;
            if ($section !== null && !in_array($section, ['guides', 'articles'], true)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid section']);
                return;
            }

            $useCase = new GetCollectionItems($pageRepo, $blockRepo);
            $result = $useCase->execute($pageId, $section, $page, $limit);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $code = (int)$e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * PATCH /api/pages/:id/card-image
     * 
     * Обновить картинку карточки в коллекции
     */
    public function updateCardImage(string $pageId, ?array $input = null): void
    {
        try {
            // Basic validation
            if (!preg_match('/^[a-z0-9-]{36}$/i', $pageId)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid page id']);
                return;
            }

            // Authenticate and authorize
            $userId = $this->authenticate();
            $this->authorize($userId);

            // If configured to require CSRF for cookie sessions, check token header
            if ($this->requireCsrfForCookieSessions) {
                $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['X-CSRF-TOKEN'] ?? null;
                // Compare with token stored in session (if available)
                $sessionToken = is_array($this->lastSession) ? ($this->lastSession['csrf_token'] ?? null) : null;
                if (empty($csrf) || ($sessionToken !== null && hash_equals((string)$sessionToken, (string)$csrf) === false)) {
                    throw new \Exception('Missing CSRF token', 403);
                }
            }

            // simple rate limit per user
            $rateKey = 'collection_patch_user_' . $userId;
            if (!$this->rateLimitCheck($rateKey)) {
                throw new \Exception('Too many requests', 429);
            }

            if ($input === null) {
                $input = json_decode(file_get_contents('php://input'), true);
            }

            if (!isset($input['targetPageId']) || !isset($input['imageUrl'])) {
                $this->auditLog([
                    'action' => 'update_card_image_attempt',
                    'userId' => $userId,
                    'targetPageId' => $input['targetPageId'] ?? null,
                    'imageUrl' => $input['imageUrl'] ?? null,
                    'outcome' => 'validation_failed',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                throw new \Exception('Missing targetPageId or imageUrl', 422);
            }

            $targetPageId = $input['targetPageId'];
            $imageUrl = $input['imageUrl'];

            // validate target id
            if (!preg_match('/^[a-f0-9-]{36}$/i', $targetPageId)) {
                $this->auditLog([
                    'action' => 'update_card_image_attempt',
                    'userId' => $userId,
                    'targetPageId' => $targetPageId,
                    'imageUrl' => $imageUrl,
                    'outcome' => 'validation_failed',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                throw new \Exception('Invalid targetPageId', 422);
            }

            if (!$this->isValidImageUrl($imageUrl)) {
                $this->auditLog([
                    'action' => 'update_card_image_attempt',
                    'userId' => $userId,
                    'targetPageId' => $targetPageId,
                    'imageUrl' => $imageUrl,
                    'outcome' => 'validation_failed',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                throw new \Exception('Invalid imageUrl', 422);
            }

            $pageRepo = $this->pageRepository;

            // fetch previous value for audit
            $collectionPage = $pageRepo->findById($pageId);
            $oldImage = null;
            if ($collectionPage !== null) {
                $config = $collectionPage->getCollectionConfig();
                if (is_array($config) && isset($config['cardImages'][$targetPageId])) {
                    $oldImage = $config['cardImages'][$targetPageId];
                }
            }

            if ($this->updateCardImageUseCase !== null) {
                $useCase = $this->updateCardImageUseCase;
            } else {
                $useCase = new UpdateCollectionCardImage($pageRepo);
            }

            $useCase->execute($pageId, $targetPageId, $imageUrl);

            $this->auditLog([
                'action' => 'update_card_image',
                'userId' => $userId,
                'targetPageId' => $targetPageId,
                'oldImage' => $oldImage,
                'newImage' => $imageUrl,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'outcome' => 'success',
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Card image updated'
            ]);
        } catch (\Exception $e) {
            $code = (int)$e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // === Helpers: auth, authorization, validation, audit, rate limit ===
    protected function authenticate(): string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        // Try common server variables as fallback
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if (empty($authHeader)) {
            throw new \Exception('Missing Authorization header', 401);
        }

        if (!preg_match('/^Bearer\s+(.+)$/', $authHeader, $m)) {
            throw new \Exception('Invalid Authorization header', 401);
        }

        $token = $m[1];

        // use isValid() if available
        if (method_exists($this->sessionRepository, 'isValid')) {
            if (!$this->sessionRepository->isValid($token)) {
                throw new \Exception('Invalid or expired token', 401);
            }
        }

        $session = $this->sessionRepository->findByToken($token);
        if (!$session) {
            throw new \Exception('Invalid or expired token', 401);
        }

        // session row contains user_id
        // keep session available for downstream checks (CSRF etc.)
        $this->lastSession = $session;
        if (is_array($session) && isset($session['user_id'])) {
            return $session['user_id'];
        }

        // object support
        if (is_object($session) && method_exists($session, 'getUserId')) {
            return $session->getUserId();
        }

        throw new \Exception('Invalid session payload', 401);
    }

    protected function authorize(string $userId, array $allowedRoles = ['admin','editor','super_admin']): void
    {
    $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \Exception('User not found', 403);
        }

        $role = null;
        if (is_object($user) && method_exists($user, 'getRole')) {
            $roleObj = $user->getRole();
            if (is_object($roleObj) && (property_exists($roleObj, 'value') || isset($roleObj->value))) {
                $role = $roleObj->value;
            } elseif (is_string($roleObj)) {
                $role = $roleObj;
            }
        } elseif (is_array($user) && isset($user['role'])) {
            $role = $user['role'];
        }

        if (!in_array($role, $allowedRoles, true)) {
            throw new \Exception('Forbidden', 403);
        }
    }

    protected function isValidImageUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)) {
            return false;
        }

        $path = $parts['path'] ?? '';
        $allowedExt = ['jpg','jpeg','png','webp','gif'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && !in_array($ext, $allowedExt, true)) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if (!$host) return false;

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($records)) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                return false;
            }
            $ips = [$ip];
        } else {
            $ips = array_map(function($r){ return $r['ip'] ?? $r['ipv6'] ?? null; }, $records);
        }

        foreach ($ips as $ip) {
            if ($ip === null) continue;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    protected function auditLog(array $entry): void
    {
        $entry['timestamp'] = (new \DateTime())->format(\DateTime::ATOM);

        // Normalize entry: convert objects to scalars where possible to avoid json_encode errors
        $normalize = function($value) use (&$normalize) {
            if (is_array($value)) {
                $res = [];
                foreach ($value as $k => $v) {
                    $res[$k] = $normalize($v);
                }
                return $res;
            }
            if (is_object($value)) {
                // If object has property 'value' (e.g., enum-like), return it
                if (property_exists($value, 'value')) {
                    return $value->value;
                }
                // If object has toArray method
                if (method_exists($value, 'toArray')) {
                    return $normalize($value->toArray());
                }
                // If object has __toString
                if (method_exists($value, '__toString')) {
                    return (string)$value;
                }
                // Fallback: return class name and public properties
                $props = get_object_vars($value);
                if (!empty($props)) {
                    return $normalize($props);
                }
                return ['__class' => get_class($value)];
            }
            return $value;
        };

        $safeEntry = $normalize($entry);

        // If an audit logger was injected, use it (test harness can provide a stub)
        if ($this->auditLogger !== null && method_exists($this->auditLogger, 'write')) {
            try {
                $this->auditLogger->write($safeEntry);
                return;
            } catch (\Throwable $e) {
                // Fall through to file fallback and notify security monitor
            }
        }

        $logFile = __DIR__ . '/../../../logs/collection-changes.log';
        $line = json_encode($safeEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $res = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if ($res === false && $this->securityMonitor !== null && method_exists($this->securityMonitor, 'alertEvent')) {
            $this->securityMonitor->alertEvent('audit_log_write_failed', $safeEntry);
        }
    }

    protected function rateLimitCheck(string $key, int $limit = 30, int $windowSec = 60): bool
    {
        $file = __DIR__ . '/../../../logs/collection-rate.json';
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(@file_get_contents($file), true) ?: [];
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
        @file_put_contents($file, json_encode($data));
        return $data[$key]['count'] <= $limit;
    }
}
