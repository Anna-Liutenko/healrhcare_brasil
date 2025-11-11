<?php

/**
 * Email Configuration
 *
 * Настройки для отправки email уведомлений
 */

return [
    // From адрес (отправитель)
    'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'noreply@healthcare-cms.local',
    
    // From имя (отображаемое имя отправителя)
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Healthcare CMS',

    // SMTP конфигурация (для будущего использования)
    'smtp' => [
        'host' => getenv('MAIL_HOST') ?: 'localhost',
        'port' => getenv('MAIL_PORT') ?: 25,
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: null, // 'tls' или 'ssl'
    ],

    // Использовать PHP mail() вместо SMTP
    'use_php_mail' => getenv('MAIL_USE_PHP') !== 'false', // По умолчанию true

    // Email адреса для различных целей
    'admin_email' => getenv('ADMIN_EMAIL') ?: 'admin@healthcare-cms.local',
    'support_email' => getenv('SUPPORT_EMAIL') ?: 'support@healthcare-cms.local',

    // Шаблоны (пути к html файлам)
    'templates_dir' => dirname(__DIR__) . '/templates/emails',

    // Режим развития (логировать письма вместо отправки)
    'dev_mode' => getenv('APP_ENV') === 'development',
    'dev_recipient' => getenv('MAIL_DEV_RECIPIENT') ?: 'dev@localhost',

    // Количество попыток повторной отправки
    'retry_attempts' => 3,
    'retry_delay_minutes' => 5,
];
