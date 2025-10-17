-- =====================================================
-- SEED DATA for Healthcare CMS
-- Description: Test data for development and testing
-- =====================================================
--
-- This file contains:
-- - Test users (super_admin, admin, editor)
-- - Sample pages (home, about, services, etc.)
-- - Blocks for each page
-- - Menu items
-- - Global settings
--
-- IMPORTANT: This is for DEVELOPMENT ONLY!
-- Do NOT use in production.
-- =====================================================

USE healthcare_cms;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- 1. USERS
-- =====================================================

-- Clear existing data (except super_admin anna)
DELETE FROM users WHERE username != 'anna';

-- Admin user
INSERT INTO users (id, username, email, password_hash, role, is_active, created_at)
VALUES (
    '550e8400-e29b-41d4-a716-446655440001',
    'admin',
    'admin@healthcare-brazil.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'admin',
    1,
    '2025-01-01 10:00:00'
);

-- Editor user
INSERT INTO users (id, username, email, password_hash, role, is_active, created_at)
VALUES (
    '550e8400-e29b-41d4-a716-446655440002',
    'editor',
    'editor@healthcare-brazil.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'editor',
    1,
    '2025-01-10 14:30:00'
);

-- =====================================================
-- 2. PAGES
-- =====================================================

-- Get super_admin user ID (anna)
SET @admin_id = (SELECT id FROM users WHERE username = 'anna' LIMIT 1);

-- Clear existing pages (cascading will delete blocks)
DELETE FROM pages;

-- Home Page
INSERT INTO pages (id, title, slug, status, type, seo, tracking, created_at, updated_at, published_at, created_by)
VALUES (
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'Home',
    'home',
    'published',
    'regular',
    JSON_OBJECT(
        'meta_title', 'Healthcare in Brazil - Quality Medical Services',
        'meta_description', 'Discover quality healthcare services in Brazil. Expert medical care, modern facilities, and comprehensive health solutions.',
        'meta_keywords', 'healthcare, brazil, medical services, health, hospitals'
    ),
    JSON_OBJECT('page_specific_code', ''),
    '2025-01-15 10:00:00',
    '2025-01-20 14:30:00',
    '2025-01-20 14:30:00',
    @admin_id
);

-- About Page
INSERT INTO pages (id, title, slug, status, type, seo, tracking, created_at, updated_at, published_at, created_by)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'About Us',
    'about',
    'published',
    'regular',
    JSON_OBJECT(
        'meta_title', 'About Us - Healthcare Brazil',
        'meta_description', 'Learn about our mission to provide quality healthcare services across Brazil.',
        'meta_keywords', 'about, healthcare, brazil, mission, vision'
    ),
    JSON_OBJECT('page_specific_code', ''),
    '2025-01-16 11:00:00',
    '2025-01-18 09:15:00',
    '2025-01-18 09:15:00',
    @admin_id
);

-- Services Page
INSERT INTO pages (id, title, slug, status, type, seo, tracking, created_at, updated_at, published_at, created_by)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567892',
    'Our Services',
    'services',
    'published',
    'regular',
    JSON_OBJECT(
        'meta_title', 'Medical Services - Healthcare Brazil',
        'meta_description', 'Comprehensive medical services including cardiology, pediatrics, surgery, and more.',
        'meta_keywords', 'medical services, cardiology, pediatrics, surgery, healthcare'
    ),
    JSON_OBJECT('page_specific_code', ''),
    '2025-01-17 13:30:00',
    '2025-01-19 16:45:00',
    '2025-01-19 16:45:00',
    @admin_id
);

-- Blog Article (Draft)
INSERT INTO pages (id, title, slug, status, type, seo, tracking, created_at, updated_at, published_at, created_by)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567893',
    'Understanding Preventive Healthcare',
    'understanding-preventive-healthcare',
    'draft',
    'article',
    JSON_OBJECT(
        'meta_title', 'Understanding Preventive Healthcare - Healthcare Brazil Blog',
        'meta_description', 'Learn about the importance of preventive healthcare and how it can improve your quality of life.',
        'meta_keywords', 'preventive healthcare, health tips, wellness, prevention'
    ),
    JSON_OBJECT('page_specific_code', ''),
    '2025-01-22 08:00:00',
    '2025-01-22 08:00:00',
    NULL,
    @admin_id
);

-- Contact Page
INSERT INTO pages (id, title, slug, status, type, seo, tracking, created_at, updated_at, published_at, created_by)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567894',
    'Contact Us',
    'contact',
    'published',
    'regular',
    JSON_OBJECT(
        'meta_title', 'Contact Us - Healthcare Brazil',
        'meta_description', 'Get in touch with Healthcare Brazil. Find our locations, phone numbers, and email addresses.',
        'meta_keywords', 'contact, healthcare, brazil, phone, email, address'
    ),
    JSON_OBJECT('page_specific_code', ''),
    '2025-01-18 15:00:00',
    '2025-01-20 11:20:00',
    '2025-01-20 11:20:00',
    @admin_id
);

