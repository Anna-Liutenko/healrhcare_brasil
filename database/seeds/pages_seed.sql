-- Seed data for basic pages
-- Run this after creating the database schema

USE healthcare_cms;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Get super_admin user ID (anna)
SET @admin_id = (SELECT id FROM users WHERE username = 'anna' LIMIT 1);

-- Clear existing pages and blocks
DELETE FROM blocks;
DELETE FROM pages;

-- Home Page
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'Главная',
    'home',
    'published',
    'regular',
    'Healthcare Hacks Brazil - Медицина в Бразилии',
    'Помогаю русскоязычным экспатам разобраться в системе SUS, частных страховках, найти проверенных врачей и получить необходимое лечение.',
    'медицина бразилия, SUS, страховка, врачи, лечение',
    1,
    1,
    1,
    '2025-01-15 10:00:00',
    '2025-01-20 14:30:00',
    '2025-01-20 14:30:00',
    @admin_id
);

-- Guides Page
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'Гайды',
    'guides',
    'published',
    'regular',
    'Полезные гайды - Healthcare Hacks Brazil',
    'Пошаговые инструкции и проверенные алгоритмы для решения ваших медицинских задач в Бразилии.',
    'гайды, инструкции, медицина бразилия, SUS, страховка',
    1,
    1,
    2,
    '2025-01-16 11:00:00',
    '2025-01-18 09:15:00',
    '2025-01-18 09:15:00',
    @admin_id
);

-- Blog Page
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    'b2c3d4e5-f6g7-8901-bcde-f23456789012',
    'Блог',
    'blog',
    'published',
    'regular',
    'Блог - Healthcare Hacks Brazil',
    'Личные истории и опыт о медицине в Бразилии.',
    'блог, истории, опыт, медицина бразилия',
    1,
    1,
    3,
    '2025-01-17 12:00:00',
    '2025-01-19 10:20:00',
    '2025-01-19 10:20:00',
    @admin_id
);

-- All Materials Page
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    'c3d4e5f6-g7h8-9012-cdef-345678901234',
    'Все материалы',
    'all-materials',
    'published',
    'regular',
    'Все материалы - Healthcare Hacks Brazil',
    'Полная коллекция гайдов и статей о медицинской системе Бразилии.',
    'все материалы, гайды, статьи, медицина бразилия',
    1,
    1,
    4,
    '2025-01-18 13:00:00',
    '2025-01-20 11:30:00',
    '2025-01-20 11:30:00',
    @admin_id
);

-- Bot Page
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    'd4e5f6g7-h8i9-0123-def0-456789012345',
    'Бот-помощник',
    'bot',
    'published',
    'regular',
    'Бот-помощник - Healthcare Hacks Brazil',
    'Ответьте на вопросы, чтобы предварительно определить право на льготные лекарства.',
    'бот, помощник, лекарства, льготы, Alto Custo',
    1,
    1,
    5,
    '2025-01-19 14:00:00',
    '2025-01-21 12:45:00',
    '2025-01-21 12:45:00',
    @admin_id
);

-- Sample Article
INSERT INTO pages (id, title, slug, status, type, seo_title, seo_description, seo_keywords, show_in_menu, show_in_sitemap, menu_order, created_at, updated_at, published_at, created_by)
VALUES (
    'e5f6g7h8-i9j0-1234-ef01-567890123456',
    'Полный гайд по SUS для экспата',
    'polnyj-gajd-po-sus-dlya-eksdata',
    'published',
    'article',
    'Полный гайд по SUS для экспата - Healthcare Hacks Brazil',
    'Пошаговая инструкция, как зарегистрироваться в государственной системе здравоохранения Бразилии.',
    'SUS, экспат, регистрация, здравоохранение, бразилия',
    0,
    1,
    0,
    '2025-01-20 15:00:00',
    '2025-01-22 13:50:00',
    '2025-01-22 13:50:00',
    @admin_id
);