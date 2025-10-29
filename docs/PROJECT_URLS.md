# 🔗 Ссылки для доступа к Healthcare CMS

## 📝 Админка (Редактор)

### Главный редактор страниц:
```
http://localhost/healthcare-cms-frontend/editor.html
```
**Что здесь:**
- Создание и редактирование страниц
- Добавление/изменение блоков контента
- Управление SEO настройками

---

### Медиа библиотека (загрузка изображений):
```
http://localhost/healthcare-cms-frontend/media-library.html
```
**Что здесь:**
- Загрузка изображений
- Просмотр загруженных файлов
- Управление медиа контентом

---

### Редактор меню:
```
http://localhost/healthcare-cms-frontend/menu-editor.html
```
**Что здесь:**
- Создание и редактирование пунктов меню
- Настройка навигации сайта
- Управление порядком пунктов

---

### Менеджер шаблонов:
```
http://localhost/healthcare-cms-frontend/template-manager.html
```
**Что здесь:**
- Управление шаблонами страниц
- Импорт/экспорт шаблонов

---

## 🌐 Публичный сайт (Frontend)

### Главная страница:
```
http://localhost/healthcare-cms-frontend/index.html
```

### Просмотр конкретной страницы по slug:
```
http://localhost/healthcare-cms-backend/public/p/home
http://localhost/healthcare-cms-backend/public/p/about-us
http://localhost/healthcare-cms-backend/public/p/guides
```
**Формат:** `/p/{slug}` где `slug` - это адрес страницы

---

## 🔌 Backend API

### API Base URL:
```
http://localhost/healthcare-cms-backend/public/api
```

### Основные endpoint'ы:

**Авторизация:**
```
POST http://localhost/healthcare-cms-backend/public/api/auth/login
GET  http://localhost/healthcare-cms-backend/public/api/auth/me
```

**Страницы:**
```
GET  http://localhost/healthcare-cms-backend/public/api/pages
POST http://localhost/healthcare-cms-backend/public/api/pages
GET  http://localhost/healthcare-cms-backend/public/api/pages/{id}
PUT  http://localhost/healthcare-cms-backend/public/api/pages/{id}
PUT  http://localhost/healthcare-cms-backend/public/api/pages/{id}/publish
```

**Меню:**
```
GET  http://localhost/healthcare-cms-backend/public/api/menu
POST http://localhost/healthcare-cms-backend/public/api/menu
```

**Медиа:**
```
GET  http://localhost/healthcare-cms-backend/public/api/media
POST http://localhost/healthcare-cms-backend/public/api/media/upload
```

---

## 🧪 Проверка работы sync layer fix

### 1. Откройте редактор:
```
http://localhost/healthcare-cms-frontend/editor.html
```

### 2. Откройте DevTools (F12) → Network

### 3. Выполните любое действие:
- Загрузите список страниц
- Создайте новую страницу
- Отредактируйте существующую

### 4. Проверьте JSON ответы:

✅ **Правильно (camelCase):**
```json
{
  "pageId": "123",
  "createdAt": "2025-10-18 12:00:00",
  "createdBy": "admin",
  "showInMenu": true,
  "customName": "Hero Section"
}
```

❌ **Неправильно (не должно быть):**
```json
{
  "page_id": "123",
  "created_at": "...",
  "show_in_menu": true,
  "custom_name": "..."
}
```

---

## 📋 Quick Start

### Для начала работы:

1. **Убедитесь что Apache запущен:**
   ```powershell
   # Проверка
   netstat -ano | findstr ":80"
   
   # Если не запущен - откройте XAMPP Control Panel и запустите Apache
   ```

2. **Откройте редактор:**
   ```
   http://localhost/healthcare-cms-frontend/editor.html
   ```

3. **Залогиньтесь** (если требуется)

4. **Начните редактировать!**

---

## 🔧 Troubleshooting

### Проблема: "Cannot connect to backend"

**Решение:**
1. Проверьте что Apache запущен
2. Проверьте что MySQL запущен
3. Откройте: `http://localhost/healthcare-cms-backend/public/`
4. Должны увидеть JSON response или страницу

### Проблема: "404 Not Found"

**Решение:**
1. Проверьте правильность URL
2. Убедитесь что файлы есть в `C:\xampp\htdocs\`
3. Перезапустите Apache

### Проблема: "500 Internal Server Error"

**Решение:**
```powershell
# Проверьте логи
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50
Get-Content "C:\xampp\htdocs\healthcare-cms-backend\logs\api.log" -Tail 50
```

---

## 📱 Быстрые ссылки (для закладок):

- 📝 Редактор: `http://localhost/healthcare-cms-frontend/editor.html`
- 🖼️ Медиа: `http://localhost/healthcare-cms-frontend/media-library.html`
- 📋 Меню: `http://localhost/healthcare-cms-frontend/menu-editor.html`
- 🌐 Сайт: `http://localhost/healthcare-cms-frontend/index.html`
- 🔌 API: `http://localhost/healthcare-cms-backend/public/api`

---

**Готово!** Сохраните этот файл для быстрого доступа к ссылкам. 🚀