-- =====================================================
-- 3. BLOCKS
-- =====================================================

-- Clear existing blocks
DELETE FROM blocks;

-- HOME PAGE BLOCKS
-- Main Screen (Hero)
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b1000000-0000-0000-0000-000000000001',
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'main-screen',
    0,
    'Hero Section',
    JSON_OBJECT(
        'title', 'Quality Healthcare in Brazil',
        'subtitle', 'Comprehensive medical services for you and your family',
        'backgroundImage', 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d',
        'ctaText', 'Learn More',
        'ctaLink', '/about'
    )
);

-- Service Cards
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b1000000-0000-0000-0000-000000000002',
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'service-cards',
    1,
    'Main Services',
    JSON_OBJECT(
        'title', 'Our Services',
        'cards', JSON_ARRAY(
            JSON_OBJECT('icon', 'heart', 'title', 'Cardiology', 'description', 'Expert heart care services'),
            JSON_OBJECT('icon', 'baby', 'title', 'Pediatrics', 'description', 'Specialized care for children'),
            JSON_OBJECT('icon', 'surgery', 'title', 'Surgery', 'description', 'Advanced surgical procedures'),
            JSON_OBJECT('icon', 'diagnostics', 'title', 'Diagnostics', 'description', 'Comprehensive diagnostic services')
        )
    )
);

-- About Section
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b1000000-0000-0000-0000-000000000003',
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'about-section',
    2,
    'About Preview',
    JSON_OBJECT(
        'title', 'About Healthcare Brazil',
        'content', '<p>We are committed to providing world-class healthcare services across Brazil. With state-of-the-art facilities and expert medical professionals, we ensure the best care for our patients.</p><p>Our mission is to make quality healthcare accessible to everyone.</p>',
        'image', 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d',
        'ctaText', 'Read More',
        'ctaLink', '/about'
    )
);

-- ABOUT PAGE BLOCKS
-- Page Header
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b2000000-0000-0000-0000-000000000001',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'page-header',
    0,
    NULL,
    JSON_OBJECT(
        'title', 'About Us',
        'subtitle', 'Dedicated to your health and well-being'
    )
);

-- Text Block - Mission
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b2000000-0000-0000-0000-000000000002',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'text-block',
    1,
    'Mission Statement',
    JSON_OBJECT(
        'content', '<h2>Our Mission</h2><p>At Healthcare Brazil, our mission is to provide exceptional medical care that is accessible, affordable, and patient-centered. We believe that everyone deserves access to quality healthcare services.</p><h3>Our Values</h3><ul><li>Patient-centered care</li><li>Medical excellence</li><li>Innovation and technology</li><li>Compassion and empathy</li><li>Community health</li></ul>'
    )
);

-- Text Block - History
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b2000000-0000-0000-0000-000000000003',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'text-block',
    2,
    'Our History',
    JSON_OBJECT(
        'content', '<h2>Our History</h2><p>Founded in 2010, Healthcare Brazil has grown from a single clinic to a network of medical facilities across the country. We have served over 100,000 patients and continue to expand our services to reach more communities.</p>'
    )
);

-- SERVICES PAGE BLOCKS
-- Page Header
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b3000000-0000-0000-0000-000000000001',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567892',
    'page-header',
    0,
    NULL,
    JSON_OBJECT(
        'title', 'Our Services',
        'subtitle', 'Comprehensive healthcare solutions'
    )
);

-- Service Cards
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b3000000-0000-0000-0000-000000000002',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567892',
    'service-cards',
    1,
    'All Services',
    JSON_OBJECT(
        'title', 'Medical Specialties',
        'cards', JSON_ARRAY(
            JSON_OBJECT('icon', 'heart', 'title', 'Cardiology', 'description', 'Comprehensive heart and vascular care'),
            JSON_OBJECT('icon', 'baby', 'title', 'Pediatrics', 'description', 'Specialized medical care for children'),
            JSON_OBJECT('icon', 'surgery', 'title', 'General Surgery', 'description', 'Advanced surgical procedures'),
            JSON_OBJECT('icon', 'diagnostics', 'title', 'Diagnostic Imaging', 'description', 'MRI, CT, X-Ray, and Ultrasound'),
            JSON_OBJECT('icon', 'lab', 'title', 'Laboratory Services', 'description', 'Comprehensive lab testing'),
            JSON_OBJECT('icon', 'emergency', 'title', 'Emergency Care', 'description', '24/7 emergency medical services')
        )
    )
);

-- CONTACT PAGE BLOCKS
-- Page Header
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b4000000-0000-0000-0000-000000000001',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567894',
    'page-header',
    0,
    NULL,
    JSON_OBJECT(
        'title', 'Contact Us',
        'subtitle', 'Get in touch with our team'
    )
);

