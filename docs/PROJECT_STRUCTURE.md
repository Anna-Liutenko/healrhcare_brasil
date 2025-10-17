# 📂 Project Structure - Expats Health Brazil CMS

Полная структура проекта Healthcare CMS на Clean Architecture.

---

## 🗂️ Общая структура

```
Разработка сайта с CMS/
│
├── backend/                          # PHP Backend (Clean Architecture)
│   ├── config/                       # Конфигурация
│   │   └── database.php
│   │
│   ├── public/                       # Entry point
│   │   ├── index.php                 # Router
│   │   └── .htaccess                 # Apache rewrite rules
│   │
│   ├── src/
│   │   ├── Domain/                   # Domain Layer
│   │   │   ├── Entity/               # Entities (бизнес-объекты)
│   │   │   │   ├── Page.php
│   │   │   │   ├── User.php
│   │   │   │   └── Block.php
│   │   │   ├── ValueObject/          # Value Objects
│   │   │   │   ├── PageStatus.php
│   │   │   │   ├── PageType.php
│   │   │   │   └── UserRole.php
│   │   │   └── Repository/           # Repository Interfaces
│   │   │       ├── PageRepositoryInterface.php
│   │   │       ├── UserRepositoryInterface.php
│   │   │       └── BlockRepositoryInterface.php
│   │   │
│   │   ├── Application/              # Application Layer
│   │   │   └── UseCase/              # Use Cases
│   │   │       ├── CreatePage.php
│   │   │       ├── UpdatePage.php
│   │   │       ├── GetPageWithBlocks.php
│   │   │       ├── PublishPage.php
│   │   │       └── Login.php
│   │   │
│   │   ├── Infrastructure/           # Infrastructure Layer
│   │   │   ├── Database/
│   │   │   │   └── Connection.php    # PDO Singleton
│   │   │   └── Repository/           # MySQL Implementations
│   │   │       ├── MySQLPageRepository.php
│   │   │       ├── MySQLUserRepository.php
│   │   │       └── MySQLBlockRepository.php
│   │   │
│   │   └── Presentation/             # Presentation Layer
│   │       └── Controller/
│   │           └── PageController.php
│   │
│   ├── .env.example                  # Environment template
│   ├── .gitignore
│   ├── composer.json
│   ├── README.md
│   └── INSTALL.md
│
├── database/                         # Database
│   └── migrations/                   # SQL миграции
│       ├── 001_create_users_table.sql
│       ├── 002_create_sessions_table.sql
│       ├── 003_create_pages_table.sql
│       ├── 004_create_blocks_table.sql
│       ├── 005_create_media_table.sql
│       ├── 006_create_menus_table.sql
│       ├── 007_create_menu_items_table.sql
│       ├── 008_create_settings_table.sql
│       ├── 009_create_tags_table.sql
│       ├── run_migrations.sql
│       ├── rollback.sql
│       └── README.md
│
├── healthcare-visual-editor/        # Визуальный редактор
│   └── visual-editor-standalone/
│       ├── index.html                # Visual Editor UI
│       ├── editor.js                 # Editor logic (Vue.js)
│       ├── blocks.js                 # Block definitions
│       ├── templates.js              # Page templates
│       └── styles.css
│
├── Documents/                        # Документация
│   ├── CMS_DEVELOPMENT_PLAN.md
│   └── DATABASE_SCHEMA.md
│
└── PROJECT_STRUCTURE.md              # Этот файл
```

---

## 🏛️ Clean Architecture Layers

### **1. Domain Layer** (бизнес-логика)
- **Entities** — главные объекты (Page, User, Block)
- **Value Objects** — неизменяемые значения (PageStatus, UserRole)
- **Repository Interfaces** — контракты для работы с данными

**Зависимости:** НИКАКИХ! Чистая бизнес-логика.

### **2. Application Layer** (use cases)
- **Use Cases** — сценарии использования системы
  - CreatePage — создать страницу
  - UpdatePage — обновить страницу
  - PublishPage — опубликовать страницу
  - Login — авторизация

