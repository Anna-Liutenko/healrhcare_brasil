-- Сброс пароля для пользователя anna
-- Новый пароль: test123

USE healthcare_cms;

UPDATE users
SET password_hash = '$2y$10$JOQSaXnZFq0jT6Um9REzV.SFd4/bAnKJLh8QUaIAgerX0eBdv.rwu'
WHERE username = 'anna';

-- Проверка обновления
SELECT username, LEFT(password_hash, 60) as password_hash, role
FROM users
WHERE username = 'anna';
