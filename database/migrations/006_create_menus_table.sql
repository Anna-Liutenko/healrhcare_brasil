-- =====================================================
-- Migration: 006_create_menus_table.sql
-- Description: Таблица меню (навигация)
-- =====================================================

CREATE TABLE IF NOT EXISTS menus (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID меню',
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Системное имя (main-menu, footer-menu)',
    display_name VARCHAR(255) NOT NULL COMMENT 'Отображаемое название (Главное меню)',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',

    -- Индексы
    INDEX idx_name (name)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Меню навигации';

-- Создаём дефолтные меню
INSERT INTO menus (id, name, display_name)
VALUES
    (UUID(), 'main-menu', 'Главное меню'),
    (UUID(), 'footer-menu', 'Меню в футере')
ON DUPLICATE KEY UPDATE id=id;
