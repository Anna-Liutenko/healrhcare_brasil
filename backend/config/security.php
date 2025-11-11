<?php

/**
 * Security Configuration
 *
 * Настройки функций безопасности:
 * - Rate limiting
 * - Password policy
 * - Account lockout
 * - CSRF protection
 */

return [
    // Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        'window_minutes' => 15,
        
        // Специфичные лимиты для разных действий
        'actions' => [
            'login' => [
                'max_attempts' => 5,
                'lockout_minutes' => 15,
            ],
            'register' => [
                'max_attempts' => 3,
                'lockout_minutes' => 60,
            ],
            'password_reset' => [
                'max_attempts' => 3,
                'lockout_minutes' => 30,
            ],
        ],
    ],

    // Password Policy
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_digit' => true,
        'require_special' => true,
        'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?~`',
        
        // Предотвращение повторного использования
        'check_history' => true,
        'history_months' => 12,
        'history_count' => 5,
    ],

    // Account Lockout
    'account_lockout' => [
        'enabled' => true,
        'failed_attempts' => 5,
        'lockout_duration_minutes' => 15,
        'reset_on_login' => true,
    ],

    // Email Verification
    'email_verification' => [
        'required' => true,
        'token_expiry_hours' => 24,
        'allow_login_unverified' => false,
    ],

    // CSRF Protection
    'csrf' => [
        'enabled' => true,
        'token_length' => 32,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    // CORS
    'cors' => [
        'enabled' => true,
        'allow_credentials' => true,
        'max_age' => 86400, // 24 часа
    ],

    // Security Headers
    'headers' => [
        'x_content_type_options' => true,
        'x_frame_options' => 'DENY',
        'x_xss_protection' => true,
        'hsts' => getenv('APP_ENV') === 'production',
        'hsts_max_age' => 31536000, // 1 год
        'csp_enabled' => true,
    ],

    // Audit Logging
    'audit' => [
        'enabled' => true,
        'log_sensitive_actions' => true,
        'retention_days' => 90,
    ],

    // IP Whitelist / Blacklist (для будущего использования)
    'ip_control' => [
        'enabled' => false,
        'whitelist' => [], // Если не пусто - только эти IP разрешены
        'blacklist' => [], // IP которые заблокированы
    ],

    // Two-Factor Authentication (для будущего использования)
    'two_factor' => [
        'enabled' => false,
        'methods' => ['email', 'totp'],
    ],
];
