-- ================================================
-- Миграция: Добавление поля menu_label для автоматического меню
-- Дата: 5 октября 2025
-- Автор: Claude + Anna
-- Примечание: Поля show_in_menu и menu_order уже существуют в таблице
-- ================================================

-- Проверяем, существует ли поле menu_label
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = 'healthcare_cms'
      AND table_name = 'pages' 
      AND column_name = 'menu_label'
);

-- Добавляем поле menu_label только если его нет
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE pages ADD COLUMN menu_label VARCHAR(255) NULL COMMENT ''Название в меню (если NULL → используется title страницы)''',
    'SELECT ''⚠️ Поле menu_label уже существует'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Обновляем комментарии для существующих полей
ALTER TABLE pages 
MODIFY COLUMN show_in_menu TINYINT(1) DEFAULT 0 COMMENT 'Показывать страницу в главном меню (работает только для published + public)';

ALTER TABLE pages 
MODIFY COLUMN menu_order INT DEFAULT 0 COMMENT 'Порядковый номер в меню (меньшее число = выше, 0 = автоматическая позиция)';

-- Вывод результата
SELECT 
    CONCAT('✅ Миграция завершена успешно') AS result;

SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.columns 
WHERE table_schema = 'healthcare_cms'
  AND table_name = 'pages' 
  AND column_name IN ('show_in_menu', 'menu_order', 'menu_label');
