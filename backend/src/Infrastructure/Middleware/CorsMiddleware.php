<?php

declare(strict_types=1);

namespace Infrastructure\Middleware;

use Exception;

/**
 * CORS Middleware
 *
 * Управление Cross-Origin Resource Sharing (CORS)
 * - Контроль доступа к API с других доменов
 * - Предварительные запросы (preflight)
 * - Настройка разрешённых методов и заголовков
 */
class CorsMiddleware
{
    /**
     * Обработать CORS запрос
     *
     * @throws Exception если CORS запрос не разрешён
     */
    public static function handle(): void
    {
        $allowedOrigins = self::getAllowedOrigins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Проверяем разрешён ли Origin
        if (!self::isOriginAllowed($origin, $allowedOrigins)) {
            // Не добавляем CORS заголовки для неразрешённых источников
            return;
        }

        // Добавляем CORS заголовки
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 часа

        // Обработка preflight запроса (OPTIONS)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Получить список разрешённых Origins
     */
    private static function getAllowedOrigins(): array
    {
        $env = getenv('APP_ENV') ?: 'development';
        
        // Для development разрешаем локальные адреса
        if ($env === 'development' || $env === 'local') {
            return [
                'http://localhost',
                'http://localhost:3000',
                'http://localhost:8000',
                'http://127.0.0.1',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:8000',
            ];
        }

        // Для production читаем из конфигурации
        $config = self::loadCorsConfig();
        return $config['allowed_origins'] ?? [];
    }

    /**
     * Проверить разрешён ли Origin
     */
    private static function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Точное совпадение
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Проверка wildcard patterns (напр. *.example.com)
        foreach ($allowedOrigins as $allowed) {
            if (self::matchesPattern($origin, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверить соответствие Origin паттерну
     */
    private static function matchesPattern(string $origin, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return $origin === $pattern;
        }

        // Конвертируем wildcard pattern в regex
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );
        $regex = '/^' . $regex . '$/i';

        return preg_match($regex, $origin) === 1;
    }

    /**
     * Загрузить конфигурацию CORS
     */
    private static function loadCorsConfig(): array
    {
        $configFile = dirname(__DIR__, 3) . '/config/cors.php';
        
        if (file_exists($configFile)) {
            return require $configFile;
        }

        return [];
    }

    /**
     * Проверить простой CORS запрос
     * (не требует preflight)
     */
    public static function isSimpleRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Простые методы
        if (!in_array($method, ['GET', 'POST', 'HEAD'])) {
            return false;
        }

        // Простые Content-Type
        $simpleContentTypes = [
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'text/plain',
        ];

        foreach ($simpleContentTypes as $type) {
            if (strpos($contentType, $type) === 0) {
                return true;
            }
        }

        return true; // GET/HEAD не требуют Content-Type
    }

    /**
     * Проверить preflight запрос (OPTIONS)
     */
    public static function isPreflightRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'OPTIONS' &&
               !empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }
}
