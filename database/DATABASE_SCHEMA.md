# 🗄️ Database Schema - Expats Health Brazil CMS

## 📊 Структура базы данных

```
healthcare_cms
│
├── users (пользователи CMS)
│   ├── id (VARCHAR 36, PK)
│   ├── username (VARCHAR 100, UNIQUE)
│   ├── email (VARCHAR 255, UNIQUE)
│   ├── password_hash (VARCHAR 255)
│   ├── role (ENUM: super_admin, admin, editor)
│   ├── is_active (BOOLEAN)
│   ├── created_at (TIMESTAMP)
│   └── last_login_at (TIMESTAMP)
│
├── sessions (сессии авторизации)
│   ├── id (VARCHAR 64, PK)
│   ├── user_id (VARCHAR 36, FK → users.id)
│   ├── ip_address (VARCHAR 45)
│   ├── user_agent (VARCHAR 255)
│   ├── created_at (TIMESTAMP)
│   ├── expires_at (TIMESTAMP)
│   └── last_activity (TIMESTAMP)
│
├── pages (страницы сайта)
│   ├── id (VARCHAR 36, PK)
│   ├── title (VARCHAR 255)
│   ├── slug (VARCHAR 255, UNIQUE)
│   ├── status (ENUM: draft, published, hidden, unlisted, trashed)
│   ├── type (ENUM: regular, article, guide, collection)
│   ├── collection_config (JSON, NULL)
│   ├── seo_title (VARCHAR 255)
│   ├── seo_description (TEXT)
│   ├── seo_keywords (VARCHAR 255)
│   ├── page_specific_code (TEXT)
│   ├── show_in_menu (BOOLEAN)
│   ├── show_in_sitemap (BOOLEAN)
│   ├── menu_order (INT)
│   ├── created_at (TIMESTAMP)
│   ├── updated_at (TIMESTAMP)
│   ├── published_at (TIMESTAMP)
│   ├── trashed_at (TIMESTAMP)
│   └── created_by (VARCHAR 36, FK → users.id)
│
├── blocks (блоки контента страниц)
│   ├── id (VARCHAR 36, PK)
│   ├── page_id (VARCHAR 36, FK → pages.id)
│   ├── type (VARCHAR 50) — main-screen, text-block, etc.
│   ├── position (INT) — порядок на странице
│   ├── custom_name (VARCHAR 255)
│   ├── data (JSON) — все данные блока
│   ├── created_at (TIMESTAMP)
│   └── updated_at (TIMESTAMP)
│
├── media (медиафайлы / галерея)
│   ├── id (VARCHAR 36, PK)
│   ├── filename (VARCHAR 255)
│   ├── original_filename (VARCHAR 255)
│   ├── url (VARCHAR 512)
│   ├── type (ENUM: image, svg, video, document)
│   ├── mime_type (VARCHAR 100)
│   ├── size (INT) — размер в байтах
│   ├── width (INT)
│   ├── height (INT)
│   ├── alt_text (VARCHAR 255)
│   ├── uploaded_by (VARCHAR 36, FK → users.id)
│   └── uploaded_at (TIMESTAMP)
│
├── menus (меню навигации)
│   ├── id (VARCHAR 36, PK)
│   ├── name (VARCHAR 100, UNIQUE) — main-menu, footer-menu
│   ├── display_name (VARCHAR 255)
│   ├── created_at (TIMESTAMP)
│   └── updated_at (TIMESTAMP)
│
├── menu_items (пункты меню)
│   ├── id (VARCHAR 36, PK)
│   ├── menu_id (VARCHAR 36, FK → menus.id)
│   ├── label (VARCHAR 255) — текст пункта
│   ├── page_id (VARCHAR 36, FK → pages.id, NULL)
│   ├── external_url (VARCHAR 512, NULL)
│   ├── position (INT)
│   ├── parent_id (VARCHAR 36, FK → menu_items.id, NULL) — для dropdown
│   ├── open_in_new_tab (BOOLEAN)
│   ├── css_class (VARCHAR 100)
│   ├── icon (VARCHAR 50)
│   ├── created_at (TIMESTAMP)
│   └── updated_at (TIMESTAMP)
│
├── settings (глобальные настройки)
│   ├── id (INT, PK, AUTO_INCREMENT)
│   ├── setting_key (VARCHAR 100, UNIQUE)
│   ├── setting_value (TEXT)
│   ├── setting_type (ENUM: text, textarea, json, boolean, number)
│   ├── setting_group (VARCHAR 50) — general, header, footer, seo, tracking
│   ├── description (VARCHAR 255)
│   └── updated_at (TIMESTAMP)
│
├── tags (теги для контента)
│   ├── id (VARCHAR 36, PK)
│   ├── name (VARCHAR 100)
│   ├── slug (VARCHAR 100, UNIQUE)
│   ├── description (TEXT)
│   ├── color (VARCHAR 7) — HEX цвет
│   └── created_at (TIMESTAMP)
│
└── page_tags (связь страниц и тегов)
    ├── page_id (VARCHAR 36, FK → pages.id)
    ├── tag_id (VARCHAR 36, FK → tags.id)
    └── PRIMARY KEY (page_id, tag_id)
```

---

## 🔗 Связи (Foreign Keys)

### **users → sessions**
- `sessions.user_id` → `users.id` (ON DELETE CASCADE)

