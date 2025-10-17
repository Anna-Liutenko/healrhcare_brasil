USE healthcare_cms;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE pages SET title = 'Гайды' WHERE id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567891';
UPDATE pages SET title = 'Блог' WHERE id = 'b2c3d4e5-f6g7-8901-bcde-f23456789012';
UPDATE pages SET title = 'Все материалы' WHERE id = 'c3d4e5f6-g7h8-9012-cdef-345678901234';
UPDATE pages SET title = 'Бот-помощник' WHERE id = 'd4e5f6g7-h8i9-0123-def0-456789012345';
UPDATE pages SET title = 'Полный гайд по SUS для экспата' WHERE id = 'e5f6g7h8-i9j0-1234-ef01-567890123456';

SELECT id, title, HEX(title) FROM pages WHERE id IN (
 'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
 'b2c3d4e5-f6g7-8901-bcde-f23456789012',
 'c3d4e5f6-g7h8-9012-cdef-345678901234',
 'd4e5f6g7-h8i9-0123-def0-456789012345',
 'e5f6g7h8-i9j0-1234-ef01-567890123456'
);
