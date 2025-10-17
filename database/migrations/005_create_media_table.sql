-- =====================================================
-- Migration: 005_create_media_table.sql
-- Description: Таблица медиафайлов (галерея)
-- =====================================================

CREATE TABLE IF NOT EXISTS media (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID файла',
    filename VARCHAR(255) NOT NULL COMMENT 'Имя файла на диске',
    original_filename VARCHAR(255) NOT NULL COMMENT 'Оригинальное имя файла',
    url VARCHAR(512) NOT NULL COMMENT 'Полный URL файла',

    -- Метаданные файла
    type ENUM('image', 'svg', 'video', 'document') NOT NULL DEFAULT 'image' COMMENT 'Тип файла',
    mime_type VARCHAR(100) NOT NULL COMMENT 'MIME-тип (image/png, image/jpeg, etc.)',
    size INT NOT NULL COMMENT 'Размер файла в байтах',
    width INT NULL COMMENT 'Ширина изображения (если применимо)',
    height INT NULL COMMENT 'Высота изображения (если применимо)',

    -- Alt текст для SEO
    alt_text VARCHAR(255) NULL COMMENT 'Alt текст для изображения',

    -- Автор загрузки
    uploaded_by VARCHAR(36) NOT NULL COMMENT 'ID пользователя, загрузившего файл',

    -- Временные метки
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата загрузки',

    -- Связи
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,

    -- Индексы
    INDEX idx_type (type),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_filename (filename)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Медиафайлы (галерея)';
