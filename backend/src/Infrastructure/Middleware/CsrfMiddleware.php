<?php

declare(strict_types=1);

namespace Infrastructure\Middleware;

use Exception;

/**
 * CSRF Middleware
 *
 * Защита от Cross-Site Request Forgery (CSRF) атак
 * - Генерирует CSRF токены для форм
 * - Проверяет токены в POST/PUT/DELETE запросах
 * - Использует двойную проверку: сессия + cookie + заголовок/параметр
 */
class CsrfMiddleware
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_HEADER = 'X-CSRF-Token';
    private const TOKEN_FIELD = '_csrf_token';

    /**
     * Инициализировать CSRF защиту (вызвать в начале приложения)
     */
    public static function initialize(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Генерируем токен если его нет
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }

        // Устанавливаем cookie токена (для дополнительной защиты)
        if (empty($_COOKIE['csrf_token'])) {
            $token = $_SESSION['csrf_token'];
            setcookie('csrf_token', $token, [
                'expires' => time() + 86400 * 7, // 7 дней
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $_SERVER['REQUEST_SCHEME'] === 'https',
            ]);
        }
    }

    /**
     * Получить текущий CSRF токен
     */
    public static function getToken(): string
    {
        self::ensureSession();
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Проверить CSRF токен для опасных операций (POST, PUT, DELETE)
     *
     * @throws Exception если токен невалидный или отсутствует
     */
    public static function verify(string $method = ''): void
    {
        $method = $method ?: $_SERVER['REQUEST_METHOD'];

        // Безопасные методы (GET, HEAD, OPTIONS) не требуют проверку
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return;
        }

        self::ensureSession();

        $token = self::extractToken();

        if (empty($token)) {
            throw new Exception('CSRF token is missing');
        }

        if (!self::validateToken($token)) {
            throw new Exception('Invalid CSRF token');
        }
    }

    /**
     * Сгенерировать новый CSRF токен
     */
    public static function regenerate(): string
    {
        self::ensureSession();
        $token = self::generateToken();
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Извлечь токен из запроса (заголовок, параметр или поле формы)
     */
    private static function extractToken(): ?string
    {
        // 1. Проверяем заголовок X-CSRF-Token (для AJAX запросов)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // 2. Проверяем параметр GET/POST _csrf_token
        if (!empty($_REQUEST[self::TOKEN_FIELD])) {
            return $_REQUEST[self::TOKEN_FIELD];
        }

        // 3. Для JSON POST запросов проверяем JSON body
        if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true);
            if (!empty($json[self::TOKEN_FIELD])) {
                return $json[self::TOKEN_FIELD];
            }
        }

        return null;
    }

    /**
     * Валидировать CSRF токен
     */
    private static function validateToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Используем timing-safe сравнение
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Сгенерировать новый случайный токен
     */
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Убедиться что сессия запущена
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Получить HTML для встраивания CSRF токена в форму
     */
    public static function getFormField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_FIELD . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Получить JavaScript код для добавления токена в AJAX запросы
     */
    public static function getJavaScript(): string
    {
        $token = self::getToken();
        return <<<JS
<script>
// Добавляем CSRF токен в заголовок для всех AJAX запросов
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '$token';
    // Сохраняем в window для использования в api-client.js
    window.csrfToken = csrfToken;
});
</script>
JS;
    }
}
