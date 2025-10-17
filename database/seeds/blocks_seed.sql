-- Seed data for page blocks
-- Run this after pages_seed.sql

USE healthcare_cms;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Home page blocks
INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES (
    'f6g7h8i9-j0k1-2345-f012-678901234567',
    '75f53538-dd6c-489a-9b20-d0004bb5086b', -- home page
    'main-screen',
    1,
    'Hero Section',
    '{
        "title": "Медицина в Бразилии: <br> Ваш гид по сложной системе",
        "text": "Помогаю русскоязычным экспатам разобраться в системе SUS, частных страховках (planos de saúde), найти проверенных врачей и получить необходимое лечение.",
        "buttonText": "Записаться на консультацию",
        "buttonLink": "#"
    }',
    '2025-01-15 10:00:00',
    '2025-01-20 14:30:00'
);

INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES (
    'g7h8i9j0-k1l2-3456-0123-789012345678',
    '75f53538-dd6c-489a-9b20-d0004bb5086b', -- home page
    'service-cards',
    2,
    'Services Section',
    '{
        "title": "Чем я могу помочь",
        "subtitle": "Моя цель — сэкономить ваше время, деньги и нервы, опираясь на личный опыт прохождения всех этапов медицинской бюрократии в Бразилии.",
        "cards": [
            {
                "icon": "guide",
                "title": "Понятные гайды",
                "text": "Пошаговые инструкции, которые проведут вас через все этапы: от получения CPF до записи к узкому специалисту."
            },
            {
                "icon": "support",
                "title": "Личная поддержка",
                "text": "Персональные консультации, где мы разберем именно вашу ситуацию и составим индивидуальный план действий."
            }
        ]
    }',
    '2025-01-15 10:00:00',
    '2025-01-20 14:30:00'
);

INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES (
    'h8i9j0k1-l2m3-4567-1234-890123456789',
    '75f53538-dd6c-489a-9b20-d0004bb5086b', -- home page
    'about-section',
    3,
    'About Me Section',
    '{
        "image": "https://placehold.co/600x720/E9EAF2/032A49?text=Anna+L.",
        "title": "Привет, я Анна Лютенко!",
        "paragraphs": [
            "Я переехала в Бразилию несколько лет назад и, как человек с хроническим заболеванием, сразу с головой окунулась в местную медицинскую систему. Я прошла путь от полного непонимания до уверенной навигации по государственным программам и частным клиникам. Я создала этот проект, чтобы поделиться своим опытом и помочь вам избежать моих ошибок, сэкономив ваше время, деньги и, самое главное, нервы."
        ]
    }',
    '2025-01-15 10:00:00',
    '2025-01-20 14:30:00'
);

-- Guides page blocks
INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES
('h9i0j1k2-l3m4-5678-n901-234567890123', 'a1b2c3d4-e5f6-7890-abcd-ef1234567891', 'page-header', 1, NULL,
'{"title":"Полезные гайды","subtitle":"Пошаговые инструкции и проверенные алгоритмы для решения ваших медицинских задач в Бразилии."}',
'2025-01-16 11:00:00','2025-01-18 09:15:00'),
('i0j1k2l3-m4n5-6789-o012-345678901234', 'a1b2c3d4-e5f6-7890-abcd-ef1234567891', 'article-cards', 2, NULL,
'{"title":"","columns":3,"cards":[{"image":"https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=2070&auto=format&fit=crop","title":"Полный гайд по SUS для экспата","text":"Пошаговая инструкция, как зарегистрироваться в государственной системе, чего ожидать и как пользоваться ее возможностями...","link":"#"},{"image":"https://images.unsplash.com/photo-1551076805-e1869033e561?q=80&w=2070&auto=format&fit=crop","title":"Как выбрать частную страховку","text":"Разбираем типы страховок (Plano de Saúde), важные пункты в договоре и на что обратить внимание при выборе компании.","link":"#"},{"image":"https://images.unsplash.com/photo-1587854692152-cbe660dbde88?q=80&w=2070&auto=format&fit=crop","title":"Аналоги лекарств в Бразилии","text":"Полезные сервисы для поиска дженериков, а также особенности рецептов и покупки медикаментов в местных аптеках.","link":"#"}]}',
'2025-01-16 11:00:00','2025-01-18 09:15:00'),
('j1k2l3m4-n5o6-7890-p123-456789012345', 'a1b2c3d4-e5f6-7890-abcd-ef1234567891', 'button', 3, NULL,
'{"text":"Все гайды","link":"#","alignment":"center","style":"primary"}',
'2025-01-16 11:00:00','2025-01-18 09:15:00');

-- Blog page blocks
INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES
('k2l3m4n5-o6p7-8901-q234-567890123456', 'b2c3d4e5-f6g7-8901-bcde-f23456789012', 'page-header', 1, NULL,
'{"title":"Блог: личные истории и опыт","subtitle":"Наблюдения и истории из жизни в Бразилии, которые не вошли в гайды."}',
'2025-01-17 12:00:00','2025-01-19 10:20:00'),
('l3m4n5o6-p7q8-9012-r345-678901234567', 'b2c3d4e5-f6g7-8901-bcde-f23456789012', 'article-cards', 2, NULL,
'{"title":"","columns":2,"cards":[{"image":"https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=2070&auto=format&fit=crop","title":"Первый год в Бразилии: мои открытия","text":"Рассказываю о том, с какими неожиданностями я столкнулась в больницах и аптеках в первые месяцы после переезда...","link":"#"},{"image":"https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=1780&auto=format&fit=crop","title":"Как говорить с бразильским врачом","text":"Набор фраз, культурные особенности и маленькие хитрости, которые помогут вам быть понятым и получить качественную помощь.","link":"#"}]}',
'2025-01-17 12:00:00','2025-01-19 10:20:00'),
('m4n5o6p7-q8r9-0123-s456-789012345678', 'b2c3d4e5-f6g7-8901-bcde-f23456789012', 'button', 3, NULL,
'{"text":"Все статьи","link":"#","alignment":"center","style":"primary"}',
'2025-01-17 12:00:00','2025-01-19 10:20:00');

