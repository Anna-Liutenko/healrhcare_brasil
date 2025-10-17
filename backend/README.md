# 🏗️ Expats Health Brazil - CMS Backend

Backend для CMS на **Clean Architecture** (Vanilla PHP 8.1+).

---

## 📂 Структура проекта

```
backend/
├── config/                  # Конфигурационные файлы
│   └── database.php         # Настройки БД
│
├── public/                  # Публичная папка (entry point)
│   └── index.php            # Главный файл (router)
│
├── src/                     # Исходный код
│   ├── Domain/              # Domain Layer (бизнес-логика)
│   │   ├── Entity/          # Entities (Page, User, Block)
│   │   ├── ValueObject/     # Value Objects (PageStatus, UserRole)
│   │   └── Repository/      # Repository Interfaces
│   │
│   ├── Application/         # Application Layer (use cases)
│   │   └── UseCase/         # Use Cases (CreatePage, UpdatePage, etc.)
│   │
│   ├── Infrastructure/      # Infrastructure Layer (внешний мир)
│   │   ├── Database/        # Database Connection
│   │   └── Repository/      # Repository Implementations (MySQL)
│   │
│   └── Presentation/        # Presentation Layer (API)
│       ├── Controller/      # API Controllers
│       └── Middleware/      # Middleware (Auth, CORS)
│
├── database/                # Миграции БД
│   └── migrations/          # SQL-файлы миграций
│
├── .env.example             # Пример конфигурации окружения
├── .gitignore              # Git ignore
├── composer.json           # Composer зависимости
└── README.md               # Этот файл
```

---

## 🚀 Установка

### **1. Установить зависимости**

```bash
cd backend
composer install
```

### **2. Настроить окружение**

Скопируйте `.env.example` в `.env`:

```bash
copy .env.example .env   # Windows
# или
cp .env.example .env     # Linux/Mac
```

Отредактируйте `.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=healthcare_cms
DB_USERNAME=root
DB_PASSWORD=
```

### **3. Запустить миграции**

```bash
cd ../database/migrations
"C:\xampp\mysql\bin\mysql.exe" -uroot < run_migrations.sql
```

### **4. Проверить работу**

Откройте в браузере:
```
http://localhost/healthcare-cms/backend/public/
```

---

## 🏛️ Clean Architecture

### **Принципы:**

1. **Domain Layer** — бизнес-логика, не зависит от фреймворков
2. **Application Layer** — use cases, оркестрация бизнес-логики
3. **Infrastructure Layer** — БД, внешние API, файловая система
4. **Presentation Layer** — HTTP API, контроллеры

### **Зависимости:**

```
Presentation → Application → Domain
Infrastructure → Domain
```

**Domain** — ядро, не зависит ни от чего!

---

## 📝 Entities

### **Page** (`Domain\Entity\Page`)
- `id`: UUID
- `title`: Заголовок
- `slug`: URL-адрес
- `status`: PageStatus (draft, published, hidden, unlisted, trashed)
- `type`: PageType (regular, article, guide, collection)
- `blocks[]`: Массив Block

**Методы:**
- `publish()` — опубликовать
- `hide()` — скрыть
- `moveToTrash()` — в корзину
- `restore()` — восстановить (проверка 30 дней)

### **User** (`Domain\Entity\User`)
- `id`: UUID
- `username`: Логин
- `email`: Email
- `role`: UserRole (super_admin, admin, editor)

**Методы:**
- `verifyPassword()` — проверка пароля
- `changePassword()` — смена пароля
- `activate()` / `deactivate()` — активация/деактивация

### **Block** (`Domain\Entity\Block`)
- `id`: UUID
- `pageId`: ID страницы
- `type`: Тип (main-screen, text-block, etc.)
- `position`: Позиция на странице
- `data`: JSON с данными блока

---

## 🔄 Value Objects

### **PageStatus** (`Domain\ValueObject\PageStatus`)
```php
enum PageStatus: string {
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
    case Unlisted = 'unlisted';
    case Trashed = 'trashed';
}
```

### **PageType** (`Domain\ValueObject\PageType`)
```php
enum PageType: string {
    case Regular = 'regular';
    case Article = 'article';
    case Guide = 'guide';
    case Collection = 'collection';
}
```

### **UserRole** (`Domain\ValueObject\UserRole`)
```php
enum UserRole: string {
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Editor = 'editor';
}
```

---

## 🗄️ Repository Pattern

### **Interface** (`Domain\Repository\PageRepositoryInterface`)
```php
interface PageRepositoryInterface {
    public function findById(string $id): ?Page;
    public function findBySlug(string $slug): ?Page;
    public function save(Page $page): void;
    public function delete(string $id): void;
}
```

### **Implementation** (`Infrastructure\Repository\MySQLPageRepository`)
```php
class MySQLPageRepository implements PageRepositoryInterface {
    // MySQL-специфичная реализация
}
```

**Преимущество:** Можно заменить MySQL на PostgreSQL без изменений в Domain!

---

## 🎯 Use Cases

### **CreatePage** (`Application\UseCase\CreatePage`)
```php
$useCase = new CreatePage($pageRepository);
$page = $useCase->execute([
    'title' => 'About Us',
    'slug' => 'about-us',
    'type' => PageType::Regular,
    'createdBy' => $userId
]);
```

### **PublishPage** (`Application\UseCase\PublishPage`)
```php
$useCase = new PublishPage($pageRepository);
$useCase->execute($pageId);
```

---

## 📡 API Endpoints (будущие)

```
POST   /api/pages              # Создать страницу
GET    /api/pages/:id          # Получить страницу
PUT    /api/pages/:id          # Обновить страницу
DELETE /api/pages/:id          # Удалить (→ корзина)

PUT    /api/pages/:id/publish  # Опубликовать
POST   /api/pages/:id/restore  # Восстановить из корзины

POST   /api/auth/login         # Авторизация
POST   /api/auth/logout        # Выход
GET    /api/auth/me            # Текущий пользователь
```

---

## 🧪 Тестирование

```bash
composer test
```

---

## 📚 Ресурсы

- [Clean Architecture](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Repository Pattern](https://martinfowler.com/eaaCatalog/repository.html)
- [PHP PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)

---

## 👤 Автор

**Anna Liutenko**
Email: anna@liutenko.onmicrosoft.com
Website: expats-health.com.br
