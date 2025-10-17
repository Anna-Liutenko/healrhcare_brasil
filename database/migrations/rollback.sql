-- =====================================================
-- ROLLBACK ALL MIGRATIONS
-- Description: Откат всех миграций (УДАЛЯЕТ ВСЕ ДАННЫЕ!)
-- Usage: mysql -u root -p healthcare_cms < rollback.sql
-- WARNING: Это удалит все таблицы и данные!
-- =====================================================

USE healthcare_cms;

-- Отключаем foreign key constraints для удаления
SET FOREIGN_KEY_CHECKS = 0;

-- Удаляем таблицы в обратном порядке
DROP TABLE IF EXISTS page_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS blocks;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Включаем обратно
SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ All tables dropped successfully!' as Status;