-- All Materials page blocks
INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES
('n5o6p7q8-r9s0-1234-t567-890123456789', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'page-header', 1, NULL,
'{"title":"Все материалы","subtitle":"Полная коллекция гайдов и статей о медицинской системе Бразилии."}',
'2025-01-18 13:00:00','2025-01-20 11:30:00'),
('o6p7q8r9-s0t1-2345-u678-901234567890', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'section-title', 2, NULL,
'{"text":"Гайды","alignment":"left"}',
'2025-01-18 13:00:00','2025-01-20 11:30:00'),
('p7q8r9s0-t1u2-3456-v789-012345678901', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'article-cards', 3, NULL,
'{"title":"","columns":3,"cards":[{"image":"https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=2070&auto=format&fit=crop","title":"Полный гайд по SUS для экспата","text":"Пошаговая инструкция, как зарегистрироваться в государственной системе, чего ожидать и как пользоваться ее возможностями...","link":"#"},{"image":"https://images.unsplash.com/photo-1551076805-e1869033e561?q=80&w=2070&auto=format&fit=crop","title":"Как выбрать частную страховку","text":"Разбираем типы страховок (Plano de Saúde), важные пункты в договоре и на что обратить внимание при выборе компании.","link":"#"},{"image":"https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=2070&auto=format&fit=crop","title":"Как найти хорошего врача","text":"Советы по поиску специалистов...","link":"#"},{"image":"https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?q=80&w=2091&auto=format&fit=crop","title":"Медицинские анализы и обследования","text":"Как сдавать анализы через SUS...","link":"#"},{"image":"https://images.unsplash.com/photo-1505751172876-fa1923c5c528?q=80&w=2070&auto=format&fit=crop","title":"Экстренная медицинская помощь","text":"Когда вызывать SAMU (192)...","link":"#"}]}',
'2025-01-18 13:00:00','2025-01-20 11:30:00'),
-- You can expand the cards array as needed for additional guides/articles
('q8r9s0t1-u2v3-4567-w890-123456789012', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'section-divider', 4, NULL,
'{}',
'2025-01-18 13:00:00','2025-01-20 11:30:00'),
('r9s0t1u2-v3w4-5678-x901-234567890123', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'section-title', 5, NULL,
'{"text":"Статьи из блога","alignment":"left"}',
'2025-01-18 13:00:00','2025-01-20 11:30:00'),
-- article-cards for blog posts in all-materials (add cards array)
('s0t1u2v3-w4x5-6789-y012-345678901234', 'c3d4e5f6-g7h8-9012-cdef-345678901234', 'article-cards', 6, NULL,
'{"title":"","columns":3,"cards":[{"image":"https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=2070&auto=format&fit=crop","title":"Первый год в Бразилии: мои открытия","text":"Рассказываю о том, с какими неожиданностями я столкнулась в больницах и аптеках в первые месяцы после переезда...","link":"#"},{"image":"https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=1780&auto=format&fit=crop","title":"Как говорить с бразильским врачом","text":"Набор фраз, культурные особенности и маленькие хитрости, которые помогут вам быть понятым и получить качественную помощь.","link":"#"},{"image":"https://images.unsplash.com/photo-1576091160550-2173dba999ef?q=80&w=2070&auto=format&fit=crop","title":"Мой опыт с программой Alto Custo","text":"Личная история о получении лекарств...","link":"#"},{"image":"https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=2070&auto=format&fit=crop","title":"Беременность и роды в Бразилии","text":"Особенности наблюдения беременности...","link":"#"},{"image":"https://images.unsplash.com/photo-1559757148-5c350d0d3c56?q=80&w=2070&auto=format&fit=crop","title":"Психологическая помощь для экспатов","text":"Где найти русскоязычного психолога...","link":"#"},{"image":"https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?q=80&w=2070&auto=format&fit=crop","title":"Стоматология в Бразилии","text":"Мой опыт лечения зубов...","link":"#"}]}',
'2025-01-18 13:00:00','2025-01-20 11:30:00');

-- Bot page blocks
INSERT INTO blocks (id, page_id, type, position, custom_name, data, created_at, updated_at)
VALUES
('t1u2v3w4-x5y6-7890-z123-456789012345', 'd4e5f6g7-h8i9-0123-def0-456789012345', 'page-header', 1, NULL,
'{"title":"Бот-помощник","subtitle":"Ответьте на несколько вопросов, чтобы предварительно определить, можете ли вы получить дорогие лекарства бесплатно по программе Alto Custo."}',
'2025-01-19 14:00:00','2025-01-21 12:45:00'),
('u2v3w4x5-y6z7-8901-a234-567890123456', 'd4e5f6g7-h8i9-0123-def0-456789012345', 'chat-bot', 2, NULL,
'{"placeholder":"Введите ваш вопрос...","buttonText":"→"}',
'2025-01-19 14:00:00','2025-01-21 12:45:00');