-- =====================================================
-- Migration: 002_create_sessions_table.sql
-- Description: Таблица сессий (session-based авторизация)
-- =====================================================

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(64) PRIMARY KEY COMMENT 'ID сессии (генерируется случайно)',
    user_id VARCHAR(36) NOT NULL COMMENT 'ID пользователя',
    ip_address VARCHAR(45) NULL COMMENT 'IP адрес',
    user_agent VARCHAR(255) NULL COMMENT 'User-Agent браузера',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания сессии',
    expires_at TIMESTAMP NULL COMMENT 'Время истечения сессии',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Последняя активность',

    -- Связи
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Индексы
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_activity (last_activity)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Сессии пользователей';
