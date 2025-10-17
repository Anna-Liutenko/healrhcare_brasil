-- Migration: Add rendered_html and menu_title to pages table
-- Date: 2025-10-13
-- Description: Support for pre-rendered HTML caching and custom menu labels

-- Add columns (MySQL 8+ supports IF NOT EXISTS for ADD COLUMN via workaround; using conditional checks)
SET @table := 'pages';

-- Add rendered_html if missing
ALTER TABLE pages
  ADD COLUMN IF NOT EXISTS rendered_html LONGTEXT NULL
    COMMENT 'Pre-rendered static HTML (cached at publish time)'
    AFTER page_specific_code;

-- Add menu_title if missing
ALTER TABLE pages
  ADD COLUMN IF NOT EXISTS menu_title VARCHAR(255) NULL
    COMMENT 'Custom menu item label (overrides title)'
    AFTER show_in_menu;

-- Add unique index on slug if not exists (idempotent)
DO
$$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND INDEX_NAME = 'ux_pages_slug'
    ) THEN
        ALTER TABLE pages ADD UNIQUE INDEX ux_pages_slug (slug);
    END IF;
END$$;

-- Verification query
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pages'
  AND COLUMN_NAME IN ('rendered_html', 'menu_title');
