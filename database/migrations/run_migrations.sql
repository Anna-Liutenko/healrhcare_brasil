-- =====================================================
-- RUN ALL MIGRATIONS
-- Description: Запуск всех миграций по порядку
-- Usage: mysql -u root -p healthcare_cms < run_migrations.sql
-- =====================================================

-- Создаём БД если не существует
CREATE DATABASE IF NOT EXISTS healthcare_cms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE healthcare_cms;

-- Включаем foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Migration 001: Users
-- =====================================================
SOURCE 001_create_users_table.sql;

-- =====================================================
-- Migration 002: Sessions
-- =====================================================
SOURCE 002_create_sessions_table.sql;

-- =====================================================
-- Migration 003: Pages
-- =====================================================
SOURCE 003_create_pages_table.sql;

-- =====================================================
-- Migration 004: Blocks
-- =====================================================
SOURCE 004_create_blocks_table.sql;

-- =====================================================
-- Migration 005: Media
-- =====================================================
SOURCE 005_create_media_table.sql;

-- =====================================================
-- Migration 006: Menus
-- =====================================================
SOURCE 006_create_menus_table.sql;

-- =====================================================
-- Migration 007: Menu Items
-- =====================================================
SOURCE 007_create_menu_items_table.sql;

-- =====================================================
-- Migration 008: Settings
-- =====================================================
SOURCE 008_create_settings_table.sql;

-- =====================================================
-- Migration 009: Tags
-- =====================================================
SOURCE 009_create_tags_table.sql;

-- =====================================================
-- READY!
-- =====================================================
SELECT '✅ All migrations completed successfully!' as Status;
