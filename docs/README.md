# 🏥 Healthcare CMS - Система управления контентом

## 🎉 LATEST UPDATE (October 6, 2025)

**✅ PAGE EDITOR FULLY FUNCTIONAL - ALL CRITICAL BUGS FIXED**

- ✅ Pages save and load correctly
- ✅ Edit mode detection works
- ✅ F5 refresh preserves data
- ✅ All blocks render properly
- ✅ No infinite loops or double loading

**See:** [Quick Win Summary](./QUICK_WIN_OCTOBER_6_2025.md) | [Bug Fix Details](./BUGFIX_INFINITE_LOOP_OCTOBER_2025.md)

---

## 📋 Описание

CMS для создания сайтов о здравоохранении с визуальным редактором страниц.

**Разработано для:** Малый бизнес, информационные сайты, блоги  
**Технологии:** PHP 8.2 + Vue.js 3 + MySQL 8.0  
**Архитектура:** Clean Architecture (backend), Component-based (frontend)

---

## ⚠️ CRITICAL: Development Workflow

**BEFORE STARTING:** Read these documents!

1. 🔴 [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md) - **MUST READ**
2. ✅ [Sync Checklist](./SYNC_CHECKLIST.md) - Use before every test
3. 🚀 [Developer Cheat Sheet](./DEVELOPER_CHEAT_SHEET.md) - Keep open while coding

**Key Rule:** Always sync code to XAMPP after changes!

```powershell
# After ANY backend change:
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0
```

---

## 🚀 Быстрый старт

### 1️⃣ Требования

- ✅ XAMPP 8.2+ (Apache, PHP 8.2, MySQL 8.0)
- ✅ Composer
- ✅ Node.js (опционально, для будущих улучшений)

### 2️⃣ Установка

#### Шаг 1: Клонировать репозиторий (или скачать ZIP)

```powershell
git clone <URL> healthcare-cms
cd healthcare-cms
```

#### Шаг 2: Настроить XAMPP

**Выбери один из вариантов:**

**А) Symlink (рекомендуется для разработки):**

```powershell
# Запустить PowerShell от имени Администратора
New-Item -ItemType SymbolicLink -Path "c:\xampp\htdocs\healthcare-cms-backend" -Target "путь\до\проекта\backend"
New-Item -ItemType SymbolicLink -Path "c:\xampp\htdocs\visual-editor" -Target "путь\до\проекта\frontend"
```

**Б) Virtual Host (профессиональный вариант):**

См. подробную инструкцию в `SETUP_XAMPP.md`

#### Шаг 3: Создать базу данных

```sql
CREATE DATABASE healthcare_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Или используй phpMyAdmin: http://localhost/phpmyadmin

#### Шаг 4: Запустить миграции

```powershell
# Вариант 1: Через MySQL Workbench
# Открыть файл database/migrations/run_migrations.sql → Execute

