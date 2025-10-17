-- =====================================================
-- Migration: 009_create_tags_table.sql
-- Description: Таблица тегов (для фильтрации и группировки)
-- =====================================================

CREATE TABLE IF NOT EXISTS tags (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID тега',
    name VARCHAR(100) NOT NULL COMMENT 'Название тега (SUS, Частные клиники)',
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'URL-slug (sus, private-clinics)',
    description TEXT NULL COMMENT 'Описание тега',

    -- Цвет для UI
    color VARCHAR(7) DEFAULT '#008d8d' COMMENT 'Цвет тега в HEX (#008d8d)',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',

    -- Индексы
    INDEX idx_slug (slug),
    INDEX idx_name (name)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Теги для контента';

-- Связующая таблица страниц и тегов
CREATE TABLE IF NOT EXISTS page_tags (
    page_id VARCHAR(36) NOT NULL COMMENT 'ID страницы',
    tag_id VARCHAR(36) NOT NULL COMMENT 'ID тега',

    PRIMARY KEY (page_id, tag_id),

    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,

    INDEX idx_page_id (page_id),
    INDEX idx_tag_id (tag_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Связь страниц и тегов';

-- Вставляем дефолтные теги
INSERT INTO tags (id, name, slug, description, color)
VALUES
    (UUID(), 'SUS', 'sus', 'Государственная система здравоохранения', '#008d8d'),
    (UUID(), 'Частные клиники', 'private-clinics', 'Частная медицина и страховки', '#0066cc'),
    (UUID(), 'Гайды', 'guides', 'Пошаговые инструкции', '#ff6b35'),
    (UUID(), 'Лекарства', 'medications', 'Аптеки и лекарства', '#2ecc71')
ON DUPLICATE KEY UPDATE id=id;