-- Text Block - Contact Info
INSERT INTO blocks (id, page_id, type, position, custom_name, data)
VALUES (
    'b4000000-0000-0000-0000-000000000002',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567894',
    'text-block',
    1,
    'Contact Information',
    JSON_OBJECT(
        'content', '<h2>Get in Touch</h2><p><strong>Address:</strong><br>123 Healthcare Avenue<br>São Paulo, SP 01000-000<br>Brazil</p><p><strong>Phone:</strong> +55 11 1234-5678</p><p><strong>Email:</strong> contact@healthcare-brazil.com</p><p><strong>Hours:</strong><br>Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 2:00 PM<br>Sunday: Closed (Emergency services available 24/7)</p>'
    )
);

-- =====================================================
-- 4. MENUS
-- =====================================================

-- Clear existing menu items
DELETE FROM menu_items;
DELETE FROM menus;

-- Main Menu
INSERT INTO menus (id, name, display_name)
VALUES (
    'm1000000-0000-0000-0000-000000000001',
    'main-menu',
    'Main Navigation'
);

-- Menu Items
INSERT INTO menu_items (id, menu_id, label, page_id, position, parent_id)
VALUES
    ('mi100000-0000-0000-0000-000000000001', 'm1000000-0000-0000-0000-000000000001', 'Home', '75f53538-dd6c-489a-9b20-d0004bb5086b', 0, NULL),
    ('mi100000-0000-0000-0000-000000000002', 'm1000000-0000-0000-0000-000000000001', 'About', 'a1b2c3d4-e5f6-7890-abcd-ef1234567891', 1, NULL),
    ('mi100000-0000-0000-0000-000000000003', 'm1000000-0000-0000-0000-000000000001', 'Services', 'a1b2c3d4-e5f6-7890-abcd-ef1234567892', 2, NULL),
    ('mi100000-0000-0000-0000-000000000004', 'm1000000-0000-0000-0000-000000000001', 'Contact', 'a1b2c3d4-e5f6-7890-abcd-ef1234567894', 3, NULL);

-- =====================================================
-- 5. GLOBAL SETTINGS
-- =====================================================

-- Clear existing settings
DELETE FROM settings;

INSERT INTO settings (id, setting_group, setting_key, setting_value)
VALUES
    (UUID(), 'general', 'site_name', 'Healthcare Brazil'),
    (UUID(), 'general', 'site_tagline', 'Quality Medical Care for Everyone'),
    (UUID(), 'general', 'logo_url', 'https://via.placeholder.com/200x60?text=Healthcare+Brazil'),
    (UUID(), 'general', 'favicon_url', 'https://via.placeholder.com/32x32?text=HB'),
    (UUID(), 'tracking', 'google_analytics', '<!-- Google Analytics Code Here -->'),
    (UUID(), 'tracking', 'facebook_pixel', '<!-- Facebook Pixel Code Here -->'),
    (UUID(), 'widgets', 'chat_widget', '<!-- Chat Widget Code Here -->'),
    (UUID(), 'contact', 'email', 'contact@healthcare-brazil.com'),
    (UUID(), 'contact', 'phone', '+55 11 1234-5678'),
    (UUID(), 'contact', 'address', '123 Healthcare Avenue, São Paulo, SP 01000-000, Brazil');

-- =====================================================
-- 6. TAGS (Optional)
-- =====================================================

-- Clear existing tags
DELETE FROM tags;

INSERT INTO tags (id, name, slug, color)
VALUES
    ('t1000000-0000-0000-0000-000000000001', 'Healthcare', 'healthcare', '#0066cc'),
    ('t1000000-0000-0000-0000-000000000002', 'Medical Tips', 'medical-tips', '#28a745'),
    ('t1000000-0000-0000-0000-000000000003', 'Prevention', 'prevention', '#ffc107'),
    ('t1000000-0000-0000-0000-000000000004', 'Wellness', 'wellness', '#17a2b8'),
    ('t1000000-0000-0000-0000-000000000005', 'News', 'news', '#dc3545');

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Count records
SELECT
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM pages) as total_pages,
    (SELECT COUNT(*) FROM blocks) as total_blocks,
    (SELECT COUNT(*) FROM menus) as total_menus,
    (SELECT COUNT(*) FROM menu_items) as total_menu_items,
    (SELECT COUNT(*) FROM settings) as total_settings,
    (SELECT COUNT(*) FROM tags) as total_tags;

-- Show pages with block count
SELECT
    p.id,
    p.title,
    p.slug,
    p.status,
    p.type,
    COUNT(b.id) as block_count
FROM pages p
LEFT JOIN blocks b ON p.id = b.page_id
GROUP BY p.id
ORDER BY p.created_at;

-- =====================================================
-- END OF SEED DATA
-- =====================================================
