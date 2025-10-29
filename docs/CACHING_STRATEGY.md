# Стратегия HTTP кеширования для Healthcare CMS

**Дата:** October 24, 2025  
**Статус:** Applied to all directories  
**Применено:** 3 директории с `.htaccess`

## Проблема, которую мы решили

Браузер **постоянно** возвращал старые версии файлов, несмотря на:
- Очистку браузерного кеша (`Ctrl+Shift+Delete`)
- Hard refresh (`Ctrl+Shift+R`)
- Закрытие браузера

**Корневая причина:** Apache не отправлял `Cache-Control` заголовки, поэтому браузер кешировал файлы на основе `ETag` и `Last-Modified`.

## Решение: `.htaccess` с правильными заголовками

### 1. `/visual-editor-standalone/.htaccess`
**Директория:** `C:\xampp\htdocs\visual-editor-standalone\`

```apache
# Disable caching for dynamic content
<FilesMatch "\.(html|js|css)$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
</FilesMatch>

# Allow caching for static assets (images, fonts)
<FilesMatch "\.(jpg|jpeg|png|gif|woff|woff2|ttf)$">
    Header set Cache-Control "public, max-age=31536000"
</FilesMatch>
```

**Эффект:** 
- Все HTML/JS/CSS **всегда** скачиваются с сервера (no cache)
- Изображения и шрифты кешируются на 1 год

---

### 2. `/healthcare-cms-frontend/.htaccess`
**Директория:** `C:\xampp\htdocs\healthcare-cms-frontend\`

```apache
# Disable caching for dynamic content
<FilesMatch "\.(html|js|css)$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
</FilesMatch>

# Allow caching for static assets (images, fonts)
<FilesMatch "\.(jpg|jpeg|png|gif|woff|woff2|ttf)$">
    Header set Cache-Control "public, max-age=31536000"
</FilesMatch>
```

**Эффект:** Same as above - актуальные рабочая версия

---

### 3. `/healthcare-cms-backend/.htaccess`
**Директория:** `C:\xampp\htdocs\healthcare-cms-backend\` (backend корень)

**Было:**
```apache
RewriteEngine On
RewriteBase /healthcare-cms-backend
RewriteRule ^uploads/(.*)$ public/uploads/$1 [L,NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

**Стало:**
```apache
# API Cache Control Headers
<FilesMatch "\.php$|^$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
</FilesMatch>

# Allow caching for static assets (images, fonts)
<FilesMatch "\.(jpg|jpeg|png|gif|woff|woff2|ttf)$">
    Header set Cache-Control "public, max-age=31536000"
</FilesMatch>

RewriteEngine On
RewriteBase /healthcare-cms-backend
RewriteRule ^uploads/(.*)$ public/uploads/$1 [L,NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

**Эффект:**
- API ответы (PHP) **никогда** не кешируются
- Uploaded файлы (изображения) кешируются на 1 год

---

## Матрица кеширования

| Директория | Тип файла | Cache-Control | Поведение |
|------------|-----------|---|---|
| `/visual-editor-standalone/` | `.html`, `.js`, `.css` | `no-cache, no-store, must-revalidate` | Всегда с сервера |
| `/visual-editor-standalone/` | `.jpg`, `.png`, `.gif` | `public, max-age=31536000` | Кешируется на 1 год |
| `/healthcare-cms-frontend/` | `.html`, `.js`, `.css` | `no-cache, no-store, must-revalidate` | Всегда с сервера |
| `/healthcare-cms-frontend/` | Изображения | `public, max-age=31536000` | Кешируется на 1 год |
| `/healthcare-cms-backend/` | `.php` (API) | `no-cache, no-store, must-revalidate` | Всегда с сервера |
| `/healthcare-cms-backend/uploads/` | Изображения | `public, max-age=31536000` | Кешируется на 1 год |

---

## Требования

- ✅ Apache модуль `mod_headers` включен (XAMPP по умолчанию)
- ✅ `.htaccess` в каждой директории
- ✅ Apache перезагружен после изменений

## Перезагрузка Apache

```powershell
# Способ 1: Через XAMPP Control Panel
# Нажать "Stop" → дождаться → нажать "Start"

# Способ 2: Через PowerShell
taskkill /PID 101104 /F
Start-Sleep -Seconds 2
C:\xampp\apache_start.bat
Start-Sleep -Seconds 3
```

## Проверка

```powershell
# Проверить заголовки
Invoke-WebRequest -Uri "http://localhost/visual-editor-standalone/editor.html" `
  -Method Head -UseBasicParsing | Select-Object -ExpandProperty Headers | `
  Where-Object {$_.Keys -match "Cache|Pragma|Expires"}

# Должны увидеть:
# Cache-Control: no-cache, no-store, must-revalidate
# Pragma: no-cache
# Expires: 0
```

---

## Результат

### ✅ РЕШЕНО

- Браузер **всегда** скачивает свежие версии `.html`, `.js`, `.css`
- Нет необходимости в ручной очистке кеша
- Нет необходимости в `Ctrl+Shift+R`
- Обновление кода моментально видно всем пользователям
- Изображения остаются оптимизированными для скорости

### ❌ БОЛЬШЕ НЕ ПРОБЛЕМА

- Старый код в браузере
- "А вот у меня старая версия"
- "Попробуй очистить кеш"
- Бесконечные перезагрузки браузера

---

## Для будущих разработчиков

**При добавлении новой директории с frontend кодом:**

1. Скопировать `.htaccess` из `/visual-editor-standalone/`
2. Поместить в новую директорию
3. Перезагрузить Apache

**При добавлении API endpoints:**

1. Убедиться, что `.htaccess` содержит `Header set Cache-Control "no-cache, no-store, must-revalidate"` для PHP файлов
2. Тестировать с открытым DevTools (F12 → Network tab)
3. Убедиться, что `Cache-Control: no-cache...` есть в Response Headers

---

**Документ:** `CACHE_CONTROL_PATTERN.md` (подробное описание)  
**Статус:** Implemented и tested (October 24, 2025)