# Вариант 2: Через командную строку
c:\xampp\mysql\bin\mysql.exe -u root healthcare_cms < database/migrations/run_migrations.sql
```

#### Шаг 5: Загрузить тестовые данные (опционально)

```powershell
c:\xampp\mysql\bin\mysql.exe -u root healthcare_cms < database/seeds/SEED_DATA.sql
```

#### Шаг 6: Настроить backend

Отредактировать `backend/config/database.php`:

```php
return [
    'host' => 'localhost',
    'database' => 'healthcare_cms',
    'username' => 'root',
    'password' => '',  // Твой пароль от MySQL
    'charset' => 'utf8mb4'
];
```

#### Шаг 7: Установить зависимости backend

```powershell
cd backend
composer install
```

#### Шаг 8: Проверка установки

- **Backend API:** http://localhost/healthcare-cms-backend/public/
- **Visual Editor:** http://localhost/visual-editor/

Должна открыться страница логина.

**Тестовый пользователь:**
- Email: `admin@example.com`
- Password: `password123`

---

## 📂 Структура проекта

```
healthcare-cms/
├── backend/                 # PHP Backend (Clean Architecture)
│   ├── config/             # Конфигурация (database.php)
│   ├── public/             # Точка входа (index.php)
│   ├── src/
│   │   ├── Application/    # Use Cases (бизнес-логика)
│   │   ├── Domain/         # Entities, Repositories, Value Objects
│   │   ├── Infrastructure/ # Database, Middleware
│   │   └── Presentation/   # Controllers, Routes
│   └── vendor/             # Composer dependencies
│
├── frontend/                # Visual Editor (Vue.js 3)
│   ├── components/         # UI компоненты
│   ├── api-client.js       # Обёртка для API
│   ├── editor.js           # Главная логика редактора
│   ├── blocks.js           # Определения блоков (Hero, Text, etc)
│   ├── index.html          # Страница логина
│   └── pages.html          # Редактор страниц
│
├── database/
│   ├── migrations/         # SQL миграции
│   ├── seeds/              # Тестовые данные
│   └── backups/            # Бэкапы БД
│
├── docs/                    # 📚 Документация
│   ├── README.md                        # Это файл
│   ├── PROJECT_STATUS.md                # Текущий статус
│   ├── CMS_DEVELOPMENT_PLAN.md          # План разработки
│   ├── API_ENDPOINTS_CHEATSHEET.md      # API справка
│   │
│   ├── 🔴 XAMPP_SYNC_ANTIPATTERNS.md    # **КРИТИЧНО - ОБЯЗАТЕЛЬНО ПРОЧИТАТЬ**
│   ├── ✅ SYNC_CHECKLIST.md             # Чеклист синхронизации
│   ├── 🚀 DEVELOPER_CHEAT_SHEET.md      # Шпаргалка разработчика
│   │
│   ├── TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md  # История отладки
│   ├── DEBUG_HISTORY.md                 # Общая история отладки
│   └── troubleshooting/                 # Архив проблем
│
└── prototypes/              # HTML прототипы
```

---

## 🔧 Разработка

### Backend (PHP API)

```powershell
cd backend
composer install

# Запустить встроенный сервер PHP (альтернатива XAMPP)
php -S localhost:8000 -t public
```

**Основные файлы:**
- `src/Presentation/Controller/` - REST API endpoints
- `src/Application/UseCase/` - Бизнес-логика
- `src/Domain/Entity/` - Модели данных

### Frontend (Visual Editor)

```powershell
cd frontend
# Нет сборки - работает напрямую в браузере
# Просто открой index.html через XAMPP
```

**Основные файлы:**
- `editor.js` - Главный скрипт редактора
- `api-client.js` - API_BASE_URL и методы запросов
- `blocks.js` - Определения блоков для drag&drop

---

## 📚 Документация

| Файл | Описание |
|------|----------|
| `docs/API_CONTRACT.md` | Описание всех REST endpoints |
| `docs/CMS_DEVELOPMENT_PLAN.md` | План разработки (этапы 0-10) |
| `docs/DEBUG_HISTORY.md` | История отладки проекта (lessons learned) |
| `database/DATABASE_SCHEMA.md` | Схема базы данных |
| `SETUP_XAMPP.md` | Настройка XAMPP (symlink/virtual host) |

---

## 🛠️ Основные команды

### База данных

```powershell
# Создать бэкап
c:\xampp\mysql\bin\mysqldump.exe -u root healthcare_cms > database/backups/backup_2025-01-04.sql

# Восстановить из бэкапа
c:\xampp\mysql\bin\mysql.exe -u root healthcare_cms < database/backups/backup_2025-01-04.sql

# Откатить миграции (удалить все таблицы)
c:\xampp\mysql\bin\mysql.exe -u root healthcare_cms < database/migrations/rollback.sql

# Запустить миграции заново
c:\xampp\mysql\bin\mysql.exe -u root healthcare_cms < database/migrations/run_migrations.sql
```

### Composer

```powershell
cd backend

# Установить зависимости
composer install

# Обновить зависимости
composer update

# Автозагрузка классов
composer dump-autoload
```

---

## 🧪 Тестирование

### Проверка API

```powershell
# Проверить, что API отвечает
curl http://localhost/healthcare-cms-backend/public/

