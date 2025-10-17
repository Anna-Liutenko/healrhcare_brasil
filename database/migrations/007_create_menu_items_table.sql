-- =====================================================
-- Migration: 007_create_menu_items_table.sql
-- Description: Таблица пунктов меню
-- =====================================================

CREATE TABLE IF NOT EXISTS menu_items (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID пункта меню',
    menu_id VARCHAR(36) NOT NULL COMMENT 'ID меню',
    label VARCHAR(255) NOT NULL COMMENT 'Текст пункта меню',

    -- Ссылка (либо внутренняя страница, либо внешний URL)
    page_id VARCHAR(36) NULL COMMENT 'ID страницы (NULL если внешняя ссылка)',
    external_url VARCHAR(512) NULL COMMENT 'Внешний URL (NULL если внутренняя страница)',

    -- Позиция и вложенность
    position INT NOT NULL DEFAULT 0 COMMENT 'Порядок пункта в меню',
    parent_id VARCHAR(36) NULL COMMENT 'ID родительского пункта (для dropdown)',

    -- Настройки
    open_in_new_tab BOOLEAN DEFAULT 0 COMMENT 'Открывать в новой вкладке',
    css_class VARCHAR(100) NULL COMMENT 'CSS класс для кастомизации',
    icon VARCHAR(50) NULL COMMENT 'Иконка (название или SVG)',

    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',

    -- Связи
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE,

    -- Индексы
    INDEX idx_menu_id (menu_id),
    INDEX idx_page_id (page_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_menu_position (menu_id, position)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пункты меню';
