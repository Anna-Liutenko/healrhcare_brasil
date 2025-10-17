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
    'Р“Р»Р°РІРЅР°СЏ',
    'home',
    'published',
    'regular',
    'Healthcare Hacks Brazil - РњРµРґРёС†РёРЅР° РІ Р‘СЂР°Р·РёР»РёРё',
    'РџРѕРјРѕРіР°СЋ СЂСѓСЃСЃРєРѕСЏР·С‹С‡РЅС‹Рј СЌРєСЃРїР°С‚Р°Рј СЂР°Р·РѕР±СЂР°С‚СЊСЃСЏ РІ СЃРёСЃС‚РµРјРµ SUS, С‡Р°СЃС‚РЅС‹С… СЃС‚СЂР°С…РѕРІРєР°С…, РЅР°Р№С‚Рё РїСЂРѕРІРµСЂРµРЅРЅС‹С… РІСЂР°С‡РµР№ Рё РїРѕР»СѓС‡РёС‚СЊ РЅРµРѕР±С…РѕРґРёРјРѕРµ Р»РµС‡РµРЅРёРµ.',
    'РјРµРґРёС†РёРЅР° Р±СЂР°Р·РёР»РёСЏ, SUS, СЃС‚СЂР°С…РѕРІРєР°, РІСЂР°С‡Рё, Р»РµС‡РµРЅРёРµ',
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
    'Р“Р°Р№РґС‹',
    'guides',
    'published',
    'regular',
    'РџРѕР»РµР·РЅС‹Рµ РіР°Р№РґС‹ - Healthcare Hacks Brazil',
    'РџРѕС€Р°РіРѕРІС‹Рµ РёРЅСЃС‚СЂСѓРєС†РёРё Рё РїСЂРѕРІРµСЂРµРЅРЅС‹Рµ Р°Р»РіРѕСЂРёС‚РјС‹ РґР»СЏ СЂРµС€РµРЅРёСЏ РІР°С€РёС… РјРµРґРёС†РёРЅСЃРєРёС… Р·Р°РґР°С‡ РІ Р‘СЂР°Р·РёР»РёРё.',
    'РіР°Р№РґС‹, РёРЅСЃС‚СЂСѓРєС†РёРё, РјРµРґРёС†РёРЅР° Р±СЂР°Р·РёР»РёСЏ, SUS, СЃС‚СЂР°С…РѕРІРєР°',
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
    'Р‘Р»РѕРі',
    'blog',
    'published',
    'regular',
    'Р‘Р»РѕРі - Healthcare Hacks Brazil',
    'Р›РёС‡РЅС‹Рµ РёСЃС‚РѕСЂРёРё Рё РѕРїС‹С‚ Рѕ РјРµРґРёС†РёРЅРµ РІ Р‘СЂР°Р·РёР»РёРё.',
    'Р±Р»РѕРі, РёСЃС‚РѕСЂРёРё, РѕРїС‹С‚, РјРµРґРёС†РёРЅР° Р±СЂР°Р·РёР»РёСЏ',
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
    'Р’СЃРµ РјР°С‚РµСЂРёР°Р»С‹',
    'all-materials',
    'published',
    'regular',
    'Р’СЃРµ РјР°С‚РµСЂРёР°Р»С‹ - Healthcare Hacks Brazil',
    'РџРѕР»РЅР°СЏ РєРѕР»Р»РµРєС†РёСЏ РіР°Р№РґРѕРІ Рё СЃС‚Р°С‚РµР№ Рѕ РјРµРґРёС†РёРЅСЃРєРѕР№ СЃРёСЃС‚РµРјРµ Р‘СЂР°Р·РёР»РёРё.',
    'РІСЃРµ РјР°С‚РµСЂРёР°Р»С‹, РіР°Р№РґС‹, СЃС‚Р°С‚СЊРё, РјРµРґРёС†РёРЅР° Р±СЂР°Р·РёР»РёСЏ',
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
    'Р‘РѕС‚-РїРѕРјРѕС‰РЅРёРє',
    'bot',
    'published',
    'regular',
    'Р‘РѕС‚-РїРѕРјРѕС‰РЅРёРє - Healthcare Hacks Brazil',
    'РћС‚РІРµС‚СЊС‚Рµ РЅР° РІРѕРїСЂРѕСЃС‹, С‡С‚РѕР±С‹ РїСЂРµРґРІР°СЂРёС‚РµР»СЊРЅРѕ РѕРїСЂРµРґРµР»РёС‚СЊ РїСЂР°РІРѕ РЅР° Р»СЊРіРѕС‚РЅС‹Рµ Р»РµРєР°СЂСЃС‚РІР°.',
    'Р±РѕС‚, РїРѕРјРѕС‰РЅРёРє, Р»РµРєР°СЂСЃС‚РІР°, Р»СЊРіРѕС‚С‹, Alto Custo',
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
    'РџРѕР»РЅС‹Р№ РіР°Р№Рґ РїРѕ SUS РґР»СЏ СЌРєСЃРїР°С‚Р°',
    'polnyj-gajd-po-sus-dlya-eksdata',
    'published',
    'article',
    'РџРѕР»РЅС‹Р№ РіР°Р№Рґ РїРѕ SUS РґР»СЏ СЌРєСЃРїР°С‚Р° - Healthcare Hacks Brazil',
    'РџРѕС€Р°РіРѕРІР°СЏ РёРЅСЃС‚СЂСѓРєС†РёСЏ, РєР°Рє Р·Р°СЂРµРіРёСЃС‚СЂРёСЂРѕРІР°С‚СЊСЃСЏ РІ РіРѕСЃСѓРґР°СЂСЃС‚РІРµРЅРЅРѕР№ СЃРёСЃС‚РµРјРµ Р·РґСЂР°РІРѕРѕС…СЂР°РЅРµРЅРёСЏ Р‘СЂР°Р·РёР»РёРё.',
    'SUS, СЌРєСЃРїР°С‚, СЂРµРіРёСЃС‚СЂР°С†РёСЏ, Р·РґСЂР°РІРѕРѕС…СЂР°РЅРµРЅРёРµ, Р±СЂР°Р·РёР»РёСЏ',
    0,
    1,
    0,
    '2025-01-20 15:00:00',
    '2025-01-22 13:50:00',
    '2025-01-22 13:50:00',
    @admin_id
);