### **users → pages**
- `pages.created_by` → `users.id` (ON DELETE CASCADE)

### **users → media**
- `media.uploaded_by` → `users.id` (ON DELETE CASCADE)

### **pages → blocks**
- `blocks.page_id` → `pages.id` (ON DELETE CASCADE)

### **pages → page_tags**
- `page_tags.page_id` → `pages.id` (ON DELETE CASCADE)

### **tags → page_tags**
- `page_tags.tag_id` → `tags.id` (ON DELETE CASCADE)

### **menus → menu_items**
- `menu_items.menu_id` → `menus.id` (ON DELETE CASCADE)

### **pages → menu_items**
- `menu_items.page_id` → `pages.id` (ON DELETE SET NULL)

### **menu_items → menu_items (self)**
- `menu_items.parent_id` → `menu_items.id` (ON DELETE CASCADE)

---

## 📈 Индексы для производительности

### **users**
- `idx_username` (username)
- `idx_email` (email)
- `idx_role` (role)
- `idx_is_active` (is_active)

### **sessions**
- `idx_user_id` (user_id)
- `idx_expires_at` (expires_at)
- `idx_last_activity` (last_activity)

### **pages**
- `idx_status` (status)
- `idx_type` (type)
- `idx_slug` (slug)
- `idx_created_at` (created_at)
- `idx_published_at` (published_at)
- `idx_show_in_menu` (show_in_menu, status, menu_order)
- `idx_type_status` (type, status, published_at)

### **blocks**
- `idx_page_id` (page_id)
- `idx_type` (type)
- `idx_page_position` (page_id, position)

### **media**
- `idx_type` (type)
- `idx_uploaded_by` (uploaded_by)
- `idx_uploaded_at` (uploaded_at)
- `idx_filename` (filename)

### **menu_items**
- `idx_menu_id` (menu_id)
- `idx_page_id` (page_id)
- `idx_parent_id` (parent_id)
- `idx_menu_position` (menu_id, position)

### **settings**
- `idx_setting_key` (setting_key)
- `idx_setting_group` (setting_group)

### **tags**
- `idx_slug` (slug)
- `idx_name` (name)

### **page_tags**
- `idx_page_id` (page_id)
- `idx_tag_id` (tag_id)

---

## 🎯 Основные сценарии использования

### **1. Получить страницу со всеми блоками**
```sql
SELECT p.*, b.*
FROM pages p
LEFT JOIN blocks b ON p.id = b.page_id
WHERE p.slug = 'about-us' AND p.status = 'published'
ORDER BY b.position ASC;
```

### **2. Получить меню с пунктами**
```sql
SELECT m.*, mi.*, p.slug as page_slug
FROM menus m
LEFT JOIN menu_items mi ON m.id = mi.menu_id
LEFT JOIN pages p ON mi.page_id = p.id
WHERE m.name = 'main-menu'
ORDER BY mi.position ASC;
```

### **3. Получить страницы для коллекции (автосборник)**
```sql
SELECT p.*
FROM pages p
WHERE p.type = 'article'
  AND p.status = 'published'
ORDER BY p.published_at DESC
LIMIT 12;
```

### **4. Получить страницы с тегом**
```sql
SELECT p.*
FROM pages p
JOIN page_tags pt ON p.id = pt.page_id
JOIN tags t ON pt.tag_id = t.id
WHERE t.slug = 'sus' AND p.status = 'published'
ORDER BY p.published_at DESC;
```

### **5. Очистка корзины (старше 30 дней)**
```sql
DELETE FROM pages
WHERE status = 'trashed'
  AND trashed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### **6. Проверка активной сессии**
```sql
SELECT u.*
FROM users u
JOIN sessions s ON u.id = s.user_id
WHERE s.id = ?
  AND s.expires_at > NOW()
  AND u.is_active = 1;
```

---

## 📝 Особенности реализации

### **UUID вместо AUTO_INCREMENT**
- Все ID (кроме settings) — `VARCHAR(36)` с UUID
- Преимущества: переносимость, безопасность, распределённые системы
- Генерация: PHP `Ramsey\Uuid\Uuid::uuid4()` или MySQL `UUID()`

### **JSON поля**
- `blocks.data` — все данные блока (title, text, images, etc.)
- `pages.collection_config` — настройки автосборника
- Гибкость: можно добавлять поля без миграций

### **ENUM для статусов**
- Защита от некорректных значений на уровне БД
- Легко читается в SQL-запросах

### **Soft Delete (корзина)**
- `pages.trashed_at` — дата удаления
- Автоочистка через cron (30 дней)

### **Timestamp с автообновлением**
- `created_at` — DEFAULT CURRENT_TIMESTAMP
- `updated_at` — ON UPDATE CURRENT_TIMESTAMP

---

## 🔒 Безопасность

- ✅ Foreign key constraints — целостность данных
- ✅ UNIQUE constraints — дубликаты невозможны
- ✅ Password hashing — bcrypt (cost=10)
- ✅ Session expiration — автоматическое истечение
- ✅ Prepared statements — защита от SQL-инъекций (в PHP)

---

## 🌍 Локализация

- **Кодировка:** `utf8mb4` — поддержка эмодзи, кириллицы, спецсимволов
- **Collation:** `utf8mb4_unicode_ci` — без учёта регистра
- **Timezone:** UTC (рекомендуется хранить в UTC, конвертировать на frontend)