**Зависимости:** Domain Layer

### **3. Infrastructure Layer** (внешний мир)
- **Database Connection** — подключение к MySQL
- **Repository Implementations** — MySQL реализации интерфейсов

**Зависимости:** Domain Layer

### **4. Presentation Layer** (API)
- **Controllers** — HTTP endpoints
- **Router** — маршрутизация запросов

**Зависимости:** Application Layer, Infrastructure Layer

---

## 📊 Схема зависимостей

```
┌─────────────────┐
│  Presentation   │  (Controllers, Router)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   Application   │  (Use Cases)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Domain      │  (Entities, Value Objects, Interfaces)
└─────────────────┘
         ▲
         │
┌────────┴────────┐
│ Infrastructure  │  (MySQL, Files, API clients)
└─────────────────┘
```

**Правило:** Зависимости идут внутрь (→ Domain). Domain не зависит ни от чего!

---

## 🗄️ База данных (10 таблиц)

1. **users** — пользователи CMS
2. **sessions** — сессии авторизации
3. **pages** — страницы сайта
4. **blocks** — блоки контента
5. **media** — медиафайлы (галерея)
6. **menus** — меню навигации
7. **menu_items** — пункты меню
8. **settings** — глобальные настройки
9. **tags** — теги для контента
10. **page_tags** — связь страниц и тегов

---

## 📡 API Endpoints

### **Health Check**
```
GET /api/health
```

### **Pages**
```
GET    /api/pages              # Список страниц
POST   /api/pages              # Создать страницу
GET    /api/pages/:id          # Получить страницу
PUT    /api/pages/:id          # Обновить страницу
PUT    /api/pages/:id/publish  # Опубликовать
```

---

## 🎨 Visual Editor

### **Поддерживаемые блоки:**
- main-screen — главный экран с фоном
- page-header — заголовок страницы
- service-cards — карточки услуг
- article-cards — карточки статей
- about-section — секция "О себе"
- text-block — блок текста
- image-block — изображение
- blockquote — цитата
- button — кнопка
- section-title — H3 заголовок
- section-divider — разделитель
- chat-bot — рамка для AI-бота
- spacer — пустое пространство

### **Возможности редактора:**
- ✅ Drag & Drop блоков
- ✅ Редактирование в правой панели
- ✅ Undo/Redo (50 версий)
- ✅ Переименование блоков
- ✅ Шаблоны страниц
- ✅ Медиа-галерея
- ✅ Редактор статей (Quill.js)
- ✅ Сохранение в localStorage

---

## 🔧 Технологии

### **Backend:**
- PHP 8.1+
- PDO (MySQL)
- Ramsey UUID
- Clean Architecture

### **Frontend (Editor):**
- Vue.js 3
- Quill.js (rich text editor)
- Vanilla CSS

### **Database:**
- MySQL 5.7+ / MariaDB 10.3+
- UTF-8 (utf8mb4)

---

## 🚀 Быстрый старт

### **1. Backend**
```bash
cd backend
composer install
cp .env.example .env
```

### **2. Database**
```bash
cd database/migrations
mysql -uroot healthcare_cms < run_migrations.sql
```

### **3. Test API**
```
http://localhost/healthcare-cms/backend/public/api/health
```

### **4. Visual Editor**
```
http://localhost/healthcare-cms/healthcare-visual-editor/visual-editor-standalone/
```

---

## 📚 Документация

- `backend/README.md` — Backend архитектура
- `backend/INSTALL.md` — Установка backend
- `database/migrations/README.md` — Миграции БД
- `DATABASE_SCHEMA.md` — Полная схема БД
- `CMS_DEVELOPMENT_PLAN.md` — План разработки

---

## 👤 Автор

**Anna Liutenko**
Email: anna@liutenko.onmicrosoft.com
Domain: expats-health.com.br
