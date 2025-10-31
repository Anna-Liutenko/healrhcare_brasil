<?php

declare(strict_types=1);

namespace Infrastructure\Middleware;

/**
 * API Logger Middleware
 *
 * Логирует входящие запросы и исходящие ответы для отладки и мониторинга.
 * По умолчанию в production среде логирование можно отключить переменной окружения
 * API_LOG_ENABLED=false. Ошибки (status >= 400) логируются всегда.
 */
class ApiLogger
{
    private const MAX_PAYLOAD_LENGTH = 4000;

    /** @var string[] */
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'secret',
        'api_key'
    ];

    private static string $logDir;
    private static bool $initialized = false;
    private static bool $logRequests = true;
    private static ?string $requestBody = null;
    private static array $requestHeaders = [];

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$logDir = dirname(__DIR__, 3) . '/logs';

        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        $logEnabledEnv = getenv('API_LOG_ENABLED');
        if ($logEnabledEnv !== false) {
            self::$logRequests = filter_var($logEnabledEnv, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        } else {
            $appEnv = getenv('APP_ENV') ?: 'development';
            self::$logRequests = $appEnv !== 'production';
        }

        self::$initialized = true;
    }

    /**
     * Логирует запрос и возвращает метку времени начала (microtime) для расчёта duration.
     */
    public static function logRequest(): float
    {
        self::init();

        self::$requestHeaders = self::captureHeaders();
        self::$requestBody = self::captureRawBody();

        $startTime = microtime(true);

        if (!self::$logRequests) {
            return $startTime;
        }

        $payload = [
            'timestamp' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'query' => $_SERVER['QUERY_STRING'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'headers' => self::sanitizeHeaders(self::$requestHeaders),
            'body' => self::sanitizeBody(self::$requestBody)
        ];

        self::writeLog('api-requests.log', $payload);

        return $startTime;
    }

    /**
     * Логирует ответ API. Ошибки пишутся всегда, успешные ответы — если разрешено логирование запросов.
     */
    public static function logResponse(int $statusCode, $responseData, ?float $startTime = null): void
    {
        self::init();

        $shouldLog = self::$logRequests || $statusCode >= 400;
        if (!$shouldLog) {
            return;
        }

        $durationMs = null;
        if ($startTime !== null) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        }

        $payload = [
            'timestamp' => date('c'),
            'status' => $statusCode,
            'duration_ms' => $durationMs,
            'body' => self::sanitizeResponse($responseData)
        ];

        self::writeLog('api-responses.log', $payload);
    }

    // Accept Throwable so both Exception and Error (PHP 7+ Throwable) can be logged
    public static function logError(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        self::init();

        $payload = [
            'timestamp' => date('c'),
            'message' => $message,
            'context' => self::sanitizeArray($context)
        ];

        if ($exception) {
            $payload['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => getenv('APP_ENV') === 'production' ? 'hidden' : $exception->getTraceAsString()
            ];
        }

        self::writeLog('errors.log', $payload);
        error_log('CMS Error: ' . $message);
    }

    /** Возвращает исходное тело запроса (raw). */
    public static function getRawRequestBody(): string
    {
        self::init();

        if (self::$requestBody === null) {
            self::$requestBody = self::captureRawBody();
        }

        return self::$requestBody;
    }

    /** Возвращает тело запроса как массив (JSON). */
    public static function getJsonRequestBody(): array
    {
        $raw = self::getRawRequestBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    /** Возвращает заголовки запроса. */
    public static function getRequestHeaders(): array
    {
        self::init();

        if (empty(self::$requestHeaders)) {
            self::$requestHeaders = self::captureHeaders();
        }

        return self::$requestHeaders;
    }

    public static function clearOldLogs(int $days = 30): void
    {
        self::init();

        $logFiles = ['api-requests.log', 'api-responses.log', 'errors.log'];
        $cutoffTime = time() - ($days * 86400);

        foreach ($logFiles as $file) {
            $filePath = self::$logDir . '/' . $file;

            if (file_exists($filePath) && filemtime($filePath) < $cutoffTime) {
                $archiveDir = self::$logDir . '/archive';
                if (!is_dir($archiveDir)) {
                    @mkdir($archiveDir, 0755, true);
                }

                $archivePath = $archiveDir . '/' . date('Y-m-d') . '-' . $file;
                if (@rename($filePath, $archivePath) === false) {
                    self::logError('Не удалось архивировать лог', null, ['file' => $filePath]);
                }
            }
        }
    }

    public static function getLog(string $logType = 'errors', int $lines = 100): string
    {
        self::init();

        $map = [
            'requests' => 'api-requests.log',
            'responses' => 'api-responses.log',
            'errors' => 'errors.log'
        ];

        $file = $map[$logType] ?? 'errors.log';
        $path = self::$logDir . '/' . $file;

        if (!file_exists($path)) {
            return 'Log file not found: ' . $file;
        }

        $spl = new \SplFileObject($path, 'r');
        $spl->seek(PHP_INT_MAX);
        $lastLine = $spl->key();
        $startLine = max(0, $lastLine - $lines);
        $spl->seek($startLine);

        $buffer = [];
        while (!$spl->eof()) {
            $buffer[] = $spl->current();
            $spl->next();
        }

        return implode('', $buffer);
    }

    private static function writeLog(string $fileName, array $payload): void
    {
        $path = self::$logDir . '/' . $fileName;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (strlen($json) > self::MAX_PAYLOAD_LENGTH) {
            $json = substr($json, 0, self::MAX_PAYLOAD_LENGTH) . '…';
        }

        try {
            file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
            error_log('ApiLogger write failed: ' . $exception->getMessage());
        }
    }

    private static function captureHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    private static function captureRawBody(): string
    {
        $body = file_get_contents('php://input');
        return $body === false ? '' : $body;
    }

    private static function sanitizeHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            $normalized = strtolower($key);

            if ($normalized === 'authorization') {
                $headers[$key] = self::maskToken((string) $value);
            }

            if ($normalized === 'cookie' || $normalized === 'set-cookie') {
                $headers[$key] = '[HIDDEN]';
            }
        }

        return $headers;
    }

    private static function sanitizeBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return json_encode(self::sanitizeArray($decoded), JSON_UNESCAPED_UNICODE);
        }

        return mb_substr($body, 0, self::MAX_PAYLOAD_LENGTH);
    }

    private static function sanitizeResponse($response)
    {
        if (is_array($response)) {
            return self::sanitizeArray($response);
        }

        if (is_object($response)) {
            return self::sanitizeArray(json_decode(json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR), true) ?? []);
        }

        if (is_string($response)) {
            return mb_substr($response, 0, self::MAX_PAYLOAD_LENGTH);
        }

        return $response;
    }

    private static function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[HIDDEN]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::sanitizeArray($value);
            } elseif (is_string($value) && strlen($value) > self::MAX_PAYLOAD_LENGTH) {
                $data[$key] = substr($value, 0, self::MAX_PAYLOAD_LENGTH) . '…';
            }
        }

        return $data;
    }

    private static function maskToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '[HIDDEN]';
        }

        $visible = 4;
        if (strlen($token) <= $visible * 2) {
            return '[HIDDEN]';
        }

        return substr($token, 0, $visible) . '...' . substr($token, -$visible);
    }
}
