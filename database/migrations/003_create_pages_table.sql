-- =====================================================
-- Migration: 003_create_pages_table.sql
-- Description: Таблица страниц (главная сущность CMS)
-- =====================================================

CREATE TABLE IF NOT EXISTS pages (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID страницы',
    title VARCHAR(255) NOT NULL COMMENT 'Заголовок страницы',
    slug VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL-адрес (например: about-us)',

    -- Статусы и типы
    status ENUM('draft', 'published', 'hidden', 'unlisted', 'trashed') NOT NULL DEFAULT 'draft' COMMENT 'Статус страницы',
    type ENUM('regular', 'article', 'guide', 'collection') NOT NULL DEFAULT 'regular' COMMENT 'Тип страницы',

    -- Конфигурация коллекции (JSON, NULL если не коллекция)
    collection_config JSON NULL COMMENT 'Настройки автосборника (для type=collection)',

    -- SEO
    seo_title VARCHAR(255) NULL COMMENT 'SEO заголовок (meta title)',
    seo_description TEXT NULL COMMENT 'SEO описание (meta description)',
    seo_keywords VARCHAR(255) NULL COMMENT 'SEO ключевые слова',

    -- Tracking codes
    page_specific_code TEXT NULL COMMENT 'Скрипты аналитики для конкретной страницы',

    -- Видимость в навигации
    show_in_menu BOOLEAN DEFAULT 0 COMMENT 'Показывать в меню',
    show_in_sitemap BOOLEAN DEFAULT 1 COMMENT 'Показывать в sitemap.xml',
    menu_order INT DEFAULT 0 COMMENT 'Порядок в меню (0 = первый)',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',
    published_at TIMESTAMP NULL COMMENT 'Дата публикации',
    trashed_at TIMESTAMP NULL COMMENT 'Дата перемещения в корзину',

    -- Автор
    created_by VARCHAR(36) NOT NULL COMMENT 'ID создателя страницы',

    -- Связи
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,

    -- Индексы для производительности
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_slug (slug),
    INDEX idx_created_at (created_at),
    INDEX idx_published_at (published_at),
    INDEX idx_show_in_menu (show_in_menu, status, menu_order),
    INDEX idx_type_status (type, status, published_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Страницы сайта';
