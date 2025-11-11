<?php

declare(strict_types=1);

namespace Infrastructure\Middleware;

/**
 * Security Headers Middleware
 *
 * Добавляет важные HTTP заголовки безопасности:
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: DENY
 * - X-XSS-Protection: 1; mode=block
 * - Strict-Transport-Security (HSTS)
 * - Content-Security-Policy (CSP)
 * - Referrer-Policy
 * - Permissions-Policy
 */
class SecurityHeadersMiddleware
{
    /**
     * Добавить все заголовки безопасности
     */
    public static function addHeaders(): void
    {
        // Предотвращение MIME sniffing атак
        header('X-Content-Type-Options: nosniff');

        // Предотвращение clickjacking - запретить встраивание в iframe
        header('X-Frame-Options: DENY');

        // Старая защита от XSS (для старых браузеров)
        header('X-XSS-Protection: 1; mode=block');

        // HSTS - заставить использовать HTTPS
        if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy - строгая политика
        self::addContentSecurityPolicy();

        // Контроль referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy - контроль доступа к API браузера
        self::addPermissionsPolicy();

        // Отключить кэширование для чувствительных данных
        if (self::isSensitivePage()) {
            self::addNoCacheHeaders();
        }
    }

    /**
     * Добавить Content-Security-Policy заголовок
     */
    private static function addContentSecurityPolicy(): void
    {
        $isProduction = getenv('APP_ENV') === 'production';
        
        // CSP политика
        $directives = [
            // Скрипты - только с того же домена и inline (для нашего приложения)
            "script-src 'self' 'unsafe-inline'",
            
            // Стили - только с того же домена
            "style-src 'self' 'unsafe-inline'",
            
            // Изображения
            "img-src 'self' data: https:",
            
            // Шрифты
            "font-src 'self'",
            
            // Подключения (AJAX, WebSocket)
            "connect-src 'self'",
            
            // Формы
            "form-action 'self'",
            
            // Фреймы
            "frame-ancestors 'none'",
            
            // Встраивание объектов
            "object-src 'none'",
            
            // Base tag
            "base-uri 'self'",
        ];

        $csp = implode('; ', $directives);
        
        if ($isProduction) {
            // В production режиме используем report-only для мониторинга
            // header("Content-Security-Policy-Report-Only: {$csp}; report-uri /api/csp-report");
        }
        
        header("Content-Security-Policy: {$csp}");
    }

    /**
     * Добавить Permissions-Policy заголовок
     */
    private static function addPermissionsPolicy(): void
    {
        $directives = [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
            'vr=()',
            'xr-spatial-tracking=()',
        ];

        header('Permissions-Policy: ' . implode(', ', $directives));
    }

    /**
     * Добавить заголовки для отключения кэширования
     */
    private static function addNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }

    /**
     * Проверить, чувствительна ли текущая страница (admin panel)
     */
    private static function isSensitivePage(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Отключаем кэширование для admin панели
        return strpos($uri, '/api/') === 0 || 
               strpos($uri, '/admin') === 0;
    }
}
