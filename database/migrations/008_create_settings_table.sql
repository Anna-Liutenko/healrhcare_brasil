-- =====================================================
-- Migration: 008_create_settings_table.sql
-- Description: Таблица глобальных настроек
-- =====================================================

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID настройки',
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Ключ настройки (site_name, logo_url)',
    setting_value TEXT NULL COMMENT 'Значение настройки',
    setting_type ENUM('text', 'textarea', 'json', 'boolean', 'number') NOT NULL DEFAULT 'text' COMMENT 'Тип значения',

    -- Группировка настроек
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Группа (general, header, footer, seo, tracking)',

    -- Описание
    description VARCHAR(255) NULL COMMENT 'Описание настройки',

    -- Временные метки
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',

    -- Индексы
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Глобальные настройки сайта';

-- Вставляем дефолтные настройки
INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, description)
VALUES
    -- General
    ('site_name', 'Expats Health Brazil', 'text', 'general', 'Название сайта'),
    ('site_description', 'Navigating Brazilian Healthcare for Expats', 'textarea', 'general', 'Описание сайта'),
    ('site_domain', 'expats-health.com.br', 'text', 'general', 'Домен сайта'),

    -- Header
    ('header_logo_text', 'Expats Health Brazil', 'text', 'header', 'Текст логотипа в шапке'),
    ('header_logo_url', '', 'text', 'header', 'URL логотипа (если используется изображение)'),

    -- Footer
    ('footer_logo_text', 'Expats Health Brazil', 'text', 'footer', 'Текст логотипа в футере'),
    ('footer_copyright', '© 2025 Anna Liutenko. All rights reserved.', 'text', 'footer', 'Текст копирайта'),
    ('footer_privacy_link', '/privacy', 'text', 'footer', 'Ссылка на политику конфиденциальности'),
    ('footer_privacy_text', 'Privacy Policy', 'text', 'footer', 'Текст ссылки на политику'),

    -- Cookie Banner
    ('cookie_banner_enabled', '1', 'boolean', 'general', 'Включить Cookie Banner'),
    ('cookie_banner_message', 'Мы используем cookie для улучшения работы сайта. Продолжая использовать сайт, вы соглашаетесь с нашей Политикой конфиденциальности.', 'textarea', 'general', 'Сообщение в Cookie Banner'),
    ('cookie_banner_accept_text', 'Принять', 'text', 'general', 'Текст кнопки "Принять"'),
    ('cookie_banner_details_text', 'Подробнее', 'text', 'general', 'Текст кнопки "Подробнее"'),

    -- Tracking
    ('global_tracking_code', '', 'textarea', 'tracking', 'Глобальные скрипты аналитики (Google Analytics, Facebook Pixel)'),
    ('global_widgets_code', '', 'textarea', 'tracking', 'Виджеты (чат, соц. кнопки)')

ON DUPLICATE KEY UPDATE setting_key=setting_key;
