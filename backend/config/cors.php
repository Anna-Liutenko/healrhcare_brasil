<?php

/**
 * CORS Configuration
 *
 * Настройки Cross-Origin Resource Sharing (CORS)
 * Определяет какие домены имеют доступ к API
 */

$env = getenv('APP_ENV') ?: 'development';

$config = [
    'enabled' => true,
    'allow_credentials' => true,
    'max_age' => 86400, // 24 часа
];

if ($env === 'development' || $env === 'local') {
    // Development: разрешаем локальные хосты
    $config['allowed_origins'] = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:8000',
        'http://localhost:8080',
        'http://127.0.0.1',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
    ];
} elseif ($env === 'staging') {
    // Staging: разрешаем staging домен
    $config['allowed_origins'] = [
        'https://staging.healthcare-cms.com',
        'https://staging.example.com',
    ];
} else {
    // Production: только production домен
    $config['allowed_origins'] = [
        'https://healthcare-cms.com',
        'https://www.healthcare-cms.com',
    ];
}

// Разрешённые методы
$config['allowed_methods'] = [
    'GET',
    'POST',
    'PUT',
    'DELETE',
    'PATCH',
    'OPTIONS',
];

// Разрешённые заголовки
$config['allowed_headers'] = [
    'Content-Type',
    'Authorization',
    'X-CSRF-Token',
    'X-Requested-With',
    'Accept',
    'Origin',
];

// Expose заголовки (доступны для JavaScript)
$config['expose_headers'] = [
    'Content-Length',
    'X-Total-Count',
    'X-Page-Count',
];

return $config;
