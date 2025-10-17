-- =====================================================
-- Migration: 001_create_users_table.sql
-- Description: Таблица пользователей (админы, редакторы)
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID пользователя',
    username VARCHAR(100) NOT NULL UNIQUE COMMENT 'Логин (уникальный)',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'Email',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Хэш пароля (bcrypt)',

    -- Роли
    role ENUM('super_admin', 'admin', 'editor') NOT NULL DEFAULT 'editor' COMMENT 'Роль пользователя',

    -- Статус
    is_active BOOLEAN NOT NULL DEFAULT 1 COMMENT 'Активен ли пользователь',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
    last_login_at TIMESTAMP NULL COMMENT 'Последний вход',

    -- Индексы
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пользователи CMS';

-- Создаём super_admin по умолчанию
-- Пароль: admin123 (ОБЯЗАТЕЛЬНО СМЕНИТЬ после первого входа!)
INSERT INTO users (id, username, email, password_hash, role, is_active)
VALUES (
    UUID(),
    'anna',
    'anna@liutenko.onmicrosoft.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt hash of 'admin123'
    'super_admin',
    1
) ON DUPLICATE KEY UPDATE id=id;
