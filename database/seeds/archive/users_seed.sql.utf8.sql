-- Seed data for users
-- Run this after migrations

USE healthcare_cms;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Super admin user (anna)
INSERT INTO users (id, username, email, password_hash, role, is_active, created_at, last_login_at)
VALUES (
    '550e8400-e29b-41d4-a716-446655440000',
    'anna',
    'anna@healthcare-brazil.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'super_admin',
    1,
    '2025-01-01 09:00:00',
    '2025-10-06 22:00:00'
);