# Логин (получить JWT token)
curl -X POST http://localhost/healthcare-cms-backend/public/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"admin@example.com","password":"password123"}'
```

### Проверка Visual Editor

1. Открыть: http://localhost/visual-editor/
2. Ввести: admin@example.com / password123
3. Создать новую страницу
4. Добавить блок (Hero, Text, Contact Form)
5. Сохранить → проверить в базе данных (таблица `pages`)

---

## 🐛 Отладка

### ⚠️ ПЕРВЫМ ДЕЛОМ: Проверь синхронизацию с XAMPP

90% проблем возникают из-за того, что код не синхронизирован!

```powershell
# Синхронизируй backend
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0

# Проверь, что файл существует в XAMPP
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\[твой-файл].php"
```

См. подробнее: [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md)

---

### Backend не отвечает

1. Проверь, что Apache и MySQL запущены в XAMPP
2. Проверь конфиг: `backend/config/database.php`
3. Посмотри логи: `backend/logs/` и `c:\xampp\apache\logs\error.log`
4. **Проверь синхронизацию с XAMPP** (см. выше)

### Frontend не загружается

1. Проверь, что symlink создан правильно
2. Проверь `frontend/api-client.js` → `API_BASE_URL`
3. Открой DevTools (F12) → Console → ищи ошибки
4. **Проверь синхронизацию frontend в XAMPP**

### Class not found / Fatal error

**99% это проблема синхронизации!**

```powershell
# 1. Проверь, что файл существует в workspace
Test-Path "backend\src\Infrastructure\Repository\MySQLPageRepository.php"

# 2. Синхронизируй в XAMPP
robocopy "backend\src" "C:\xampp\htdocs\healthcare-cms-backend\src" /MIR /R:0 /W:0

# 3. Проверь, что файл существует в XAMPP
Test-Path "C:\xampp\htdocs\healthcare-cms-backend\src\Infrastructure\Repository\MySQLPageRepository.php"

# 4. Перезапусти Apache
net stop Apache2.4
net start Apache2.4
```

### Ошибки БД

```powershell
# Проверить подключение к MySQL
c:\xampp\mysql\bin\mysql.exe -u root -p

# Проверить, что БД существует
SHOW DATABASES;
USE healthcare_cms;
SHOW TABLES;
```

---

## 📦 Использование в новых проектах

Этот CMS можно переиспользовать для любых проектов малого бизнеса:

### Шаг 1: Скопировать template

```powershell
Copy-Item "healthcare-cms" -Destination "my-new-project" -Recurse
cd my-new-project
```

### Шаг 2: Переименовать БД

1. Создать новую БД: `CREATE DATABASE my_project_cms;`
2. Изменить `backend/config/database.php` → `database = 'my_project_cms'`
3. Запустить миграции

### Шаг 3: Настроить контент

1. Удалить тестовые данные: `DELETE FROM pages;`
2. Создать свои страницы через Visual Editor
3. Настроить меню, блоки, стили

### Шаг 4: Кастомизация

- Добавить свои блоки в `frontend/blocks.js`
- Изменить стили в `frontend/styles.css`
- Добавить новые endpoints в `backend/src/Presentation/Controller/`

---

## 🎯 Roadmap

### Текущая версия: 1.0 (MVP)
- ✅ Авторизация (JWT)
- ✅ Visual Editor с drag&drop
- ✅ CRUD страниц
- ✅ Блоки: Hero, Text, Contact Form, CTA
- ✅ Загрузка изображений

### Версия 1.1 (в разработке)
- ⏳ Меню редактор
- ⏳ SEO мета-теги
- ⏳ Превью страниц
- ⏳ Дублирование страниц

### Версия 2.0 (планируется)
- 📋 Роли пользователей (Admin, Editor, Viewer)
- 📋 История изменений (Version Control)
- 📋 Multi-language support
- 📋 Темы оформления

---

## 🤝 Поддержка

Если возникли вопросы или нашёл баг:

1. Проверь `docs/DEBUG_HISTORY.md` - возможно, проблема уже решалась
2. Посмотри в `docs/troubleshooting/` - логи отладки
3. Открой Issue на GitHub (если репозиторий публичный)

---

## 📄 Лицензия

MIT License - можно использовать в коммерческих проектах

---

## 🙏 Автор

Создано для проектов малого бизнеса с фокусом на простоту и переиспользуемость.

**Версия:** 1.0  
**Дата:** Октябрь 2025  
**Статус:** Production Ready
