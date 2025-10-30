# 🗄️ Database Migrations - Healthcare CMS

Миграции базы данных для Healthcare Brazil CMS.

## 📋 Список миграций

| # | Файл | Описание |
|---|------|----------|
| 001 | `001_create_users_table.sql` | Таблица пользователей (админы, редакторы) |
| 002 | `002_create_sessions_table.sql` | Таблица сессий (авторизация) |
| 003 | `003_create_pages_table.sql` | Таблица страниц (главная сущность) |
| 004 | `004_create_blocks_table.sql` | Таблица блоков контента |
| 005 | `005_add_source_template_to_pages.sql` | Колонка `source_template_slug` для страниц |
| 006 | `005_create_media_table.sql` | Таблица медиафайлов (галерея) |
| 007 | `006_create_menus_table.sql` | Таблица меню навигации |
| 008 | `007_create_menu_items_table.sql` | Таблица пунктов меню |
| 009 | `008_create_settings_table.sql` | Глобальные настройки сайта |
| 010 | `009_create_tags_table.sql` | Теги для контента |
| 011 | `010_add_inline_editing_fields.sql` | Поля для inline-редактирования страниц |
| 012 | `011_add_menu_fields_to_pages.sql` | Поля меню для страниц |
| 013 | `2025_10_13_add_rendered_html_and_menu_title.sql` | Колонки `rendered_html` и `menu_title` |
| 014 | `2025_10_16_add_client_id_to_blocks.sql` | Колонка `client_id` для блоков |
| 015 | `20251019_add_csrf_token_to_sessions.sql` | Колонка `csrf_token` для сессий |
| 016 | `20251030_add_pages_columns.sql` | Гарантия наличия колонок в `pages` |
| 017 | `20251030_add_card_image_column.sql` | Колонка `card_image` для страниц |

---

## 🚀 Запуск миграций

### **Вариант 1: XAMPP (Windows)**

1. **Откройте MySQL Shell в XAMPP:**
   ```bash
   cd C:\xampp\mysql\bin
   mysql.exe -uroot
   ```

2. **Создайте БД и запустите миграции:**
   ```sql
   CREATE DATABASE IF NOT EXISTS healthcare_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE healthcare_cms;
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/001_create_users_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/002_create_sessions_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/003_create_pages_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/004_create_blocks_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/005_add_source_template_to_pages.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/005_create_media_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/006_create_menus_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/007_create_menu_items_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/008_create_settings_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/009_create_tags_table.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/010_add_inline_editing_fields.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/011_add_menu_fields_to_pages.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/2025_10_13_add_rendered_html_and_menu_title.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/2025_10_16_add_client_id_to_blocks.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/20251019_add_csrf_token_to_sessions.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/20251030_add_pages_columns.sql";
  SOURCE "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/20251030_add_card_image_column.sql";
   ```

### **Вариант 2: Один скрипт (проще)**

```bash
cd "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\database\migrations"
"C:\xampp\mysql\bin\mysql.exe" -uroot < run_migrations.sql
```

### **Вариант 3: Ubuntu (production)**

```bash
cd /var/www/healthcare-cms/database/migrations
mysql -u healthcare_user -p healthcare_cms < run_migrations.sql
```

### **Вариант 4: PHP-скрипт (MySQL + SQLite)**

```bash
php backend/tools/apply_schema_updates.php
```

Скрипт проверит наличие ключевых колонок (`card_image`, `rendered_html`, `menu_title`, `source_template_slug`) в таблице `pages` и автоматически добавит их как в MySQL, так и в тестовой SQLite-базе. Индекс `idx_source_template` также создаётся при необходимости.

---

## 🔄 Откат миграций (УДАЛЯЕТ ВСЕ ДАННЫЕ!)

⚠️ **ВНИМАНИЕ:** Это удалит все таблицы и данные!

```bash
cd "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\database\migrations"
"C:\xampp\mysql\bin\mysql.exe" -uroot < rollback.sql
```

---

## 📊 Схема базы данных

```
users (пользователи)
  └── sessions (сессии)
  └── pages (страницы)
      └── blocks (блоки контента)
      └── page_tags (связь с тегами)
  └── media (медиафайлы)

menus (меню)
  └── menu_items (пункты меню)
      └── pages (ссылки на страницы)

tags (теги)
  └── page_tags (связь со страницами)

settings (глобальные настройки)
```

---

## 🔐 Дефолтный админ

После запуска миграций создаётся super_admin:

- **Username:** `anna`
- **Email:** `anna@liutenko.onmicrosoft.com`
- **Password:** `admin123`

⚠️ **ОБЯЗАТЕЛЬНО СМЕНИТЬ ПАРОЛЬ** после первого входа!

---

## 📝 Дефолтные данные

### **Меню:**
- `main-menu` — Главное меню
- `footer-menu` — Меню в футере

### **Теги:**
- SUS — Государственная система (#008d8d)
- Частные клиники — Частная медицина (#0066cc)
- Гайды — Пошаговые инструкции (#ff6b35)
- Лекарства — Аптеки и лекарства (#2ecc71)

### **Настройки:**
- `site_name` — Expats Health Brazil
- `site_domain` — expats-health.com.br
- `header_logo_text` — Expats Health Brazil
- `footer_copyright` — © 2025 Anna Liutenko
- Cookie Banner (включен по умолчанию)

---

## 🛠️ Проверка БД

После запуска миграций проверьте:

```sql
USE healthcare_cms;

-- Список таблиц
SHOW TABLES;

-- Проверка пользователя
SELECT * FROM users;

-- Проверка настроек
SELECT * FROM settings;

-- Проверка меню
SELECT * FROM menus;

-- Проверка тегов
SELECT * FROM tags;
```

---

## 🔧 Troubleshooting

### **Ошибка: "Can't find file"**
Используйте полные пути с прямыми слешами (`/`), а не обратными (`\`).

### **Ошибка: "Access denied"**
Убедитесь, что пользователь MySQL имеет права:
```sql
GRANT ALL PRIVILEGES ON healthcare_cms.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### **Ошибка: "Foreign key constraint fails"**
Запускайте миграции **строго по порядку** (001 → 002 → 003...).

---

## 📚 Дополнительная информация

- **Кодировка:** `utf8mb4` (поддержка эмодзи и спецсимволов)
- **Collation:** `utf8mb4_unicode_ci` (без учёта регистра)
- **Engine:** InnoDB (поддержка транзакций и foreign keys)
- **UUID:** Используем `VARCHAR(36)` для ID (переносимость между БД)
