# 🚀 Installation Guide - Backend

## Требования

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Apache/Nginx с mod_rewrite

---

## Установка

### **1. Установить Composer (если нет)**

Скачайте и установите: https://getcomposer.org/download/

### **2. Установить зависимости**

```bash
cd backend
composer install
```

Это установит:
- `ramsey/uuid` — генерация UUID
- PSR-4 автозагрузка классов

### **3. Настроить окружение**

Скопируйте `.env.example` в `.env`:

```bash
cp .env.example .env  # Linux/Mac
copy .env.example .env  # Windows
```

Отредактируйте `.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=healthcare_cms
DB_USERNAME=root
DB_PASSWORD=
```

### **4. Запустить миграции БД**

```bash
cd ../database/migrations
mysql -uroot healthcare_cms < run_migrations.sql  # Linux/Mac

# Windows (XAMPP):
"C:\xampp\mysql\bin\mysql.exe" -uroot healthcare_cms < run_migrations.sql
```

### **5. Проверить работу API**

Откройте в браузере:

```
http://localhost/healthcare-cms/backend/public/api/health
```

Должны увидеть:

```json
{
  "status": "ok",
  "service": "Expats Health Brazil CMS API",
  "version": "1.0.0"
}
```

---

## Тестирование API

### **Health Check**

```bash
curl http://localhost/healthcare-cms/backend/public/api/health
```

### **Получить список страниц**

```bash
curl http://localhost/healthcare-cms/backend/public/api/pages
```

### **Создать страницу**

```bash
curl -X POST http://localhost/healthcare-cms/backend/public/api/pages \
  -H "Content-Type: application/json" \
  -d '{
    "title": "About Us",
    "slug": "about-us",
    "type": "regular",
    "createdBy": "UUID-вашего-пользователя"
  }'
```

### **Получить страницу по ID**

```bash
curl http://localhost/healthcare-cms/backend/public/api/pages/PAGE_ID
```

### **Опубликовать страницу**

```bash
curl -X PUT http://localhost/healthcare-cms/backend/public/api/pages/PAGE_ID/publish
```

---

## Troubleshooting

### **Ошибка: "Class not found"**

Запустите:

```bash
composer dump-autoload
```

### **Ошибка: "Database connection failed"**

Проверьте `.env` и убедитесь, что MySQL запущен.

### **Ошибка 404 на все запросы**

Проверьте, что `mod_rewrite` включен в Apache:

```apache
# httpd.conf или apache2.conf
LoadModule rewrite_module modules/mod_rewrite.so
```

И что `.htaccess` работает:

```apache
<Directory "/path/to/backend/public">
    AllowOverride All
</Directory>
```

---

## Структура API

```
GET    /api/health              # Health check
GET    /api/pages               # Список страниц
POST   /api/pages               # Создать страницу
GET    /api/pages/:id           # Получить страницу
PUT    /api/pages/:id           # Обновить страницу
PUT    /api/pages/:id/publish   # Опубликовать страницу
```

---

## Готово! 🎉

Backend API запущен и готов к работе!
