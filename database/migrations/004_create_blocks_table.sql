-- =====================================================
-- Migration: 004_create_blocks_table.sql
-- Description: Таблица блоков (контент страниц)
-- =====================================================

CREATE TABLE IF NOT EXISTS blocks (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID блока',
    page_id VARCHAR(36) NOT NULL COMMENT 'ID страницы',
    type VARCHAR(50) NOT NULL COMMENT 'Тип блока (main-screen, text-block, etc.)',
    position INT NOT NULL DEFAULT 0 COMMENT 'Позиция блока на странице (0, 1, 2...)',

    -- Кастомное название блока
    custom_name VARCHAR(255) NULL COMMENT 'Пользовательское название блока',

    -- Данные блока (JSON)
    data JSON NOT NULL COMMENT 'Данные блока (title, text, images, etc.)',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',

    -- Связи
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,

    -- Индексы
    INDEX idx_page_id (page_id),
    INDEX idx_type (type),
    INDEX idx_page_position (page_id, position)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Блоки контента страниц';
