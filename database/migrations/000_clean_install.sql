-- =====================================================
-- CLEAN INSTALL (Fresh Start)
-- Description: Удаляет старые таблицы и создаёт новые
-- WARNING: Удалит все данные из БД!
-- =====================================================

USE healthcare_cms;

-- Отключаем foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Удаляем ВСЕ старые таблицы
DROP TABLE IF EXISTS page_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS uploads; -- старая таблица
DROP TABLE IF EXISTS blocks;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Включаем обратно
SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ Old tables dropped. Ready for migrations!' as Status;
